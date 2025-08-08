<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Post;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use App\Services\TwitterService;
use App\Models\Asset;
use Livewire\WithFileUploads;

class ChatComponent extends Component
{
    use WithFileUploads;
    public $activeTab = 'compose';
    public $message = '';
    public $successMessage = '';
    public $errorMessage = '';
    public $draftId = null;
    public $drafts = [];
    public $threadStarted = false;
    public $assetUpload;
    public $showAssetPicker = false;
    public $userAssets = [];
    public $threadMessages = [];
    public $editingThreadIndex = null;
    public $showSchedulePicker = false;
    public $scheduledDateTime = '';
    public $scheduledPosts = [];
    public $sentPosts = [];

    public function mount()
    {
        $this->drafts = [];
        $this->threadMessages = [];
        $this->sentPosts = [];
        if ($this->activeTab === 'drafts') {
            $this->loadDrafts();
        } elseif ($this->activeTab === 'scheduled') {
            $this->loadScheduledPosts();
        } elseif ($this->activeTab === 'sent') {
            $this->loadSentPosts();
        }
    }

    public function startThread()
    {
        $this->threadStarted = true;
        $this->dispatch('thread-state-updated', ['threadStarted' => true]);
    }

    public function endThread()
    {
        $this->threadStarted = false;
        $this->threadMessages = [];
        $this->editingThreadIndex = null;
        $this->message = '';
        $this->dispatch('thread-state-updated', ['threadStarted' => false]);
        $this->dispatch('update-alpine-message', ['message' => $this->message]);
    }

    public function continueDraft($id)
    {
        $draft = Post::find($id);
        if ($draft && $draft->user_id === (Auth::user()?->id)) {
            $this->activeTab = 'compose';
            $this->message = $draft->content;
            $this->draftId = null;
            $draft->delete();
        }
    }

    public function setTab($tab)
    {
        // If leaving compose and editing a draft, save as draft again
        if ($this->activeTab === 'compose' && $tab !== 'compose' && $this->draftId && !empty($this->message)) {
            $draft = Post::find($this->draftId);
            if ($draft && $draft->user_id === (Auth::user()?->id)) {
                $draft->content = $this->message;
                $draft->status = 'draft';
                $draft->save();
            }
        }
        $this->activeTab = $tab;
        if ($tab === 'drafts') {
            $this->loadDrafts();
        } elseif ($tab === 'scheduled') {
            $this->loadScheduledPosts();
        } elseif ($tab === 'sent') {
            $this->loadSentPosts();
        }
    }

    public function loadDrafts()
    {
        $user = Auth::user();
        if ($user) {
            $this->drafts = Post::where('user_id', $user->id)
                ->where('status', 'draft')
                ->orderBy('updated_at', 'desc')
                ->get();
        } else {
            $this->drafts = [];
        }
    }

    public function loadScheduledPosts()
    {
        $user = Auth::user();
        if ($user) {
            $this->scheduledPosts = Post::where('user_id', $user->id)
                ->where('status', 'scheduled')
                ->orderBy('scheduled_at', 'asc')
                ->get();
        } else {
            $this->scheduledPosts = [];
        }
    }

    public function schedulePost()
    {
        // Check if we have thread messages or a current message
        if (empty($this->threadMessages) && empty(trim($this->message))) {
            return;
        }

        $this->validate([
            'scheduledDateTime' => 'required|date|after:now'
        ]);

        $user = Auth::user();
        if (!$user) {
            $this->errorMessage = 'You must be logged in to schedule posts.';
            return;
        }

        // Prepare messages array
        $messages = $this->threadMessages;
        if (!empty(trim($this->message))) {
            $messages[] = $this->message;
        }

        $isThread = count($messages) > 1;
        $prevLocalPostId = null;

        // Schedule each message in the thread
        foreach ($messages as $index => $part) {
            $part = trim($part);
            if ($part === '') continue;

            // Extract image codes
            $media = [];
            if (preg_match_all('/\[img:([a-zA-Z0-9]+)\]/', $part, $matches)) {
                $media = $matches[1];
                // Remove image codes from content
                $part = preg_replace('/\[img:([a-zA-Z0-9]+)\]/', '', $part);
            }

            // Create scheduled post
            $post = Post::create([
                'user_id' => $user->id,
                'content' => trim($part),
                'media' => $media,
                'in_reply_to_post_id' => $isThread && $prevLocalPostId ? $prevLocalPostId : null,
                'scheduled_at' => $this->scheduledDateTime,
                'status' => 'scheduled'
            ]);

            // Store the local post ID for the next iteration
            $prevLocalPostId = $post->id;
        }

        $this->message = '';
        $this->threadMessages = [];
        $this->scheduledDateTime = '';
        $this->showSchedulePicker = false;
        $this->threadStarted = false;
        $this->successMessage = $isThread ? 'Thread scheduled successfully!' : 'Post scheduled successfully!';
        
        if ($this->activeTab === 'scheduled') {
            $this->loadScheduledPosts();
        }

        $this->dispatch('post-scheduled');
        $this->dispatch('thread-state-updated', ['threadStarted' => false]);
    }

