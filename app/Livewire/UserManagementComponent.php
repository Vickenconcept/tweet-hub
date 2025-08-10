<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Services\TwitterService;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Cache;

class UserManagementComponent extends Component
{
    use WithPagination;

    public $activeTab = 'followers';
    public $loading = false;
    public $errorMessage = '';
    public $successMessage = '';
    public $searchQuery = '';
    public $perPage = 10;

    // Data properties
    public $followers = [];
    public $following = [];
    public $blockedUsers = [];
    public $mutedUsers = [];
    public $basicUserInfo = null;
    public $rateLimitInfo = null;

    // Action properties
    public $selectedUser = null;
    public $showConfirmModal = false;
    public $actionType = '';
    public $actionMessage = '';

    protected $queryString = ['activeTab'];

    public function mount()
    {
        $this->loadData();
    }

    public function loadBasicUserInfo()
    {
        $this->loading = true;
        $this->errorMessage = '';

        try {
            $settings = $this->getTwitterSettings();
            $twitterService = new TwitterService($settings);
            
            // Check if we have cached data first
            $cacheKey = 'twitter_user_info_' . Auth::user()->id;
            $cachedData = Cache::get($cacheKey);
            
            if ($cachedData && $cachedData['expires_at'] > now()) {
                // Use cached data
                $this->basicUserInfo = $cachedData['data'];
                $this->successMessage = 'Basic user info loaded from cache!';
                $this->loading = false;
                return;
            }
            
            // Try to get fresh user info
            $me = $twitterService->findMe();
            if ($me && isset($me->data)) {
                $this->basicUserInfo = $me->data;
                $this->successMessage = 'Basic user info loaded successfully!';
                
                // Cache the data for 10 minutes (well within the 15-minute rate limit window)
                Cache::put($cacheKey, [
                    'data' => $me->data,
                    'expires_at' => now()->addMinutes(10)
                ], 600);
            } else {
                $this->errorMessage = 'Unable to load basic user info';
            }
            
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to load basic user info: ' . $e->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    public function loadData()
    {
        if (!Auth::user() || !Auth::user()->twitter_account_id) {
            $this->errorMessage = 'Please connect your Twitter account first.';
            return;
        }

        $this->loading = true;
        $this->errorMessage = '';

        try {
            $settings = $this->getTwitterSettings();
            $twitterService = new TwitterService($settings);

            // Load data based on active tab
            switch ($this->activeTab) {
                case 'followers':
                    $this->loadFollowers($twitterService);
                    break;
                case 'following':
                    $this->loadFollowing($twitterService);
                    break;
                case 'blocked':
                    $this->loadBlockedUsers($twitterService);
                    break;
                case 'muted':
                    $this->loadMutedUsers($twitterService);
                    break;
            }

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $body = $e->getResponse()->getBody()->getContents();
            
            if ($statusCode === 403) {
                $this->errorMessage = 'Access denied. This feature requires elevated Twitter API access. Please check your Twitter Developer Portal settings or contact support.';
            } elseif ($statusCode === 429) {
                $this->errorMessage = 'Rate limit exceeded. Please wait a few minutes before trying again. Twitter API has strict rate limits for user relationship endpoints.';
            } else {
                $this->errorMessage = 'Failed to load data: ' . $e->getMessage();
            }
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to load data: ' . $e->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    private function loadFollowers($twitterService)
    {
        try {
            // Check cache first
            $cacheKey = 'twitter_followers_' . Auth::user()->id;
            $cachedData = Cache::get($cacheKey);
            
            if ($cachedData && $cachedData['expires_at'] > now()) {
                $this->followers = $cachedData['data'];
                return;
            }
            
            // Get fresh data
            $result = $twitterService->getFollowers();
            $this->followers = $result->data ?? [];
            
            // Cache for 10 minutes
            Cache::put($cacheKey, [
                'data' => $this->followers,
                'expires_at' => now()->addMinutes(10)
            ], 600);
            
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to load followers: ' . $e->getMessage();
        }
    }

    private function loadFollowing($twitterService)
    {
        try {
            // Check cache first
            $cacheKey = 'twitter_following_' . Auth::user()->id;
            $cachedData = Cache::get($cacheKey);
            
            if ($cachedData && $cachedData['expires_at'] > now()) {
                $this->following = $cachedData['data'];
                return;
            }
            
            // Get fresh data
            $result = $twitterService->getFollowing();
            $this->following = $result->data ?? [];
            
            // Cache for 10 minutes
            Cache::put($cacheKey, [
                'data' => $this->following,
                'expires_at' => now()->addMinutes(10)
            ], 600);
            
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to load following: ' . $e->getMessage();
        }
    }

    private function loadBlockedUsers($twitterService)
    {
        try {
            // Check cache first
            $cacheKey = 'twitter_blocked_' . Auth::user()->id;
            $cachedData = Cache::get($cacheKey);
            
            if ($cachedData && $cachedData['expires_at'] > now()) {
                $this->blockedUsers = $cachedData['data'];
                return;
            }
            
            // Get fresh data
            $result = $twitterService->getBlockedUsers();
            $this->blockedUsers = $result->data ?? [];
            
            // Cache for 10 minutes
            Cache::put($cacheKey, [
                'data' => $this->blockedUsers,
                'expires_at' => now()->addMinutes(10)
            ], 600);
            
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to load blocked users: ' . $e->getMessage();
        }
    }

    private function loadMutedUsers($twitterService)
    {
        try {
            // Check cache first
            $cacheKey = 'twitter_muted_' . Auth::user()->id;
            $cachedData = Cache::get($cacheKey);
            
            if ($cachedData && $cachedData['expires_at'] > now()) {
                $this->mutedUsers = $cachedData['data'];
                return;
            }
            
            // Get fresh data
            $result = $twitterService->getMutedUsers();
            $this->mutedUsers = $result->data ?? [];
            
            // Cache for 10 minutes
            Cache::put($cacheKey, [
                'data' => $this->mutedUsers,
                'expires_at' => now()->addMinutes(10)
            ], 600);
            
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to load muted users: ' . $e->getMessage();
        }
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
        $this->loadData();
    }

    public function followUser($userId)
    {
        $this->performUserAction('follow', $userId, 'Follow user');
    }

    public function unfollowUser($userId)
    {
        $this->performUserAction('unfollow', $userId, 'Unfollow user');
    }

    public function muteUser($userId)
    {
        $this->performUserAction('mute', $userId, 'Mute user');
    }

    public function unmuteUser($userId)
    {
        $this->performUserAction('unmute', $userId, 'Unmute user');
    }

    public function blockUser($userId)
    {
        $this->performUserAction('block', $userId, 'Block user');
    }

    public function unblockUser($userId)
    {
        $this->performUserAction('unblock', $userId, 'Unblock user');
    }

    private function performUserAction($action, $userId, $actionMessage)
    {
        $this->selectedUser = $userId;
        $this->actionType = $action;
        $this->actionMessage = $actionMessage;
        $this->showConfirmModal = true;
    }

    public function confirmAction()
    {
        if (!$this->selectedUser || !$this->actionType) {
            return;
        }

        $this->loading = true;
        $this->errorMessage = '';
        $this->successMessage = '';

        try {
            $settings = $this->getTwitterSettings();
            $twitterService = new TwitterService($settings);

            switch ($this->actionType) {
                case 'follow':
                    $twitterService->followUser($this->selectedUser);
                    $this->successMessage = 'User followed successfully!';
                    break;
                case 'unfollow':
                    $twitterService->unfollowUser($this->selectedUser);
                    $this->successMessage = 'User unfollowed successfully!';
                    break;
                case 'mute':
                    $twitterService->muteUser($this->selectedUser);
                    $this->successMessage = 'User muted successfully!';
                    break;
                case 'unmute':
                    $twitterService->unmuteUser($this->selectedUser);
                    $this->successMessage = 'User unmuted successfully!';
                    break;
                case 'block':
                    // Note: Twitter API v2 doesn't have direct block endpoint in this library
                    $this->errorMessage = 'Block functionality not available in current API version.';
                    break;
                case 'unblock':
                    // Note: Twitter API v2 doesn't have direct unblock endpoint in this library
                    $this->errorMessage = 'Unblock functionality not available in current API version.';
                    break;
            }

            // Reload data after successful action
            if (empty($this->errorMessage)) {
                $this->loadData();
            }

        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to perform action: ' . $e->getMessage();
        } finally {
            $this->loading = false;
            $this->closeConfirmModal();
        }
    }

    public function closeConfirmModal()
    {
        $this->showConfirmModal = false;
        $this->selectedUser = null;
        $this->actionType = '';
        $this->actionMessage = '';
    }

    public function clearMessages()
    {
        $this->errorMessage = '';
        $this->successMessage = '';
    }

    public function refreshData()
    {
        $this->loadData();
        $this->successMessage = 'Data refreshed successfully!';
    }

    public function testApiConnection()
    {
        $this->loading = true;
        $this->errorMessage = '';
        $this->successMessage = '';

        try {
            $settings = $this->getTwitterSettings();
            $twitterService = new TwitterService($settings);
            
            // Test basic connectivity first
            $me = $twitterService->findMe();
            if ($me && isset($me->data)) {
                $this->successMessage = 'Basic API connection successful! User ID: ' . $me->data->id . ', Username: @' . $me->data->username;
            } else {
                $this->errorMessage = 'Basic API connection failed - unable to get user info';
            }
            
        } catch (\Exception $e) {
            $this->errorMessage = 'API connection test failed: ' . $e->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    public function checkApiEndpoints()
    {
        $this->loading = true;
        $this->errorMessage = '';
        $this->successMessage = '';

        try {
            $settings = $this->getTwitterSettings();
            $twitterService = new TwitterService($settings);
            
            $status = [];
            
            // Test basic user lookup
            try {
                $me = $twitterService->findMe();
                $status[] = '✅ Basic user info (findMe)';
            } catch (\Exception $e) {
                $status[] = '❌ Basic user info (findMe): ' . $e->getMessage();
            }
            
            // Test mentions (we know this works)
            try {
                $mentions = $twitterService->getRecentMentions(Auth::user()->twitter_account_id);
                $status[] = '✅ Recent mentions';
            } catch (\Exception $e) {
                $status[] = '❌ Recent mentions: ' . $e->getMessage();
            }
            
            // Test followers
            try {
                $followers = $twitterService->getFollowers();
                $status[] = '✅ Followers list';
            } catch (\Exception $e) {
                $status[] = '❌ Followers list: ' . $e->getMessage();
            }
            
            // Test following
            try {
                $following = $twitterService->getFollowing();
                $status[] = '✅ Following list';
            } catch (\Exception $e) {
                $status[] = '❌ Following list: ' . $e->getMessage();
            }
            
            $this->successMessage = 'API Endpoint Status:<br>' . implode('<br>', $status);
            
        } catch (\Exception $e) {
            $this->errorMessage = 'API endpoint check failed: ' . $e->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    public function checkRateLimits()
    {
        $this->loading = true;
        $this->errorMessage = '';
        $this->successMessage = '';

        try {
            $settings = $this->getTwitterSettings();
            $twitterService = new TwitterService($settings);
            
            // Check what's in cache
            $cacheKey = 'twitter_user_info_' . Auth::user()->id;
            $cachedData = Cache::get($cacheKey);
            
            $status = [];
            
            if ($cachedData) {
                $remainingTime = $cachedData['expires_at']->diffInSeconds(now());
                $status[] = "✅ Cached user info available for {$remainingTime} more seconds";
            } else {
                $status[] = "❌ No cached user info available";
            }
            
            // Check other cached data
            $followersCache = Cache::get('twitter_followers_' . Auth::user()->id);
            $followingCache = Cache::get('twitter_following_' . Auth::user()->id);
            
            if ($followersCache) {
                $remainingTime = $followersCache['expires_at']->diffInSeconds(now());
                $status[] = "✅ Cached followers available for {$remainingTime} more seconds";
            } else {
                $status[] = "❌ No cached followers available";
            }
            
            if ($followingCache) {
                $remainingTime = $followingCache['expires_at']->diffInSeconds(now());
                $status[] = "✅ Cached following available for {$remainingTime} more seconds";
            } else {
                $status[] = "❌ No cached following available";
            }
            
            $this->successMessage = 'Rate Limit & Cache Status:<br>' . implode('<br>', $status);
            
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to check rate limits: ' . $e->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    public function clearAllCache()
    {
        $userId = Auth::user()->id;
        
        Cache::forget('twitter_user_info_' . $userId);
        Cache::forget('twitter_followers_' . $userId);
        Cache::forget('twitter_following_' . $userId);
        Cache::forget('twitter_blocked_' . $userId);
        Cache::forget('twitter_muted_' . $userId);
        
        $this->successMessage = 'All Twitter API cache cleared! You can now try fresh API calls.';
        $this->basicUserInfo = null;
        $this->followers = [];
        $this->following = [];
        $this->blockedUsers = [];
        $this->mutedUsers = [];
    }

    private function getTwitterSettings()
    {
        return [
            'account_id' => Auth::user()->twitter_account_id,
            'access_token' => Auth::user()->twitter_access_token,
            'access_token_secret' => Auth::user()->twitter_access_token_secret,
            'consumer_key' => config('services.twitter.api_key'),
            'consumer_secret' => config('services.twitter.api_key_secret'),
            'bearer_token' => config('services.twitter.bearer_token'),
        ];
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
            default:
                return [];
        }
    }

    public function getFilteredData()
    {
        $data = $this->getCurrentData();
        
        if (empty($this->searchQuery)) {
            return $data;
        }

        return array_filter($data, function($user) {
            $searchLower = strtolower($this->searchQuery);
            $name = strtolower($user->name ?? '');
            $username = strtolower($user->username ?? '');
            
            return strpos($name, $searchLower) !== false || 
                   strpos($username, $searchLower) !== false;
        });
    }

    public function getPaginatedData()
    {
        $filteredData = $this->getFilteredData();
        $start = ($this->page - 1) * $this->perPage;
        return array_slice($filteredData, $start, $this->perPage);
    }

    public function getTotalPages()
    {
        $filteredData = $this->getFilteredData();
        return ceil(count($filteredData) / $this->perPage);
    }

    public function render()
    {
        return view('livewire.user-management-component'); 
    }
}
