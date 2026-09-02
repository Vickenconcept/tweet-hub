<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZernioService
{
    protected string $baseUrl;

    protected ?string $apiKey;

    protected ?string $accountId;

    protected int $timeoutSeconds;

    protected int $connectTimeoutSeconds;

    protected int $maxAttempts;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.zernio.base_url', 'https://zernio.com/api/v1'), '/');
        $this->apiKey = config('services.zernio.api_key');
        $this->accountId = config('services.zernio.whatsapp_account_id');
        $this->timeoutSeconds = (int) config('services.zernio.timeout', 30);
        $this->connectTimeoutSeconds = (int) config('services.zernio.connect_timeout', 15);
        $this->maxAttempts = (int) config('services.zernio.retry_attempts', 3);
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey) && ! empty($this->accountId);
    }

    public function botPhoneNumber(): ?string
    {
        return config('services.zernio.bot_phone_number');
    }

    public function sendInboxMessage(string $conversationId, string $message): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('Zernio is not configured; cannot send WhatsApp message.');

            return false;
        }

        try {
            $response = $this->request('post', "/inbox/conversations/{$conversationId}/messages", [
                'accountId' => $this->accountId,
                'message' => $message,
            ]);
        } catch (ConnectionException $e) {
            Log::error('Zernio sendInboxMessage connection failed after retries', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful()) {
            Log::error('Zernio sendInboxMessage failed', [
                'conversation_id' => $conversationId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        Log::info('Zernio sendInboxMessage delivered', [
            'conversation_id' => $conversationId,
            'message_preview' => mb_substr($message, 0, 80),
        ]);

        return true;
    }

    /**
     * Send a Meta-approved WhatsApp template outside the 24h session window.
     */
    public function sendTemplateMessage(string $toPhone, string $templateName, array $bodyVariables = [], ?string $language = null): bool
    {
        if (! $this->isConfigured() || $templateName === '') {
            return false;
        }

        $phone = self::normalizePhone($toPhone);
        if (! $phone) {
            return false;
        }

        $parameters = array_map(
            fn ($text) => ['type' => 'text', 'text' => (string) $text],
            $bodyVariables
        );

        $response = $this->request('post', '/whatsapp/messages', [
            'accountId' => $this->accountId,
            'to' => $phone,
            'template' => [
                'name' => $templateName,
                'language' => $language ?? config('services.zernio.template_language', 'en'),
                'components' => $parameters ? [
                    ['type' => 'body', 'parameters' => $parameters],
                ] : [],
            ],
        ]);

        if (! $response->successful()) {
            Log::warning('Zernio template message failed', [
                'to' => $phone,
                'template' => $templateName,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    public function sendVerificationCode(string $toPhone, string $code): bool
    {
        $template = config('services.zernio.verification_template');

        if ($template) {
            return $this->sendTemplateMessage($toPhone, $template, [$code]);
        }

        return false;
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        $secret = config('services.zernio.webhook_secret');

        if (empty($secret)) {
            return app()->environment('local');
        }

        if (empty($signature)) {
            return false;
        }

        $computed = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($computed, $signature);
    }

    protected function request(string $method, string $path, array $body = []): Response
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            try {
                $response = Http::withToken($this->apiKey)
                    ->acceptJson()
                    ->connectTimeout($this->connectTimeoutSeconds)
                    ->timeout($this->timeoutSeconds)
                    ->{$method}($this->baseUrl.$path, $body);

                if ($response->successful() || $response->clientError() || $response->serverError()) {
                    return $response;
                }

                Log::warning('Zernio API unexpected response', [
                    'attempt' => $attempt,
                    'path' => $path,
                    'status' => $response->status(),
                ]);
            } catch (ConnectionException $e) {
                $lastException = $e;

                Log::warning('Zernio API connection failed', [
                    'attempt' => $attempt,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $this->maxAttempts) {
                    usleep($attempt * 2_000_000);
                }
            }
        }

        if ($lastException) {
            throw $lastException;
        }

        throw new \RuntimeException('Zernio request failed after '.$this->maxAttempts.' attempts: '.$path);
    }

    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return null;
        }

        return '+'.$digits;
    }
}
