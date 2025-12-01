<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Services\TwitterService;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UserManagementComponent extends Component
{
    use WithPagination;

    public $activeTab = 'followers';
    public $loading = false;
    public $errorMessage = '';
    public $successMessage = '';
    public $searchQuery = '';
    public $perPage = 10;
    public $lastRefresh = '';
    private $isLoading = false; // Prevent concurrent loads

    // Rate limit properties
    public $isRateLimited = false;
    public $rateLimitResetTime = '';
    public $rateLimitWaitMinutes = 0;

    // Advanced search properties
    public $searchInBio = false;
    public $minFollowers = '';
    public $maxFollowers = '';
    public $verifiedOnly = false;
    public $locationFilter = '';

    // Data properties
    public $followers = [];
    public $following = [];
    public $blockedUsers = [];
    public $mutedUsers = [];
    public $basicUserInfo = null;
    
    // Mutual analysis data
    public $mutualAnalysis = [
        'following_not_followers' => [],
        'followers_not_following' => [],
        'mutual_followers' => []
    ];
    
    // Expanded user card
    public $expandedUserId = null;

    protected $queryString = ['activeTab'];

    public function mount()
    {
        // Check rate limit status on mount
        $this->checkRateLimitStatus();
        $this->loadData(false);
    }

    public function checkRateLimitStatus()
    {
        $user = Auth::user();
        if ($user) {
            $settings = $this->getTwitterSettings();
            $twitterService = new TwitterService($settings);
            $rateLimitCheck = $twitterService->isRateLimitedForFollowers();
            
            Log::info('🔍 Rate limit status check (Followers)', [
                'rate_limited' => $rateLimitCheck['rate_limited'] ?? false,
                'reset_time' => $rateLimitCheck['reset_time'] ?? 'none',
                'wait_minutes' => $rateLimitCheck['wait_minutes'] ?? 0
            ]);
            
            if ($rateLimitCheck['rate_limited']) {
                $this->isRateLimited = true;
                $this->rateLimitResetTime = $rateLimitCheck['reset_time'];
                $this->rateLimitWaitMinutes = $rateLimitCheck['wait_minutes'];
                Log::warning('🚫 Rate limit ACTIVE - UI updated (Followers)', [
                    'reset_time' => $this->rateLimitResetTime,
                    'wait_minutes' => $this->rateLimitWaitMinutes
                ]);
            } else {
                $this->isRateLimited = false;
                $this->rateLimitResetTime = '';
                $this->rateLimitWaitMinutes = 0;
            }
        }
    }

    public function loadData($forceRefresh = false)
    {
        // Always check rate limit status before making API calls
        $this->checkRateLimitStatus();
        
        // If rate limited, only allow cache load, not API calls
        if ($this->isRateLimited && $forceRefresh) {
            $this->errorMessage = "Rate limit active. Please wait {$this->rateLimitWaitMinutes} minute(s) before refreshing. Reset time: {$this->rateLimitResetTime}";
            return;
        }
        
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

        // Define cache keys
        $cacheKeyBase = "twitter_user_management_{$user->id}";
        $followersCacheKey = "{$cacheKeyBase}_followers";
        $followingCacheKey = "{$cacheKeyBase}_following";
        $blockedCacheKey = "{$cacheKeyBase}_blocked";
        $mutedCacheKey = "{$cacheKeyBase}_muted";
        $userInfoCacheKey = "{$cacheKeyBase}_userinfo";

        // Check cache first (only if not forcing refresh)
        if (!$forceRefresh) {
            $cachedFollowers = Cache::get($followersCacheKey);
            $cachedFollowing = Cache::get($followingCacheKey);
            $cachedBlocked = Cache::get($blockedCacheKey);
            $cachedMuted = Cache::get($mutedCacheKey);
            $cachedUserInfo = Cache::get($userInfoCacheKey);
            
            if ($cachedFollowers && $cachedFollowing) {
                Log::info('✅ CACHE HIT: User management data loaded from cache (NO API CALL)', [
                    'cache_key_base' => $cacheKeyBase,
                    'followers_count' => count($cachedFollowers['data'] ?? []),
                    'following_count' => count($cachedFollowing['data'] ?? []),
                    'last_updated' => $cachedFollowers['timestamp'] ?? 'unknown',
                    'timestamp' => now()->format('Y-m-d H:i:s')
                ]);
                
                $this->followers = $cachedFollowers['data'] ?? [];
                $this->following = $cachedFollowing['data'] ?? [];
                $this->blockedUsers = $cachedBlocked['data'] ?? [];
                $this->mutedUsers = $cachedMuted['data'] ?? [];
                $this->basicUserInfo = $cachedUserInfo['data'] ?? null;
                $this->lastRefresh = $cachedFollowers['timestamp'] ?? now()->format('M j, Y g:i A');
                
                // Perform mutual analysis on cached data
                $this->performMutualAnalysis();
                
                $this->successMessage = 'Data loaded from cache (updated ' . \Carbon\Carbon::parse($this->lastRefresh)->diffForHumans() . '). Click Refresh for fresh data.';
                $this->loading = false;
                $this->isLoading = false;
                return;
            }
            
            // If no cache and rate limited, don't make API call
            if ($this->isRateLimited) {
                Log::warning('🚫 Rate limited - skipping API call, no cache available');
                $this->errorMessage = "Rate limit active. Please wait {$this->rateLimitWaitMinutes} minute(s). Reset time: {$this->rateLimitResetTime}";
                $this->loading = false;
                $this->isLoading = false;
                return;
            }
        } else {
            Log::info('⚠️ FORCE REFRESH: Bypassing cache - this will make API calls');
            
            // If forcing refresh but rate limited, don't make API call
            if ($this->isRateLimited) {
                Log::warning('🚫 Rate limited - cannot force refresh');
                $this->errorMessage = "Rate limit active. Please wait {$this->rateLimitWaitMinutes} minute(s) before refreshing. Reset time: {$this->rateLimitResetTime}";
                $this->loading = false;
                $this->isLoading = false;
                return;
            }
        }

        try {
            $settings = $this->getTwitterSettings();
            $twitterService = new TwitterService($settings);
            
            // Load basic user info
            $this->loadBasicUserInfo($twitterService, $userInfoCacheKey);

            // Load all user data
            $this->loadFollowers($twitterService, $followersCacheKey, $forceRefresh);
            $this->loadFollowing($twitterService, $followingCacheKey, $forceRefresh);
            $this->loadBlockedUsers($twitterService, $blockedCacheKey, $forceRefresh);
            $this->loadMutedUsers($twitterService, $mutedCacheKey, $forceRefresh);
            
            // Perform mutual analysis
            $this->performMutualAnalysis();
            
            $this->lastRefresh = now()->format('M j, Y g:i A');
            $this->successMessage = 'Data refreshed successfully!';

        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to load data: ' . $e->getMessage();
            Log::error('Failed to load user management data', ['error' => $e->getMessage()]);
            
            // Check if it's a rate limit error
            if (strpos($e->getMessage(), 'Rate limit') !== false) {
                $this->checkRateLimitStatus();
                if ($this->isRateLimited) {
                    $this->errorMessage = "Rate limit exceeded. Please wait {$this->rateLimitWaitMinutes} minute(s) before trying again. Reset time: {$this->rateLimitResetTime}";
                }
            }
        } finally {
            $this->loading = false;
            $this->isLoading = false;
        }
    }

    private function loadBasicUserInfo($twitterService, $cacheKey)
    {
        try {
            $me = $twitterService->findMe();
            if ($me && isset($me->data)) {
                $this->basicUserInfo = $me->data;

                // Cache the data for 4 hours
                Cache::put($cacheKey, [
                    'data' => $me->data,
                    'timestamp' => now()->format('M j, Y g:i A')
                ], 14400);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to load basic user info', ['error' => $e->getMessage()]);
        }
    }

    private function loadFollowers($twitterService, $cacheKey, $forceRefresh = false)
    {
        try {
            $followers = $twitterService->getFollowers();
            Log::info('Followers API Response', ['response' => $followers]);
            
            if ($followers && isset($followers->data)) {
                $this->followers = $followers->data;
                Log::info('Followers loaded successfully', ['count' => count($this->followers)]);

                // Cache the data for 4 hours
                Cache::put($cacheKey, [
                    'data' => $followers->data,
                    'timestamp' => now()->format('M j, Y g:i A')
                ], 14400);
            } else {
                Log::warning('No followers data in response', ['response' => $followers]);
                $this->followers = [];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to load followers', ['error' => $e->getMessage()]);
            $this->followers = [];
            
            // Check if it's a setup issue
            if (strpos($e->getMessage(), 'client-not-enrolled') !== false || strpos($e->getMessage(), 'Twitter API Setup Required') !== false) {
                $this->errorMessage = 'Twitter API Setup Required: Your app needs to be attached to a Project with Elevated access. Please visit the Twitter Developer Portal to set this up.';
            } elseif (strpos($e->getMessage(), 'Rate limit') !== false) {
                $this->checkRateLimitStatus();
                throw $e; // Re-throw to be handled by loadData
            } else {
                $this->errorMessage = 'Failed to load followers: ' . $e->getMessage();
            }
        }
    }

    private function loadFollowing($twitterService, $cacheKey, $forceRefresh = false)
    {
        try {
            $following = $twitterService->getFollowing();
            if ($following && isset($following->data)) {
                $this->following = $following->data;

                // Cache the data for 4 hours
                Cache::put($cacheKey, [
                    'data' => $following->data,
                    'timestamp' => now()->format('M j, Y g:i A')
                ], 14400);
            } else {
                $this->following = [];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to load following', ['error' => $e->getMessage()]);
            $this->following = [];
            
            if (strpos($e->getMessage(), 'Rate limit') !== false) {
                $this->checkRateLimitStatus();
                throw $e; // Re-throw to be handled by loadData
            }
        }
    }

    private function loadBlockedUsers($twitterService, $cacheKey, $forceRefresh = false)
    {
        try {
            $blocked = $twitterService->getBlockedUsers();
            if ($blocked && isset($blocked->data)) {
                $this->blockedUsers = $blocked->data;

                // Cache the data for 4 hours
                Cache::put($cacheKey, [
                    'data' => $blocked->data,
                    'timestamp' => now()->format('M j, Y g:i A')
                ], 14400);
            } else {
                $this->blockedUsers = [];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to load blocked users', ['error' => $e->getMessage()]);
            $this->blockedUsers = [];
        }
    }

    private function loadMutedUsers($twitterService, $cacheKey, $forceRefresh = false)
    {
        try {
            $muted = $twitterService->getMutedUsers();
            if ($muted && isset($muted->data)) {
                $this->mutedUsers = $muted->data;

                // Cache the data for 4 hours
                Cache::put($cacheKey, [
                    'data' => $muted->data,
                    'timestamp' => now()->format('M j, Y g:i A')
                ], 14400);
            } else {
                $this->mutedUsers = [];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to load muted users', ['error' => $e->getMessage()]);
            $this->mutedUsers = [];
        }
    }

    public function refreshData()
    {
        // Check rate limit before making request
        $this->checkRateLimitStatus();
        
        if ($this->isRateLimited) {
            $this->errorMessage = "Rate limit active. Please wait {$this->rateLimitWaitMinutes} minute(s) before refreshing. Reset time: {$this->rateLimitResetTime}";
            // Don't clear cache when rate limited - try to load from existing cache
            Log::warning('🚫 Refresh blocked - rate limited, attempting to load from existing cache');
            
            // Try to load from cache if available
            $user = Auth::user();
            if ($user) {
                $cacheKeyBase = "twitter_user_management_{$user->id}";
                $followersCacheKey = "{$cacheKeyBase}_followers";
                $followingCacheKey = "{$cacheKeyBase}_following";
                
                $cachedFollowers = Cache::get($followersCacheKey);
                $cachedFollowing = Cache::get($followingCacheKey);
                
                if ($cachedFollowers && $cachedFollowing) {
                    Log::info('✅ Loading from cache while rate limited');
                    $this->followers = $cachedFollowers['data'] ?? [];
                    $this->following = $cachedFollowing['data'] ?? [];
                    $this->lastRefresh = $cachedFollowers['timestamp'] ?? now()->format('M j, Y g:i A');
                    $this->performMutualAnalysis();
                    $this->successMessage = 'Showing cached data (rate limited). Cache updated ' . \Carbon\Carbon::parse($this->lastRefresh)->diffForHumans() . '.';
                }
            }
            return;
        }
        
        // Clear cache to ensure fresh data
        $user = Auth::user();
        if ($user) {
            $cacheKeyBase = "twitter_user_management_{$user->id}";
            Cache::forget("{$cacheKeyBase}_followers");
            Cache::forget("{$cacheKeyBase}_following");
            Cache::forget("{$cacheKeyBase}_blocked");
            Cache::forget("{$cacheKeyBase}_muted");
            Cache::forget("{$cacheKeyBase}_userinfo");
        }
        
        Log::info('Refreshing user management data - cache cleared, forcing fresh data fetch');
        
        // Then load fresh data with force refresh
        $this->loadData(true);
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
        // Don't reload data on tab switch - use existing cached data
    }

    public function clearMessages()
    {
        $this->errorMessage = '';
        $this->successMessage = '';
    }

    public function checkApiAccess()
    {
        try {
            $settings = $this->getTwitterSettings();
            $twitterService = new TwitterService($settings);
            
            // Test basic access
            $me = $twitterService->findMe();
            if ($me && isset($me->data)) {
                $this->successMessage = 'Basic API access confirmed. User: @' . $me->data->username;
            }
            
            // Test followers access
            try {
                $followers = $twitterService->getFollowers();
                $this->successMessage .= ' | Followers API: OK';
            } catch (\Exception $e) {
                $this->errorMessage .= ' | Followers API failed: ' . $e->getMessage();
            }
            
        } catch (\Exception $e) {
            $this->errorMessage = 'API access check failed: ' . $e->getMessage();
        }
    }

    private function clearCache()
    {
        $userId = Auth::user()->id;
        $cacheKeys = [
            'twitter_user_info_' . $userId,
            'twitter_followers_' . $userId,
            'twitter_following_' . $userId,
            'twitter_blocked_' . $userId,
            'twitter_muted_' . $userId,
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }

    public function getCurrentData()
    {
        switch ($this->activeTab) {
            case 'followers':
                return $this->followers;
            case 'following':
                return $this->following;
            case 'blocked':
                return $this->blockedUsers;
            case 'muted':
                return $this->mutedUsers;
            case 'mutual_analysis':
                return $this->mutualAnalysis['mutual_followers'];
            case 'following_not_followers':
                return $this->mutualAnalysis['following_not_followers'];
            case 'followers_not_following':
                return $this->mutualAnalysis['followers_not_following'];
            default:
                return [];
        }
    }

    public function getFilteredData()
    {
        $data = $this->getCurrentData();
        
        if (empty($this->searchQuery) && !$this->searchInBio && empty($this->minFollowers) && 
            empty($this->maxFollowers) && !$this->verifiedOnly && empty($this->locationFilter)) {
            return $data;
        }

        return array_filter($data, function ($user) {
            // Text search
            if (!empty($this->searchQuery)) {
                $searchLower = strtolower($this->searchQuery);
                $matchesText = (
                    strpos(strtolower($user->name ?? ''), $searchLower) !== false ||
                    strpos(strtolower($user->username ?? ''), $searchLower) !== false
                );
                
                if ($this->searchInBio) {
                    $matchesText = $matchesText || strpos(strtolower($user->description ?? ''), $searchLower) !== false;
                }
                
                if (!$matchesText) return false;
            }
            
            // Bio search
            if ($this->searchInBio && !empty($this->searchQuery)) {
                $searchLower = strtolower($this->searchQuery);
                if (strpos(strtolower($user->description ?? ''), $searchLower) === false) {
                    return false;
                }
            }
            
            // Follower count filters
            if (!empty($this->minFollowers) || !empty($this->maxFollowers)) {
                $followerCount = $user->public_metrics->followers_count ?? 0;
                
                if (!empty($this->minFollowers) && $followerCount < (int)$this->minFollowers) {
                    return false;
                }
                
                if (!empty($this->maxFollowers) && $followerCount > (int)$this->maxFollowers) {
                    return false;
                }
            }
            
            // Verified filter
            if ($this->verifiedOnly && !($user->verified ?? false)) {
                return false;
            }
            
            // Location filter
            if (!empty($this->locationFilter)) {
                $locationLower = strtolower($this->locationFilter);
                $userLocation = strtolower($user->location ?? $user->description ?? '');
                if (strpos($userLocation, $locationLower) === false) {
                    return false;
                }
            }
            
            return true;
        });
    }

    public function getPaginatedData()
    {
        $filteredData = $this->getFilteredData();
        $offset = ($this->page - 1) * $this->perPage;
        return array_slice($filteredData, $offset, $this->perPage);
    }

    public function getTotalPages()
    {
        $filteredData = $this->getFilteredData();
        return ceil(count($filteredData) / $this->perPage);
    }

    private function getTwitterSettings()
    {
        $user = Auth::user();
        
        if (!$user->twitter_account_connected || !$user->twitter_account_id || !$user->twitter_access_token || !$user->twitter_access_token_secret) {
            throw new \Exception('Twitter account not properly connected. Please reconnect your account.');
        }

        return [
            'account_id' => $user->twitter_account_id,
            'access_token' => $user->twitter_access_token,
            'access_token_secret' => $user->twitter_access_token_secret,
            'consumer_key' => config('services.twitter.api_key'),
            'consumer_secret' => config('services.twitter.api_key_secret'),
            'bearer_token' => config('services.twitter.bearer_token'),
        ];
    }

    /**
     * Perform mutual followers analysis
     */
    public function performMutualAnalysis()
    {
        try {
            $followerIds = collect($this->followers)->pluck('id')->toArray();
            $followingIds = collect($this->following)->pluck('id')->toArray();
            
            // People you follow but don't follow you back
            $followingNotFollowersIds = array_diff($followingIds, $followerIds);
            $this->mutualAnalysis['following_not_followers'] = collect($this->following)
                ->filter(function($user) use ($followingNotFollowersIds) {
                    return in_array($user->id, $followingNotFollowersIds);
                })->values()->toArray();
            
            // People who follow you but you don't follow back
            $followersNotFollowingIds = array_diff($followerIds, $followingIds);
            $this->mutualAnalysis['followers_not_following'] = collect($this->followers)
                ->filter(function($user) use ($followersNotFollowingIds) {
                    return in_array($user->id, $followersNotFollowingIds);
                })->values()->toArray();
            
            // Mutual followers
            $mutualIds = array_intersect($followerIds, $followingIds);
            $this->mutualAnalysis['mutual_followers'] = collect($this->followers)
                ->filter(function($user) use ($mutualIds) {
                    return in_array($user->id, $mutualIds);
                })->values()->toArray();
                
            Log::info('Mutual analysis completed', [
                'following_not_followers' => count($this->mutualAnalysis['following_not_followers']),
                'followers_not_following' => count($this->mutualAnalysis['followers_not_following']),
                'mutual_followers' => count($this->mutualAnalysis['mutual_followers'])
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to perform mutual analysis', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Clear all filters
     */
    public function clearFilters()
    {
        $this->searchQuery = '';
        $this->searchInBio = false;
        $this->minFollowers = '';
        $this->maxFollowers = '';
        $this->verifiedOnly = false;
        $this->locationFilter = '';
        $this->resetPage();
    }

    public function toggleUserCard($userId)
    {
        if ($this->expandedUserId === $userId) {
            $this->expandedUserId = null;
        } else {
            $this->expandedUserId = $userId;
        }
    }

    /**
     * Export current filtered data to CSV
     */
    public function exportData()
    {
        try {
            $data = $this->getFilteredData();
            $filename = $this->activeTab . '_export_' . date('Y-m-d_H-i-s') . '.csv';
            
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];
            
            $callback = function() use ($data) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Name', 'Username', 'Bio', 'Followers', 'Following', 'Tweets', 'Verified']);
                
                foreach ($data as $user) {
                    fputcsv($file, [
                        $user->name ?? '',
                        $user->username ?? '',
                        $user->description ?? '',
                        $user->public_metrics->followers_count ?? 0,
                        $user->public_metrics->following_count ?? 0,
                        $user->public_metrics->tweet_count ?? 0,
                        $user->verified ?? false ? 'Yes' : 'No'
                    ]);
                }
                
                fclose($file);
            };
            
            $this->successMessage = 'Export started! Download will begin shortly.';
            
            return response()->stream($callback, 200, $headers);
            
        } catch (\Exception $e) {
            $this->errorMessage = 'Export failed: ' . $e->getMessage();
            Log::error('Export failed', ['error' => $e->getMessage()]);
        }
    }

    public function render()
    {
        return view('livewire.user-management-component'); 
    }
}