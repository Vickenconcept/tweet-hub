<?php

namespace App\Jobs;

use App\Models\Post;
use App\Models\Asset;
use App\Services\TwitterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessScheduledPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected Post $post
    ) {}

    public function handle(): void
    {
        try {
            // Update status to processing
            $this->post->update(['status' => 'processing']);

            $user = $this->post->user;
            
            // Log user information
            Log::info('Processing scheduled post', [
                'post_id' => $this->post->id,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'post_content' => $this->post->content,
                'scheduled_at' => $this->post->scheduled_at,
                'twitter_account_connected' => $user->twitter_account_connected,
                'twitter_account_id' => $user->twitter_account_id,
                'twitter_access_token' => $user->twitter_access_token ? 'SET' : 'NOT_SET',
                'twitter_access_token_secret' => $user->twitter_access_token_secret ? 'SET' : 'NOT_SET',
                'twitter_refresh_token' => $user->twitter_refresh_token ? 'SET' : 'NOT_SET',
            ]);

            // Log full Twitter credentials for debugging
            Log::info('Twitter credentials for user', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'twitter_access_token' => $user->twitter_access_token,
                'twitter_access_token_secret' => $user->twitter_access_token_secret,
                'twitter_refresh_token' => $user->twitter_refresh_token,
                'twitter_account_id' => $user->twitter_account_id,
            ]);

            $settings = [
                'account_id' => $user->twitter_account_id,
                'access_token' => $user->twitter_access_token,
                'access_token_secret' => $user->twitter_access_token_secret,
                'consumer_key' => config('services.twitter.api_key'),
                'consumer_secret' => config('services.twitter.api_key_secret'),
                'bearer_token' => config('services.twitter.bearer_token'),
            ];

            // Log Twitter service settings
            Log::info('Twitter service settings', [
                'user_id' => $user->id,
                'account_id' => $settings['account_id'],
                'access_token' => $settings['access_token'] ? 'SET' : 'NOT_SET',
                'access_token_secret' => $settings['access_token_secret'] ? 'SET' : 'NOT_SET',
                'consumer_key' => $settings['consumer_key'] ? 'SET' : 'NOT_SET',
                'consumer_secret' => $settings['consumer_secret'] ? 'SET' : 'NOT_SET',
                'bearer_token' => $settings['bearer_token'] ? 'SET' : 'NOT_SET',
            ]);

            $twitter = new TwitterService($settings);
            $mediaIds = [];

            // Process media if any
            if (!empty($this->post->media)) {
                Log::info('Processing media for post', [
                    'post_id' => $this->post->id,
                    'user_id' => $user->id,
                    'media_codes' => $this->post->media,
                ]);

                foreach ($this->post->media as $code) {
                    $asset = Asset::where('user_id', $user->id)->where('code', $code)->first();
                    if ($asset) {
                        $mediaId = $twitter->uploadLocalMedia(storage_path('app/public/' . $asset->path));
                        if ($mediaId) {
                            $mediaIds[] = $mediaId;
                            Log::info('Media uploaded successfully', [
                                'post_id' => $this->post->id,
                                'user_id' => $user->id,
                                'asset_code' => $code,
                                'media_id' => $mediaId,
                            ]);
                        } else {
                            Log::warning('Failed to upload media', [
                                'post_id' => $this->post->id,
                                'user_id' => $user->id,
                                'asset_code' => $code,
                            ]);
                        }
                    } else {
                        Log::warning('Asset not found', [
                            'post_id' => $this->post->id,
                            'user_id' => $user->id,
                            'asset_code' => $code,
                        ]);
                    }
                }
            }

            // Post to Twitter
            Log::info('Attempting to post to Twitter', [
                'post_id' => $this->post->id,
                'user_id' => $user->id,
                'content' => $this->post->content,
                'media_ids' => $mediaIds,
            ]);

            $response = $twitter->createTweet($this->post->content, $mediaIds);

            Log::info('Twitter API response', [
                'post_id' => $this->post->id,
                'user_id' => $user->id,
                'response' => $response,
            ]);

            // Mark as sent
            $this->post->update([
                'status' => 'sent',
                'sent_at' => now(),
                'twitter_post_id' => $response->data->id ?? null
            ]);

            Log::info('Post marked as sent successfully', [
                'post_id' => $this->post->id,
                'user_id' => $user->id,
                'twitter_post_id' => $response->data->id ?? null,
            ]);

        } catch (\Exception $e) {
            // Log the error
            Log::error('Failed to process scheduled post', [
                'post_id' => $this->post->id,
                'user_id' => $this->post->user_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark as failed
            $this->post->update([
                'status' => 'failed'
            ]);

            throw $e;
        }
    }
} 