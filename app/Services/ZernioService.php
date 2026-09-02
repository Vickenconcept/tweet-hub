<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

    public function hasApiKey(): bool
    {
        return ! empty($this->apiKey);
    }

    public function isConfigured(): bool
    {
        return $this->hasApiKey() && ! empty($this->accountId);
    }

    public function botPhoneNumber(): ?string
    {
        return config('services.zernio.bot_phone_number');
    }

    public function createProfile(string $name, ?string $description = null): array
    {
        $body = array_filter([
            'name' => $name,
            'description' => $description,
        ]);

        $response = $this->request('post', '/profiles', $body);

        if ($response->status() === 409) {
            $existingId = $response->json('details.existingProfileId')
                ?? $response->json('existingProfileId');

            if ($existingId) {
                Log::info('Zernio profile name already exists, reusing existing profile', [
                    'name' => $name,
                    'existing_profile_id' => $existingId,
                ]);

                return [
                    '_id' => $existingId,
                    'id' => $existingId,
                    'name' => $name,
                    'reused' => true,
                ];
            }
        }

        $this->throwIfFailed($response, 'createProfile');

        return $response->json('profile') ?? $response->json();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listProfiles(): array
    {
        $response = $this->request('get', '/profiles');
        $this->throwIfFailed($response, 'listProfiles');

        return $response->json('profiles') ?? [];
    }

    public function findProfileByName(string $name): ?array
    {
        $name = trim($name);

        foreach ($this->listProfiles() as $profile) {
            if (strcasecmp(trim($profile['name'] ?? ''), $name) === 0) {
                return $profile;
            }
        }

        return null;
    }

    public function getTwitterConnectUrl(string $profileId, string $redirectUrl): array
    {
        $response = $this->request('get', '/connect/twitter', [
            'profileId' => $profileId,
            'redirect_url' => $redirectUrl,
        ]);
        $this->throwIfFailed($response, 'getTwitterConnectUrl');

        return $response->json();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAccounts(?string $platform = null, ?string $profileId = null): array
    {
        $query = array_filter([
            'platform' => $platform,
            'profileId' => $profileId,
        ]);

        $response = $this->request('get', '/accounts', $query);
        $this->throwIfFailed($response, 'listAccounts');

        return $response->json('accounts') ?? [];
    }

    public function getAccount(string $accountId, ?string $profileId = null): array
    {
        foreach ($this->findAccountCandidates($accountId, $profileId) as $account) {
            return $account;
        }

        throw new \RuntimeException("Account {$accountId} not found.");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function findAccountCandidates(string $accountId, ?string $profileId = null): array
    {
        $matches = [];

        $queries = array_filter([
            ['platform' => 'twitter', 'profileId' => $profileId],
            ['platform' => 'twitter', 'profileId' => null],
            ['platform' => null, 'profileId' => null],
        ]);

        foreach ($queries as $query) {
            $accounts = $this->listAccounts($query['platform'], $query['profileId']);

            foreach ($accounts as $account) {
                $id = (string) ($account['_id'] ?? $account['id'] ?? '');
                if ($id === (string) $accountId) {
                    $matches[] = $account;
                }
            }

            if ($matches !== []) {
                break;
            }
        }

        return $matches;
    }

    /**
     * @return array<string, mixed>
     */
    public function updateAccount(string $accountId, array $body): array
    {
        $response = $this->request('put', "/accounts/{$accountId}", $body);
        $this->throwIfFailed($response, 'updateAccount');

        return $response->json();
    }

    public function enableXAccountCapabilities(string $accountId, bool $analytics = true, bool $inbox = true): array
    {
        return $this->updateAccount($accountId, [
            'xCapabilities' => [
                'analytics' => $analytics,
                'inbox' => $inbox,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getFollowerStats(array $query = []): array
    {
        $response = $this->request('get', '/accounts/follower-stats', array_filter([
            'accountIds' => $query['accountIds'] ?? null,
            'profileId' => $query['profileId'] ?? null,
            'fromDate' => $query['fromDate'] ?? null,
            'toDate' => $query['toDate'] ?? null,
            'granularity' => $query['granularity'] ?? null,
        ]));
        $this->throwIfFailed($response, 'getFollowerStats');

        return $response->json();
    }

    public function createPost(array $payload, ?string $idempotencyKey = null): array
    {
        $headers = [];
        if ($idempotencyKey) {
            $headers['x-request-id'] = $idempotencyKey;
        }

        $response = $this->request('post', '/posts', $payload, $headers);
        $this->throwIfFailed($response, 'createPost');

        return $response->json();
    }

    public function getPost(string $postId): array
    {
        $response = $this->request('get', "/posts/{$postId}");
        $this->throwIfFailed($response, 'getPost');

        return $response->json('post') ?? $response->json();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAccountPosts(string $accountId): array
    {
        $response = $this->request('get', "/accounts/{$accountId}/posts");
        $this->throwIfFailed($response, 'listAccountPosts');

        return $response->json('posts') ?? [];
    }

    public function presignMedia(string $filename, string $contentType): array
    {
        $response = $this->request('post', '/media/presign', [
            'filename' => $filename,
            'contentType' => $contentType,
        ]);
        $this->throwIfFailed($response, 'presignMedia');

        return $response->json();
    }

    public function uploadToPresignedUrl(string $uploadUrl, string $localPath, string $contentType): bool
    {
        $contents = file_get_contents($localPath);
        if ($contents === false) {
            return false;
        }

        $response = Http::withBody($contents, $contentType)
            ->timeout($this->timeoutSeconds)
            ->put($uploadUrl);

        return $response->successful();
    }

    public function uploadLocalFile(string $localPath): ?string
    {
        if (! is_readable($localPath)) {
            return null;
        }

        $filename = basename($localPath);
        $contentType = $this->guessContentType($localPath);

        $presign = $this->presignMedia($filename, $contentType);
        $uploadUrl = $presign['uploadUrl'] ?? null;
        $publicUrl = $presign['publicUrl'] ?? null;

        if (! $uploadUrl || ! $publicUrl) {
            return null;
        }

        if (! $this->uploadToPresignedUrl($uploadUrl, $localPath, $contentType)) {
            return null;
        }

        return $publicUrl;
    }

    /**
     * @return array<string, mixed>
     */
    public function listInboxComments(?string $platform = null, ?string $accountId = null, int $limit = 50): array
    {
        $response = $this->request('get', '/inbox/comments', array_filter([
            'platform' => $platform,
            'accountId' => $accountId,
            'limit' => $limit,
            'sortBy' => 'date',
            'sortOrder' => 'desc',
        ]));
        $this->throwIfFailed($response, 'listInboxComments');

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function getPostComments(string $postId, string $accountId): array
    {
        $response = $this->request('get', "/inbox/comments/{$postId}", [
            'accountId' => $accountId,
        ]);
        $this->throwIfFailed($response, 'getPostComments');

        return $response->json();
    }

    public function replyToComment(string $postId, string $accountId, string $message, ?string $commentId = null): array
    {
        $body = array_filter([
            'accountId' => $accountId,
            'message' => $message,
            'commentId' => $commentId,
        ]);

        $response = $this->request('post', "/inbox/comments/{$postId}/reply", $body);
        $this->throwIfFailed($response, 'replyToComment');

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function listInboxConversations(string $accountId, int $limit = 50): array
    {
        $response = $this->request('get', '/inbox/conversations', [
            'accountId' => $accountId,
            'limit' => $limit,
        ]);
        $this->throwIfFailed($response, 'listInboxConversations');

        return $response->json();
    }

    public function sendInboxMessage(string $conversationId, string $message, ?string $accountId = null): bool
    {
        $accountId = $accountId ?: $this->accountId;

        if (! $this->hasApiKey() || ! $accountId) {
            Log::warning('Zernio is not configured; cannot send inbox message.');

            return false;
        }

        try {
            $response = $this->request('post', "/inbox/conversations/{$conversationId}/messages", [
                'accountId' => $accountId,
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
     * @return array<string, mixed>
     */
    public function getAnalytics(array $query): array
    {
        $response = $this->request('get', '/analytics', $query);
        $this->throwIfFailed($response, 'getAnalytics');

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function searchTwitterTweets(string $accountId, string $query, int $limit = 10, ?string $cursor = null): array
    {
        $response = $this->request('get', '/twitter/search', array_filter([
            'accountId' => $accountId,
            'query' => $query,
            'limit' => max(10, min(100, $limit)),
            'cursor' => $cursor,
        ]));
        $this->throwIfFailed($response, 'searchTwitterTweets');

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function getTwitterTweet(string $accountId, string $idOrUrl): array
    {
        $response = $this->request('get', '/twitter/tweet', [
            'accountId' => $accountId,
            'id' => $idOrUrl,
        ]);
        $this->throwIfFailed($response, 'getTwitterTweet');

        return $response->json('tweet') ?? $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function retweetTwitterPost(string $accountId, string $tweetId): array
    {
        $response = $this->request('post', '/twitter/retweet', [
            'accountId' => $accountId,
            'tweetId' => $tweetId,
        ]);

        if ($response->status() === 400) {
            $error = (string) ($response->json('error') ?? $response->json('message') ?? '');
            if (stripos($error, 'already retweeted') !== false) {
                return [
                    'retweeted' => true,
                    'alreadyRetweeted' => true,
                    'message' => 'Tweet was already retweeted.',
                ];
            }
        }

        $this->throwIfFailed($response, 'retweetTwitterPost');

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function undoTwitterRetweet(string $accountId, string $tweetId): array
    {
        $response = $this->request('delete', '/twitter/retweet', [
            'accountId' => $accountId,
            'tweetId' => $tweetId,
        ]);
        $this->throwIfFailed($response, 'undoTwitterRetweet');

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function followTwitterUser(string $accountId, string $targetUserId): array
    {
        $response = $this->request('post', '/twitter/follow', [
            'accountId' => $accountId,
            'targetUserId' => $targetUserId,
        ]);
        $this->throwIfFailed($response, 'followTwitterUser');

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function unfollowTwitterUser(string $accountId, string $targetUserId): array
    {
        $response = $this->request('delete', '/twitter/follow', [
            'accountId' => $accountId,
            'targetUserId' => $targetUserId,
        ]);
        $this->throwIfFailed($response, 'unfollowTwitterUser');

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function likeTwitterPost(string $accountId, string $tweetId): array
    {
        $response = $this->request('post', "/inbox/posts/{$tweetId}/like", [
            'accountId' => $accountId,
        ]);
        $this->throwIfFailed($response, 'likeTwitterPost');

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function unlikeTwitterPost(string $accountId, string $tweetId): array
    {
        $response = $this->request('delete', "/inbox/posts/{$tweetId}/like", [
            'accountId' => $accountId,
        ]);
        $this->throwIfFailed($response, 'unlikeTwitterPost');

        return $response->json();
    }

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

    protected function request(string $method, string $path, array $body = [], array $extraHeaders = []): Response
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            try {
                $pending = Http::withToken($this->apiKey)
                    ->acceptJson()
                    ->connectTimeout($this->connectTimeoutSeconds)
                    ->timeout($this->timeoutSeconds);

                foreach ($extraHeaders as $key => $value) {
                    $pending = $pending->withHeaders([$key => $value]);
                }

                $url = $this->baseUrl.$path;
                $method = strtolower($method);

                $response = match ($method) {
                    'get' => $pending->get($url, $body),
                    'delete' => $body === []
                        ? $pending->delete($url)
                        : $pending->withQueryParameters($body)->delete($url),
                    default => $pending->{$method}($url, $body),
                };

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

    protected function throwIfFailed(Response $response, string $context): void
    {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('error')
            ?? $response->json('message')
            ?? (trim($response->body()) !== '' ? $response->body() : null)
            ?? "HTTP {$response->status()}";

        Log::error("Zernio {$context} failed", [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new \RuntimeException(is_string($message) ? $message : "Request failed (HTTP {$response->status()})");
    }

    protected function guessContentType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            default => 'application/octet-stream',
        };
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