    public function editScheduledPost($id)
    {
        $post = Post::where('id', $id)
            ->where('user_id', Auth::id())
            ->where('status', 'scheduled')
            ->first();

        if ($post) {
            $this->message = $post->content;
            if (!empty($post->media)) {
                foreach ($post->media as $code) {
                    $this->message .= " [img:$code] ";
                }
            }
            $this->scheduledDateTime = $post->scheduled_at->format('Y-m-d\TH:i');
            $this->showSchedulePicker = true;
            $this->activeTab = 'compose';
            
            // Delete the scheduled post as we're editing it
            $post->delete();
            
            $this->dispatch('update-alpine-message', ['message' => $this->message]);
        }
    }

    public function deleteScheduledPost($id)
    {
        Post::where('id', $id)
            ->where('user_id', Auth::id())
            ->where('status', 'scheduled')
            ->delete();

        $this->loadScheduledPosts();
    }

    public function updatedMessage($value)
    {
        // Handle draft saving
        $user = Auth::user();
        if (!$user) return;
        
        $content = trim($value);
        if ($content === '') {
            if ($this->draftId) {
                Post::where('id', $this->draftId)->where('user_id', $user->id)->delete();
                $this->draftId = null;
            }
            
            // Only end thread if no messages
            if (empty($this->threadMessages)) {
                $this->threadStarted = false;
                $this->dispatch('thread-state-updated', ['threadStarted' => false]);
            }
            return;
        }

        // Save as draft
        $draft = Post::where('user_id', $user->id)
            ->where('status', 'draft')
            ->whereNull('in_reply_to_post_id')
            ->first();

        if ($draft) {
            $draft->content = $content;
            $draft->save();
            $this->draftId = $draft->id;
        } else {
            $draft = Post::create([
                'user_id' => $user->id,
                'content' => $content,
                'media' => null,
                'in_reply_to_post_id' => null,
                'status' => 'draft',
            ]);
            $this->draftId = $draft->id;
        }
    }

    public function addToThread()
    {
        if (empty(trim($this->message))) {
            return;
        }
        
        if ($this->editingThreadIndex !== null) {
            $this->threadMessages[$this->editingThreadIndex] = trim($this->message);
            $this->editingThreadIndex = null;
        } else {
            $this->threadMessages[] = trim($this->message);
        }
        
        $this->message = '';
        $this->threadStarted = true;
        $this->dispatch('thread-message-added');
        $this->dispatch('thread-state-updated', ['threadStarted' => true]);
        $this->dispatch('update-alpine-message', ['message' => $this->message]);
    }

    public function editThreadMessage($index)
    {
        if (isset($this->threadMessages[$index])) {
            $this->message = $this->threadMessages[$index];
            $this->editingThreadIndex = $index;
            $this->threadStarted = true;
            $this->dispatch('thread-state-updated', ['threadStarted' => true]);
            $this->dispatch('update-alpine-message', ['message' => $this->message]);
        }
    }

    public function removeThreadMessage($index)
    {
        if (isset($this->threadMessages[$index])) {
            array_splice($this->threadMessages, $index, 1);
            if ($this->editingThreadIndex === $index) {
                $this->editingThreadIndex = null;
                $this->message = '';
                $this->dispatch('update-alpine-message', ['message' => $this->message]);
            }
            // If no more thread messages and empty content, end thread mode
            if (empty($this->threadMessages) && empty(trim($this->message))) {
                $this->endThread();
            } else {
                $this->threadStarted = true;
                $this->dispatch('thread-state-updated', ['threadStarted' => true]);
            }
        }
    }

