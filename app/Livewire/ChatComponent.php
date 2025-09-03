<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Post;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use App\Services\TwitterService;
use App\Services\CloudinaryService;
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

    public function updatedAssetUpload()
    {
        Log::info('assetUpload property updated', [
            'assetUpload' => $this->assetUpload ? 'File present' : 'No file'
        ]);
        
        if ($this->assetUpload) {
            $this->uploadAsset();
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
                    // Get the asset to determine its type
                    $asset = Asset::where('code', $code)->first();
                    if ($asset) {
                        $tag = match($asset->type) {
                            'video' => 'vid',
                            'image' => str_contains($asset->original_name, '.gif') ? 'gif' : 'img',
                            default => 'img'
                        };
                        $this->message .= " [$tag:$code] ";
                    } else {
                        // Fallback to img if asset not found
                    $this->message .= " [img:$code] ";
                    }
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
        if (!$user->twitter_account_connected) {
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

            // Parse asset codes [img:code], [vid:code], [gif:code] and upload assets
            $mediaIds = [];
            $mediaCodes = [];
            if (preg_match_all('/\[(img|vid|gif):([a-zA-Z0-9]+)\]/', $part, $matches)) {
                Log::info('Found asset codes in message', [
                    'codes' => $matches[2],
                    'types' => $matches[1],
                    'message_part' => $part
                ]);
                
                foreach ($matches[2] as $index => $code) {
                    $asset = Asset::where('user_id', $user->id)->where('code', $code)->first();
                    if ($asset) {
                        Log::info('Processing asset', [
                            'code' => $code,
                            'asset_path' => $asset->path,
                            'is_cloudinary' => str_contains($asset->path, 'cloudinary.com')
                        ]);
                        
                        // Skip video uploads for now due to Twitter API issues
                        if ($asset->type === 'video') {
                            Log::info('Skipping video upload due to API limitations', ['code' => $code]);
                            $this->errorMessage = 'Video uploads are temporarily disabled due to Twitter API limitations. The post will continue without the video.';
                            continue; // Skip this asset and continue with others
                        }
                        
                        // For GIFs, try proper upload with consistent media ID handling
                        if (str_contains($asset->original_name, '.gif')) {
                            Log::info('Attempting GIF upload with proper media ID handling', ['code' => $code]);
                            
                            // Try GIF upload once with proper media ID tracking
                            $mediaId = null;
                            
                            try {
                                if (str_contains($asset->path, 'cloudinary.com')) {
                                    $mediaId = $this->uploadCloudinaryMediaToTwitter($twitter, $asset->path);
                                } else {
                                    $mediaId = $twitter->uploadLocalMedia($asset->path);
                                }
                                
                                if ($mediaId) {
                                    Log::info('GIF upload successful', [
                                        'code' => $code,
                                        'media_id' => $mediaId
                                    ]);
                                    
                                    // Add to arrays immediately to ensure consistency
                                    $mediaIds[] = $mediaId;
                                    $mediaCodes[] = $code;
                                    
                                    Log::info('GIF uploaded successfully with consistent media ID', [
                                        'code' => $code,
                                        'media_id' => $mediaId
                                    ]);
                                    continue; // Skip the normal upload process
                                }
                            } catch (\Exception $e) {
                                Log::warning('GIF upload failed', [
                                    'code' => $code,
                                    'error' => $e->getMessage()
                                ]);
                            }
                            
                            // If GIF upload failed, show error but don't convert to static image
                            Log::error('GIF upload failed', ['code' => $code]);
                            $this->errorMessage = 'GIF upload failed. The post will continue without the GIF.';
                            
                            // Add fallback content for GIFs
                            $part = trim($part);
                            if (empty($part)) {
                                $part = '🎬'; // Add a movie emoji as fallback
                            }
                            
                            continue; // Skip this asset and continue with others
                        }
                        
                        // Check if it's a Cloudinary URL or local file
                        if (str_contains($asset->path, 'cloudinary.com')) {
                            // For Cloudinary URLs, we need to download the file first
                            $mediaId = $this->uploadCloudinaryMediaToTwitter($twitter, $asset->path);
                        } else {
                            // For local files, use the existing method
                            $fullPath = storage_path('app/public/' . $asset->path);
                            $mediaId = $twitter->uploadLocalMedia($fullPath);
                        }
                        
                        // If uploadLocalMedia fails, try the original uploadMedia method
                        if (!$mediaId) {
                            Log::info('Trying alternative upload method', ['code' => $code]);
                            if (str_contains($asset->path, 'cloudinary.com')) {
                                $tempFile = $this->downloadCloudinaryFile($asset->path);
                                if ($tempFile) {
                                    $mediaId = $twitter->uploadMedia($tempFile);
                                    unlink($tempFile); // Clean up
                                }
                            } else {
                                $fullPath = storage_path('app/public/' . $asset->path);
                                $mediaId = $twitter->uploadMedia($fullPath);
                            }
                        }
                        
                        if ($mediaId) {
                            $mediaIds[] = $mediaId;
                            $mediaCodes[] = $code;
                            Log::info('Media uploaded successfully', [
                                'code' => $code,
                                'media_id' => $mediaId
                            ]);
                        } else {
                            Log::error('Failed to upload media', [
                                'code' => $code,
                                'path' => $asset->path,
                                'asset_type' => $asset->type
                            ]);
                            
                            // Add specific error message for different media types
                            if ($asset->type === 'video') {
                                $this->errorMessage = 'Video upload failed. Please ensure the video is under 15MB and in MP4 format. The post will continue without the video.';
                            } elseif (str_contains($asset->original_name, '.gif')) {
                                $this->errorMessage = 'GIF upload failed. Twitter may need more time to process animated GIFs. The post will continue without the GIF.';
                                
                                // For GIFs, try to add a fallback message
                                $part = trim($part);
                                if (empty($part)) {
                                    $part = '🎬'; // Add a movie emoji as fallback
                                }
                            }
                            
                            // Continue without this media - don't fail the entire post
                        }
                    } else {
                        Log::error('Asset not found', ['code' => $code, 'user_id' => $user->id]);
                    }
                }
                // Remove all asset code tags from the message
                $part = preg_replace('/\[(img|vid|gif):([a-zA-Z0-9]+)\]/', '', $part);
            }

            // If we have no text content, add a default message
            if (empty(trim($part))) {
                if (!empty($mediaIds)) {
                    // Check if we have videos or GIFs
                    $hasVideo = false;
                    $hasGif = false;
                    foreach ($matches[1] ?? [] as $type) {
                        if ($type === 'vid') $hasVideo = true;
                        if ($type === 'gif') $hasGif = true;
                    }
                    
                    if ($hasVideo) {
                        $part = '🎬'; // Video emoji
                    } elseif ($hasGif) {
                        $part = '✨'; // Sparkles for GIF
                    } else {
                        $part = '📸'; // Camera for image
                    }
                } else {
                    $part = 'Hello! 👋'; // Default text
                }
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
        Log::info('uploadAsset method called', [
            'assetUpload' => $this->assetUpload ? 'File present' : 'No file',
            'message_before' => $this->message
        ]);
        
        if (!$this->assetUpload) {
            Log::warning('No file to upload');
            $this->errorMessage = 'No file selected for upload.';
            return;
        }
        
        $this->validate([
            'assetUpload' => 'required|image|max:5120',
        ]);
        $user = Auth::user();
        if (!$user) {
            $this->errorMessage = 'You must be logged in to upload.';
            return;
        }
        $file = $this->assetUpload;
        $cloudinaryService = new CloudinaryService();
        
                    // Upload to Cloudinary - let it determine the type
            $uploadResult = $cloudinaryService->uploadFile($file);
        
        $code = uniqid();
        $asset = Asset::create([
            'user_id' => $user->id,
            'type' => $uploadResult['file_type'], // Use the detected type
            'path' => $uploadResult['file_path'], // Store Cloudinary URL
            'original_name' => $uploadResult['original_name'],
            'code' => $code,
        ]);
        
        // Use appropriate tag based on media type
        $tag = match($uploadResult['file_type']) {
            'video' => 'vid',
            'image' => str_contains($uploadResult['original_name'], '.gif') ? 'gif' : 'img',
            default => 'img'
        };
        
        $this->message = rtrim($this->message) . " [$tag:$code] ";
        $this->assetUpload = null;
        $this->successMessage = ucfirst($uploadResult['file_type']) . ' uploaded to Cloudinary and added to message!';
        $this->dispatch('tweet-asset-uploaded', code: $code);
        $this->dispatch('update-alpine-message', ['message' => $this->message]);

        Log::info('Media uploaded to Cloudinary successfully', [
            'code' => $code,
            'type' => $uploadResult['file_type'],
            'cloudinary_url' => $uploadResult['file_path'],
            'message_after' => $this->message
        ]);

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
        // Get the asset to determine its type
        $asset = Asset::where('code', $code)->first();
        if ($asset) {
            $tag = match($asset->type) {
                'video' => 'vid',
                'image' => str_contains($asset->original_name, '.gif') ? 'gif' : 'img',
                default => 'img'
            };
            $this->message = rtrim($this->message) . " [$tag:$code] ";
        } else {
            // Fallback to img if asset not found
        $this->message = rtrim($this->message) . " [img:$code] ";
        }
        
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

    /**
     * Download Cloudinary file to temporary location
     */
    private function downloadCloudinaryFile($cloudinaryUrl)
    {
        try {
            $fileContent = file_get_contents($cloudinaryUrl);
            if ($fileContent === false) {
                return null;
            }
            
            $urlParts = parse_url($cloudinaryUrl);
            $pathInfo = pathinfo($urlParts['path']);
            $extension = $pathInfo['extension'] ?? 'jpg';
            
            $tempFile = tempnam(sys_get_temp_dir(), 'cloudinary_') . '.' . $extension;
            file_put_contents($tempFile, $fileContent);
            
            return $tempFile;
        } catch (\Exception $e) {
            Log::error('Failed to download Cloudinary file', [
                'url' => $cloudinaryUrl,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Upload Cloudinary media to Twitter
     */
    private function uploadCloudinaryMediaToTwitter($twitter, $cloudinaryUrl)
    {
        try {
            Log::info('Starting Cloudinary to Twitter upload', ['url' => $cloudinaryUrl]);
            
            // Download the file from Cloudinary
            $fileContent = file_get_contents($cloudinaryUrl);
            
            if ($fileContent === false) {
                Log::error('Failed to download file from Cloudinary', ['url' => $cloudinaryUrl]);
                return null;
            }
            
            // Get file extension from URL
            $urlParts = parse_url($cloudinaryUrl);
            $pathInfo = pathinfo($urlParts['path']);
            $extension = $pathInfo['extension'] ?? 'jpg';
            
            // Create temp file with proper extension
            $tempFile = tempnam(sys_get_temp_dir(), 'cloudinary_') . '.' . $extension;
            file_put_contents($tempFile, $fileContent);
            
            Log::info('File downloaded and saved', [
                'temp_file' => $tempFile,
                'file_size' => filesize($tempFile),
                'extension' => $extension
            ]);
            
            // Upload to Twitter
            $mediaId = $twitter->uploadLocalMedia($tempFile);
            
            Log::info('Twitter upload result', [
                'media_id' => $mediaId,
                'temp_file' => $tempFile
            ]);
            
            // Clean up temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            return $mediaId;
            
        } catch (\Exception $e) {
            Log::error('Failed to upload Cloudinary media to Twitter', [
                'url' => $cloudinaryUrl,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Convert GIF to static image as fallback
     */
    private function convertGifToStaticImage($gifPath)
    {
        try {
            Log::info('Converting GIF to static image', ['gif_path' => $gifPath]);
            
            // Create a temporary file for the converted image
            $tempPath = tempnam(sys_get_temp_dir(), 'gif_converted_') . '.jpg';
            
            // Use GD to convert GIF to JPEG
            $gif = imagecreatefromgif($gifPath);
            if ($gif === false) {
                throw new \Exception('Failed to create image from GIF');
            }
            
            // Convert to JPEG
            $result = imagejpeg($gif, $tempPath, 90);
            imagedestroy($gif);
            
            if ($result === false) {
                throw new \Exception('Failed to save converted image');
            }
            
            Log::info('GIF converted to static image', [
                'original' => $gifPath,
                'converted' => $tempPath,
                'size' => filesize($tempPath)
            ]);
            
            return $tempPath;
        } catch (\Exception $e) {
            Log::error('Failed to convert GIF to static image', [
                'gif_path' => $gifPath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function render()
    {
        return view('livewire.chat-component', [
            'activeTab' => $this->activeTab,
            'drafts' => $this->drafts,
        ]);
    }
}
