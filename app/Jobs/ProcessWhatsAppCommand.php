<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\WhatsAppCommandLog;
use App\Services\WhatsApp\WhatsAppCommandExecutor;
use App\Services\WhatsApp\WhatsAppIntentResolver;
use App\Services\WhatsApp\WhatsAppUserMessages;
use App\Services\ZernioService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppCommand implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(
        protected string $eventId,
        protected string $conversationId,
        protected string $fromPhone,
        protected string $messageText,
        protected ?int $userId = null,
    ) {
        $this->onConnection('database');
    }

    public function handle(
        WhatsAppIntentResolver $intentResolver,
        WhatsAppCommandExecutor $executor,
        ZernioService $zernio,
    ): void {
        $log = WhatsAppCommandLog::firstOrCreate(
            ['zernio_event_id' => $this->eventId],
            [
                'from_phone' => $this->fromPhone,
                'conversation_id' => $this->conversationId,
                'command' => $this->messageText,
                'status' => 'processing',
            ]
        );

        Log::info('WhatsApp command processing', [
            'event_id' => $this->eventId,
            'from_phone' => $this->fromPhone,
            'user_id' => $this->userId,
            'command_preview' => mb_substr($this->messageText, 0, 120),
            'attempt' => $this->attempts(),
        ]);

        if ($log->status === 'success') {
            Log::info('WhatsApp command skipped (already delivered)', ['event_id' => $this->eventId]);

            return;
        }

        $pendingReply = Cache::get($this->replyCacheKey());
        if (is_string($pendingReply) && $pendingReply !== '') {
            Log::info('WhatsApp retrying pending Zernio delivery', ['event_id' => $this->eventId]);
            $this->deliverReply($zernio, $log, $pendingReply);

            return;
        }

        $user = $this->resolveUser();

        if (! $user) {
            $this->deliverReply($zernio, $log, $this->unlinkedMessage());

            return;
        }

        $log->update(['user_id' => $user->id]);

        if ($this->isRateLimited($user)) {
            $this->deliverReply(
                $zernio,
                $log,
                WhatsAppUserMessages::tooBusyTryLater(),
                failedStatus: true,
                error: 'rate_limited',
            );

            return;
        }

        if ($user->zernio_conversation_id !== $this->conversationId) {
            $user->update(['zernio_conversation_id' => $this->conversationId]);
        }

        $parsed = $intentResolver->resolve($this->messageText);

        Log::info('WhatsApp intent resolved', [
            'event_id' => $this->eventId,
            'action' => $parsed['action'] ?? 'unknown',
            'resolved_by' => $parsed['resolved_by'] ?? 'none',
        ]);

        if ($parsed['action'] !== 'verify' && ! $user->isWhatsAppVerified()) {
            $log->update(['parsed_action' => 'unverified']);
            $this->deliverReply(
                $zernio,
                $log,
                "👋 Link your WhatsApp in XEngager → WhatsApp Settings.\n\nOr reply: verify {6-digit code}",
            );

            return;
        }

        if ($parsed['action'] !== 'verify' && ! $user->whatsapp_bot_enabled) {
            $log->update(['parsed_action' => 'disabled']);
            $this->deliverReply(
                $zernio,
                $log,
                'WhatsApp remote control is disabled. Enable it in XEngager → WhatsApp Settings.',
            );

            return;
        }

        $response = $executor->execute($user, $parsed, $log);
        Cache::put($this->replyCacheKey(), $response, now()->addHours(2));
        $this->deliverReply($zernio, $log, $response);

        if ($log->fresh()->status !== 'success') {
            return;
        }

        Log::info('WhatsApp command completed', [
            'event_id' => $this->eventId,
            'user_id' => $user->id,
            'action' => $parsed['action'] ?? 'unknown',
            'status' => $log->fresh()->status,
            'response_preview' => mb_substr($response, 0, 120),
        ]);
    }

    protected function deliverReply(
        ZernioService $zernio,
        WhatsAppCommandLog $log,
        string $response,
        bool $failedStatus = false,
        ?string $error = null,
    ): void {
        if ($zernio->sendInboxMessage($this->conversationId, $response)) {
            Cache::forget($this->replyCacheKey());
            $log->update([
                'status' => $failedStatus ? 'failed' : 'success',
                'response_preview' => mb_substr($response, 0, 500),
                'error' => $error,
            ]);

            return;
        }

        $log->update([
            'status' => 'processing',
            'response_preview' => mb_substr($response, 0, 500),
            'error' => 'zernio_send_failed',
        ]);

        Log::warning('Zernio reply not delivered; queue job will retry', [
            'event_id' => $this->eventId,
            'attempt' => $this->attempts(),
            'max_tries' => $this->tries,
        ]);

        $this->release($this->backoff[min($this->attempts() - 1, count($this->backoff) - 1)] ?? 30);
    }

    protected function resolveUser(): ?User
    {
        if ($this->userId) {
            return User::find($this->userId);
        }

        $phone = ZernioService::normalizePhone($this->fromPhone);

        if (preg_match('/^(?:verify\s+)?(\d{6})$/i', trim($this->messageText), $matches)) {
            $byCode = User::where('whatsapp_verification_code', $matches[1])
                ->where('whatsapp_verification_expires_at', '>', now())
                ->first();

            if ($byCode) {
                if ($phone) {
                    $byCode->update(['whatsapp_phone' => $phone]);
                }

                return $byCode->fresh();
            }
        }

        if (! $phone) {
            return null;
        }

        return User::where('whatsapp_phone', $phone)->first();
    }

    protected function unlinkedMessage(): string
    {
        $bot = config('services.zernio.bot_phone_number', 'our WhatsApp number');

        return implode("\n", [
            '👋 Welcome to *XEngager*!',
            '',
            'To control your X account from WhatsApp:',
            '1. Log in at '.config('app.url'),
            '2. Open *WhatsApp Settings*',
            '3. Enter your number and verify',
            '',
            "Then message {$bot} with commands like:",
            'start',
            'post: Hello world!',
            'help',
        ]);
    }

    protected function isRateLimited(User $user): bool
    {
        $limit = max(1, (int) config('services.whatsapp.commands_per_hour', 60));
        $key = 'whatsapp_rate_'.$user->id;
        $count = (int) Cache::get($key, 0);

        if ($count >= $limit) {
            return true;
        }

        Cache::put($key, $count + 1, now()->addHour());

        return false;
    }

    protected function replyCacheKey(): string
    {
        return 'whatsapp_reply_'.$this->eventId;
    }
}
