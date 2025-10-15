<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\TwitterService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class TwitterMentionsComponent extends Component
{
    public $mentions = [];
    public $users = []; // Store user information
    public $loading = false;
    public $errorMessage = '';
    public $successMessage = '';
    public $lastRefresh = '';
    public $selectedMention = null;
    public $replyContent = '';
    public $showReplyModal = false;
    public $currentPage = 1;
    public $perPage = 5;
    private $isLoading = false; // Prevent concurrent loads

    public function mount()
    {
        // Load mentions directly from cache or API - no delays needed
        $this->loadMentions();
    }

    public function loadMentions($forceRefresh = false)
    {
        // Prevent concurrent loads
        if ($this->isLoading) {
            Log::info('Load already in progress, skipping duplicate request');
            return;
        }
        
        $this->isLoading = true;
        $this->loading = true;
        $this->errorMessage = '';
        $this->successMessage = '';

        $user = Auth::user();
        if (!$user) {
            $this->errorMessage = 'User not authenticated.';
            $this->loading = false;
            $this->isLoading = false;
            return;
        }

        if (!$user->twitter_account_connected || !$user->twitter_account_id || !$user->twitter_access_token || !$user->twitter_access_token_secret) {
            $this->errorMessage = 'Please connect your Twitter account first.';
            $this->loading = false;
            $this->isLoading = false;
            return;
        }

        // Define cache key for use throughout the method
        $cacheKey = "twitter_mentions_{$user->id}";

        // Check cache first (only if not forcing refresh)
        if (!$forceRefresh) {
        $cachedMentions = \Illuminate\Support\Facades\Cache::get($cacheKey);
        
        if ($cachedMentions) {
                \Log::info('Loading mentions from cache', ['cache_key' => $cacheKey, 'mentions_count' => count($cachedMentions['data'] ?? [])]);
            $this->mentions = $cachedMentions['data'] ?? [];
            $this->users = $cachedMentions['users'] ?? [];
            $this->lastRefresh = $cachedMentions['timestamp'] ?? now()->format('M j, Y g:i A');
            $this->currentPage = 1; // Reset to first page when loading from cache
                $this->successMessage = 'Mentions loaded from cache (updated ' . \Carbon\Carbon::parse($this->lastRefresh)->diffForHumans() . '). Click Sync for fresh data.';
            $this->loading = false;
            $this->isLoading = false;
            return;
            }
        } else {
            \Log::info('Force refresh requested - bypassing cache');
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
            
            // Use timeline method (single API call - more reliable and avoids double rate limit hits)
            Log::info('Fetching mentions using timeline API', ['user_id' => $user->twitter_account_id]);
            $mentionsResponse = $twitterService->getRecentMentions($user->twitter_account_id);
            Log::info('Mentions fetched successfully');

            // Log the response for debugging
            Log::info('Twitter API Response', [
                'response_type' => gettype($mentionsResponse),
                'response_keys' => is_object($mentionsResponse) ? array_keys(get_object_vars($mentionsResponse)) : (is_array($mentionsResponse) ? array_keys($mentionsResponse) : 'not object/array'),
                'response_content' => $mentionsResponse
            ]);

            // Handle different response structures
            if (is_object($mentionsResponse)) {
                if (isset($mentionsResponse->data)) {
                    $this->mentions = is_array($mentionsResponse->data) ? $mentionsResponse->data : (array) $mentionsResponse->data;
                    
                    // Extract user information if available
                    if (isset($mentionsResponse->includes) && isset($mentionsResponse->includes->users)) {
                        $this->users = is_array($mentionsResponse->includes->users) ? 
                            $mentionsResponse->includes->users : 
                            (array) $mentionsResponse->includes->users;
                    }
                } elseif (isset($mentionsResponse->errors)) {
                    throw new \Exception('Twitter API returned errors: ' . json_encode($mentionsResponse->errors));
                } else {
                    // Response might be empty or have different structure
                    $this->mentions = [];
                    $this->users = [];
                }
            } elseif (is_array($mentionsResponse)) {
                if (isset($mentionsResponse['data'])) {
                    $this->mentions = $mentionsResponse['data'];
                    
                    // Extract user information if available
                    if (isset($mentionsResponse['includes']) && isset($mentionsResponse['includes']['users'])) {
                        $this->users = $mentionsResponse['includes']['users'];
                    }
                } else {
                    $this->mentions = [];
                    $this->users = [];
                }
            } else {
                throw new \Exception('Unexpected API response format: ' . gettype($mentionsResponse));
            }
            $this->lastRefresh = now()->format('M j, Y g:i A');
            $this->currentPage = 1; // Reset to first page when new mentions are loaded

            $mentionsCount = count($this->mentions);
            \Log::info('Fresh mentions loaded from Twitter API', ['mentions_count' => $mentionsCount, 'force_refresh' => $forceRefresh]);
            
            if ($mentionsCount > 0) {
                $this->successMessage = "Mentions loaded successfully! Found {$mentionsCount} fresh mentions.";
            } else {
                $this->successMessage = "No mentions found. This could mean no one has mentioned you recently, or your account hasn't been mentioned yet.";
            }

            // Cache the results for 1 hour to reduce API calls
            \Illuminate\Support\Facades\Cache::put($cacheKey, [
                'data' => $this->mentions,
                'users' => $this->users,
                'timestamp' => $this->lastRefresh
            ], 3600); // 1 hour cache

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            
            if ($statusCode === 429) {
                $this->errorMessage = 'Rate limit exceeded. Please wait a few minutes before trying again. Twitter API has limits on how many requests can be made.';
            } elseif ($statusCode === 401) {
                $this->errorMessage = 'Authentication failed. Please check your Twitter connection.';
            } elseif ($statusCode === 403) {
                $this->errorMessage = 'Access forbidden. You may not have permission to access this data.';
            } else {
                $this->errorMessage = "Failed to load mentions: HTTP {$statusCode}";
            }
        } catch (\Exception $e) {
            // Check if error message contains rate limit info
            if (strpos($e->getMessage(), '429 Too Many Requests') !== false || 
                strpos($e->getMessage(), 'Rate limit') !== false) {
                $this->errorMessage = 'Rate limit exceeded. Please wait a few minutes before trying again. Twitter API has limits on how many requests can be made.';
            } else {
                $this->errorMessage = 'Failed to load mentions: ' . $e->getMessage();
            }
        }

        $this->loading = false;
        $this->isLoading = false;
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
                // Check if it's a rate limit message
                if (isset($response->data->message) && strpos($response->data->message, 'Rate limit exceeded') !== false) {
                    $this->errorMessage = $response->data->message;
                    $this->dispatch('show-error', $response->data->message);
                } else {
                $this->successMessage = 'Tweet liked successfully!';
                $this->dispatch('show-success', 'Tweet liked successfully!');
                }
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
                // Check if it was already retweeted
                if (isset($response->data->message) && strpos($response->data->message, 'already retweeted') !== false) {
                    $this->successMessage = 'Tweet was already retweeted!';
                    $this->dispatch('show-success', 'Tweet was already retweeted!');
                } 
                // Check if it's a rate limit message
                elseif (isset($response->data->message) && strpos($response->data->message, 'Rate limit exceeded') !== false) {
                    $this->errorMessage = $response->data->message;
                    $this->dispatch('show-error', $response->data->message);
                } else {
                $this->successMessage = 'Tweet retweeted successfully!';
                $this->dispatch('show-success', 'Tweet retweeted successfully!');
                }
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
        // Clear only this user's cache to ensure fresh data
        $this->clearMentionsCache();
        
        // Add some debugging info
        \Log::info('Refreshing mentions - user cache cleared, forcing fresh data fetch with fast method');
        
        // Then load fresh mentions with force refresh
        $this->loadMentions(true);
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
        // Clear only this user's cache to ensure fresh data
        $this->clearMentionsCache();
        // Then load fresh mentions with force refresh
        $this->loadMentions(true);
    }

    public function clearMentionsCache()
    {
        $user = Auth::user();
        if ($user) {
            $userId = $user->id;
            $cacheKey = "twitter_mentions_{$userId}";
            
            // Check if cache exists before clearing
            $cachedData = \Illuminate\Support\Facades\Cache::get($cacheKey);
            \Log::info('Before cache clear - cache exists', [
                'user_id' => $userId, 
                'cache_key' => $cacheKey, 
                'has_data' => !empty($cachedData)
            ]);
            
            // Clear only this user's specific cache keys
            $userSpecificKeys = [
                "twitter_mentions_{$userId}",
                "mentions_{$userId}",
                "twitter_mentions_user_{$userId}",
                "mentions_cache_{$userId}"
            ];
            
            $clearedCount = 0;
            foreach ($userSpecificKeys as $key) {
                $cleared = \Illuminate\Support\Facades\Cache::forget($key);
                if ($cleared) $clearedCount++;
            }
            
            \Log::info('Cache clear result', [
                'user_id' => $userId,
                'keys_attempted' => count($userSpecificKeys),
                'keys_cleared' => $clearedCount
            ]);
            
            // Also clear from database cache table directly (only for this user)
            try {
                $deletedRows = \DB::table('cache')
                    ->where(function($query) use ($userId) {
                        $query->where('key', 'like', "%twitter_mentions_{$userId}%")
                              ->orWhere('key', 'like', "%mentions_{$userId}%")
                              ->orWhere('key', 'like', "%twitter_mentions_user_{$userId}%")
                              ->orWhere('key', 'like', "%mentions_cache_{$userId}%");
                    })
                    ->delete();
                    
                \Log::info('Cleared cache from database table', [
                    'user_id' => $userId,
                    'deleted_rows' => $deletedRows
                ]);
            } catch (\Exception $e) {
                \Log::warning('Could not clear cache from database table', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Verify cache is actually cleared for this user only
            $cachedDataAfter = \Illuminate\Support\Facades\Cache::get($cacheKey);
            \Log::info('After cache clear - cache exists', [
                'user_id' => $userId,
                'cache_key' => $cacheKey, 
                'has_data' => !empty($cachedDataAfter)
            ]);
        }
    }
    
    /**
     * Clear all user-specific cache (use with caution - only for this user)
     */
    public function clearAllUserCache()
    {
        $user = Auth::user();
        if ($user) {
            $userId = $user->id;
            
            // All possible cache keys for this user across all components
            $allUserCacheKeys = [
                // Mentions cache
                "twitter_mentions_{$userId}",
                "mentions_{$userId}",
                "twitter_mentions_user_{$userId}",
                "mentions_cache_{$userId}",
                
                // User management cache
                "twitter_user_info_{$userId}",
                "twitter_followers_{$userId}",
                "twitter_following_{$userId}",
                "twitter_blocked_{$userId}",
                "twitter_muted_{$userId}",
                
                // Post ideas cache
                "generated_ideas_{$userId}",
                "daily_ideas_{$userId}_" . now()->format('Y-m-d'),
                
                // Any other user-specific cache patterns
                "user_{$userId}_",
                "cache_{$userId}_"
            ];
            
            $clearedCount = 0;
            foreach ($allUserCacheKeys as $key) {
                $cleared = \Illuminate\Support\Facades\Cache::forget($key);
                if ($cleared) $clearedCount++;
            }
            
            \Log::info('Cleared all user cache', [
                'user_id' => $userId,
                'keys_attempted' => count($allUserCacheKeys),
                'keys_cleared' => $clearedCount
            ]);
            
            // Clear from database cache table
            try {
                $deletedRows = \DB::table('cache')
                    ->where('key', 'like', "%{$userId}%")
                    ->delete();
                    
                \Log::info('Cleared all user cache from database', [
                    'user_id' => $userId,
                    'deleted_rows' => $deletedRows
                ]);
            } catch (\Exception $e) {
                \Log::warning('Could not clear all user cache from database', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    public function delayedLoadMentions()
    {
        // Add a 2-second delay before loading mentions
        $this->loading = true;
        $this->successMessage = 'Loading mentions...';
        
        // Use JavaScript setTimeout to delay the actual loading
        $this->dispatch('start-delayed-loading');
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

    public function getUserByAuthorId($authorId)
    {
        foreach ($this->users as $user) {
            $userId = is_object($user) ? ($user->id ?? null) : ($user['id'] ?? null);
            if ($userId == $authorId) {
                return $user;
            }
        }
        return null;
    }

    public function render()
    {
        return view('livewire.twitter-mentions-component');
    }
} 