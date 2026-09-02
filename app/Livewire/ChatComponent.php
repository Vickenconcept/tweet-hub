<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Post;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use App\Services\TwitterService;
use App\Services\CloudinaryService;
use App\Services\ChatGptService;
use App\Models\Asset;
use Livewire\WithFileUploads;
use Carbon\Carbon;

class ChatComponent extends Component
{
    use WithFileUploads;

    public const MAX_THREAD_PARTS = 2;

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
    public $timezone = '';
    public array $timezoneOptions = [];
    
    // AI Image Generation
    public $showImageGenerator = false;
    public $aiImagePrompt = '';
    public $generatingImage = false;
    public $generatedImageUrl = '';
    public $generatedImageCode = '';

    public function mount()
    {
        $this->timezoneOptions = $this->availableTimezoneOptions();
        $this->timezone = Auth::user()?->timezone ?? config('app.timezone');
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
            'scheduledDateTime' => 'required|date_format:Y-m-d\TH:i'
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

        $userTimezone = $this->userTimezone();

        try {
            $scheduledAtUserTz = Carbon::createFromFormat('Y-m-d\TH:i', $this->scheduledDateTime, $userTimezone);
        } catch (\Throwable $e) {
            $this->errorMessage = 'Invalid schedule time.';
            return;
        }

        if ($scheduledAtUserTz->lessThanOrEqualTo(now($userTimezone))) {
            $this->errorMessage = 'Please pick a future time in your timezone.';
            return;
        }

        $scheduledAt = $scheduledAtUserTz->clone()->timezone(config('app.timezone'));

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
                'scheduled_at' => $scheduledAt->copy(),
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
            $this->scheduledDateTime = $post->scheduled_at->timezone($this->userTimezone())->format('Y-m-d\TH:i');
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

        if (count($this->threadMessages) >= self::MAX_THREAD_PARTS - 1) {
            $this->errorMessage = 'Threads are limited to '.self::MAX_THREAD_PARTS.' tweets. Write the second tweet below and click Tweet now.';

            return;
        }
        
        if ($this->editingThreadIndex !== null) {
            $this->threadMessages[$this->editingThreadIndex] = trim($this->message);
            $this->editingThreadIndex = null;
        } else {
            $this->threadMessages[] = trim($this->message);
        }
        
        $this->message = '';
        $this->errorMessage = '';
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

    /**
     * @return array{text: string, mediaIds: array<int, string>, mediaCodes: array<int, string>}|null
     */
    protected function prepareMessagePart(string $part, $user, TwitterService $twitter): ?array
    {
        $part = trim($part);
        if ($part === '') {
            return null;
        }

        $mediaIds = [];
        $mediaCodes = [];
        $matches = [];

        if (preg_match_all('/\[(img|vid|gif):([a-zA-Z0-9]+)\]/', $part, $matches)) {
            foreach ($matches[2] as $index => $code) {
                $asset = Asset::where('user_id', $user->id)->where('code', $code)->first();
                if (! $asset) {
                    Log::error('Asset not found', ['code' => $code, 'user_id' => $user->id]);
                    continue;
                }

                if ($asset->type === 'video') {
                    $this->errorMessage = 'Video uploads are temporarily disabled due to Twitter API limitations. The post will continue without the video.';
                    continue;
                }

                $mediaId = null;
                if (str_contains($asset->original_name, '.gif')) {
                    try {
                        $mediaId = str_contains($asset->path, 'cloudinary.com')
                            ? $this->uploadCloudinaryMediaToTwitter($twitter, $asset->path)
                            : $twitter->uploadLocalMedia($asset->path);
                    } catch (\Exception $e) {
                        Log::warning('GIF upload failed', ['code' => $code, 'error' => $e->getMessage()]);
                    }

                    if (! $mediaId) {
                        $this->errorMessage = 'GIF upload failed. The post will continue without the GIF.';
                        if (trim($part) === '') {
                            $part = '🎬';
                        }
                        continue;
                    }
                } elseif (str_contains($asset->path, 'cloudinary.com')) {
                    $mediaId = $this->uploadCloudinaryMediaToTwitter($twitter, $asset->path);
                } else {
                    $mediaId = $twitter->uploadLocalMedia(storage_path('app/public/'.$asset->path));
                }

                if (! $mediaId) {
                    if (str_contains($asset->path, 'cloudinary.com')) {
                        $tempFile = $this->downloadCloudinaryFile($asset->path);
                        if ($tempFile) {
                            $mediaId = $twitter->uploadMedia($tempFile);
                            unlink($tempFile);
                        }
                    } else {
                        $mediaId = $twitter->uploadMedia(storage_path('app/public/'.$asset->path));
                    }
                }

                if ($mediaId) {
                    $mediaIds[] = $mediaId;
                    $mediaCodes[] = $code;
                }
            }

            $part = preg_replace('/\[(img|vid|gif):([a-zA-Z0-9]+)\]/', '', $part);
        }

        if (empty(trim($part))) {
            if (! empty($mediaIds)) {
                $hasVideo = in_array('vid', $matches[1] ?? [], true);
                $hasGif = in_array('gif', $matches[1] ?? [], true);
                $part = $hasVideo ? '🎬' : ($hasGif ? '✨' : '📸');
            } else {
                $part = 'Hello! 👋';
            }
        }

        if (mb_strlen($part, 'UTF-8') > 280) {
            $part = $twitter->truncateForTwitter($part);
            $this->errorMessage = 'A tweet part was too long and has been truncated to fit the 280 character limit.';
        }

        return [
            'text' => trim($part),
            'mediaIds' => $mediaIds,
            'mediaCodes' => $mediaCodes,
        ];
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
        if (! $user->isTwitterConnected()) {
            $this->errorMessage = 'You must connect your X (Twitter) account first.';
            return;
        }

        $twitter = new TwitterService($user);
        $preparedParts = [];

        foreach ($messages as $part) {
            $prepared = $this->prepareMessagePart($part, $user, $twitter);
            if ($prepared !== null) {
                $preparedParts[] = $prepared;
            }
        }

        if ($preparedParts === []) {
            $this->errorMessage = 'Nothing to post.';
            return;
        }

        if (count($preparedParts) > self::MAX_THREAD_PARTS) {
            $this->errorMessage = 'Threads are limited to '.self::MAX_THREAD_PARTS.' tweets.';
            return;
        }

        $isThread = count($preparedParts) > 1;

        try {
            if ($isThread) {
                $texts = array_column($preparedParts, 'text');
                $mediaByPart = [];
                foreach ($preparedParts as $index => $part) {
                    if (! empty($part['mediaIds'])) {
                        $mediaByPart[$index] = $part['mediaIds'];
                    }
                }

                $response = $twitter->createThread($texts, $mediaByPart);
                $tweetIds = $response->data->tweet_ids ?? [$response->data->id];
                $prevLocalPostId = null;

                foreach ($preparedParts as $index => $part) {
                    $post = Post::create([
                        'user_id' => $user->id,
                        'content' => $part['text'],
                        'media' => $part['mediaCodes'],
                        'twitter_post_id' => $tweetIds[$index] ?? ($tweetIds[0] ?? null),
                        'in_reply_to_post_id' => $index > 0 ? $prevLocalPostId : null,
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);
                    $prevLocalPostId = $post->id;
                }
            } else {
                $part = $preparedParts[0];
                $response = $twitter->createTweet($part['text'], $part['mediaIds']);

                if (isset($response->data) && isset($response->data->id)) {
                    Post::create([
                        'user_id' => $user->id,
                        'content' => $part['text'],
                        'media' => $part['mediaCodes'],
                        'twitter_post_id' => $response->data->id,
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'already scheduled, publishing, or was posted')) {
                $message = 'This exact content was already posted in the last 24 hours. Change the text slightly and try again.';
            } elseif (str_contains($message, 'Hourly limit') || str_contains($message, '25/25') || str_contains($message, 'posting limit reached')) {
                $message = 'X posting limit reached (25 posts/hour). Wait for the limit to reset and try again.';
            }
            $this->errorMessage = 'Failed to post to X: '.$message;
            return;
        }

        $this->message = '';
        $this->threadMessages = [];
        $this->editingThreadIndex = null;
        $this->draftId = null;
        $this->successMessage = $isThread
            ? 'Thread posted to X ('.count($preparedParts).' tweets)! Open the first tweet and tap "Show this thread" if needed.'
            : 'Tweet posted to X successfully!';
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

    public function toggleImageGenerator()
    {
        $this->showImageGenerator = !$this->showImageGenerator;
        if (!$this->showImageGenerator) {
            // Reset when closing
            $this->aiImagePrompt = '';
            $this->generatedImageUrl = '';
            $this->generatedImageCode = '';
            $this->generatingImage = false;
        }
    }

    public function generateAIImage()
    {
        $this->validate([
            'aiImagePrompt' => 'required|string|min:10|max:4000'
        ]);

        $this->generatingImage = true;
        $this->errorMessage = '';
        $this->successMessage = '';

        try {
            $chatGptService = new ChatGptService();
            $imageUrl = $chatGptService->generateImage($this->aiImagePrompt, '1024x1024', 'vivid');

            if (!$imageUrl) {
                $this->errorMessage = 'Failed to generate image. Please try again.';
                $this->generatingImage = false;
                return;
            }

            // Download the generated image and upload to Cloudinary
            $this->generatedImageUrl = $imageUrl;
            $this->saveGeneratedImageToAssets($imageUrl);

        } catch (\Exception $e) {
            Log::error('AI image generation failed', [
                'error' => $e->getMessage(),
                'prompt' => $this->aiImagePrompt
            ]);
            $this->errorMessage = 'Failed to generate image: ' . $e->getMessage();
            $this->generatingImage = false;
        }
    }

    private function saveGeneratedImageToAssets($imageUrl)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                $this->errorMessage = 'You must be logged in.';
                $this->generatingImage = false;
                return;
            }

            // Download the image from OpenAI
            $imageContent = file_get_contents($imageUrl);
            if ($imageContent === false) {
                throw new \Exception('Failed to download generated image');
            }

            // Create asset record
            $code = uniqid();
            
            // Upload to Cloudinary
            $cloudinaryService = new CloudinaryService();
            
            // Create a temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'ai_image_') . '.png';
            file_put_contents($tempFile, $imageContent);

            // Upload to Cloudinary using file path method
            $uploadResult = $cloudinaryService->uploadFileFromPath($tempFile, 'ai_generated_' . $code . '.png');

            // Clean up temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            $asset = Asset::create([
                'user_id' => $user->id,
                'type' => 'image',
                'path' => $uploadResult['file_path'],
                'original_name' => 'ai_generated_' . $code . '.png',
                'code' => $code,
            ]);

            // Store the code for later use
            $this->generatedImageCode = $code;
            
            // Don't add to message automatically - let user decide
            // Keep the modal open with the generated image displayed
            // $this->successMessage = 'AI image generated and saved! Click "Use This Image" to add it to your post, or "Try Again" to generate another.';
            
            $this->dispatch('ai-image-generated', code: $code);

            Log::info('AI image generated and saved', [
                'code' => $code,
                'cloudinary_url' => $uploadResult['file_path']
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to save generated image', [
                'error' => $e->getMessage()
            ]);
            $this->errorMessage = 'Failed to save generated image: ' . $e->getMessage();
        } finally {
            $this->generatingImage = false;
        }
    }

