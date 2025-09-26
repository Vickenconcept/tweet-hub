<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\TwitterService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TweetAnalyticsComponent extends Component
{
    public $loading = false;
    public $errorMessage = '';
    public $successMessage = '';
    public $activeTab = 'likes';
    
    // Data properties
    public $likedTweets = [];
    public $usersWhoLiked = [];
    public $quoteTweets = [];
    public $replies = [];
    public $recentTweets = [];
    
    // Input properties
    public $tweetId = '';
    public $selectedTweet = null;
    public $selectedTweetData = null;
    
    // Pagination
    public $currentPage = 1;
    public $perPage = 10;
    
    protected $twitterService;

    public function mount()
    {
        $this->loadRecentTweets();
    }

    public function boot()
    {
        // Initialize TwitterService when needed
    }

    private function getTwitterService()
    {
        if (!$this->twitterService) {
            $user = Auth::user();
            if (!$user || !$user->twitter_account_connected) {
                throw new \Exception('Twitter account not connected');
            }

            $settings = [
                'account_id' => $user->twitter_account_id,
                'consumer_key' => config('services.twitter.api_key'),
                'consumer_secret' => config('services.twitter.api_key_secret'),
                'access_token' => $user->twitter_access_token,
                'access_token_secret' => $user->twitter_access_token_secret,
                'bearer_token' => config('services.twitter.bearer_token'),
            ];

            // Validate that all required settings are present
            $requiredSettings = ['account_id', 'consumer_key', 'consumer_secret', 'access_token', 'access_token_secret'];
            foreach ($requiredSettings as $setting) {
                if (empty($settings[$setting])) {
                    Log::error('Missing Twitter setting', [
                        'setting' => $setting,
                        'user_id' => $user->id,
                        'twitter_account_connected' => $user->twitter_account_connected,
                        'twitter_account_id' => $user->twitter_account_id,
                        'has_access_token' => !empty($user->twitter_access_token),
                        'has_access_token_secret' => !empty($user->twitter_access_token_secret)
                    ]);
                    throw new \Exception("Missing required Twitter setting: {$setting}");
                }
            }

            $this->twitterService = new TwitterService($settings);
        }
        
        return $this->twitterService;
    }

    public function loadRecentTweets()
    {
        try {
            $this->loading = true;
            $this->errorMessage = '';
            $this->successMessage = '';
            
            $user = Auth::user();
            if (!$user || !$user->twitter_account_connected) {
                throw new \Exception('Twitter account not connected');
            }

            $cacheKey = 'recent_tweets_' . $user->id;
            $cachedData = Cache::get($cacheKey);

            if ($cachedData && $cachedData['expires_at'] > now()) {
                $this->recentTweets = $cachedData['data'];
                $this->loading = false;
                return;
            }

            $twitterService = $this->getTwitterService();
            
            // Debug: Log the user ID being used
            Log::info('Loading recent tweets', [
                'user_id' => $user->id,
                'twitter_account_id' => $user->twitter_account_id
            ]);
            
            $result = $twitterService->getRecentTweets($user->twitter_account_id);
            
            if ($result && isset($result->data)) {
                $this->recentTweets = $result->data;
                
                // Debug: Log the structure of the first tweet for debugging
                if (!empty($this->recentTweets)) {
                    $firstTweet = $this->recentTweets[0];
                    Log::info('Tweet Analytics - First tweet structure', [
                        'type' => gettype($firstTweet),
                        'is_object' => is_object($firstTweet),
                        'is_array' => is_array($firstTweet),
                        'keys' => is_array($firstTweet) ? array_keys($firstTweet) : 'N/A',
                        'properties' => is_object($firstTweet) ? get_object_vars($firstTweet) : 'N/A',
                        'has_id' => is_object($firstTweet) ? isset($firstTweet->id) : (is_array($firstTweet) ? isset($firstTweet['id']) : false),
                        'has_text' => is_object($firstTweet) ? isset($firstTweet->text) : (is_array($firstTweet) ? isset($firstTweet['text']) : false)
                    ]);
                }
                
                // Cache for 15 minutes
                Cache::put($cacheKey, [
                    'data' => $result->data,
                    'expires_at' => now()->addMinutes(15)
                ], 900);
                
                $this->successMessage = 'Recent tweets loaded successfully!';
            } else {
                $this->errorMessage = 'No recent tweets found.';
            }
        } catch (\Exception $e) {
            Log::error('Failed to load recent tweets', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'twitter_connected' => Auth::user()?->twitter_account_connected ?? false
            ]);
            
            // Handle specific error types with user-friendly messages
            if (strpos($e->getMessage(), '429 Too Many Requests') !== false) {
                $this->errorMessage = 'Rate limit exceeded. Please wait a few minutes before trying again. Twitter API has limits on how many requests can be made.';
            } elseif (strpos($e->getMessage(), '401') !== false) {
                $this->errorMessage = 'Authentication failed. Please check your Twitter connection.';
            } elseif (strpos($e->getMessage(), '403') !== false) {
                $this->errorMessage = 'Access forbidden. You may not have permission to access this data.';
            } else {
                $this->errorMessage = 'Failed to load recent tweets: ' . $e->getMessage();
            }
        } finally {
            $this->loading = false;
        }
    }

    public function analyzeTweet($tweetId)
    {
        try {
            $this->loading = true;
            $this->errorMessage = '';
            $this->tweetId = $tweetId;
            
            // Validate tweet ID
            if (empty($tweetId)) {
                throw new \Exception('Invalid tweet ID provided');
            }
            
            $twitterService = $this->getTwitterService();
            
            // Get tweet details first
            $tweetResult = $twitterService->fetchTweet($tweetId);
            if ($tweetResult && isset($tweetResult->data)) {
                $this->selectedTweet = $tweetResult->data;
                $this->selectedTweetData = $tweetResult->data;
                
                // Debug: Log the selected tweet structure
                Log::info('Tweet Analytics - Selected tweet structure', [
                    'tweet_id' => $tweetId,
                    'type' => gettype($this->selectedTweet),
                    'is_object' => is_object($this->selectedTweet),
                    'is_array' => is_array($this->selectedTweet),
                    'has_id' => is_object($this->selectedTweet) ? isset($this->selectedTweet->id) : (is_array($this->selectedTweet) ? isset($this->selectedTweet['id']) : false),
                    'has_text' => is_object($this->selectedTweet) ? isset($this->selectedTweet->text) : (is_array($this->selectedTweet) ? isset($this->selectedTweet['text']) : false)
                ]);
            } else {
                throw new \Exception('Failed to fetch tweet details. Tweet may not exist or be accessible.');
            }
            
            // Load analytics data based on active tab
            $this->loadAnalyticsData();
            
            $this->successMessage = 'Tweet analytics loaded successfully!';
        } catch (\Exception $e) {
            Log::error('Failed to analyze tweet', [
                'tweet_id' => $tweetId,
                'error' => $e->getMessage()
            ]);
            
            // Handle specific error types with user-friendly messages
            if (strpos($e->getMessage(), '429 Too Many Requests') !== false) {
                $this->errorMessage = 'Rate limit exceeded. Please wait a few minutes before trying again. Twitter API has limits on how many requests can be made.';
            } elseif (strpos($e->getMessage(), '401') !== false) {
                $this->errorMessage = 'Authentication failed. Please check your Twitter connection.';
            } elseif (strpos($e->getMessage(), '403') !== false) {
                $this->errorMessage = 'Access forbidden. You may not have permission to access this tweet.';
            } elseif (strpos($e->getMessage(), '404') !== false) {
                $this->errorMessage = 'Tweet not found. The tweet may have been deleted or is not accessible.';
            } else {
                $this->errorMessage = 'Failed to analyze tweet: ' . $e->getMessage();
            }
        } finally {
            $this->loading = false;
        }
    }

    private function loadAnalyticsData()
    {
        if (!$this->tweetId) {
            return;
        }

        $twitterService = $this->getTwitterService();
        
        try {
            switch ($this->activeTab) {
                case 'likes':
                    $this->loadUsersWhoLiked();
                    break;
                case 'quotes':
                    $this->loadQuoteTweets();
                    break;
                case 'replies':
                    $this->loadReplies();
                    break;
                case 'liked-by-me':
                    $this->loadLikedTweets();
                    break;
            }
        } catch (\Exception $e) {
            Log::error('Failed to load analytics data', [
                'tab' => $this->activeTab,
                'error' => $e->getMessage()
            ]);
            
            // Handle specific error types with user-friendly messages
            if (strpos($e->getMessage(), '429 Too Many Requests') !== false) {
                $this->errorMessage = 'Rate limit exceeded. Please wait a few minutes before trying again. Twitter API has limits on how many requests can be made.';
            } elseif (strpos($e->getMessage(), '401') !== false) {
                $this->errorMessage = 'Authentication failed. Please check your Twitter connection.';
            } elseif (strpos($e->getMessage(), '403') !== false) {
                $this->errorMessage = 'Access forbidden. You may not have permission to access this data.';
            } else {
                $this->errorMessage = 'Failed to load ' . $this->activeTab . ' data: ' . $e->getMessage();
            }
        }
    }

    private function loadUsersWhoLiked()
    {
        $user = Auth::user();
        $cacheKey = 'users_who_liked_' . $this->tweetId . '_' . $user->id;
        $cachedData = Cache::get($cacheKey);

        if ($cachedData && $cachedData['expires_at'] > now()) {
            $this->usersWhoLiked = $cachedData['data'];
            return;
        }

        $twitterService = $this->getTwitterService();
        $result = $twitterService->getUsersWhoLiked($this->tweetId, 100);
        
        if ($result && isset($result->data)) {
            $this->usersWhoLiked = $result->data;
            
            // Cache for 10 minutes
            Cache::put($cacheKey, [
                'data' => $result->data,
                'expires_at' => now()->addMinutes(10)
            ], 600);
        }
    }

    private function loadQuoteTweets()
    {
        $user = Auth::user();
        $cacheKey = 'quote_tweets_' . $this->tweetId . '_' . $user->id;
        $cachedData = Cache::get($cacheKey);

        if ($cachedData && $cachedData['expires_at'] > now()) {
            $this->quoteTweets = $cachedData['data'];
            return;
        }

        $twitterService = $this->getTwitterService();
        $result = $twitterService->getQuoteTweets($this->tweetId);
        
        if ($result && isset($result->data)) {
            $this->quoteTweets = $result->data;
            
            // Cache for 10 minutes
            Cache::put($cacheKey, [
                'data' => $result->data,
                'expires_at' => now()->addMinutes(10)
            ], 600);
        }
    }

    private function loadReplies()
    {
        $user = Auth::user();
        $cacheKey = 'replies_' . $this->tweetId . '_' . $user->id;
        $cachedData = Cache::get($cacheKey);

        if ($cachedData && $cachedData['expires_at'] > now()) {
            $this->replies = $cachedData['data'];
            return;
        }

        $twitterService = $this->getTwitterService();
        $result = $twitterService->getReplies($this->tweetId);
        
        if ($result && isset($result->data)) {
            $this->replies = $result->data;
            
            // Cache for 10 minutes
            Cache::put($cacheKey, [
                'data' => $result->data,
                'expires_at' => now()->addMinutes(10)
            ], 600);
        }
    }

    private function loadLikedTweets()
    {
        $user = Auth::user();
        $cacheKey = 'liked_tweets_' . $user->id;
        $cachedData = Cache::get($cacheKey);

        if ($cachedData && $cachedData['expires_at'] > now()) {
            $this->likedTweets = $cachedData['data'];
            return;
        }

        $twitterService = $this->getTwitterService();
        $result = $twitterService->getLikedTweets($user->twitter_account_id, 100);
        
        if ($result && isset($result->data)) {
            $this->likedTweets = $result->data;
            
            // Cache for 15 minutes
            Cache::put($cacheKey, [
                'data' => $result->data,
                'expires_at' => now()->addMinutes(15)
            ], 900);
        }
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        $this->currentPage = 1;
        
        // Load data for the new tab
        if (!empty($this->tweetId) || $tab === 'liked-by-me') {
            $this->loadAnalyticsData();
        }
    }

    public function clearCache()
    {
        $user = Auth::user();
        $cacheKeys = [
            'recent_tweets_' . $user->id,
            'users_who_liked_' . $this->tweetId . '_' . $user->id,
            'quote_tweets_' . $this->tweetId . '_' . $user->id,
            'replies_' . $this->tweetId . '_' . $user->id,
            'liked_tweets_' . $user->id,
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
        
        $this->successMessage = 'Cache cleared successfully!';
    }

    public function getCurrentData()
    {
        switch ($this->activeTab) {
            case 'likes':
                return $this->usersWhoLiked;
            case 'quotes':
                return $this->quoteTweets;
            case 'replies':
                return $this->replies;
            case 'liked-by-me':
                return $this->likedTweets;
            default:
                return [];
        }
    }

    public function getCurrentDataCount()
    {
        $data = $this->getCurrentData();
        return is_array($data) ? count($data) : 0;
    }

    public function render()
    {
        return view('livewire.tweet-analytics-component');
    }
}
