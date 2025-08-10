<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\TwitterService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TwitterMentionsComponent extends Component
{
    public $mentions = [];
    public $loading = false;
    public $errorMessage = '';
    public $successMessage = '';
    public $lastRefresh = '';
    public $selectedMention = null;
    public $replyContent = '';
    public $showReplyModal = false;
    public $currentPage = 1;
    public $perPage = 5;

    public function mount()
    {
        $this->loadMentions();
    }

    public function loadMentions()
    {
        $this->loading = true;
        $this->errorMessage = '';
        $this->successMessage = '';

        $user = Auth::user();
        if (!$user) {
            $this->errorMessage = 'User not authenticated.';
            $this->loading = false;
            return;
        }

        if (!$user->twitter_account_connected || !$user->twitter_account_id || !$user->twitter_access_token || !$user->twitter_access_token_secret) {
            $this->errorMessage = 'Please connect your Twitter account first.';
            $this->loading = false;
            return;
        }

        // Check cache first
        $cacheKey = "twitter_mentions_{$user->id}";
        $cachedMentions = \Illuminate\Support\Facades\Cache::get($cacheKey);
        
        if ($cachedMentions) {
            $this->mentions = $cachedMentions['data'] ?? [];
            $this->lastRefresh = $cachedMentions['timestamp'] ?? now()->format('M j, Y g:i A');
            $this->currentPage = 1; // Reset to first page when loading from cache
            $this->successMessage = 'Mentions loaded. Click refresh to get latest mentions.';
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

            $twitterService = new TwitterService($settings);
            $mentionsResponse = $twitterService->getRecentMentions($user->twitter_account_id);

            if (!isset($mentionsResponse->data)) {
                throw new \Exception('Invalid API response: missing data property');
            }

            $this->mentions = is_array($mentionsResponse->data) ? $mentionsResponse->data : (array) $mentionsResponse->data;
            $this->lastRefresh = now()->format('M j, Y g:i A');
            $this->currentPage = 1; // Reset to first page when new mentions are loaded

            $mentionsCount = count($this->mentions);
            if ($mentionsCount > 0) {
                $this->successMessage = "Mentions loaded successfully! Found {$mentionsCount} mentions.";
            } else {
                $this->successMessage = "No mentions found. This could mean no one has mentioned you recently, or your self-mention hasn't been indexed yet.";
            }

            // Cache the results for 15 minutes
            \Illuminate\Support\Facades\Cache::put($cacheKey, [
                'data' => $this->mentions,
                'timestamp' => $this->lastRefresh
            ], 900);

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            
            if ($statusCode === 429) {
                $this->errorMessage = 'Rate limit exceeded. Please wait a few minutes before trying again.';
            } else {
                $this->errorMessage = "Failed to load mentions: HTTP {$statusCode}";
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

        if (!$this->selectedMention) {
            $this->errorMessage = 'Unable to send reply.';
            return;
        }

        try {
            $settings = [
                'account_id' => Auth::user()->twitter_account_id,
                'access_token' => Auth::user()->twitter_access_token,
                'access_token_secret' => Auth::user()->twitter_access_token_secret,
                'consumer_key' => config('services.twitter.api_key'),
                'consumer_secret' => config('services.twitter.api_key_secret'),
                'bearer_token' => config('services.twitter.bearer_token'),
            ];

            $twitterService = new TwitterService($settings);
            $response = $twitterService->createTweet(
                $this->replyContent, 
                [], 
                $this->selectedMention->id
            );
            
            if ($response && isset($response->data)) {
                $this->successMessage = 'Reply sent successfully!';
                $this->showReplyModal = false;
                $this->selectedMention = null;
                $this->replyContent = '';
                // Show floating toast and refresh mentions after a delay
                $this->dispatch('reply-sent');
                $this->dispatch('show-success', 'Reply sent successfully!');
                $this->dispatch('refresh-mentions-delayed');
            } else {
                $this->errorMessage = 'Failed to send reply.';
            }
        } catch (\Exception $e) {
            $this->errorMessage = 'Error sending reply: ' . $e->getMessage();
        }
    }

    public function likeMention($mentionId)
    {
        try {
            $settings = [
                'account_id' => Auth::user()->twitter_account_id,
                'access_token' => Auth::user()->twitter_access_token,
                'access_token_secret' => Auth::user()->twitter_access_token_secret,
                'consumer_key' => config('services.twitter.api_key'),
                'consumer_secret' => config('services.twitter.api_key_secret'),
                'bearer_token' => config('services.twitter.bearer_token'),
            ];

            $twitterService = new TwitterService($settings);
            $response = $twitterService->likeTweet($mentionId);
            
            if ($response) {
                $this->successMessage = 'Tweet liked successfully!';
                $this->dispatch('show-success', 'Tweet liked successfully!');
                // Clear cache to show updated data
                $this->clearMentionsCache();
            }
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to like tweet: ' . $e->getMessage();
        }
    }

    public function retweetMention($mentionId)
    {
        try {
            $settings = [
                'account_id' => Auth::user()->twitter_account_id,
                'access_token' => Auth::user()->twitter_access_token,
                'access_token_secret' => Auth::user()->twitter_access_token_secret,
                'consumer_key' => config('services.twitter.api_key'),
                'consumer_secret' => config('services.twitter.api_key_secret'),
                'bearer_token' => config('services.twitter.bearer_token'),
            ];

            $twitterService = new TwitterService($settings);
            $response = $twitterService->retweet($mentionId);
            
            if ($response) {
                $this->successMessage = 'Tweet retweeted successfully!';
                $this->dispatch('show-success', 'Tweet retweeted successfully!');
                // Clear cache to show updated data
                $this->clearMentionsCache();
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

    public function refreshMentions()
    {
        $this->loadMentions();
    }

    public function clearSuccessMessage()
    {
        $this->successMessage = '';
    }

    public function clearErrorMessage()
    {
        $this->errorMessage = '';
    }

    public function refreshMentionsDelayed()
    {
        // This method will be called by JavaScript after a delay
        $this->loadMentions();
    }

    public function clearMentionsCache()
    {
        $user = Auth::user();
        if ($user) {
            $cacheKey = "twitter_mentions_{$user->id}";
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
        }
    }

    public function nextPage()
    {
        $this->currentPage++;
    }

    public function previousPage()
    {
        if ($this->currentPage > 1) {
            $this->currentPage--;
        }
    }

    public function goToPage($page)
    {
        $this->currentPage = $page;
    }

    public function getPaginatedMentions()
    {
        $start = ($this->currentPage - 1) * $this->perPage;
        return array_slice($this->mentions, $start, $this->perPage);
    }

    public function getTotalPages()
    {
        return ceil(count($this->mentions) / $this->perPage);
    }

    public function render()
    {
        return view('livewire.twitter-mentions-component');
    }
} 