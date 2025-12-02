<?php

namespace App\Jobs;

use App\Models\AutoDm;
use App\Models\User;
use App\Services\TwitterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAutoDms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $userId;
    public array $recipients; // [['twitter_recipient_id' => ..., 'context' => ...], ...]
    public string $sourceType;
    public ?string $campaignName;

    /**
     * Create a new job instance.
     */
    public function __construct(int $userId, array $recipients, string $sourceType = 'campaign', ?string $campaignName = null)
    {
        $this->userId = $userId;
        $this->recipients = $recipients;
        $this->sourceType = $sourceType;
        $this->campaignName = $campaignName;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            Log::warning('📩 ProcessAutoDms: user not found', ['user_id' => $this->userId]);
            return;
        }

        // Basic DM safety cap per job run
        $maxDmsPerJob = 10;
        $sentCount = 0;
        $skippedExisting = 0;

        $settings = [
            'account_id' => $user->twitter_account_id,
            'consumer_key' => config('services.twitter.api_key'),
            'consumer_secret' => config('services.twitter.api_key_secret'),
            'access_token' => $user->twitter_access_token,
            'access_token_secret' => $user->twitter_access_token_secret,
            'bearer_token' => config('services.twitter.bearer_token'),
        ];

        $twitter = new TwitterService($settings);

        Log::info('📩 ProcessAutoDms started', [
            'user_id' => $user->id,
            'source_type' => $this->sourceType,
            'recipients_count' => count($this->recipients),
        ]);

        foreach ($this->recipients as $recipient) {
            if ($sentCount >= $maxDmsPerJob) {
                break;
            }

            $recipientId = (string) ($recipient['twitter_recipient_id'] ?? '');
            $context = $recipient['context'] ?? null;
            $dmText = $recipient['dm_text'] ?? null;

            if ($recipientId === '' || $dmText === null || trim($dmText) === '') {
                continue;
            }

            // Ensure we don't DM the same recipient twice for this source_type/campaign
            $existing = AutoDm::where('user_id', $user->id)
                ->where('twitter_recipient_id', $recipientId)
                ->where('source_type', $this->sourceType)
                ->when($this->campaignName, function ($q) {
                    $q->where('campaign_name', $this->campaignName);
                })
                ->first();

            if ($existing && in_array($existing->status, ['sent', 'skipped'], true)) {
                $skippedExisting++;
                continue;
            }

            $record = $existing ?: new AutoDm([
                'user_id' => $user->id,
                'twitter_recipient_id' => $recipientId,
                'source_type' => $this->sourceType,
                'campaign_name' => $this->campaignName,
            ]);

            $record->original_context = $context;
            $record->dm_text = $dmText;

            try {
                Log::info('📩 ProcessAutoDms: sending DM (stub)', [
                    'user_id' => $user->id,
                    'recipient_id' => $recipientId,
                    'source_type' => $this->sourceType,
                ]);

                $response = $twitter->sendDirectMessage($recipientId, $dmText);

                // For now we just log and mark as skipped if DM API is not really enabled
                if (isset($response->data['sent']) && $response->data['sent'] === true) {
                    $record->status = 'sent';
                    $record->sent_at = now();
                    $record->last_error = null;
                    $sentCount++;
                } else {
                    $record->status = 'skipped';
                    $record->last_error = $response->data['message'] ?? 'DM sending not enabled';
                }

                $record->save();
            } catch (\Throwable $e) {
                $record->status = 'failed';
                $record->last_error = $e->getMessage();
                $record->save();

                Log::error('📩 ProcessAutoDms: failed to send DM', [
                    'user_id' => $user->id,
                    'recipient_id' => $recipientId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('📩 ProcessAutoDms finished', [
            'user_id' => $user->id,
            'source_type' => $this->sourceType,
            'sent_count' => $sentCount,
            'skipped_existing' => $skippedExisting,
        ]);
    }
}


