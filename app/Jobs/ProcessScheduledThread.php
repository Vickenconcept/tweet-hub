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

class ProcessScheduledThread implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected int $threadRootPostId
    ) {}

    public function handle(): void
    {
        try {
            // Get the root post (first post in thread)
            $rootPost = Post::find($this->threadRootPostId);
            if (!$rootPost || $rootPost->status !== 'scheduled') {
                Log::warning('Root post not found or not scheduled', [
                    'thread_root_post_id' => $this->threadRootPostId,
                    'root_post_status' => $rootPost->status ?? 'not_found',
                ]);
                return;
            }

            $user = $rootPost->user;
            
            Log::info('Processing scheduled thread', [
                'thread_root_post_id' => $this->threadRootPostId,
                'user_id' => $user->id,
                'user_name' => $user->name,
            ]);

            // Get all posts in the thread (root + replies)
            $threadPosts = Post::where('id', $this->threadRootPostId)
                ->orWhere('in_reply_to_post_id', $this->threadRootPostId)
                ->orderBy('id', 'asc')
                ->get();

            Log::info('Thread posts found', [
                'thread_root_post_id' => $this->threadRootPostId,
                'total_posts' => $threadPosts->count(),
                'post_ids' => $threadPosts->pluck('id')->toArray(),
            ]);

            $settings = [
                'account_id' => $user->twitter_account_id,
                'access_token' => $user->twitter_access_token,
                'access_token_secret' => $user->twitter_access_token_secret,
                'consumer_key' => config('services.twitter.api_key'),
                'consumer_secret' => config('services.twitter.api_key_secret'),
                'bearer_token' => config('services.twitter.bearer_token'),
            ];

            $twitter = new TwitterService($settings);
            $prevTweetId = null;

            // Process each post in the thread sequentially
            foreach ($threadPosts as $post) {
                // Update status to processing
                $post->update(['status' => 'processing']);

                $mediaIds = [];

                // Process media if any
                if (!empty($post->media)) {
                    Log::info('Processing media for thread post', [
                        'post_id' => $post->id,
                        'user_id' => $user->id,
                        'media_codes' => $post->media,
                    ]);

                    foreach ($post->media as $code) {
                        $asset = Asset::where('user_id', $user->id)->where('code', $code)->first();
                        if ($asset) {
                            $mediaId = $twitter->uploadLocalMedia(storage_path('app/public/' . $asset->path));
                            if ($mediaId) {
                                $mediaIds[] = $mediaId;
                                Log::info('Media uploaded successfully for thread post', [
                                    'post_id' => $post->id,
                                    'user_id' => $user->id,
                                    'asset_code' => $code,
                                    'media_id' => $mediaId,
                                ]);
                            }
                        }
                    }
                }

                // Post to Twitter
                Log::info('Posting thread post to Twitter', [
                    'post_id' => $post->id,
                    'user_id' => $user->id,
                    'content' => $post->content,
                    'media_ids' => $mediaIds,
                    'is_reply' => $prevTweetId ? true : false,
                    'reply_to_tweet_id' => $prevTweetId,
                ]);

                if ($prevTweetId) {
                    // This is a reply to the previous tweet
                    $response = $twitter->createTweet($post->content, $mediaIds, $prevTweetId);
                } else {
                    // This is the first tweet in the thread
                    $response = $twitter->createTweet($post->content, $mediaIds);
                }

                Log::info('Twitter API response for thread post', [
                    'post_id' => $post->id,
                    'user_id' => $user->id,
                    'response' => $response,
                ]);

                // Mark as sent and store Twitter post ID
                $post->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'twitter_post_id' => $response->data->id ?? null
                ]);

                // Store the Twitter post ID for the next iteration
                $prevTweetId = $response->data->id ?? null;

                Log::info('Thread post marked as sent', [
                    'post_id' => $post->id,
                    'user_id' => $user->id,
                    'twitter_post_id' => $response->data->id ?? null,
                ]);

                // Small delay between posts to ensure proper threading
                if ($threadPosts->last()->id !== $post->id) {
                    sleep(2);
                }
            }

            Log::info('Thread processing completed successfully', [
                'thread_root_post_id' => $this->threadRootPostId,
                'user_id' => $user->id,
                'total_posts_processed' => $threadPosts->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process scheduled thread', [
                'thread_root_post_id' => $this->threadRootPostId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark all posts in the thread as failed
            Post::where('id', $this->threadRootPostId)
                ->orWhere('in_reply_to_post_id', $this->threadRootPostId)
                ->update(['status' => 'failed']);

            throw $e;
        }
    }
}
