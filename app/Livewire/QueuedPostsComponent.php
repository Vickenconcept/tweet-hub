<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Post;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Carbon\Carbon;

class QueuedPostsComponent extends Component
{
    public $queuedPosts = [];
    public $loading = false;
    public $errorMessage = '';
    public $successMessage = '';
    public $selectedPost = null;
    public $showEditModal = false;
    public $editContent = '';
    public $editScheduledAt = '';
    public $timezone = '';

    public function mount()
    {
        $this->timezone = Auth::user()?->timezone ?? config('app.timezone');
        $this->loadQueuedPosts();
    }

    public function loadQueuedPosts()
    {
        $this->loading = true;
        $user = Auth::user();
        
        if ($user) {
            $this->queuedPosts = Post::where('user_id', $user->id)
                ->where('status', 'scheduled')
                ->orderBy('scheduled_at', 'asc')
                ->get();
        } else {
            $this->queuedPosts = [];
        }
        
        $this->loading = false;
    }

    public function editPost($postId)
    {
        $post = Post::where('id', $postId)
            ->where('user_id', Auth::id())
            ->where('status', 'scheduled')
            ->first();

        if ($post) {
            $this->selectedPost = $post;
            $this->editContent = $post->content;
            $this->editScheduledAt = $post->scheduled_at->timezone($this->timezone)->format('Y-m-d\TH:i');
            $this->showEditModal = true;
        }
    }

    public function updatePost()
    {
        $this->validate([
            'editContent' => 'required|min:1|max:280',
            'editScheduledAt' => 'required|date_format:Y-m-d\TH:i',
        ], [
            'editContent.required' => 'Content is required.',
            'editContent.min' => 'Content must be at least 1 character.',
            'editContent.max' => 'Content cannot exceed 280 characters.',
            'editScheduledAt.required' => 'Schedule time is required.',
            'editScheduledAt.date_format' => 'Please enter a valid date and time.',
        ]);

        try {
            $scheduledAtUserTz = Carbon::createFromFormat('Y-m-d\TH:i', $this->editScheduledAt, $this->timezone);
        } catch (\Throwable $e) {
            $this->errorMessage = 'Invalid schedule time.';
            return;
        }

        if ($scheduledAtUserTz->lessThanOrEqualTo(now($this->timezone))) {
            $this->errorMessage = 'Schedule time must be in the future for your timezone.';
            return;
        }

        $scheduledAt = $scheduledAtUserTz->clone()->timezone(config('app.timezone'));

        try {
            // Refresh the post to ensure we have the latest version
            $post = Post::where('id', $this->selectedPost->id)
                ->where('user_id', Auth::id())
                ->where('status', 'scheduled')
                ->first();

            if (!$post) {
                $this->errorMessage = 'Post not found or you do not have permission to edit it.';
                return;
            }

            $post->update([
                'content' => $this->editContent,
                'scheduled_at' => $scheduledAt,
            ]);

            $this->showEditModal = false;
            $this->selectedPost = null;
            $this->editContent = '';
            $this->editScheduledAt = '';
            $this->errorMessage = '';
            $this->successMessage = 'Post updated successfully!';
            $this->loadQueuedPosts();
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to update post: ' . $e->getMessage();
            Log::error('Failed to update queued post', [
                'post_id' => $this->selectedPost->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function deletePost($postId)
    {
        $post = Post::where('id', $postId)
            ->where('user_id', Auth::id())
            ->where('status', 'scheduled')
            ->first();

        if ($post) {
            $post->delete();
            $this->successMessage = 'Post deleted successfully!';
            $this->loadQueuedPosts();
        }
    }

    public function cancelEdit()
    {
        $this->showEditModal = false;
        $this->selectedPost = null;
        $this->editContent = '';
        $this->editScheduledAt = '';
    }

    public function clearMessage()
    {
        $this->errorMessage = '';
        $this->successMessage = '';
    }

    #[On('post-scheduled')]
    public function refreshPosts()
    {
        $this->loadQueuedPosts();
    }

    public function render()
    {
        return view('livewire.queued-posts-component');
    }
} 