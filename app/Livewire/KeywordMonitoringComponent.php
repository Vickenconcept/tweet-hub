<?php

namespace App\Livewire;

use App\Models\TwitterAccount;
use App\Models\User;
use App\Models\AutoReply;
use App\Services\ChatGptService;
use App\Services\TwitterService;
use App\Jobs\ProcessMentionAutoReplies;
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
    public $isRateLimited = false;
    public $rateLimitResetTime = '';
    public $rateLimitWaitMinutes = 0;
    public $autoReplyEnabled = false;
    public $twitterAccountConfigured = false;
    /**
     * IDs of tweets that have already been auto-replied by AI (for UI badge).
     *
     * @var array<int, string>
     */
    public $autoRepliedIds = [];
    
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
        
        // Don't load immediately - let page load first, then it will auto-load after 3 seconds
        // This prevents page delay when opening
        Log::info('Keyword page mounted - skipping immediate API call to prevent delay');
        
        // Load TwitterAccount status
        $user = Auth::user();
        if ($user) {
            $twitterAccount = TwitterAccount::where('user_id', $user->id)->first();
            $this->twitterAccountConfigured = $twitterAccount && $twitterAccount->isConfigured();
            $this->autoReplyEnabled = $twitterAccount && $twitterAccount->isAutoEnabled();
        }

        // Check rate limit status on mount
        $this->checkRateLimitStatus();
        
        // Initialize tweets array
        $this->tweets = [];
        $this->lastRefresh = now()->format('M j, Y g:i A');
    }

    // Removed updatedAutoReplyEnabled - toggle is now read-only status indicator
    // Users manage auto-comment settings in Twitter Settings page
    
    
    public function checkRateLimitStatus()
    {
        $user = Auth::user();
        if ($user) {
            $settings = [
                'account_id' => $user->twitter_account_id,
                'access_token' => $user->twitter_access_token,
                'access_token_secret' => $user->twitter_access_token_secret,
                'consumer_key' => config('services.twitter.api_key'),
                'consumer_secret' => config('services.twitter.api_key_secret'),
                'bearer_token' => config('services.twitter.bearer_token'),
            ];
            
            $twitterService = new TwitterService($settings);
            $rateLimitCheck = $twitterService->isRateLimitedForSearch();
            
            Log::info('🔍 Rate limit status check (Search)', [
                'rate_limited' => $rateLimitCheck['rate_limited'] ?? false,
                'reset_time' => $rateLimitCheck['reset_time'] ?? 'none',
                'wait_minutes' => $rateLimitCheck['wait_minutes'] ?? 0
            ]);
            
            if ($rateLimitCheck['rate_limited']) {
                $this->isRateLimited = true;
                $this->rateLimitResetTime = $rateLimitCheck['reset_time'];
                $this->rateLimitWaitMinutes = $rateLimitCheck['wait_minutes'];
                Log::warning('🚫 Rate limit ACTIVE - UI updated (Search)', [
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
        // Always check rate limit status before making API calls
        $this->checkRateLimitStatus();
        
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
                Log::info('✅ CACHE HIT: Keywords loaded from cache (NO API CALL)', [
                    'cache_key' => $cacheKey,
                    'tweets_count' => count($cachedTweets['data'] ?? []),
                    'last_updated' => $cachedTweets['timestamp'] ?? 'unknown',
                    'timestamp' => now()->format('Y-m-d H:i:s')
                ]);
                $this->tweets = $cachedTweets['data'] ?? [];
                
                // Filter out duplicate tweets by content before displaying
                $this->deduplicateTweetsByContent();
                
                $this->lastRefresh = $cachedTweets['timestamp'] ?? now()->format('M j, Y g:i A');
                $this->currentPage = 1;
                $this->successMessage = 'Tweets loaded from cache (updated ' . \Carbon\Carbon::parse($this->lastRefresh)->diffForHumans() . '). Click Refresh for fresh data.';
                
                // Update local cache of which tweets have been auto-replied already
                $this->loadAutoRepliedIds($user);
                
                // Trigger AI auto-replies even when loading from cache
                $tweetCount = count($this->tweets);
                $this->triggerAutoReplyIfEnabled($user, $tweetCount);
                
                $this->searchLoading = false;
                $this->isLoading = false;
                return;
            }
            
            // If no cache and rate limited, don't make API call
            if ($this->isRateLimited) {
                Log::warning('🚫 Rate limited - skipping API call, no cache available');
                $this->errorMessage = "Rate limit active. Please wait {$this->rateLimitWaitMinutes} minute(s). Reset time: {$this->rateLimitResetTime}";
                $this->searchLoading = false;
                $this->isLoading = false;
                return;
            }
        } else {
            Log::info('Force refresh requested - bypassing cache');
            
            // If forcing refresh but rate limited, don't make API call
            if ($this->isRateLimited) {
                Log::warning('🚫 Rate limited - cannot force refresh');
                $this->errorMessage = "Rate limit active. Please wait {$this->rateLimitWaitMinutes} minute(s) before refreshing. Reset time: {$this->rateLimitResetTime}";
                $this->searchLoading = false;
                $this->isLoading = false;
                return;
            }
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
                
                // IMPORTANT: Batch keywords to make ONE API call instead of multiple
                // Use OR operator to search all keywords at once
                $searchQuery = implode(' OR ', array_map(function($keyword) {
                    // Format each keyword properly
                    if (str_starts_with($keyword, '#')) {
                        return $keyword; // Hashtag
                    } elseif (str_starts_with($keyword, '@')) {
                        return $keyword; // Mention
                    } else {
                        return '"' . $keyword . '"'; // Exact phrase
                    }
                }, $this->keywords));
                
                Log::info('🔴 API CALL: Batch searching keywords (ONE CALL for all)', [
                    'keywords_count' => count($this->keywords),
                    'keywords' => $this->keywords,
                    'batch_query' => $searchQuery,
                    'timestamp' => now()->format('Y-m-d H:i:s')
                ]);
                
                // Make ONE API call for all keywords
                $searchResponse = $twitterService->searchTweetsDirect(
                    $searchQuery,
                    $this->perPage * count($this->keywords) // Scale results by keyword count
                );

                // Log the response for debugging
                Log::info('Batch Keyword Search API Response', [
                    'response_type' => gettype($searchResponse),
                    'has_data' => is_object($searchResponse) ? isset($searchResponse->data) : (is_array($searchResponse) ? isset($searchResponse['data']) : false)
                ]);

                // Handle different response structures and extract tweets from batch search
                $keywordTweets = [];
                if (is_object($searchResponse)) {
                    if (isset($searchResponse->data)) {
                        $keywordTweets = is_array($searchResponse->data) ? $searchResponse->data : (array) $searchResponse->data;
                    } elseif (isset($searchResponse->errors)) {
                        Log::warning('Twitter API returned errors for batch search', [
                            'errors' => $searchResponse->errors
                        ]);
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
                        $allTweets[] = $tweet;
                        $seenTweetIds[] = $tweetId;
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

            // Filter out duplicate tweets by content before displaying
            $this->deduplicateTweetsByContent();

            $this->lastRefresh = now()->format('M j, Y g:i A');
            $this->currentPage = 1;

            $tweetCount = count($this->tweets);
            if ($tweetCount > 0) {
                $this->successMessage = "Found {$tweetCount} tweets matching your " . ($this->advancedSearch ? 'advanced search' : 'monitored keywords') . "!";
            } else {
                $this->successMessage = "No tweets found. Try adjusting your " . ($this->advancedSearch ? 'search criteria' : 'keywords') . " or check back later.";
            }

            // Update local cache of which tweets have been auto-replied already
            $this->loadAutoRepliedIds($user);

            // Trigger AI auto-replies in the background if enabled
            $this->triggerAutoReplyIfEnabled($user, $tweetCount);
            
            // Cache the results for 4 hours to drastically reduce API calls
            \Illuminate\Support\Facades\Cache::put($cacheKey, [
                'data' => $this->tweets,
                'timestamp' => $this->lastRefresh
            ], 14400); // 4 hour cache

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            
            // Extract rate limit headers directly from response
            $rateLimitReset = null;
            try {
                if ($response->hasHeader('x-rate-limit-reset')) {
                    $rateLimitReset = (int) $response->getHeaderLine('x-rate-limit-reset');
                }
            } catch (\Exception $headerEx) {
                Log::warning('Could not read rate limit reset header', ['error' => $headerEx->getMessage()]);
            }
            
            Log::error('Twitter API Client Error - Search', [
                'status_code' => $statusCode,
                'rate_limit_reset' => $rateLimitReset ? date('Y-m-d H:i:s', $rateLimitReset) : null,
                'response_body' => $responseBody,
                'query' => $this->advancedSearch ? $this->buildAdvancedQuery() : 'keyword search'
            ]);
            
            if ($statusCode === 429) {
                // Update rate limit status immediately from response headers
                if ($rateLimitReset) {
                    $waitMinutes = ceil(($rateLimitReset - time()) / 60);
                    $resetDateTime = date('Y-m-d H:i:s', $rateLimitReset);
                    
                    $this->isRateLimited = true;
                    $this->rateLimitResetTime = $resetDateTime;
                    $this->rateLimitWaitMinutes = $waitMinutes;
                    $this->errorMessage = "Rate limit exceeded. Please wait {$waitMinutes} minute(s) before trying again. Reset time: {$resetDateTime}";
                    
                    Log::warning('🚫 Rate limit detected from 429 response (Search)', [
                        'reset_time' => $resetDateTime,
                        'wait_minutes' => $waitMinutes
                    ]);
                } else {
                    // Fallback: check cache (TwitterService should have stored it)
                    $this->checkRateLimitStatus();
                    if ($this->isRateLimited) {
                        $this->errorMessage = "Rate limit exceeded. Please wait {$this->rateLimitWaitMinutes} minute(s) before trying again. Reset time: {$this->rateLimitResetTime}";
                    } else {
                        $this->errorMessage = 'Rate limit exceeded. Please wait a few minutes before trying again. Twitter API has limits on how many requests can be made.';
                    }
                }
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
                'query' => $this->advancedSearch ? $this->buildAdvancedQuery() : 'keyword search',
                'class' => get_class($e)
            ]);
            
            // Check if error message contains rate limit info
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, '429 Too Many Requests') !== false || 
                strpos($errorMessage, 'Rate limit') !== false ||
                strpos($errorMessage, 'Rate limit active') !== false ||
                strpos($errorMessage, 'UsageCapExceeded') !== false ||
                strpos($errorMessage, 'Monthly') !== false) {
                
                // First, try to extract reset time from error message (TwitterService includes it)
                $resetTimeFromMessage = null;
                if (preg_match('/Reset time: ([^\.]+)/', $errorMessage, $matches)) {
                    $resetTimeFromMessage = trim($matches[1]);
                }
                
                // Check rate limit info from cache (TwitterService should have stored it)
                $user = Auth::user();
                if ($user) {
                    $settings = [
                        'account_id' => $user->twitter_account_id,
                        'access_token' => $user->twitter_access_token,
                        'access_token_secret' => $user->twitter_access_token_secret,
                        'consumer_key' => config('services.twitter.api_key'),
                        'consumer_secret' => config('services.twitter.api_key_secret'),
                        'bearer_token' => config('services.twitter.bearer_token'),
                    ];
                    
                    $twitterService = new TwitterService($settings);
                    $rateLimitCheck = $twitterService->isRateLimitedForSearch();
                    
                    if ($rateLimitCheck['rate_limited']) {
                        // Use cache data (most reliable)
                        $this->isRateLimited = true;
                        $this->rateLimitResetTime = $rateLimitCheck['reset_time'];
                        $this->rateLimitWaitMinutes = $rateLimitCheck['wait_minutes'];
                        $this->errorMessage = "Rate limit exceeded. Please wait {$rateLimitCheck['wait_minutes']} minute(s) before trying again. Reset time: {$rateLimitCheck['reset_time']}";
                        
                        Log::warning('🚫 Rate limit detected from cache after exception (Search)', [
                            'reset_time' => $rateLimitCheck['reset_time'],
                            'wait_minutes' => $rateLimitCheck['wait_minutes']
                        ]);
                    } elseif ($resetTimeFromMessage) {
                        // Fallback: use reset time from exception message
                        $this->isRateLimited = true;
                        $this->rateLimitResetTime = $resetTimeFromMessage;
                        // Calculate wait minutes from reset time string
                        try {
                            $resetTimestamp = strtotime($resetTimeFromMessage);
                            if ($resetTimestamp) {
                                $this->rateLimitWaitMinutes = ceil(($resetTimestamp - time()) / 60);
                            }
                        } catch (\Exception $timeEx) {
                            $this->rateLimitWaitMinutes = 15; // Default fallback
                        }
                        $this->errorMessage = "Rate limit exceeded. Reset time: {$resetTimeFromMessage}. Please wait before trying again.";
                        
                        Log::warning('🚫 Rate limit detected from exception message (Search)', [
                            'reset_time' => $resetTimeFromMessage
                        ]);
                    } else {
                        // Generic rate limit message
                        $this->isRateLimited = true;
                        $this->rateLimitWaitMinutes = 15; // Default fallback
                        $this->errorMessage = 'Rate limit exceeded. Please wait a few minutes before trying again. Twitter API has limits on how many requests can be made.';
                    }
                } else {
                    $this->isRateLimited = true;
                    $this->errorMessage = 'Rate limit exceeded. Please wait a few minutes before trying again. Twitter API has limits on how many requests can be made.';
                }
            } else {
                // Clean up error message - remove URLs and technical details
                $cleanMessage = $errorMessage;
                
                // Remove full URLs
                $cleanMessage = preg_replace('/https?:\/\/[^\s]+/', '', $cleanMessage);
                
                // Remove technical details
                $cleanMessage = preg_replace('/Client error:.*?response:/', '', $cleanMessage);
                $cleanMessage = preg_replace('/resulted in a `\d+.*?` response:/', '', $cleanMessage);
                
                $this->errorMessage = 'Failed to search tweets: ' . trim($cleanMessage);
            }
        }

        $this->searchLoading = false;
        $this->isLoading = false;
    }

    /**
     * Automatically reply to new keyword / search tweets using AI, ensuring we don't reply twice.
     */
    protected function handleAutoRepliesForTweets(User $user): void
    {
        try {
            $chatGptService = app(ChatGptService::class);

            // Safety: limit how many auto replies we send per load to avoid rate limits
            $maxAutoRepliesPerLoad = 5;
            $autoRepliesSent = 0;

            foreach ($this->tweets as $tweet) {
                if ($autoRepliesSent >= $maxAutoRepliesPerLoad) {
                    break;
                }

                $tweetId = is_object($tweet) ? ($tweet->id ?? null) : ($tweet['id'] ?? null);
                $tweetText = is_object($tweet) ? ($tweet->text ?? '') : ($tweet['text'] ?? '');
                $authorId = is_object($tweet) ? ($tweet->author_id ?? null) : ($tweet['author_id'] ?? null);

                if (!$tweetId || !$tweetText) {
                    continue;
                }

                // Simple safeguard: only reply to question-like / engaged tweets
                if (strpos($tweetText, '?') === false) {
                    continue;
                }

                // Don't auto-reply to our own tweets
                if ($authorId && $user->twitter_account_id && (string) $authorId === (string) $user->twitter_account_id) {
                    continue;
                }

                // Skip if we've already auto-replied to this tweet for keyword search
                $alreadyReplied = AutoReply::where('user_id', $user->id)
                    ->where('tweet_id', (string) $tweetId)
                    ->where('source_type', 'keyword')
                    ->exists();

                if ($alreadyReplied) {
                    continue;
                }

                // Build a prompt for a short, positive, thoughtful reply tailored to THIS user's brand
                $brandName = $user->twitter_name ?: $user->name ?: 'your personal brand';
                $handleUsername = ltrim((string) $user->twitter_username, '@');
                $handle = $handleUsername ? '@' . $handleUsername : '@brand';
                
                // Get TwitterAccount for context URL and prompt
                $twitterAccount = TwitterAccount::where('user_id', $user->id)->first();
                $contextString = $this->buildContextString($twitterAccount);
                
                $prompt = "You are helping {$brandName} reply on Twitter (X) as {$handle}.\n"
                    . "This tweet was found via keyword monitoring - we want to join the conversation about this topic.\n"
                    . ($contextString ? "Context about the brand:\n{$contextString}\n\n" : "")
                    . "Write ONE short, human, warm, and genuinely helpful reply (max 220 characters) to this tweet.\n"
                    . "Tone: positive, respectful, and professional. No slang, no sarcasm, no negativity.\n"
                    . "Do NOT include hashtags, links, or emojis unless absolutely necessary. Avoid sounding like AI.\n"
                    . "If the tweet is offensive, political, or unsafe, politely decline instead of engaging.\n\n"
                    . "Tweet content:\n\"{$tweetText}\"";

                $replyText = trim($chatGptService->generateContent($prompt) ?? '');

                // Basic safety checks
                if ($replyText === '' || mb_strlen($replyText) > 280) {
                    continue;
                }

                $settings = [
                    'account_id' => $user->twitter_account_id,
                    'access_token' => $user->twitter_access_token,
                    'access_token_secret' => $user->twitter_access_token_secret,
                    'consumer_key' => config('services.twitter.api_key'),
                    'consumer_secret' => config('services.twitter.api_key_secret'),
                    'bearer_token' => config('services.twitter.bearer_token'),
                ];

                $twitterService = new TwitterService($settings);

                Log::info('🤖 Sending AI auto-reply to keyword tweet', [
                    'user_id' => $user->id,
                    'tweet_id' => $tweetId,
                ]);

                $response = $twitterService->createTweet(
                    $replyText,
                    [],
                    $tweetId
                );

                if ($response && isset($response->data)) {
                    AutoReply::create([
                        'user_id' => $user->id,
                        'tweet_id' => (string) $tweetId,
                        'source_type' => 'keyword',
                        'original_text' => $tweetText,
                        'reply_text' => $replyText,
                        'replied_at' => now(),
                    ]);

                    $autoRepliesSent++;
                    $this->autoRepliedIds[] = (string) $tweetId;
                }
            }

            if ($autoRepliesSent > 0) {
                $this->dispatch('show-success', "{$autoRepliesSent} tweet(s) auto-replied with AI.");
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle AI auto-replies for keyword tweets', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Trigger auto-reply job if conditions are met
     */
    protected function triggerAutoReplyIfEnabled(User $user, int $tweetCount): void
    {
        Log::info('🔍 Checking if auto-reply should be triggered (keywords)', [
            'user_id' => $user->id,
            'auto_reply_enabled' => $this->autoReplyEnabled,
            'tweet_count' => $tweetCount,
            'twitter_account_configured' => $this->twitterAccountConfigured,
        ]);

        if ($this->autoReplyEnabled && $tweetCount > 0) {
            // Verify TwitterAccount is actually enabled
            $twitterAccount = TwitterAccount::where('user_id', $user->id)->first();
            $isActuallyEnabled = $twitterAccount && $twitterAccount->isAutoEnabled();

            Log::info('✅ Auto-reply conditions met, preparing job dispatch (keywords)', [
                'user_id' => $user->id,
                'tweet_count' => $tweetCount,
                'source_type' => 'keyword',
                'twitter_account_exists' => $twitterAccount !== null,
                'is_configured' => $twitterAccount?->isConfigured() ?? false,
                'is_auto_enabled' => $isActuallyEnabled,
                'can_post' => $twitterAccount?->canPost() ?? false,
                'comments_posted_today' => $twitterAccount?->comments_posted_today ?? 0,
                'daily_limit' => $twitterAccount?->daily_comment_limit ?? 0,
            ]);

            if (!$isActuallyEnabled) {
                Log::warning('⚠️ Auto-reply UI shows enabled but TwitterAccount is not actually enabled (keywords)', [
                    'user_id' => $user->id,
                    'ui_shows_enabled' => $this->autoReplyEnabled,
                ]);
                return;
            }

            $tweetPayloads = [];
            foreach ($this->tweets as $tweet) {
                $tweetPayloads[] = [
                    'id' => is_object($tweet) ? ($tweet->id ?? null) : ($tweet['id'] ?? null),
                    'text' => is_object($tweet) ? ($tweet->text ?? '') : ($tweet['text'] ?? ''),
                    'author_id' => is_object($tweet) ? ($tweet->author_id ?? null) : ($tweet['author_id'] ?? null),
                ];
            }

            Log::info('🚀 Dispatching ProcessMentionAutoReplies job for keyword tweets', [
                'user_id' => $user->id,
                'tweets_count' => $tweetCount,
                'payloads_count' => count($tweetPayloads),
                'source_type' => 'keyword',
            ]);

            // Reuse the same job class; it only cares about user id + tweet payloads
            ProcessMentionAutoReplies::dispatch($user->id, $tweetPayloads, 'keyword');

            Log::info('✅ ProcessMentionAutoReplies job dispatched successfully (keywords)', [
                'user_id' => $user->id,
                'source_type' => 'keyword',
            ]);
        } else {
            Log::info('⏭️ Auto-reply not triggered (keywords)', [
                'user_id' => $user->id,
                'auto_reply_enabled' => $this->autoReplyEnabled,
                'tweet_count' => $tweetCount,
                'reason' => !$this->autoReplyEnabled ? 'auto-reply disabled in UI' : 'no tweets found',
            ]);
        }
    }

    /**
     * Filter out duplicate tweets by text content (normalizes and deduplicates)
     */
    protected function deduplicateTweetsByContent(): void
    {
        $uniqueTweets = [];
        $seenTextHashes = [];
        $duplicateCount = 0;
        
        foreach ($this->tweets as $tweet) {
            $tweetId = is_object($tweet) ? ($tweet->id ?? null) : ($tweet['id'] ?? null);
            $tweetText = is_object($tweet) ? ($tweet->text ?? '') : ($tweet['text'] ?? '');
            
            // Normalize text: trim, lowercase, remove RT prefix, normalize whitespace
            $normalizedText = mb_strtolower(trim($tweetText));
            // Remove "RT @username: " prefix if present (retweets have this)
            $normalizedText = preg_replace('/^rt\s+@\w+:\s*/i', '', $normalizedText);
            // Normalize whitespace (multiple spaces to single space)
            $normalizedText = preg_replace('/\s+/', ' ', trim($normalizedText));
            
            // Skip empty or very short texts
            if (mb_strlen($normalizedText) < 10) {
                continue;
            }
            
            // Create a hash of the normalized text for comparison
            $textHash = md5($normalizedText);
            
            // Skip if we've seen this exact text content before
            if (isset($seenTextHashes[$textHash])) {
                $duplicateCount++;
                continue;
            }
            
            // Track this text hash with the first tweet ID we saw it with
            $seenTextHashes[$textHash] = $tweetId;
            $uniqueTweets[] = $tweet;
        }
        
        if ($duplicateCount > 0) {
            Log::info('🔄 Filtered duplicate tweets by content (UI)', [
                'user_id' => Auth::id(),
                'original_count' => count($this->tweets),
                'unique_count' => count($uniqueTweets),
                'duplicates_removed' => $duplicateCount,
            ]);
        }
        
        $this->tweets = $uniqueTweets;
    }

    /**
     * Load IDs of tweets that already have an AI auto-reply (for UI badges).
     */
    protected function loadAutoRepliedIds(User $user): void
    {
        $ids = [];
        foreach ($this->tweets as $tweet) {
            $tweetId = is_object($tweet) ? ($tweet->id ?? null) : ($tweet['id'] ?? null);
            if ($tweetId) {
                $ids[] = (string) $tweetId;
            }
        }

        if (empty($ids)) {
            $this->autoRepliedIds = [];
            return;
        }

        $this->autoRepliedIds = AutoReply::where('user_id', $user->id)
            ->where('source_type', 'keyword')
            ->whereIn('tweet_id', $ids)
            ->pluck('tweet_id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
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
            
            // Get tweet ID - handle both object and array formats
            $tweetId = is_object($this->selectedTweet) ? $this->selectedTweet->id : $this->selectedTweet['id'];
            
            Log::info('🔴 UI ACTION: Sending reply from keyword page', [
                'tweet_id' => $tweetId,
                'reply_text' => $this->replyContent
            ]);
            
            $response = $twitterService->createTweet(
                $this->replyContent, 
                [], 
                $tweetId
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
            Log::info('🔴 UI ACTION: Like tweet from keyword page', ['tweet_id' => $tweetId]);
            
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
                // Log the full response for debugging
                Log::info('Like response received (keyword)', [
                    'tweet_id' => $tweetId,
                    'response' => $response,
                    'has_error' => isset($response->error),
                    'has_data' => isset($response->data),
                    'has_message' => isset($response->data->message),
                    'has_liked' => isset($response->data->liked),
                    'liked_value' => isset($response->data->liked) ? $response->data->liked : 'not_set',
                    'message_value' => isset($response->data->message) ? $response->data->message : 'not_set'
                ]);
                
                // Check for explicit success indicator first
                if (isset($response->data->liked) && $response->data->liked === true) {
                    // Definitely successful
                    Log::info('✅ Like successful (liked=true)', ['tweet_id' => $tweetId]);
                    $this->successMessage = 'Tweet liked successfully!';
                    $this->dispatch('show-success', 'Tweet liked successfully!');
                }
                // Check for explicit error indicator
                elseif (isset($response->error)) {
                    // Has error property - definitely an error
                    $errorMsg = $response->error;
                    Log::warning('⚠️ Like failed (has error property)', ['tweet_id' => $tweetId, 'error' => $errorMsg]);
                    $this->errorMessage = $errorMsg;
                    $this->dispatch('show-error', $errorMsg);
                }
                // Check if message contains error keywords
                elseif (isset($response->data->message) && !empty($response->data->message)) {
                    $message = $response->data->message;
                    // Only treat as error if message contains error keywords
                    if (stripos($message, 'error') !== false || 
                        stripos($message, 'failed') !== false || 
                        stripos($message, 'rate limit') !== false ||
                        stripos($message, 'too many requests') !== false ||
                        stripos($message, 'forbidden') !== false ||
                        stripos($message, 'unauthorized') !== false ||
                        stripos($message, 'not found') !== false) {
                        Log::warning('⚠️ Like failed (error message detected)', ['tweet_id' => $tweetId, 'message' => $message]);
                        $this->errorMessage = $message;
                        $this->dispatch('show-error', $message);
                    } else {
                        // Message doesn't indicate error - treat as success
                        Log::info('✅ Like successful (no error keywords in message)', ['tweet_id' => $tweetId, 'message' => $message]);
                        $this->successMessage = 'Tweet liked successfully!';
                        $this->dispatch('show-success', 'Tweet liked successfully!');
                    }
                }
                // No error indicators - treat as success
                else {
                    Log::info('✅ Like successful (no error indicators)', ['tweet_id' => $tweetId]);
                    $this->successMessage = 'Tweet liked successfully!';
                    $this->dispatch('show-success', 'Tweet liked successfully!');
                }
            } else {
                // No response at all
                $this->errorMessage = 'Failed to like tweet: No response from Twitter API';
                Log::warning('⚠️ Like failed - no response', ['tweet_id' => $tweetId]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Failed to like tweet from keyword page', [
                'tweet_id' => $tweetId,
                'error' => $e->getMessage()
            ]);
            $this->errorMessage = 'Failed to like tweet: ' . $e->getMessage();
        }
    }

    public function retweetTweet($tweetId)
    {
        try {
            Log::info('🔴 UI ACTION: Retweet from keyword page', ['tweet_id' => $tweetId]);
            
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
        // Check rate limit before making request (search uses same API key pool)
        $this->checkRateLimitStatus();
        
        if ($this->isRateLimited) {
            $this->errorMessage = "Rate limit active. Please wait {$this->rateLimitWaitMinutes} minute(s) before refreshing. Reset time: {$this->rateLimitResetTime}";
            // Don't clear cache when rate limited - try to load from existing cache
            Log::warning('🚫 Refresh blocked - rate limited, attempting to load from existing cache');
            
            // Try to load from cache if available
            $user = Auth::user();
            if ($user) {
                $cacheKey = 'keyword_search_' . $user->id . '_' . md5($this->advancedSearch ? $this->buildAdvancedQuery() : implode(',', $this->keywords));
                $cachedTweets = \Illuminate\Support\Facades\Cache::get($cacheKey);
                
                if ($cachedTweets) {
                    Log::info('✅ Loading from cache while rate limited', [
                        'tweets_count' => count($cachedTweets['data'] ?? []),
                        'last_updated' => $cachedTweets['timestamp'] ?? 'unknown'
                    ]);
                    $this->tweets = $cachedTweets['data'] ?? [];
                    
                    // Filter out duplicate tweets by content before displaying
                    $this->deduplicateTweetsByContent();
                    
                    $this->lastRefresh = $cachedTweets['timestamp'] ?? now()->format('M j, Y g:i A');
                    $this->currentPage = 1;
                    $this->successMessage = 'Showing cached data (rate limited). Cache updated ' . \Carbon\Carbon::parse($this->lastRefresh)->diffForHumans() . '.';
                }
            }
            return;
        }
        
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

    /**
     * Build context string from URL and context prompt.
     *
     * @param TwitterAccount|null $twitterAccount
     * @return string
     */
    protected function buildContextString(?TwitterAccount $twitterAccount): string
    {
        if (!$twitterAccount) {
            return '';
        }

        $contextParts = [];

        // Add URL context if provided
        if (!empty($twitterAccount->auto_comment_url)) {
            $url = $twitterAccount->auto_comment_url;
            $contextParts[] = "Brand/Website URL: {$url}";
            $contextParts[] = "For more context, refer to: {$url}";
        }

        // Add context prompt if provided
        if (!empty($twitterAccount->auto_comment_context_prompt)) {
            $contextParts[] = trim($twitterAccount->auto_comment_context_prompt);
        }

        return !empty($contextParts) ? implode("\n\n", $contextParts) : '';
    }

    public function render()
    {
        return view('livewire.keyword-monitoring-component', [
            'paginatedTweets' => $this->paginatedTweets,
            'totalPages' => $this->totalPages
        ]);
    }
}
