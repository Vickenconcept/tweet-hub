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

            $twitter = new TwitterService($user);

            $texts = $threadPosts->pluck('content')->all();
            $mediaByPart = [];

            foreach ($threadPosts as $index => $post) {
                $mediaIds = [];

                if (! empty($post->media)) {
                    foreach ($post->media as $code) {
                        $asset = Asset::where('user_id', $user->id)->where('code', $code)->first();
                        if ($asset) {
                            $mediaId = $twitter->uploadLocalMedia(storage_path('app/public/'.$asset->path));
                            if ($mediaId) {
                                $mediaIds[] = $mediaId;
                            }
                        }
                    }
                }

                if ($mediaIds !== []) {
                    $mediaByPart[$index] = $mediaIds;
                }
            }

            $response = $twitter->createThread($texts, $mediaByPart);
            $tweetIds = $response->data->tweet_ids ?? [$response->data->id ?? null];

            foreach ($threadPosts as $index => $post) {
                $post->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'twitter_post_id' => $tweetIds[$index] ?? null,
                ]);
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
