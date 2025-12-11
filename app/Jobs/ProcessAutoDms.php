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
            $tweetId = $recipient['tweet_id'] ?? null;
            $interactionType = $recipient['interaction_type'] ?? null;

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
                'tweet_id' => $tweetId,
                'interaction_type' => $interactionType,
            ]);

            $record->original_context = $context;
            
            // Enforce 179 character limit for public replies (Twitter API restriction)
            // Truncate if longer to prevent 403 Forbidden errors
            $originalLength = mb_strlen($dmText);
            if ($originalLength > 179) {
                $dmText = mb_substr($dmText, 0, 179);
                Log::warning('📩 ProcessAutoDms: DM text truncated to 179 characters', [
                    'user_id' => $user->id,
                    'recipient_id' => $recipientId,
                    'original_length' => $originalLength,
                    'truncated_length' => mb_strlen($dmText),
                ]);
            }
            
            $record->dm_text = $dmText;

            try {
                // Get recipient username (required for public replies)
                $recipientUsername = null;
                try {
                    $userInfo = $twitter->findUser($recipientId, \Noweh\TwitterApi\UserLookup::MODES['ID']);
                    if ($userInfo && isset($userInfo->data)) {
                        $userData = is_array($userInfo->data) ? $userInfo->data : (array) $userInfo->data;
                        if (is_object($userData)) {
                            $recipientUsername = $userData->username ?? null;
                            $record->recipient_username = $recipientUsername;
                            $record->recipient_name = $userData->name ?? null;
                        } else {
                            $recipientUsername = $userData['username'] ?? null;
                            $record->recipient_username = $recipientUsername;
                            $record->recipient_name = $userData['name'] ?? null;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('Could not fetch recipient username for public reply', [
                        'recipient_id' => $recipientId,
                        'error' => $e->getMessage(),
                    ]);
                }
                
                if (!$recipientUsername) {
                    throw new \Exception("Could not fetch username for recipient ID: {$recipientId}");
                }
                
                if (!$tweetId) {
                    throw new \Exception("Tweet ID is required for public replies");
                }
                
                Log::info('💬 ProcessAutoDms: preparing to send public reply (Basic tier workaround)', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'recipient_id' => $recipientId,
                    'recipient_username' => $recipientUsername,
                    'tweet_id' => $tweetId,
                    'is_test_dm' => $isTestDm,
                    'source_type' => $this->sourceType,
                    'campaign_name' => $this->campaignName,
                    'reply_text_preview' => \Illuminate\Support\Str::limit($dmText, 100),
                    'method' => 'public_reply',
                    'note' => 'Using public replies instead of DMs (works on Basic tier)',
                ]);

                // Use public reply instead of DM (works on Basic tier)
                $response = $twitter->sendPublicReply($tweetId, $recipientUsername, $dmText);

                // Check if reply was sent successfully
                if (isset($response->data['sent']) && $response->data['sent'] === true) {
                    $record->status = 'sent';
                    $record->sent_at = now();
                    $record->last_error = null;
                    
                    // Save Twitter API response data for tracking
                    if (isset($response->data['reply_tweet_id'])) {
                        $record->twitter_message_id = $response->data['reply_tweet_id'];
                    }
                    if (isset($response->raw->data->id)) {
                        $record->twitter_event_id = $response->raw->data->id;
                    }
                    
                    $sentCount++;
                    
                    Log::info('✅ ProcessAutoDms: Public reply sent successfully', [
                        'user_id' => $user->id,
                        'user_email' => $user->email,
                        'recipient_id' => $recipientId,
                        'recipient_username' => $recipientUsername,
                        'tweet_id' => $tweetId,
                        'reply_tweet_id' => $response->data['reply_tweet_id'] ?? null,
                        'source_type' => $this->sourceType,
                        'campaign_name' => $this->campaignName,
                        'sent_at' => $record->sent_at->toDateTimeString(),
                        'reply_text_preview' => \Illuminate\Support\Str::limit($dmText, 80),
                        'method' => 'public_reply',
                    ]);
                } else {
                    $record->status = 'failed';
                    $errorMsg = $response->data['message'] ?? 'Public reply sending failed - unknown error';
                    $record->last_error = $errorMsg;
                    
                    Log::error('❌ ProcessAutoDms: Public reply sending failed', [
                        'user_id' => $user->id,
                        'user_email' => $user->email,
                        'recipient_id' => $recipientId,
                        'recipient_username' => $recipientUsername,
                        'tweet_id' => $tweetId,
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

                // Log error (public replies should work on Basic tier)
                Log::error('❌ ProcessAutoDms: exception while sending public reply', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'recipient_id' => $recipientId,
                    'tweet_id' => $tweetId ?? null,
                    'is_test_dm' => $isTestDm,
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


