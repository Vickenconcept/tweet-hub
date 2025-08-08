<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\TwitterService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class TwitterMentionsComponent extends Component
{
    public $mentions = [];
    public $loading = false;
    public $errorMessage = '';
    public $successMessage = '';
    public $selectedMention = null;
    public $replyContent = '';
    public $showReplyModal = false;
    public $lastRefresh = null;
    public $forceRefresh = false;

    protected $twitterService;

    public function boot()
    {
        $this->twitterService = null;
    }

    public function mount()
    {
        $this->loadMentions();
    }

    public function loadMentions()
    {
        $this->loading = true;
        $this->errorMessage = '';
        
        $user = Auth::user();
        if (!$user || !$user->isTwitterConnected()) {
            $this->errorMessage = 'Please connect your Twitter account first.';
            $this->loading = false;
            return;
        }

        // Check cache first to reduce API calls
        $cacheKey = "twitter_mentions_{$user->id}";
        $cachedMentions = \Illuminate\Support\Facades\Cache::get($cacheKey);
        
        if ($cachedMentions && !$this->forceRefresh) {
            $this->mentions = $cachedMentions['data'] ?? [];
            $this->lastRefresh = $cachedMentions['timestamp'] ?? now()->format('M j, Y g:i A');
            $this->successMessage = 'Mentions loaded from cache. Click refresh to get latest mentions.';
            $this->loading = false;
            return;
        }

        try {
            $settings = [
                'account_id' => $user->twitter_account_id,
                'access_token' => $user->twitter_access_token,
                'access_token_secret' => $user->twitter_access_token_secret,
                'consumer_key' => config('services.twitter.api_key'),
                'consumer_secret' => config('services.twitter.api_key_secret'),
                'bearer_token' => config('services.twitter.bearer_token'),
            ];

            $this->twitterService = new TwitterService($settings);
            $mentionsResponse = $this->twitterService->getRecentMentions($user->twitter_account_id);
            $this->mentions = $mentionsResponse->data ?? [];
            $this->lastRefresh = now()->format('M j, Y g:i A');
            $this->successMessage = 'Mentions loaded successfully!';
            
            // Cache the results for 5 minutes
            \Illuminate\Support\Facades\Cache::put($cacheKey, [
                'data' => $this->mentions,
                'timestamp' => $this->lastRefresh
            ], 300);
            
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 429) {
                $this->errorMessage = 'Rate limit exceeded. Please wait a few minutes before trying again.';
            } else {
                $this->errorMessage = 'Failed to load mentions: ' . $e->getMessage();
            }
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to load mentions: ' . $e->getMessage();
        }

        $this->loading = false;
    }

    public function replyToMention($mentionId)
    {
        $mention = collect($this->mentions)->firstWhere('id', $mentionId);
        if ($mention) {
            $this->selectedMention = $mention;
            $this->replyContent = '';
            $this->showReplyModal = true;
        }
    }

    public function sendReply()
    {
        $this->validate([
            'replyContent' => 'required|min:1|max:280',
        ]);

        if (!$this->selectedMention || !$this->twitterService) {
            $this->errorMessage = 'Unable to send reply.';
            return;
        }

        try {
            $response = $this->twitterService->createTweet(
                $this->replyContent, 
                [], 
                $this->selectedMention['id']
            );
            
            if ($response && isset($response->data)) {
                $this->successMessage = 'Reply sent successfully!';
                $this->showReplyModal = false;
                $this->selectedMention = null;
                $this->replyContent = '';
                $this->loadMentions(); // Refresh mentions
            } else {
                $this->errorMessage = 'Failed to send reply.';
            }
        } catch (\Exception $e) {
            $this->errorMessage = 'Error sending reply: ' . $e->getMessage();
        }
    }

    public function likeMention($mentionId)
    {
        if (!$this->twitterService) {
            $this->errorMessage = 'Twitter service not available.';
            return;
        }

        try {
            $response = $this->twitterService->likeTweet($mentionId);
            if ($response) {
                $this->successMessage = 'Tweet liked successfully!';
                $this->loadMentions(); // Refresh to update like status
            }
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to like tweet: ' . $e->getMessage();
        }
    }

    public function retweetMention($mentionId)
    {
        if (!$this->twitterService) {
            $this->errorMessage = 'Twitter service not available.';
            return;
        }

        try {
            $response = $this->twitterService->retweet($mentionId);
            if ($response) {
                $this->successMessage = 'Tweet retweeted successfully!';
                $this->loadMentions(); // Refresh to update retweet status
            }
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to retweet: ' . $e->getMessage();
        }
    }

    public function cancelReply()
    {
        $this->showReplyModal = false;
        $this->selectedMention = null;
        $this->replyContent = '';
    }

    public function clearMessage()
    {
        $this->errorMessage = '';
        $this->successMessage = '';
    }

    public function forceRefreshMentions()
    {
        $this->forceRefresh = true;
        $this->loadMentions();
        $this->forceRefresh = false;
    }

    public function render()
    {
        return view('livewire.twitter-mentions-component');
    }
} 