    public function useGeneratedImage()
    {
        if ($this->generatedImageCode) {
            // Add the saved image to the message
            $this->message = rtrim($this->message) . " [img:" . $this->generatedImageCode . "] ";
            
            // Close modal and reset
            $this->showImageGenerator = false;
            $this->aiImagePrompt = '';
            $this->generatedImageUrl = '';
            $this->generatedImageCode = '';
            $this->successMessage = 'AI image added to your post!';
            
            $this->dispatch('update-alpine-message', ['message' => $this->message]);
            
            // Maintain thread mode if active
            if ($this->threadStarted) {
                $this->dispatch('thread-state-updated', ['threadStarted' => true]);
            }
        }
    }

    public function updateTimezone()
    {
        $this->validate([
            'timezone' => 'required|timezone',
        ]);

        $user = Auth::user();
        if (!$user) {
            $this->errorMessage = 'You must be logged in to update timezone.';
            return;
        }

        $user->update(['timezone' => $this->timezone]);
        $this->successMessage = 'Timezone updated to ' . $this->timezone . '.';
    }

    protected function availableTimezoneOptions(): array
    {
        return [
            'UTC' => 'UTC',
            'Africa/Lagos' => 'Africa/Lagos (GMT+1)',
            'Europe/London' => 'Europe/London',
            'Europe/Paris' => 'Europe/Paris',
            'America/New_York' => 'America/New_York',
            'America/Chicago' => 'America/Chicago',
            'America/Los_Angeles' => 'America/Los_Angeles',
            'Asia/Dubai' => 'Asia/Dubai',
            'Asia/Singapore' => 'Asia/Singapore',
            'Asia/Tokyo' => 'Asia/Tokyo',
            'Australia/Sydney' => 'Australia/Sydney',
        ];
    }

    protected function userTimezone(): string
    {
        return $this->timezone ?: (Auth::user()?->timezone ?? config('app.timezone'));
    }

    public function render()
    {
        return view('livewire.chat-component', [
            'activeTab' => $this->activeTab,
            'drafts' => $this->drafts,
        ]);
    }
}
