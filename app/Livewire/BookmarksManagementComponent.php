<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\TwitterService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BookmarksManagementComponent extends Component
{
    public $bookmarks = [];
    public $loading = false;
    public $errorMessage = '';
    public $successMessage = '';
    public $searchQuery = '';
    public $filterType = 'all'; // 'all', 'tweets', 'replies', 'quotes'
    public $sortBy = 'newest'; // 'newest', 'oldest', 'most_liked', 'most_retweeted'
    public $currentPage = 1;
    public $perPage = 20;
    public $cacheKey = '';
    public $selectedBookmark = null;
    public $showAddBookmarkModal = false;
    public $newBookmarkUrl = '';

    protected $twitterService;

    public function boot()
    {
        // Initialize TwitterService when needed
    }

    public function mount()
    {
        $this->cacheKey = 'bookmarks_' . Auth::id();
        $this->loadBookmarks();
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

    public function loadBookmarks()
    {
        try {
            $this->loading = true;
            $this->errorMessage = '';
            $this->successMessage = '';
            
            $user = Auth::user();
            if (!$user || !$user->twitter_account_connected) {
                throw new \Exception('Twitter account not connected');
            }

            $cacheKey = $this->cacheKey . '_bookmarks';
            $cachedData = Cache::get($cacheKey);

            if ($cachedData && $cachedData['expires_at'] > now()) {
                $this->bookmarks = $cachedData['data'];
                $this->successMessage = 'Bookmarks loaded from cache.';
                $this->loading = false;
                return;
            }

            $twitterService = $this->getTwitterService();
            
            // Debug: Log the user ID being used
            Log::info('Loading bookmarks', [
                'user_id' => $user->id,
                'twitter_account_id' => $user->twitter_account_id
            ]);
            
            $result = $twitterService->getBookmarks($user->twitter_account_id);
            
            if ($result && isset($result->data)) {
                $this->bookmarks = $result->data;
                
                // Debug: Log the structure of the first bookmark for debugging
                if (!empty($this->bookmarks)) {
                    $firstBookmark = $this->bookmarks[0];
                    Log::info('Bookmarks Management - First bookmark structure', [
                        'type' => gettype($firstBookmark),
                        'is_object' => is_object($firstBookmark),
                        'is_array' => is_array($firstBookmark),
                        'keys' => is_array($firstBookmark) ? array_keys($firstBookmark) : 'N/A',
                        'properties' => is_object($firstBookmark) ? get_object_vars($firstBookmark) : 'N/A',
                        'has_id' => is_object($firstBookmark) ? isset($firstBookmark->id) : (is_array($firstBookmark) ? isset($firstBookmark['id']) : false),
                        'has_text' => is_object($firstBookmark) ? isset($firstBookmark->text) : (is_array($firstBookmark) ? isset($firstBookmark['text']) : false)
                    ]);
                }
                
                // Cache for 15 minutes
                Cache::put($cacheKey, [
                    'data' => $result->data,
                    'expires_at' => now()->addMinutes(15)
                ], 900);
                
                $this->successMessage = 'Bookmarks loaded successfully!';
            } else {
                $this->errorMessage = 'No bookmarks found.';
            }
        } catch (\Exception $e) {
            Log::error('Failed to load bookmarks', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'twitter_connected' => Auth::user()?->twitter_account_connected ?? false
            ]);
            
            // Handle specific error types with user-friendly messages
            if (strpos($e->getMessage(), 'OAuth 2.0') !== false || strpos($e->getMessage(), 'Authorization Code Flow') !== false) {
                $this->errorMessage = 'Bookmarks feature requires OAuth 2.0 authorization. This feature is not available with your current Twitter API access level. Please upgrade your Twitter API access or contact support for assistance.';
            } elseif (strpos($e->getMessage(), '429 Too Many Requests') !== false) {
                $this->errorMessage = 'Rate limit exceeded. Please wait a few minutes before trying again. Twitter API has limits on how many requests can be made.';
            } elseif (strpos($e->getMessage(), '401') !== false) {
                $this->errorMessage = 'Authentication failed. Please check your Twitter connection.';
            } elseif (strpos($e->getMessage(), '403') !== false) {
                $this->errorMessage = 'Access forbidden. You may not have permission to access this data.';
            } else {
                $this->errorMessage = 'Failed to load bookmarks: ' . $e->getMessage();
            }
        } finally {
            $this->loading = false;
        }
    }

    public function searchBookmarks()
    {
        $this->currentPage = 1;
        $this->applyFilters();
    }

    public function setFilter($filter)
    {
        $this->filterType = $filter;
        $this->currentPage = 1;
        $this->applyFilters();
    }

    public function setSort($sort)
    {
        $this->sortBy = $sort;
        $this->currentPage = 1;
        $this->applyFilters();
    }

    public function applyFilters()
    {
        // This will be handled in the computed property
        $this->dispatch('$refresh');
    }

    public function getFilteredBookmarksProperty()
    {
        $filtered = $this->bookmarks;

        // Apply search filter
        if (!empty($this->searchQuery)) {
            $filtered = array_filter($filtered, function($bookmark) {
                $text = is_object($bookmark) ? ($bookmark->text ?? '') : ($bookmark['text'] ?? '');
                return stripos($text, $this->searchQuery) !== false;
            });
        }

        // Apply type filter
        if ($this->filterType !== 'all') {
            $filtered = array_filter($filtered, function($bookmark) {
                $referencedTweets = is_object($bookmark) ? ($bookmark->referenced_tweets ?? []) : ($bookmark['referenced_tweets'] ?? []);
                
                switch ($this->filterType) {
                    case 'tweets':
                        return empty($referencedTweets);
                    case 'replies':
                        return !empty($referencedTweets) && 
                               (is_array($referencedTweets) ? 
                                   (isset($referencedTweets[0]['type']) && $referencedTweets[0]['type'] === 'replied_to') :
                                   (isset($referencedTweets[0]->type) && $referencedTweets[0]->type === 'replied_to'));
                    case 'quotes':
                        return !empty($referencedTweets) && 
                               (is_array($referencedTweets) ? 
                                   (isset($referencedTweets[0]['type']) && $referencedTweets[0]['type'] === 'quoted') :
                                   (isset($referencedTweets[0]->type) && $referencedTweets[0]->type === 'quoted'));
                    default:
                        return true;
                }
            });
        }

        // Apply sorting
        usort($filtered, function($a, $b) {
            switch ($this->sortBy) {
                case 'newest':
                    $dateA = is_object($a) ? $a->created_at : $a['created_at'];
                    $dateB = is_object($b) ? $b->created_at : $b['created_at'];
                    return strtotime($dateB) - strtotime($dateA);
                
                case 'oldest':
                    $dateA = is_object($a) ? $a->created_at : $a['created_at'];
                    $dateB = is_object($b) ? $b->created_at : $b['created_at'];
                    return strtotime($dateA) - strtotime($dateB);
                
                case 'most_liked':
                    $likesA = is_object($a) ? ($a->public_metrics->like_count ?? 0) : ($a['public_metrics']['like_count'] ?? 0);
                    $likesB = is_object($b) ? ($b->public_metrics->like_count ?? 0) : ($b['public_metrics']['like_count'] ?? 0);
                    return $likesB - $likesA;
                
                case 'most_retweeted':
                    $retweetsA = is_object($a) ? ($a->public_metrics->retweet_count ?? 0) : ($a['public_metrics']['retweet_count'] ?? 0);
                    $retweetsB = is_object($b) ? ($b->public_metrics->retweet_count ?? 0) : ($b['public_metrics']['retweet_count'] ?? 0);
                    return $retweetsB - $retweetsA;
                
                default:
                    return 0;
            }
        });

        return array_values($filtered);
    }

    public function getPaginatedBookmarksProperty()
    {
        $filtered = $this->filteredBookmarks;
        $offset = ($this->currentPage - 1) * $this->perPage;
        return array_slice($filtered, $offset, $this->perPage);
    }

    public function nextPage()
    {
        $totalPages = ceil(count($this->filteredBookmarks) / $this->perPage);
        if ($this->currentPage < $totalPages) {
            $this->currentPage++;
        }
    }

    public function previousPage()
    {
        if ($this->currentPage > 1) {
            $this->currentPage--;
        }
    }

    public function selectBookmark($bookmarkId)
    {
        $this->selectedBookmark = collect($this->bookmarks)->first(function($bookmark) use ($bookmarkId) {
            $id = is_object($bookmark) ? $bookmark->id : $bookmark['id'];
            return $id === $bookmarkId;
        });
    }

    public function removeBookmark($bookmarkId)
    {
        try {
            $this->loading = true;
            $this->errorMessage = '';
            
            $twitterService = $this->getTwitterService();
            $result = $twitterService->removeBookmark($bookmarkId);
            
            if ($result && isset($result->data) && $result->data->bookmarked === false) {
                // Remove from local array
                $this->bookmarks = array_filter($this->bookmarks, function($bookmark) use ($bookmarkId) {
                    $id = is_object($bookmark) ? $bookmark->id : $bookmark['id'];
                    return $id !== $bookmarkId;
                });
                
                // Clear cache
                Cache::forget($this->cacheKey . '_bookmarks');
                
                $this->successMessage = 'Bookmark removed successfully!';
            } else {
                $this->errorMessage = 'Failed to remove bookmark.';
            }
        } catch (\Exception $e) {
            Log::error('Failed to remove bookmark', [
                'bookmark_id' => $bookmarkId,
                'error' => $e->getMessage()
            ]);
            
            if (strpos($e->getMessage(), 'OAuth 2.0') !== false || strpos($e->getMessage(), 'Authorization Code Flow') !== false) {
                $this->errorMessage = 'Bookmark removal requires OAuth 2.0 authorization. This feature is not available with your current Twitter API access level.';
            } elseif (strpos($e->getMessage(), '429 Too Many Requests') !== false) {
                $this->errorMessage = 'Rate limit exceeded. Please wait a few minutes before trying again.';
            } else {
                $this->errorMessage = 'Failed to remove bookmark: ' . $e->getMessage();
            }
        } finally {
            $this->loading = false;
        }
    }

    public function addBookmark()
    {
        try {
            $this->loading = true;
            $this->errorMessage = '';
            
            if (empty($this->newBookmarkUrl)) {
                throw new \Exception('Please enter a tweet URL');
            }
            
            // Extract tweet ID from URL
            $tweetId = $this->extractTweetIdFromUrl($this->newBookmarkUrl);
            if (!$tweetId) {
                throw new \Exception('Invalid tweet URL. Please enter a valid Twitter URL.');
            }
            
            $twitterService = $this->getTwitterService();
            $result = $twitterService->addBookmark($tweetId);
            
            if ($result && isset($result->data) && $result->data->bookmarked === true) {
                $this->successMessage = 'Tweet bookmarked successfully!';
                $this->newBookmarkUrl = '';
                $this->showAddBookmarkModal = false;
                
                // Reload bookmarks
                $this->loadBookmarks();
            } else {
                $this->errorMessage = 'Failed to add bookmark.';
            }
        } catch (\Exception $e) {
            Log::error('Failed to add bookmark', [
                'url' => $this->newBookmarkUrl,
                'error' => $e->getMessage()
            ]);
            
            if (strpos($e->getMessage(), 'OAuth 2.0') !== false || strpos($e->getMessage(), 'Authorization Code Flow') !== false) {
                $this->errorMessage = 'Bookmark addition requires OAuth 2.0 authorization. This feature is not available with your current Twitter API access level.';
            } elseif (strpos($e->getMessage(), '429 Too Many Requests') !== false) {
                $this->errorMessage = 'Rate limit exceeded. Please wait a few minutes before trying again.';
            } else {
                $this->errorMessage = 'Failed to add bookmark: ' . $e->getMessage();
            }
        } finally {
            $this->loading = false;
        }
    }

    private function extractTweetIdFromUrl($url)
    {
        // Extract tweet ID from various Twitter URL formats
        $patterns = [
            '/twitter\.com\/\w+\/status\/(\d+)/',
            '/x\.com\/\w+\/status\/(\d+)/',
            '/t\.co\/\w+/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }

    public function clearCache()
    {
        Cache::forget($this->cacheKey . '_bookmarks');
        $this->successMessage = 'Cache cleared successfully!';
        $this->loadBookmarks();
    }

    public function render()
    {
        return view('livewire.bookmarks-management-component');
    }
}
