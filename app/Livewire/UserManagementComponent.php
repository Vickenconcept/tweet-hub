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

    // Data properties
    public $followers = [];
    public $following = [];
    public $blockedUsers = [];
    public $mutedUsers = [];
    public $basicUserInfo = null;

    protected $queryString = ['activeTab'];

    public function mount()
    {
        $this->loadData();
    }

    public function loadData()
    {
        $this->loading = true;
        $this->errorMessage = '';
        $this->successMessage = '';

        try {
            $settings = $this->getTwitterSettings();
            $twitterService = new TwitterService($settings);
            
            // Load basic user info
            $this->loadBasicUserInfo($twitterService);

            // Load all user data
            $this->loadFollowers($twitterService);
            $this->loadFollowing($twitterService);
            $this->loadBlockedUsers($twitterService);
            $this->loadMutedUsers($twitterService);

        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to load data: ' . $e->getMessage();
            Log::error('Failed to load user management data', ['error' => $e->getMessage()]);
        } finally {
            $this->loading = false;
        }
    }

    private function loadBasicUserInfo($twitterService)
    {
        try {
            $cacheKey = 'twitter_user_info_' . Auth::user()->id;
            $cachedData = Cache::get($cacheKey);
            
            if ($cachedData && $cachedData['expires_at'] > now()) {
                $this->basicUserInfo = $cachedData['data'];
                return;
            }
            
            $me = $twitterService->findMe();
            if ($me && isset($me->data)) {
                $this->basicUserInfo = $me->data;

                // Cache the data for 10 minutes
                Cache::put($cacheKey, [
                    'data' => $me->data,
                    'expires_at' => now()->addMinutes(10)
                ], 600);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to load basic user info', ['error' => $e->getMessage()]);
        }
    }

    private function loadFollowers($twitterService)
    {
        try {
            $cacheKey = 'twitter_followers_' . Auth::user()->id;
            $cachedData = Cache::get($cacheKey);
            
            if ($cachedData && $cachedData['expires_at'] > now()) {
                $this->followers = $cachedData['data'];
                return;
            }
            
            $followers = $twitterService->getFollowers();
            Log::info('Followers API Response', ['response' => $followers]);
            
            if ($followers && isset($followers->data)) {
                $this->followers = $followers->data;
                Log::info('Followers loaded successfully', ['count' => count($this->followers)]);

                // Cache the data for 15 minutes
                Cache::put($cacheKey, [
                    'data' => $followers->data,
                    'expires_at' => now()->addMinutes(15)
                ], 900);
            } else {
                Log::warning('No followers data in response', ['response' => $followers]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to load followers', ['error' => $e->getMessage()]);
            $this->followers = [];
            
            // Check if it's a setup issue
            if (strpos($e->getMessage(), 'client-not-enrolled') !== false || strpos($e->getMessage(), 'Twitter API Setup Required') !== false) {
                $this->errorMessage = 'Twitter API Setup Required: Your app needs to be attached to a Project with Elevated access. Please visit the Twitter Developer Portal to set this up.';
            } else {
                $this->errorMessage = 'Failed to load followers: ' . $e->getMessage();
            }
        }
    }

    private function loadFollowing($twitterService)
    {
        try {
            $cacheKey = 'twitter_following_' . Auth::user()->id;
            $cachedData = Cache::get($cacheKey);
            
            if ($cachedData && $cachedData['expires_at'] > now()) {
                $this->following = $cachedData['data'];
                return;
            }
            
            $following = $twitterService->getFollowing();
            if ($following && isset($following->data)) {
                $this->following = $following->data;

                // Cache the data for 15 minutes
            Cache::put($cacheKey, [
                    'data' => $following->data,
                    'expires_at' => now()->addMinutes(15)
                ], 900);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to load following', ['error' => $e->getMessage()]);
            $this->following = [];
        }
    }

    private function loadBlockedUsers($twitterService)
    {
        try {
            $cacheKey = 'twitter_blocked_' . Auth::user()->id;
            $cachedData = Cache::get($cacheKey);
            
            if ($cachedData && $cachedData['expires_at'] > now()) {
                $this->blockedUsers = $cachedData['data'];
                return;
            }
            
            $blocked = $twitterService->getBlockedUsers();
            if ($blocked && isset($blocked->data)) {
                $this->blockedUsers = $blocked->data;

                // Cache the data for 15 minutes
            Cache::put($cacheKey, [
                    'data' => $blocked->data,
                    'expires_at' => now()->addMinutes(15)
                ], 900);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to load blocked users', ['error' => $e->getMessage()]);
            $this->blockedUsers = [];
        }
    }

    private function loadMutedUsers($twitterService)
    {
        try {
            $cacheKey = 'twitter_muted_' . Auth::user()->id;
            $cachedData = Cache::get($cacheKey);
            
            if ($cachedData && $cachedData['expires_at'] > now()) {
                $this->mutedUsers = $cachedData['data'];
                return;
            }
            
            $muted = $twitterService->getMutedUsers();
            if ($muted && isset($muted->data)) {
                $this->mutedUsers = $muted->data;

                // Cache the data for 15 minutes
            Cache::put($cacheKey, [
                    'data' => $muted->data,
                    'expires_at' => now()->addMinutes(15)
                ], 900);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to load muted users', ['error' => $e->getMessage()]);
            $this->mutedUsers = [];
        }
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
        $this->loadData();
    }

    public function clearMessages()
    {
        $this->errorMessage = '';
        $this->successMessage = '';
    }

    public function refreshData()
    {
        $this->clearCache();
        $this->loadData();
        $this->successMessage = 'Data refreshed successfully!';
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

        return array_filter($data, function ($user) {
            $searchLower = strtolower($this->searchQuery);
            return (
                strpos(strtolower($user->name ?? ''), $searchLower) !== false ||
                strpos(strtolower($user->username ?? ''), $searchLower) !== false ||
                strpos(strtolower($user->description ?? ''), $searchLower) !== false
            );
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

    public function render()
    {
        return view('livewire.user-management-component'); 
    }
}