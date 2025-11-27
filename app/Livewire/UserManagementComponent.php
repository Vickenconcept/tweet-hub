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
            
            // Perform mutual analysis
            $this->performMutualAnalysis();

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