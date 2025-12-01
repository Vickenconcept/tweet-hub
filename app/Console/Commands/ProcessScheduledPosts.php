<?php

namespace App\Console\Commands;

use App\Jobs\ProcessScheduledPost;
use App\Jobs\ProcessScheduledThread;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledPosts extends Command
{
    protected $signature = 'posts:process-scheduled';
    protected $description = 'Process scheduled posts that are due';

    public function handle()
    {
        Log::info('🔄 Starting scheduled posts processing command');

        // Get all scheduled posts
        $posts = Post::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->orderBy('in_reply_to_post_id', 'asc') // Process parent posts first
            ->orderBy('id', 'asc') // Then by creation order
            ->get();

        Log::info('📌 Found scheduled posts to process', [
            'count' => $posts->count(),
            'post_ids' => $posts->pluck('id')->all(),
        ]);

        $dispatchedCount = 0;
        $processedThreadRoots = [];

        foreach ($posts as $post) {
            // Check if this post is part of a thread
            if ($post->in_reply_to_post_id) {
                // This is a reply post, find the root
                $rootPostId = $this->findThreadRoot($post->in_reply_to_post_id);
                
                // If we haven't processed this thread yet, process it
                if (!in_array($rootPostId, $processedThreadRoots)) {
                    Log::info('🧵 Dispatching scheduled thread for processing', [
                        'root_post_id' => $rootPostId,
                        'trigger_post_id' => $post->id,
                    ]);
                    ProcessScheduledThread::dispatch($rootPostId);
                    $processedThreadRoots[] = $rootPostId;
                    $dispatchedCount++;
                }
            } else {
                // Check if this post has replies (is a thread root)
                $hasReplies = Post::where('in_reply_to_post_id', $post->id)
                    ->where('status', 'scheduled')
                    ->where('scheduled_at', '<=', now())
                    ->exists();

                if ($hasReplies) {
                    // This is a thread root with replies
                    if (!in_array($post->id, $processedThreadRoots)) {
                        Log::info('🧵 Dispatching scheduled thread root for processing', [
                            'root_post_id' => $post->id,
                        ]);
                        ProcessScheduledThread::dispatch($post->id);
                        $processedThreadRoots[] = $post->id;
                        $dispatchedCount++;
                    }
                } else {
                    // This is a single post
                    Log::info('✉️ Dispatching single scheduled post for processing', [
                        'post_id' => $post->id,
                    ]);
                    ProcessScheduledPost::dispatch($post);
                    $dispatchedCount++;
                }
            }
        }

        Log::info('✅ Finished dispatching scheduled posts/threads', [
            'dispatched_count' => $dispatchedCount,
            'processed_thread_roots' => $processedThreadRoots,
        ]);

        $this->info("Dispatched {$dispatchedCount} posts/threads for processing.");
    }

    private function findThreadRoot($postId)
    {
        $currentPost = Post::find($postId);
        while ($currentPost && $currentPost->in_reply_to_post_id) {
            $currentPost = Post::find($currentPost->in_reply_to_post_id);
        }
        return $currentPost ? $currentPost->id : $postId;
    }
} 