<?php

namespace App\Console\Commands;

use App\Jobs\ProcessScheduledPost;
use App\Models\Post;
use Illuminate\Console\Command;

class ProcessScheduledPosts extends Command
{
    protected $signature = 'posts:process-scheduled';
    protected $description = 'Process scheduled posts that are due';

    public function handle()
    {
        $posts = Post::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($posts as $post) {
            ProcessScheduledPost::dispatch($post);
        }

        $this->info("Dispatched {$posts->count()} posts for processing.");
    }
} 