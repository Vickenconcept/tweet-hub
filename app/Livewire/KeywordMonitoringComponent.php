<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\TwitterService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class KeywordMonitoringComponent extends Component
{
    public $keywords = [];
    public $newKeyword = '';
    public $tweets = [];
    public $loading = false;
    public $errorMessage = '';
    public $successMessage = '';
    public $lastRefresh = '';
    public $selectedTweet = null;
    public $replyContent = '';
    public $showReplyModal = false;
    public $currentPage = 1;
    public $perPage = 10;
    public $searchLoading = false;
    private $isLoading = false; // Prevent concurrent loads
    
    // Advanced Search Properties
    public $advancedSearch = false;
    public $searchQuery = '';
    public $language = '';
    public $fromUser = '';
    public $excludeRetweets = false;
    public $excludeReplies = false;
    public $hasMedia = false;
    public $hasLinks = false;
    public $minLikes = '';
    public $minRetweets = '';
    public $minReplies = '';
    public $sinceDate = '';
    public $untilDate = '';
    public $nearLocation = '';
    public $withinRadius = '';
    public $isQuestion = false;
    public $sentiment = ''; // positive, negative, neutral
    public $isVerified = false;

    protected $rules = [
        'newKeyword' => 'required|string|min:2|max:50',
    ];

    protected $messages = [
        'newKeyword.required' => 'Please enter a keyword to monitor.',
        'newKeyword.min' => 'Keyword must be at least 2 characters.',
        'newKeyword.max' => 'Keyword must not exceed 50 characters.',
    ];

    public function mount()
    {
        $this->loadKeywords();
        
        // Load tweets directly if we have keywords (uses cache when available)
        if (!empty($this->keywords) || $this->advancedSearch) {
            $this->loadTweets();
        } else {
            $this->tweets = [];
            $this->lastRefresh = now()->format('M j, Y g:i A');
        }
    }

    public function loadKeywords()
    {
        $user = Auth::user();
        if ($user && $user->monitored_keywords) {
            $this->keywords = json_decode($user->monitored_keywords, true) ?? [];
        }
    }

    public function addKeyword()
    {
        $this->validate();

        $keyword = $this->cleanKeyword(trim($this->newKeyword));
        
        // Check if keyword already exists
        if (in_array($keyword, $this->keywords)) {
            $this->errorMessage = 'This keyword is already being monitored.';
            return;
        }

        // Add keyword to array
        $this->keywords[] = $keyword;
        
        // Save to user
        $user = Auth::user();
        if ($user) {
            $user->monitored_keywords = json_encode($this->keywords);
            $user->save();
        }

        $this->newKeyword = '';
        $this->successMessage = "Keyword '{$keyword}' added successfully!";
        
        // Clear any previous errors
        $this->errorMessage = '';
        
        // Load tweets for the new keyword
        $this->loadTweets();
    }

    public function removeKeyword($index)
    {
        if (isset($this->keywords[$index])) {
            $removedKeyword = $this->keywords[$index];
            unset($this->keywords[$index]);
            $this->keywords = array_values($this->keywords); // Re-index array
            
            // Save to user
            $user = Auth::user();
            if ($user) {
                $user->monitored_keywords = json_encode($this->keywords);
                $user->save();
            }

            $this->successMessage = "Keyword '{$removedKeyword}' removed successfully!";
            
            // Load tweets again
            $this->loadTweets();
        }
    }

    public function loadTweets($forceRefresh = false)
    {
        // Prevent concurrent loads
        if ($this->isLoading) {
            Log::info('Load already in progress, skipping duplicate request');
            return;
        }
        
        // For advanced search, we don't need keywords
        if (!$this->advancedSearch && empty($this->keywords)) {
            $this->tweets = [];
            $this->lastRefresh = now()->format('M j, Y g:i A');
            $this->searchLoading = false;
            return;
        }
        
        // For advanced search, we need a search query
        if ($this->advancedSearch && empty($this->searchQuery)) {
            $this->errorMessage = 'Please enter a search query for advanced search.';
            $this->searchLoading = false;
            return;
        }

        $this->isLoading = true;
        $this->searchLoading = true;
        $this->errorMessage = '';
        $this->successMessage = '';

        $user = Auth::user();
        if (!$user) {
            $this->errorMessage = 'User not authenticated.';
            $this->searchLoading = false;
            $this->isLoading = false;
            return;
        }

        if (!$user->twitter_account_connected || !$user->twitter_access_token || !$user->twitter_access_token_secret) {
            $this->errorMessage = 'Please connect your Twitter account first.';
            $this->searchLoading = false;
            $this->isLoading = false;
            return;
        }
        
        // Generate cache key based on keywords or advanced search query
        $cacheKey = 'keyword_search_' . $user->id . '_' . md5($this->advancedSearch ? $this->buildAdvancedQuery() : implode(',', $this->keywords));
        
        // Check cache first (only if not forcing refresh)
        if (!$forceRefresh) {
            $cachedTweets = \Illuminate\Support\Facades\Cache::get($cacheKey);
            
            if ($cachedTweets) {
                Log::info('Loading tweets from cache', ['cache_key' => $cacheKey, 'tweets_count' => count($cachedTweets['data'] ?? [])]);
                $this->tweets = $cachedTweets['data'] ?? [];
                $this->lastRefresh = $cachedTweets['timestamp'] ?? now()->format('M j, Y g:i A');
                $this->currentPage = 1;
                $this->successMessage = 'Tweets loaded from cache (updated ' . \Carbon\Carbon::parse($this->lastRefresh)->diffForHumans() . '). Click Refresh for fresh data.';
                $this->searchLoading = false;
                $this->isLoading = false;
                return;
            }
        } else {
            Log::info('Force refresh requested - bypassing cache');
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
            
            if ($this->advancedSearch && !empty($this->searchQuery)) {
                // Use advanced search
                $this->tweets = $this->performAdvancedSearch($twitterService);
            } else {
                // Use simple keyword search (existing logic)
                $allTweets = [];
                $seenTweetIds = []; // To avoid duplicates
                
                foreach ($this->keywords as $keyword) {
                Log::info('Searching for keyword', ['keyword' => $keyword]);
                
                $searchResponse = $twitterService->searchTweetsByKeyword(
                    $keyword, // single keyword with proper formatting
                    $this->perPage * 3 // Get more results per keyword
                );

                // Log the response for debugging
                Log::info('Keyword Search API Response', [
                    'keyword' => $keyword,
                    'response_type' => gettype($searchResponse),
                    'response_content' => $searchResponse
                ]);

                // Handle different response structures and extract tweets
                $keywordTweets = [];
                if (is_object($searchResponse)) {
                    if (isset($searchResponse->data)) {
                        $keywordTweets = is_array($searchResponse->data) ? $searchResponse->data : (array) $searchResponse->data;
                    } elseif (isset($searchResponse->errors)) {
                        Log::warning('Twitter API returned errors for keyword', [
                            'keyword' => $keyword,
                            'errors' => $searchResponse->errors
                        ]);
                        continue; // Skip this keyword but continue with others
                    }
                } elseif (is_array($searchResponse)) {
                    if (isset($searchResponse['data'])) {
                        $keywordTweets = $searchResponse['data'];
                    }
                }

                // Add unique tweets to the combined results
                foreach ($keywordTweets as $tweet) {
                    $tweetId = is_object($tweet) ? $tweet->id : $tweet['id'];
                    if (!in_array($tweetId, $seenTweetIds)) {
                        // Log tweet structure for debugging
                        Log::info('Tweet structure', [
                            'tweet_id' => $tweetId,
                            'tweet_keys' => is_object($tweet) ? array_keys(get_object_vars($tweet)) : array_keys($tweet),
                            'has_created_at' => is_object($tweet) ? isset($tweet->created_at) : isset($tweet['created_at']),
                        ]);
                        
                        $allTweets[] = $tweet;
                        $seenTweetIds[] = $tweetId;
                    }
                }
            }

            // Sort tweets by creation date (newest first)
            usort($allTweets, function($a, $b) {
                // Try different possible date field names
                $dateA = null;
                $dateB = null;
                
                if (is_object($a)) {
                    $dateA = $a->created_at ?? $a->timestamp ?? $a->date ?? null;
                } else {
                    $dateA = $a['created_at'] ?? $a['timestamp'] ?? $a['date'] ?? null;
                }
                
                if (is_object($b)) {
                    $dateB = $b->created_at ?? $b->timestamp ?? $b->date ?? null;
                } else {
                    $dateB = $b['created_at'] ?? $b['timestamp'] ?? $b['date'] ?? null;
                }
                
                // If we can't find dates, keep original order
                if (!$dateA || !$dateB) {
                    return 0;
                }
                
                return strtotime($dateB) - strtotime($dateA);
            });

                $this->tweets = $allTweets;
            }

            $this->lastRefresh = now()->format('M j, Y g:i A');
            $this->currentPage = 1;

            $tweetCount = count($this->tweets);
            if ($tweetCount > 0) {
                $this->successMessage = "Found {$tweetCount} tweets matching your " . ($this->advancedSearch ? 'advanced search' : 'monitored keywords') . "!";
            } else {
                $this->successMessage = "No tweets found. Try adjusting your " . ($this->advancedSearch ? 'search criteria' : 'keywords') . " or check back later.";
            }
            
            // Cache the results for 4 hours to drastically reduce API calls
            \Illuminate\Support\Facades\Cache::put($cacheKey, [
                'data' => $this->tweets,
                'timestamp' => $this->lastRefresh
            ], 14400); // 4 hour cache

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = $e->getResponse()->getBody()->getContents();
            
            Log::error('Twitter API Client Error', [
                'status_code' => $statusCode,
                'response_body' => $responseBody,
                'query' => $this->advancedSearch ? $this->buildAdvancedQuery() : 'keyword search'
            ]);
            
            if ($statusCode === 429) {
                $this->errorMessage = 'Rate limit exceeded. Please wait a few minutes before trying again. Twitter API has limits on how many requests can be made.';
            } elseif ($statusCode === 401) {
                $this->errorMessage = 'Authentication failed. Please check your Twitter connection.';
            } elseif ($statusCode === 403) {
                $this->errorMessage = 'Access forbidden. You may not have permission to access this data.';
            } elseif ($statusCode === 400) {
                // Parse the error response to get more specific information
                $errorData = json_decode($responseBody, true);
                if (isset($errorData['errors'][0]['message'])) {
                    $this->errorMessage = 'Invalid search query: ' . $errorData['errors'][0]['message'] . '. Please check your search terms and filters.';
                } else {
                    $this->errorMessage = 'Invalid search query. Please check your search terms and filters.';
                }
            } else {
                $this->errorMessage = "Failed to search tweets: HTTP {$statusCode}";
            }
        } catch (\Exception $e) {
            Log::error('Search error', [
                'error' => $e->getMessage(),
                'query' => $this->advancedSearch ? $this->buildAdvancedQuery() : 'keyword search'
            ]);
            
            // Check if error message contains rate limit info
            if (strpos($e->getMessage(), '429 Too Many Requests') !== false || 
                strpos($e->getMessage(), 'Rate limit') !== false) {
                $this->errorMessage = 'Rate limit exceeded. Please wait a few minutes before trying again. Twitter API has limits on how many requests can be made.';
            } else {
                $this->errorMessage = 'Failed to search tweets: ' . $e->getMessage();
            }
        }

        $this->searchLoading = false;
        $this->isLoading = false;
    }

    private function performAdvancedSearch($twitterService)
    {
        $query = $this->buildAdvancedQuery();
        
        Log::info('Advanced search query', [
            'query' => $query,
            'search_query' => $this->searchQuery,
            'filters' => [
                'language' => $this->language,
                'from_user' => $this->fromUser,
                'exclude_retweets' => $this->excludeRetweets,
                'exclude_replies' => $this->excludeReplies,
                'has_media' => $this->hasMedia,
                'has_links' => $this->hasLinks,
                'min_likes' => $this->minLikes,
                'min_retweets' => $this->minRetweets,
                'min_replies' => $this->minReplies,
                'since_date' => $this->sinceDate,
                'until_date' => $this->untilDate,
                'near_location' => $this->nearLocation,
                'within_radius' => $this->withinRadius,
                'is_question' => $this->isQuestion,
                'sentiment' => $this->sentiment,
                'is_verified' => $this->isVerified,
            ]
        ]);
        
        // Use direct search instead of searchTweetsByKeyword to avoid username processing
        $searchResponse = $twitterService->searchTweetsDirect($query, $this->perPage);
        
        Log::info('Advanced search response', [
            'has_data' => isset($searchResponse->data),
            'data_count' => isset($searchResponse->data) ? count($searchResponse->data) : 0,
            'response_type' => gettype($searchResponse)
        ]);
        
        if (isset($searchResponse->data) && is_array($searchResponse->data)) {
            return $searchResponse->data;
        }
        
        return [];
    }

    public function buildAdvancedQuery()
    {
        $queryParts = [];
        
        // Base search query
        if (!empty($this->searchQuery)) {
            // Handle @username specially - remove @ and use from: operator
            if (str_starts_with($this->searchQuery, '@')) {
                $username = ltrim($this->searchQuery, '@');
                $queryParts[] = "from:{$username}";
            } else {
                // Escape special characters that might cause issues
                $escapedQuery = $this->escapeQueryString($this->searchQuery);
                $queryParts[] = $escapedQuery;
            }
        }
        
        // Language filter
        if (!empty($this->language)) {
            $queryParts[] = "lang:{$this->language}";
        }
        
        // User filter
        if (!empty($this->fromUser)) {
            $queryParts[] = "from:{$this->fromUser}";
        }
        
        // Exclude retweets
        if ($this->excludeRetweets) {
            $queryParts[] = "-is:retweet";
        }
        
        // Exclude replies
        if ($this->excludeReplies) {
            $queryParts[] = "-is:reply";
        }
        
        // Has media
        if ($this->hasMedia) {
            $queryParts[] = "has:media";
        }
        
        // Has links
        if ($this->hasLinks) {
            $queryParts[] = "has:links";
        }
        
        // Minimum likes - Not available in Basic API access level
        // These operators require Elevated or Academic Research access
        // if (!empty($this->minLikes) && is_numeric($this->minLikes)) {
        //     $queryParts[] = "min_faves:{$this->minLikes}";
        // }
        
        // if (!empty($this->minRetweets) && is_numeric($this->minRetweets)) {
        //     $queryParts[] = "min_retweets:{$this->minRetweets}";
        // }
        
        // if (!empty($this->minReplies) && is_numeric($this->minReplies)) {
        //     $queryParts[] = "min_replies:{$this->minReplies}";
        // }
        
        // Date range - Not available in Basic API access level
        // These operators require Elevated or Academic Research access
        // if (!empty($this->sinceDate)) {
        //     $formattedDate = date('Y-m-d', strtotime($this->sinceDate));
        //     $queryParts[] = "since:{$formattedDate}";
        // }
        
        // if (!empty($this->untilDate)) {
        //     $formattedDate = date('Y-m-d', strtotime($this->untilDate));
        //     $queryParts[] = "until:{$formattedDate}";
        // }
        
        // Location - Not available in Basic API access level
        // These operators require Elevated or Academic Research access
        // if (!empty($this->nearLocation)) {
        //     $locationQuery = "near:\"{$this->nearLocation}\"";
        //     if (!empty($this->withinRadius)) {
        //         $locationQuery .= " within:{$this->withinRadius}";
        //     }
        //     $queryParts[] = $locationQuery;
        // }
        
        // Question tweets - Not available in Basic API access level
        // This operator requires Elevated or Academic Research access
        // if ($this->isQuestion) {
        //     $queryParts[] = "?";
        // }
        
        // Sentiment - Twitter doesn't support :( and :) operators in search
        // We'll skip sentiment for now as it's not supported by Twitter API v2
        // if ($this->sentiment === 'positive') {
        //     $queryParts[] = ":)";
        // } elseif ($this->sentiment === 'negative') {
        //     $queryParts[] = ":(";
        // }
        
        // Verified users - Not available in Basic API access level
        // This operator requires Elevated or Academic Research access
        // if ($this->isVerified) {
        //     $queryParts[] = "is:verified";
        // }
        
        return implode(' ', $queryParts);
    }

    private function escapeQueryString($query)
    {
        // Escape special characters that might cause Twitter API issues
        $query = trim($query);
        
        // Handle boolean operators properly for Twitter API
        $query = $this->formatBooleanOperators($query);
        
        // If the query is already quoted, don't escape
        if (str_starts_with($query, '"') && str_ends_with($query, '"')) {
            return $query;
        }
        
        // For simple queries, just return as is
        return $query;
    }

    private function formatBooleanOperators($query)
    {
        // Twitter API uses different syntax for boolean operators
        // Replace AND with space (implicit AND)
        $query = preg_replace('/\bAND\b/i', ' ', $query);
        
        // Keep OR as is (Twitter supports OR)
        // $query = preg_replace('/\bOR\b/i', ' OR ', $query);
        
        // Handle NOT operator
        $query = preg_replace('/\bNOT\b/i', '-', $query);
        
        // Clean up multiple spaces
        $query = preg_replace('/\s+/', ' ', $query);
        
        // Remove any leading/trailing operators that might cause issues
        $query = preg_replace('/^[\s\-]+/', '', $query);
        $query = preg_replace('/[\s\-]+$/', '', $query);
        
        return trim($query);
    }

    public function updatedAdvancedSearch($value)
    {
        if (!$value) {
            // Reset advanced search fields when toggling off
            $this->resetAdvancedSearch();
        }
    }
    
    public function toggleAdvancedSearch()
    {
        $this->advancedSearch = !$this->advancedSearch;
        
        if (!$this->advancedSearch) {
            // Reset advanced search fields when toggling off
            $this->resetAdvancedSearch();
        }
    }

    public function resetAdvancedSearch()
    {
        $this->searchQuery = '';
        $this->language = '';
        $this->fromUser = '';
        $this->excludeRetweets = false;
        $this->excludeReplies = false;
        $this->hasMedia = false;
        $this->hasLinks = false;
        $this->minLikes = '';
        $this->minRetweets = '';
        $this->minReplies = '';
        $this->sinceDate = '';
        $this->untilDate = '';
        $this->nearLocation = '';
        $this->withinRadius = '';
        $this->isQuestion = false;
        $this->sentiment = '';
        $this->isVerified = false;
    }

    public function performAdvancedSearchAction()
    {
        if (empty($this->searchQuery)) {
            $this->errorMessage = 'Please enter a search query for advanced search.';
            return;
        }
        
        Log::info('Advanced search action triggered', [
            'search_query' => $this->searchQuery,
            'advanced_search' => $this->advancedSearch,
            'query_built' => $this->buildAdvancedQuery()
        ]);
        
        // Set loading state immediately
        $this->searchLoading = true;
        $this->errorMessage = '';
        $this->successMessage = 'Starting advanced search...';
        
        $this->loadTweets();
    }

    public function clearMessage()
    {
        $this->errorMessage = '';
        $this->successMessage = '';
    }

    public function replyToTweet($tweetId)
    {
        $tweet = collect($this->tweets)->firstWhere('id', $tweetId);
        if ($tweet) {
            $this->selectedTweet = $tweet;
            $this->replyContent = '';
            $this->showReplyModal = true;
        }
    }

    public function sendReply()
    {
        $this->validate([
            'replyContent' => 'required|string|min:1|max:280'
        ]);

        if (!$this->selectedTweet) {
            $this->errorMessage = 'Unable to send reply.';
            return;
        }

        try {
            $user = Auth::user();
            $settings = [
                'account_id' => $user->twitter_account_id,
                'access_token' => $user->twitter_access_token,
                'access_token_secret' => $user->twitter_access_token_secret,
                'consumer_key' => config('services.twitter.api_key'),
                'consumer_secret' => config('services.twitter.api_key_secret'),
                'bearer_token' => config('services.twitter.bearer_token'),
            ];

            $twitterService = new TwitterService($settings);
            $response = $twitterService->createTweet(
                $this->replyContent, 
                [], 
                $this->selectedTweet->id
            );
            
            if ($response && isset($response->data)) {
                $this->successMessage = 'Reply sent successfully!';
                $this->showReplyModal = false;
                $this->selectedTweet = null;
                $this->replyContent = '';
                $this->dispatch('reply-sent');
                $this->dispatch('show-success', 'Reply sent successfully!');
            } else {
                $this->errorMessage = 'Failed to send reply.';
            }
        } catch (\Exception $e) {
            $this->errorMessage = 'Error sending reply: ' . $e->getMessage();
        }
    }

    public function likeTweet($tweetId)
    {
        try {
            $user = Auth::user();
            $settings = [
                'account_id' => $user->twitter_account_id,
                'access_token' => $user->twitter_access_token,
                'access_token_secret' => $user->twitter_access_token_secret,
                'consumer_key' => config('services.twitter.api_key'),
                'consumer_secret' => config('services.twitter.api_key_secret'),
                'bearer_token' => config('services.twitter.bearer_token'),
            ];

            $twitterService = new TwitterService($settings);
            $response = $twitterService->likeTweet($tweetId);
            
            if ($response) {
                // Check if it's a rate limit message
                if (isset($response->data->message) && strpos($response->data->message, 'Rate limit exceeded') !== false) {
                    $this->errorMessage = $response->data->message;
                    $this->dispatch('show-error', $response->data->message);
                } else {
                    $this->successMessage = 'Tweet liked successfully!';
                    $this->dispatch('show-success', 'Tweet liked successfully!');
                }
            }
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to like tweet: ' . $e->getMessage();
        }
    }

    public function retweetTweet($tweetId)
    {
        try {
            $user = Auth::user();
            $settings = [
                'account_id' => $user->twitter_account_id,
                'access_token' => $user->twitter_access_token,
                'access_token_secret' => $user->twitter_access_token_secret,
                'consumer_key' => config('services.twitter.api_key'),
                'consumer_secret' => config('services.twitter.api_key_secret'),
                'bearer_token' => config('services.twitter.bearer_token'),
            ];

            $twitterService = new TwitterService($settings);
            $response = $twitterService->retweet($tweetId);
            
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
            }
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to retweet: ' . $e->getMessage();
        }
    }

    public function cancelReply()
    {
        $this->showReplyModal = false;
        $this->selectedTweet = null;
        $this->replyContent = '';
    }

    public function refreshTweets()
    {
        // Force refresh to bypass cache
        $this->loadTweets(true);
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
        if ($page >= 1 && $page <= $this->totalPages) {
            $this->currentPage = $page;
        }
    }

    public function getPaginatedTweetsProperty()
    {
        $start = ($this->currentPage - 1) * $this->perPage;
        return array_slice($this->tweets, $start, $this->perPage);
    }

    public function getTotalPagesProperty()
    {
        return ceil(count($this->tweets) / $this->perPage);
    }

    /**
     * Clean and validate keyword input
     */
    private function cleanKeyword($keyword)
    {
        // Remove extra spaces and special characters that might cause issues
        $keyword = trim($keyword);
        
        // Remove any extra quotes or special characters that might break the API
        $keyword = preg_replace('/["\'`]/', '', $keyword);
        
        // For hashtags, ensure there's only one # at the beginning
        if (str_starts_with($keyword, '#')) {
            $keyword = '#' . ltrim($keyword, '#');
        }
        
        // For mentions, ensure there's only one @ at the beginning
        if (str_starts_with($keyword, '@')) {
            $keyword = '@' . ltrim($keyword, '@');
        }
        
        return $keyword;
    }

    public function render()
    {
        return view('livewire.keyword-monitoring-component', [
            'paginatedTweets' => $this->paginatedTweets,
            'totalPages' => $this->totalPages
        ]);
    }
}