    public function savePost()
    {
        if (empty($this->threadMessages) && empty(trim($this->message))) {
            return;
        }

        $messages = $this->threadMessages;
        if (!empty(trim($this->message))) {
            $messages[] = $this->message;
        }

        $user = Auth::user();
        if (!$user) {
            $this->errorMessage = 'You must be logged in to post.';
            return;
        }
        if (!$user->isTwitterConnected()) {
            $this->errorMessage = 'You must connect your X (Twitter) account first.';
            return;
        }

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
        $prevLocalPostId = null;
        $isThread = count($messages) > 1;
        $threadStarted = false;

        foreach ($messages as $part) {
            $part = trim($part);
            if ($part === '') continue;

            // Parse [img:code] tags and upload assets
            $mediaIds = [];
            $mediaCodes = [];
            if (preg_match_all('/\[img:([a-zA-Z0-9]+)\]/', $part, $matches)) {
                foreach ($matches[1] as $code) {
                    $asset = Asset::where('user_id', $user->id)->where('code', $code)->first();
                    if ($asset) {
                        $mediaId = $twitter->uploadLocalMedia(storage_path('app/public/' . $asset->path));
                        if ($mediaId) {
                            $mediaIds[] = $mediaId;
                            $mediaCodes[] = $code;
                        }
                    }
                }
                // Remove all [img:code] tags from the message
                $part = preg_replace('/\[img:([a-zA-Z0-9]+)\]/', '', $part);
            }

            // Check character limit and truncate if necessary
            $charCount = mb_strlen($part, 'UTF-8');
            if ($charCount > 280) {
                $originalPart = $part;
                $part = $twitter->truncateForTwitter($part);
                $this->errorMessage = "Tweet part was too long ({$charCount} chars) and has been truncated to fit Twitter's 280 character limit.";
            }

            try {
                if ($isThread && $prevTweetId) {
                    $response = $twitter->createTweet($part, $mediaIds, $prevTweetId);
                } else {
                    $response = $twitter->createTweet($part, $mediaIds);
                }
                
                if (isset($response->data) && isset($response->data->id)) {
                    $prevTweetId = $response->data->id;
                    
                    // Save the post to database
                    $post = Post::create([
                        'user_id' => $user->id,
                        'content' => $part,
                        'media' => $mediaCodes,
                        'twitter_post_id' => $response->data->id,
                        'in_reply_to_post_id' => $isThread && $threadStarted ? $prevLocalPostId : null,
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);
                    
                    // Store the local post ID for the next iteration
                    $prevLocalPostId = $post->id;
                }
                
                if ($isThread && !$threadStarted) {
                    $threadStarted = true;
                }
            } catch (\Exception $e) {
                $this->errorMessage = 'Failed to post to X: ' . $e->getMessage();
                return;
            }
        }

        $this->message = '';
        $this->threadMessages = [];
        $this->editingThreadIndex = null;
        $this->draftId = null;
        $this->successMessage = $isThread ? 'Thread posted to X successfully!' : 'Tweet posted to X successfully!';
        $this->errorMessage = '';
        $this->threadStarted = false;
        $this->dispatch('thread-state-updated', ['threadStarted' => false]);
        $this->dispatch('tweet-posted', message: $this->successMessage);
        
        // Load sent posts if we're on the sent tab
        if ($this->activeTab === 'sent') {
            $this->loadSentPosts();
        }
    }

    public function uploadAsset()
    {
        $this->validate([
            'assetUpload' => 'required|image|max:5120',
        ]);
        $user = Auth::user();
        if (!$user) {
            $this->errorMessage = 'You must be logged in to upload.';
            return;
        }
        $file = $this->assetUpload;
        $path = $file->store('assets', 'public');
        $code = uniqid();
        $asset = Asset::create([
            'user_id' => $user->id,
            'type' => 'image',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'code' => $code,
        ]);
        
        $this->message = rtrim($this->message) . " [img:$code] ";
        $this->assetUpload = null;
        $this->successMessage = 'Image uploaded and added to message!';
        $this->dispatch('tweet-asset-uploaded', code: $code);
        $this->dispatch('update-alpine-message', ['message' => $this->message]);

        // Maintain thread mode if active
        if ($this->threadStarted) {
            $this->dispatch('thread-state-updated', ['threadStarted' => true]);
        }
    }

    public function toggleAssetPicker()
    {
        $this->showAssetPicker = !$this->showAssetPicker;
        if ($this->showAssetPicker) {
            $user = Auth::user();
            $this->userAssets = $user ? Asset::where('user_id', $user->id)->orderBy('created_at', 'desc')->get() : [];
        }
    }

    public function selectAsset($code)
    {
        $this->message = rtrim($this->message) . " [img:$code] ";
        $this->showAssetPicker = false;
        $this->dispatch('update-alpine-message', ['message' => $this->message]);

        // Maintain thread mode if active
        if ($this->threadStarted) {
            $this->dispatch('thread-state-updated', ['threadStarted' => true]);
        }
    }

    public function loadUserAssets()
    {
        $user = Auth::user();
        $this->userAssets = $user ? Asset::where('user_id', $user->id)->orderBy('created_at', 'desc')->get() : [];
    }

    public function loadSentPosts()
    {
        $user = Auth::user();
        if ($user) {
            $this->sentPosts = Post::where('user_id', $user->id)
                ->where('status', 'sent')
                ->orderBy('sent_at', 'desc')
                ->get();
        } else {
            $this->sentPosts = [];
        }
    }

    public function deleteSentPost($id)
    {
        Post::where('id', $id)
            ->where('user_id', Auth::id())
            ->where('status', 'sent')
            ->delete();

        $this->loadSentPosts();
        $this->successMessage = 'Post deleted successfully!';
    }

    public function clearAllSentPosts()
    {
        Post::where('user_id', Auth::id())
            ->where('status', 'sent')
            ->delete();

        $this->loadSentPosts();
        $this->successMessage = 'All sent posts cleared successfully!';
    }

    #[On('edit-idea-in-chat')]
    public function editIdeaInChat($idea)
    {
        $this->message = $idea;
        $this->activeTab = 'compose';
        $this->dispatch('update-alpine-message', ['message' => $this->message]);
        $this->successMessage = 'Idea loaded for editing! You can now modify and post it.';
    }

    public function render()
    {
        return view('livewire.chat-component', [
            'activeTab' => $this->activeTab,
            'drafts' => $this->drafts,
        ]);
    }
}
