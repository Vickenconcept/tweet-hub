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

        Log::info('📩 ProcessAutoDms job started', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_twitter_id' => $user->twitter_account_id,
            'source_type' => $this->sourceType,
            'campaign_name' => $this->campaignName,
            'recipients_count' => count($this->recipients),
            'max_dms_per_job' => $maxDmsPerJob,
        ]);

        foreach ($this->recipients as $recipient) {
            if ($sentCount >= $maxDmsPerJob) {
                break;
            }

            $recipientId = (string) ($recipient['twitter_recipient_id'] ?? '');
            $context = $recipient['context'] ?? null;
            $dmText = $recipient['dm_text'] ?? null;

            if ($recipientId === '' || $dmText === null || trim($dmText) === '') {
                Log::warning('📩 ProcessAutoDms: skipping invalid recipient', [
                    'user_id' => $user->id,
                    'recipient_id' => $recipientId,
                    'has_dm_text' => !empty($dmText),
                ]);
                continue;
            }

            // Check if this is a test DM (sending to self)
            $isTestDm = ($recipientId === (string) $user->twitter_account_id);

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
                Log::info('📩 ProcessAutoDms: skipping recipient - already processed', [
                    'user_id' => $user->id,
                    'recipient_id' => $recipientId,
                    'existing_status' => $existing->status,
                    'is_test_dm' => $isTestDm,
                ]);
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
                Log::info('📩 ProcessAutoDms: preparing to send DM', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'recipient_id' => $recipientId,
                    'is_test_dm' => $isTestDm,
                    'is_sending_to_self' => $isTestDm,
                    'source_type' => $this->sourceType,
                    'campaign_name' => $this->campaignName,
                    'dm_text_preview' => \Illuminate\Support\Str::limit($dmText, 100),
                    'dm_text_length' => mb_strlen($dmText),
                ]);

                $response = $twitter->sendDirectMessage($recipientId, $dmText);

                // Check if DM was sent successfully
                if (isset($response->data['sent']) && $response->data['sent'] === true) {
                    $record->status = 'sent';
                    $record->sent_at = now();
                    $record->last_error = null;
                    $sentCount++;
                    
                    Log::info('✅ ProcessAutoDms: DM sent successfully', [
                        'user_id' => $user->id,
                        'user_email' => $user->email,
                        'recipient_id' => $recipientId,
                        'is_test_dm' => $isTestDm,
                        'is_sending_to_self' => $isTestDm,
                        'source_type' => $this->sourceType,
                        'campaign_name' => $this->campaignName,
                        'sent_at' => $record->sent_at->toDateTimeString(),
                        'dm_text_preview' => \Illuminate\Support\Str::limit($dmText, 80),
                    ]);
                } else {
                    $record->status = 'failed';
                    $errorMsg = $response->data['message'] ?? 'DM sending failed - unknown error';
                    $record->last_error = $errorMsg;
                    
                    Log::error('❌ ProcessAutoDms: DM sending failed', [
                        'user_id' => $user->id,
                        'user_email' => $user->email,
                        'recipient_id' => $recipientId,
                        'is_test_dm' => $isTestDm,
                        'is_sending_to_self' => $isTestDm,
                        'source_type' => $this->sourceType,
                        'campaign_name' => $this->campaignName,
                        'error' => $errorMsg,
                        'response_data' => $response->data ?? null,
                        'raw_response' => $response->raw ?? null,
                    ]);
                }

                $record->save();
            } catch (\Throwable $e) {
                $record->status = 'failed';
                $errorMessage = $e->getMessage();
                $record->last_error = $errorMessage;
                $record->save();

                // Check if this is a 403 error indicating API plan issue
                $is403Error = str_contains($errorMessage, '403') || str_contains($errorMessage, 'Forbidden');
                $isAccessLevelError = str_contains($errorMessage, 'access level') || str_contains($errorMessage, 'subset of X API');
                
                if ($is403Error && $isAccessLevelError) {
                    Log::error('❌ ProcessAutoDms: DM failed - API plan access issue (likely free plan)', [
                        'user_id' => $user->id,
                        'user_email' => $user->email,
                        'recipient_id' => $recipientId,
                        'is_test_dm' => $isTestDm,
                        'is_sending_to_self' => $isTestDm,
                        'source_type' => $this->sourceType,
                        'campaign_name' => $this->campaignName,
                        'error_message' => $errorMessage,
                        'error_class' => get_class($e),
                        'issue' => 'DM endpoints require Basic plan ($100/month) or higher. Free plan does not have access to v1.1 DM endpoints.',
                        'solution' => 'Upgrade to Basic plan in Twitter Developer Portal, then disconnect and reconnect Twitter account.',
                    ]);
                } else {
                    Log::error('❌ ProcessAutoDms: exception while sending DM', [
                        'user_id' => $user->id,
                        'user_email' => $user->email,
                        'recipient_id' => $recipientId,
                        'is_test_dm' => $isTestDm,
                        'is_sending_to_self' => $isTestDm,
                        'source_type' => $this->sourceType,
                        'campaign_name' => $this->campaignName,
                        'error_message' => $errorMessage,
                        'error_class' => get_class($e),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        }

        Log::info('📩 ProcessAutoDms job finished', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'source_type' => $this->sourceType,
            'campaign_name' => $this->campaignName,
            'total_recipients' => count($this->recipients),
            'sent_count' => $sentCount,
            'skipped_existing' => $skippedExisting,
            'failed_count' => count($this->recipients) - $sentCount - $skippedExisting,
        ]);
    }
}


