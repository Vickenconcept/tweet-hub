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
            $settings = [
                'account_id' => $user->twitter_account_id,
                'access_token' => $user->twitter_access_token,
                'access_token_secret' => $user->twitter_access_token_secret,
                'consumer_key' => config('services.twitter.api_key'),
                'consumer_secret' => config('services.twitter.api_key_secret'),
                'bearer_token' => config('services.twitter.bearer_token'),
            ];

            $twitter = new TwitterService($settings);
            $mediaIds = [];

            // Process media if any
            if (!empty($this->post->media)) {
                foreach ($this->post->media as $code) {
                    $asset = Asset::where('user_id', $user->id)->where('code', $code)->first();
                    if ($asset) {
                        $mediaId = $twitter->uploadLocalMedia(storage_path('app/public/' . $asset->path));
                        if ($mediaId) {
                            $mediaIds[] = $mediaId;
                        }
                    }
                }
            }

            // Post to Twitter
            $twitter->createTweet($this->post->content, $mediaIds);

            // Mark as sent
            $this->post->update([
                'status' => 'sent',
                'sent_at' => now()
            ]);

        } catch (\Exception $e) {
            // Mark as failed
            $this->post->update([
                'status' => 'failed'
            ]);

            throw $e;
        }
    }
} 