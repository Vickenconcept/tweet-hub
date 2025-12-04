<?php

namespace App\Services;

use Noweh\TwitterApi\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use GuzzleHttp\Client as GuzzleClient;

class TwitterService
{
    protected $client;
    protected $bearerToken;
    protected $settings;

    public function __construct(array $settings)
    {
        $this->client = new Client($settings);
        $this->settings = $settings;
        $this->bearerToken = $settings['bearer_token'] ?? config('services.twitter.bearer_token');
    }

    // Tweets endpoints
    /**
     * Find recent mentions for a user using fast search method.
     */
    public function getRecentMentionsFast($accountId)
    {
        try {
            Log::info('🔴 API CALL: getRecentMentionsFast', [
                'account_id' => $accountId,
                'endpoint' => 'search/recent',
                'timestamp' => now()->format('Y-m-d H:i:s')
            ]);
            
            // Get user info first to get username
            $userInfo = $this->findUser($accountId, \Noweh\TwitterApi\UserLookup::MODES['ID']);
            
            if (isset($userInfo->data->username)) {
                $username = $userInfo->data->username;
                Log::info('Found username for mentions search', ['username' => $username]);
                
                // Use search endpoint with @username query for faster results
                return $this->searchTweetsDirect("@{$username}", 50);
            } else {
                throw new \Exception('Could not find username for user ID: ' . $accountId);
            }
        } catch (\Exception $e) {
            Log::error('Fast search method failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Check if we're currently rate limited for mentions endpoint
     */
    public function isRateLimitedForMentions()
    {
        $rateLimitKey = 'twitter_rate_limit_mentions_' . md5($this->settings['consumer_key'] ?? 'default');
        $rateLimitInfo = \Illuminate\Support\Facades\Cache::get($rateLimitKey);
        
        if ($rateLimitInfo && isset($rateLimitInfo['reset_time'])) {
            $resetTime = $rateLimitInfo['reset_time'];
            if ($resetTime > time()) {
                return [
                    'rate_limited' => true,
                    'reset_time' => $rateLimitInfo['reset_datetime'] ?? date('Y-m-d H:i:s', $resetTime),
                    'wait_minutes' => ceil(($resetTime - time()) / 60),
                    'remaining' => $rateLimitInfo['remaining'] ?? 0,
                    'limit' => $rateLimitInfo['limit'] ?? 0
                ];
            }
        }
        
        return ['rate_limited' => false];
    }

    /**
     * Check if we're currently rate limited for search API calls.
     */
    public function isRateLimitedForSearch()
    {
        $rateLimitKey = 'twitter_rate_limit_search_' . md5($this->settings['consumer_key'] ?? 'default');
        $rateLimitInfo = \Illuminate\Support\Facades\Cache::get($rateLimitKey);
        
        if ($rateLimitInfo && isset($rateLimitInfo['reset_time'])) {
            $resetTime = $rateLimitInfo['reset_time'];
            if ($resetTime > time()) {
                return [
                    'rate_limited' => true,
                    'reset_time' => $rateLimitInfo['reset_datetime'] ?? date('Y-m-d H:i:s', $resetTime),
                    'wait_minutes' => ceil(($resetTime - time()) / 60),
                    'remaining' => $rateLimitInfo['remaining'] ?? 0,
                    'limit' => $rateLimitInfo['limit'] ?? 0
                ];
            }
        }
        
        return ['rate_limited' => false];
    }

    /**
     * Check if we're currently rate limited for followers/following API calls.
     */
    public function isRateLimitedForFollowers()
    {
        $rateLimitKey = 'twitter_rate_limit_followers_' . md5($this->settings['consumer_key'] ?? 'default');
        $rateLimitInfo = \Illuminate\Support\Facades\Cache::get($rateLimitKey);
        
        if ($rateLimitInfo && isset($rateLimitInfo['reset_time'])) {
            $resetTime = $rateLimitInfo['reset_time'];
            if ($resetTime > time()) {
                return [
                    'rate_limited' => true,
                    'reset_time' => $rateLimitInfo['reset_datetime'] ?? date('Y-m-d H:i:s', $resetTime),
                    'wait_minutes' => ceil(($resetTime - time()) / 60),
                    'remaining' => $rateLimitInfo['remaining'] ?? 0,
                    'limit' => $rateLimitInfo['limit'] ?? 0
                ];
            }
        }
        
        return ['rate_limited' => false];
    }

    /**
     * Find recent mentions for a user.
     */
    public function getRecentMentions($accountId)
    {
        // Check if we're currently rate limited (from previous requests)
        $rateLimitKey = 'twitter_rate_limit_mentions_' . md5($this->settings['consumer_key'] ?? 'default');
        $rateLimitInfo = \Illuminate\Support\Facades\Cache::get($rateLimitKey);
        
        if ($rateLimitInfo && isset($rateLimitInfo['reset_time'])) {
            $resetTime = $rateLimitInfo['reset_time'];
            $now = time();
            
            if ($resetTime > $now) {
                $waitSeconds = $resetTime - $now;
                $waitMinutes = ceil($waitSeconds / 60);
                
                Log::warning('🚫 Rate limit active - preventing API call', [
                    'account_id' => $accountId,
                    'reset_time' => date('Y-m-d H:i:s', $resetTime),
                    'wait_seconds' => $waitSeconds,
                    'wait_minutes' => $waitMinutes,
                    'remaining_requests' => $rateLimitInfo['remaining'] ?? 0,
                    'limit' => $rateLimitInfo['limit'] ?? 0
                ]);
                
                throw new \Exception("Rate limit active. Please wait {$waitMinutes} minute(s) before trying again. Reset time: " . date('Y-m-d H:i:s', $resetTime));
            }
        }

        try {
            Log::info('🔴 API CALL: getRecentMentions', [
                'account_id' => $accountId,
                'endpoint' => 'timeline/mentions',
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'rate_limit_check' => $rateLimitInfo ? 'passed' : 'none'
            ]);
            
            // Make the API call - we need to intercept the response to get rate limit headers
            // Since the package doesn't expose headers easily, we'll catch the exception
            $result = $this->client->timeline()->getRecentMentions($accountId)->performRequest();
            
            Log::info('✅ Recent mentions fetched successfully', ['account_id' => $accountId]);
            
            return $result;
            
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            
            // Extract rate limit headers
            $rateLimitRemaining = null;
            $rateLimitLimit = null;
            $rateLimitReset = null;
            
            try {
                if ($response->hasHeader('x-rate-limit-remaining')) {
                    $rateLimitRemaining = (int) $response->getHeaderLine('x-rate-limit-remaining');
                }
                if ($response->hasHeader('x-rate-limit-limit')) {
                    $rateLimitLimit = (int) $response->getHeaderLine('x-rate-limit-limit');
                }
                if ($response->hasHeader('x-rate-limit-reset')) {
                    $rateLimitReset = (int) $response->getHeaderLine('x-rate-limit-reset');
                }
            } catch (\Exception $headerEx) {
                Log::warning('Could not read rate limit headers', ['error' => $headerEx->getMessage()]);
            }
            
            // Parse response body to check for monthly quota errors
            $isMonthlyQuota = false;
            try {
                $errorData = json_decode($body, true);
                if (isset($errorData['title']) && strpos($errorData['title'], 'UsageCapExceeded') !== false) {
                    $isMonthlyQuota = true;
                } elseif (isset($errorData['detail']) && (strpos($errorData['detail'], 'UsageCapExceeded') !== false || strpos($errorData['detail'], 'Monthly') !== false)) {
                    $isMonthlyQuota = true;
                } elseif (isset($errorData['account_id']) && isset($errorData['product_name']) && isset($errorData['period']) && $errorData['period'] === 'Monthly') {
                    $isMonthlyQuota = true;
                }
            } catch (\Exception $parseEx) {
                // If JSON parsing fails, check if body contains quota keywords
                if (strpos($body, 'UsageCapExceeded') !== false || strpos($body, 'Monthly') !== false) {
                    $isMonthlyQuota = true;
                }
            }
            
            Log::error('❌ Twitter API Error fetching mentions', [
                'account_id' => $accountId,
                'status_code' => $statusCode,
                'rate_limit_remaining' => $rateLimitRemaining,
                'rate_limit_limit' => $rateLimitLimit,
                'rate_limit_reset' => $rateLimitReset ? date('Y-m-d H:i:s', $rateLimitReset) : null,
                'rate_limit_reset_timestamp' => $rateLimitReset,
                'error_body' => $body,
                'is_monthly_quota' => $isMonthlyQuota,
                'total_requests_made' => 1
            ]);
            
            // If we got a 429, store rate limit info and DO NOT RETRY
            if ($statusCode === 429) {
                // Check if it's a monthly quota exceeded
                if ($isMonthlyQuota) {
                    // For monthly quota, set rate limit to 30 days from now
                    $rateLimitReset = time() + (30 * 24 * 60 * 60); // 30 days
                    Log::warning('⚠️ Monthly quota exceeded detected (Mentions) - setting 30-day rate limit', [
                        'account_id' => $accountId,
                        'reset_time' => date('Y-m-d H:i:s', $rateLimitReset)
                    ]);
                } elseif (!$rateLimitReset) {
                    // If no reset time in headers and not monthly quota, use default 15 minutes from now
                    $rateLimitReset = time() + (15 * 60); // 15 minutes from now
                    Log::warning('⚠️ 429 received but no x-rate-limit-reset header - using default 15 minutes', [
                        'account_id' => $accountId,
                        'default_reset_time' => date('Y-m-d H:i:s', $rateLimitReset)
                    ]);
                }
                
                // Store rate limit info in cache until reset time
                $cacheUntil = $rateLimitReset + 60; // Cache for 1 minute after reset to be safe
                $cacheTtl = $cacheUntil - time();
                $rateLimitData = [
                    'remaining' => 0,
                    'limit' => $rateLimitLimit ?? 0,
                    'reset_time' => $rateLimitReset,
                    'reset_datetime' => date('Y-m-d H:i:s', $rateLimitReset),
                    'is_monthly_quota' => $isMonthlyQuota
                ];
                
                \Illuminate\Support\Facades\Cache::put($rateLimitKey, $rateLimitData, $cacheTtl);
                
                $waitMinutes = ceil(($rateLimitReset - time()) / 60);
                $waitDays = $isMonthlyQuota ? ceil($waitMinutes / (24 * 60)) : 0;
                
                // Verify cache was stored
                $verifyCache = \Illuminate\Support\Facades\Cache::get($rateLimitKey);
                
                Log::error('🚫 RATE LIMIT HIT - DO NOT RETRY', [
                    'account_id' => $accountId,
                    'cache_key' => $rateLimitKey,
                    'reset_time' => date('Y-m-d H:i:s', $rateLimitReset),
                    'reset_timestamp' => $rateLimitReset,
                    'wait_minutes' => $waitMinutes,
                    'wait_days' => $waitDays,
                    'cache_ttl_seconds' => $cacheTtl,
                    'rate_limit_limit' => $rateLimitLimit,
                    'cache_stored' => $verifyCache ? 'yes' : 'no',
                    'has_reset_header' => $rateLimitReset ? 'yes' : 'no',
                    'is_monthly_quota' => $isMonthlyQuota,
                    'message' => $isMonthlyQuota ? 'Monthly quota exceeded. Stored in cache to prevent further requests.' : 'Rate limit exceeded. Stored in cache to prevent further requests.'
                ]);
                
                // DO NOT RETRY on 429 - throw immediately
                $errorMsg = $isMonthlyQuota 
                    ? "Monthly API quota exceeded. Reset time: " . date('Y-m-d H:i:s', $rateLimitReset) . ". Please wait before trying again."
                    : "Rate limit exceeded (429). Reset time: " . date('Y-m-d H:i:s', $rateLimitReset) . ". Please wait before trying again.";
                
                throw new \Exception($errorMsg);
            }
            
            // For other client errors, don't retry
            if ($statusCode >= 400 && $statusCode < 500) {
                throw $e;
            }
            
            // For server errors (5xx), we could retry, but let's be conservative
            throw $e;
            
        } catch (\Exception $e) {
            // Re-throw if it's already our custom exception
            if (strpos($e->getMessage(), 'Rate limit') !== false) {
                throw $e;
            }
            
            // Check if this is a 429 error wrapped in a different exception type
            $errorMessage = $e->getMessage();
            $is429Error = false;
            $isMonthlyQuota = false;
            $rateLimitReset = null;
            
            // Check if error message contains 429 or rate limit info
            if (strpos($errorMessage, '429') !== false || 
                strpos($errorMessage, 'Too Many Requests') !== false ||
                strpos($errorMessage, 'UsageCapExceeded') !== false) {
                $is429Error = true;
                
                // Check if it's a monthly quota exceeded
                if (strpos($errorMessage, 'UsageCapExceeded') !== false || 
                    strpos($errorMessage, 'Monthly') !== false) {
                    $isMonthlyQuota = true;
                    // For monthly quota, set rate limit to 30 days from now
                    $rateLimitReset = time() + (30 * 24 * 60 * 60); // 30 days
                    Log::warning('⚠️ Monthly quota exceeded detected', [
                        'account_id' => $accountId,
                        'reset_time' => date('Y-m-d H:i:s', $rateLimitReset)
                    ]);
                } else {
                    // Standard rate limit - use default 15 minutes
                    $rateLimitReset = time() + (15 * 60); // 15 minutes
                }
            }
            
            // If it's a 429 error, store rate limit info
            if ($is429Error && $rateLimitReset) {
                $rateLimitKey = 'twitter_rate_limit_mentions_' . md5($this->settings['consumer_key'] ?? 'default');
                $cacheUntil = $rateLimitReset + 60; // Cache for 1 minute after reset
                $cacheTtl = $cacheUntil - time();
                $rateLimitData = [
                    'remaining' => 0,
                    'limit' => 0,
                    'reset_time' => $rateLimitReset,
                    'reset_datetime' => date('Y-m-d H:i:s', $rateLimitReset),
                    'is_monthly_quota' => $isMonthlyQuota
                ];
                
                \Illuminate\Support\Facades\Cache::put($rateLimitKey, $rateLimitData, $cacheTtl);
                
                $waitMinutes = ceil(($rateLimitReset - time()) / 60);
                $waitDays = $isMonthlyQuota ? ceil($waitMinutes / (24 * 60)) : 0;
                
                Log::error('🚫 RATE LIMIT HIT (from generic exception) - DO NOT RETRY', [
                    'account_id' => $accountId,
                    'cache_key' => $rateLimitKey,
                    'reset_time' => date('Y-m-d H:i:s', $rateLimitReset),
                    'wait_minutes' => $waitMinutes,
                    'wait_days' => $waitDays,
                    'is_monthly_quota' => $isMonthlyQuota,
                    'cache_stored' => 'yes',
                    'message' => $isMonthlyQuota ? 'Monthly quota exceeded. Stored in cache to prevent further requests.' : 'Rate limit exceeded. Stored in cache to prevent further requests.'
                ]);
                
                $errorMsg = $isMonthlyQuota 
                    ? "Monthly API quota exceeded. Reset time: " . date('Y-m-d H:i:s', $rateLimitReset) . ". Please wait before trying again."
                    : "Rate limit exceeded (429). Reset time: " . date('Y-m-d H:i:s', $rateLimitReset) . ". Please wait before trying again.";
                
                throw new \Exception($errorMsg);
            }
            
            Log::error('❌ Unexpected error fetching mentions', [
                'account_id' => $accountId,
                'error' => $errorMessage,
                'class' => get_class($e)
            ]);
            
            throw $e;
        }
    }

    /**
     * Find recent tweets for a user.
     */
    public function getRecentTweets($accountId)
    {
        return $this->client->timeline()->getRecentTweets($accountId)->performRequest();
    }

    /**
     * Get reverse chronological timeline by user ID.
     */
    public function getReverseChronological()
    {
        return $this->client->timeline()->getReverseChronological()->performRequest();
    }

    // Tweet/Likes endpoints
    /**
     * Get tweets liked by a user.
     */
    public function getLikedTweets($accountId, $pageSize = 10)
    {
        return $this->client->tweetLikes()->addMaxResults($pageSize)->getLikedTweets($accountId)->performRequest();
    }

    /**
     * Get users who liked a tweet.
     */
    public function getUsersWhoLiked($tweetId, $pageSize = 10)
    {
        return $this->client->tweetLikes()->addMaxResults($pageSize)->getUsersWhoLiked($tweetId)->performRequest();
    }

    // Tweet/Lookup endpoints
    /**
     * Search specific tweets with filters.
     */
    public function searchTweets($usernames = [], $keywords = [], $locales = [], $pageSize = 10)
    {
        $lookup = $this->client->tweetLookup()
            ->addMaxResults($pageSize)
            ->showUserDetails()
            ->showMetrics();
        if (!empty($usernames)) {
            $lookup->addFilterOnUsernamesFrom($usernames, \Noweh\TwitterApi\TweetLookup::OPERATORS['OR']);
        }
        if (!empty($keywords)) {
            $lookup->addFilterOnKeywordOrPhrase($keywords, \Noweh\TwitterApi\TweetLookup::OPERATORS['AND']);
        }
        if (!empty($locales)) {
            $lookup->addFilterOnLocales($locales);
        }
        return $lookup->performRequest();
    }

    /**
     * Search tweets by keyword using appropriate Twitter API endpoint.
     */
    public function searchTweetsByKeyword($keyword, $pageSize = 10)
    {
        Log::info('🔴 API CALL: searchTweetsByKeyword', [
            'keyword' => $keyword,
            'page_size' => $pageSize,
            'timestamp' => now()->format('Y-m-d H:i:s')
        ]);
        
        // Clean and format the keyword for Twitter API
        $formattedKeyword = $this->formatKeywordForTwitter($keyword);
        
        if (empty($formattedKeyword)) {
            throw new \Exception('Invalid keyword format');
        }

        // For mentions (@username), we need to use a different approach
        if (str_starts_with($formattedKeyword, '@')) {
            return $this->searchMentionsByUsername($formattedKeyword, $pageSize);
        }

        // For hashtags and regular keywords, use search endpoint
        return $this->searchTweetsDirect($formattedKeyword, $pageSize);
    }

    /**
     * Search for mentions of a specific username.
     */
    private function searchMentionsByUsername($username, $pageSize = 10)
    {
        try {
            // Remove @ symbol for user lookup
            $cleanUsername = ltrim($username, '@');
            
            // First, get the user ID for the username
            $user = $this->findUser($cleanUsername, \Noweh\TwitterApi\UserLookup::MODES['USERNAME']);
            
            if (!isset($user->data->id)) {
                throw new \Exception("User {$cleanUsername} not found");
            }
            
            $userId = $user->data->id;
            
            // Get recent mentions for this user
            return $this->getRecentMentions($userId);
            
        } catch (\Exception $e) {
            Log::error('Failed to search mentions by username', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to regular search if user lookup fails
            return $this->searchTweetsDirect($username, $pageSize);
        }
    }

    /**
     * Search tweets directly using Twitter API v2 search/recent endpoint.
     */
    public function searchTweetsDirect($query, $pageSize = 10)
    {
        try {
            Log::info('🔴 API CALL: searchTweetsDirect', [
                'query' => $query,
                'page_size' => $pageSize,
                'endpoint' => 'search/recent',
                'timestamp' => now()->format('Y-m-d H:i:s')
            ]);
            
            // Twitter API v2 search requires Bearer Token authentication
            $bearerToken = config('services.twitter.bearer_token');
            
            if (empty($bearerToken)) {
                throw new \Exception('Twitter Bearer Token not configured');
            }

            // Build the search URL
            $baseUrl = 'https://api.twitter.com/2/tweets/search/recent';
            $params = [
                'query' => $query,
                'max_results' => min($pageSize, 100), // Twitter API limit
                'tweet.fields' => 'created_at,public_metrics,author_id',
                'expansions' => 'author_id',
                'user.fields' => 'name,username,profile_image_url'
            ];

            $url = $baseUrl . '?' . http_build_query($params);
            
            Log::info('Twitter Search API Request', [
                'url' => $url,
                'query' => $query,
                'page_size' => $pageSize
            ]);

            // Make Bearer Token request
            $guzzleClient = new GuzzleClient();
            $response = $guzzleClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $bearerToken,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            Log::info('Twitter Search API Response', [
                'query' => $query,
                'response' => $data
            ]);

            if (isset($data['errors'])) {
                throw new \Exception('Twitter API Error: ' . json_encode($data['errors']));
            }

            // Convert to object format to match existing code
            return (object) $data;

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            
            // Get rate limit cache key
            $rateLimitKey = 'twitter_rate_limit_search_' . md5($this->settings['consumer_key'] ?? 'default');
            
            // Extract rate limit headers
            $rateLimitRemaining = null;
            $rateLimitLimit = null;
            $rateLimitReset = null;
            
            try {
                if ($response->hasHeader('x-rate-limit-remaining')) {
                    $rateLimitRemaining = (int) $response->getHeaderLine('x-rate-limit-remaining');
                }
                if ($response->hasHeader('x-rate-limit-limit')) {
                    $rateLimitLimit = (int) $response->getHeaderLine('x-rate-limit-limit');
                }
                if ($response->hasHeader('x-rate-limit-reset')) {
                    $rateLimitReset = (int) $response->getHeaderLine('x-rate-limit-reset');
                }
            } catch (\Exception $headerEx) {
                Log::warning('Could not read rate limit headers', ['error' => $headerEx->getMessage()]);
            }
            
            // Parse response body to check for monthly quota errors
            $isMonthlyQuota = false;
            try {
                $errorData = json_decode($body, true);
                if (isset($errorData['title']) && strpos($errorData['title'], 'UsageCapExceeded') !== false) {
                    $isMonthlyQuota = true;
                } elseif (isset($errorData['detail']) && (strpos($errorData['detail'], 'UsageCapExceeded') !== false || strpos($errorData['detail'], 'Monthly') !== false)) {
                    $isMonthlyQuota = true;
                } elseif (isset($errorData['account_id']) && isset($errorData['product_name']) && isset($errorData['period']) && $errorData['period'] === 'Monthly') {
                    $isMonthlyQuota = true;
                }
            } catch (\Exception $parseEx) {
                // If JSON parsing fails, check if body contains quota keywords
                if (strpos($body, 'UsageCapExceeded') !== false || strpos($body, 'Monthly') !== false) {
                    $isMonthlyQuota = true;
                }
            }
            
            Log::error('❌ Twitter API Error - Search', [
                'query' => $query,
                'status_code' => $statusCode,
                'rate_limit_remaining' => $rateLimitRemaining,
                'rate_limit_limit' => $rateLimitLimit,
                'rate_limit_reset' => $rateLimitReset ? date('Y-m-d H:i:s', $rateLimitReset) : null,
                'rate_limit_reset_timestamp' => $rateLimitReset,
                'error_body' => $body,
                'is_monthly_quota' => $isMonthlyQuota,
                'total_requests_made' => 1
            ]);
            
            // If we got a 429, store rate limit info and DO NOT RETRY
            if ($statusCode === 429) {
                // Check if it's a monthly quota exceeded
                if ($isMonthlyQuota) {
                    // For monthly quota, set rate limit to 30 days from now
                    $rateLimitReset = time() + (30 * 24 * 60 * 60); // 30 days
                    Log::warning('⚠️ Monthly quota exceeded detected (Search) - setting 30-day rate limit', [
                        'query' => $query,
                        'reset_time' => date('Y-m-d H:i:s', $rateLimitReset)
                    ]);
                } elseif (!$rateLimitReset) {
                    // If no reset time in headers and not monthly quota, use default 15 minutes from now
                    $rateLimitReset = time() + (15 * 60); // 15 minutes from now
                    Log::warning('⚠️ 429 received but no x-rate-limit-reset header - using default 15 minutes', [
                        'query' => $query,
                        'default_reset_time' => date('Y-m-d H:i:s', $rateLimitReset)
                    ]);
                }
                
                // Store rate limit info in cache until reset time
                $cacheUntil = $rateLimitReset + 60; // Cache for 1 minute after reset to be safe
                $cacheTtl = $cacheUntil - time();
                $rateLimitData = [
                    'remaining' => 0,
                    'limit' => $rateLimitLimit ?? 0,
                    'reset_time' => $rateLimitReset,
                    'reset_datetime' => date('Y-m-d H:i:s', $rateLimitReset),
                    'is_monthly_quota' => $isMonthlyQuota
                ];
                
                \Illuminate\Support\Facades\Cache::put($rateLimitKey, $rateLimitData, $cacheTtl);
                
                $waitMinutes = ceil(($rateLimitReset - time()) / 60);
                $waitDays = $isMonthlyQuota ? ceil($waitMinutes / (24 * 60)) : 0;
                
                // Verify cache was stored
                $verifyCache = \Illuminate\Support\Facades\Cache::get($rateLimitKey);
                
                Log::error('🚫 RATE LIMIT HIT - DO NOT RETRY (Search)', [
                    'query' => $query,
                    'cache_key' => $rateLimitKey,
                    'reset_time' => date('Y-m-d H:i:s', $rateLimitReset),
                    'reset_timestamp' => $rateLimitReset,
                    'wait_minutes' => $waitMinutes,
                    'wait_days' => $waitDays,
                    'cache_ttl_seconds' => $cacheTtl,
                    'rate_limit_limit' => $rateLimitLimit,
                    'cache_stored' => $verifyCache ? 'yes' : 'no',
                    'has_reset_header' => $rateLimitReset ? 'yes' : 'no',
                    'is_monthly_quota' => $isMonthlyQuota,
                    'message' => $isMonthlyQuota ? 'Monthly quota exceeded. Stored in cache to prevent further requests.' : 'Rate limit exceeded. Stored in cache to prevent further requests.'
                ]);
                
                // DO NOT RETRY on 429 - throw immediately
                $errorMsg = $isMonthlyQuota 
                    ? "Monthly API quota exceeded. Reset time: " . date('Y-m-d H:i:s', $rateLimitReset) . ". Please wait before trying again."
                    : "Rate limit exceeded (429). Reset time: " . date('Y-m-d H:i:s', $rateLimitReset) . ". Please wait before trying again.";
                
                throw new \Exception($errorMsg);
            }
            
            // For other client errors, don't retry
            if ($statusCode >= 400 && $statusCode < 500) {
                throw $e;
            }
            
            // For server errors (5xx), we could retry, but let's be conservative
            throw $e;
            
        } catch (\Exception $e) {
            // Re-throw if it's already our custom exception
            if (strpos($e->getMessage(), 'Rate limit') !== false || strpos($e->getMessage(), 'Monthly') !== false) {
                throw $e;
            }
            
            Log::error('Direct Twitter search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
                'class' => get_class($e)
            ]);
            throw $e;
        }
    }

    /**
     * Format keyword for Twitter API search.
     */
    private function formatKeywordForTwitter($keyword)
    {
        $keyword = trim($keyword);
        
        // Handle hashtags - Twitter API expects them as-is
        if (str_starts_with($keyword, '#')) {
            return $keyword; // Keep hashtag as-is
        }
        
        // Handle mentions - Twitter API expects them as-is
        if (str_starts_with($keyword, '@')) {
            return $keyword; // Keep mention as-is
        }
        
        // Handle regular keywords - wrap in quotes if it contains spaces
        if (strpos($keyword, ' ') !== false) {
            return '"' . $keyword . '"';
        }
        
        // Return as-is for simple keywords
        return $keyword;
    }

    /**
     * Find all replies from a Tweet.
     */
    public function getReplies($tweetId)
    {
        return $this->client->tweetLookup()->addFilterOnConversationId($tweetId)->performRequest();
    }

    // Tweet endpoints
    /**
     * Fetch a tweet by ID.
     */
    public function fetchTweet($tweetId)
    {
        return $this->client->tweet()->fetch($tweetId)->performRequest();
    }

    /**
     * Create a new tweet. Optionally as a reply (for threads).
     */
    public function createTweet($text, $mediaIds = [], $inReplyToTweetId = null)
    {
        // Validate character limit (Twitter/X limit is 280 characters)
        $charCount = mb_strlen($text, 'UTF-8');
        if ($charCount > 280) {
            $error = "Tweet exceeds character limit. Current: {$charCount}, Limit: 280";
            Log::error('Twitter API Error: ' . $error, [
                'text' => $text,
                'char_count' => $charCount,
                'limit' => 280
            ]);
            throw new \Exception($error);
        }

        try {
            $data = ['text' => $text];
            if (!empty($mediaIds)) {
                // Ensure all media IDs are strings and not empty
                $validMediaIds = array_filter(array_map('strval', $mediaIds), function($id) {
                    return !empty($id) && is_numeric($id);
                });
                
                if (!empty($validMediaIds)) {
                    $data['media'] = ['media_ids' => array_values($validMediaIds)];
                }
            }
            
            // Ensure we have either text or media
            if (empty(trim($text)) && empty($data['media'] ?? [])) {
                throw new \Exception('Tweet must contain either text or media content');
            }
            if ($inReplyToTweetId) {
                $data['reply'] = ['in_reply_to_tweet_id' => $inReplyToTweetId];
            }
            
            Log::info('Creating tweet', [
                'char_count' => $charCount,
                'has_media' => !empty($mediaIds),
                'media_ids' => $mediaIds,
                'is_reply' => !empty($inReplyToTweetId),
                'request_data' => $data
            ]);
            
            $result = $this->client->tweet()->create()->performRequest($data);
            
            Log::info('Tweet created successfully', [
                'tweet_id' => $result->data->id ?? 'unknown',
                'text' => $text,
                'response' => $result
            ]);
            
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            
            Log::error('Twitter API Client Error', [
                'status_code' => $statusCode,
                'error_body' => $body,
                'text' => $text,
                'char_count' => $charCount,
                'request_data' => $data
            ]);
            
            throw $e;
        } catch (\Exception $e) {
            Log::error('Twitter API General Error', [
                'message' => $e->getMessage(),
                'text' => $text,
                'char_count' => $charCount
            ]);
            
            throw $e;
        }
    }

    /**
     * Send a Direct Message (DM) to a user by recipient ID using Twitter API v1.1.
     *
     * Endpoint: POST https://api.twitter.com/1.1/direct_messages/events/new.json
     *
     * This requires:
     * - App-level DM permissions in the Twitter Developer Portal
     * - Valid user access_token/access_token_secret with DM scope
     */
    public function sendDirectMessage(string $recipientId, string $text)
    {
        // Basic validation – keep DMs non-empty and reasonably short
        $charCount = mb_strlen($text, 'UTF-8');
        if ($charCount === 0) {
            throw new \Exception('DM text cannot be empty');
        }
        if ($charCount > 10000) {
            // DMs can be longer than tweets, but let's be safe and cap it
            $text = $this->truncateForTwitter($text, 10000);
        }

        // Ensure we have OAuth 1.0a credentials for the authenticated user
        $consumerKey = $this->settings['consumer_key'] ?? config('services.twitter.api_key');
        $consumerSecret = $this->settings['consumer_secret'] ?? config('services.twitter.api_key_secret');
        $accessToken = $this->settings['access_token'] ?? null;
        $accessTokenSecret = $this->settings['access_token_secret'] ?? null;

        if (!$consumerKey || !$consumerSecret || !$accessToken || !$accessTokenSecret) {
            Log::error('sendDirectMessage: missing OAuth credentials', [
                'has_consumer_key' => (bool) $consumerKey,
                'has_consumer_secret' => (bool) $consumerSecret,
                'has_access_token' => (bool) $accessToken,
                'has_access_token_secret' => (bool) $accessTokenSecret,
            ]);
            throw new \Exception('Twitter OAuth credentials missing for sending DMs.');
        }

        $oauthSettings = [
            'consumer_key' => $consumerKey,
            'consumer_secret' => $consumerSecret,
            'access_token' => $accessToken,
            'access_token_secret' => $accessTokenSecret,
        ];

        $url = 'https://api.twitter.com/1.1/direct_messages/events/new.json';

        // Build DM event payload
        $body = [
            'event' => [
                'type' => 'message_create',
                'message_create' => [
                    'target' => [
                        'recipient_id' => $recipientId,
                    ],
                    'message_data' => [
                        'text' => $text,
                    ],
                ],
            ],
        ];

        Log::info('📤 TwitterService: Attempting to send Direct Message', [
            'recipient_id' => $recipientId,
            'char_count' => $charCount,
            'endpoint' => $url,
            'has_oauth_credentials' => !empty($accessToken) && !empty($accessTokenSecret),
        ]);

        try {
            $responseJson = $this->makeOAuthJsonRequest('POST', $url, $body, $oauthSettings);
            $response = json_decode($responseJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('sendDirectMessage: failed to decode Twitter DM response', [
                    'recipient_id' => $recipientId,
                    'raw_response' => $responseJson,
                    'json_error' => json_last_error_msg(),
                ]);
            }

            if (isset($response['errors'])) {
                Log::error('❌ TwitterService: Twitter DM API returned errors', [
                    'recipient_id' => $recipientId,
                    'errors' => $response['errors'],
                    'full_response' => $response,
                ]);

                return (object) [
                    'data' => [
                        'sent' => false,
                        'message' => 'Twitter DM API error: ' . json_encode($response['errors']),
                    ],
                    'raw' => $response,
                ];
            }

            Log::info('✅ TwitterService: Direct Message sent successfully', [
                'recipient_id' => $recipientId,
                'event_id' => $response['event']['id'] ?? null,
                'created_timestamp' => $response['event']['created_timestamp'] ?? null,
                'message_id' => $response['event']['message_create']['message_data']['id'] ?? null,
                'full_response' => $response,
            ]);

            return (object) [
                'data' => [
                    'sent' => true,
                    'message' => 'DM sent successfully.',
                ],
                'raw' => $response,
            ];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $res = $e->getResponse();
            $status = $res ? $res->getStatusCode() : null;
            $bodyText = $res ? $res->getBody()->getContents() : $e->getMessage();

            Log::error('❌ TwitterService: Twitter DM ClientException (HTTP error)', [
                'recipient_id' => $recipientId,
                'status_code' => $status,
                'http_status' => $status,
                'error_body' => $bodyText,
                'endpoint' => $url,
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('❌ TwitterService: Twitter DM General Error', [
                'recipient_id' => $recipientId,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'endpoint' => $url,
            ]);

            throw $e;
        }
    }

    /**
     * Upload image to Twitter and return media info.
     */
    public function uploadMedia($file)
    {
        $file_data = base64_encode(file_get_contents($file));
        return $this->client->uploadMedia()->upload($file_data);
    }

    /**
     * Upload a local file to Twitter and return the media ID.
     */
    public function uploadLocalMedia($localPath)
    {
        Log::info('Uploading media to Twitter', [
            'local_path' => $localPath,
            'file_exists' => file_exists($localPath),
            'file_size' => file_exists($localPath) ? filesize($localPath) : 'N/A'
        ]);
        
        if (!file_exists($localPath)) {
            Log::error('Media file does not exist', ['path' => $localPath]);
            return null;
        }
        
        try {
            // Check file properties
            $fileSize = filesize($localPath);
            $fileInfo = getimagesize($localPath);
            $mimeType = mime_content_type($localPath);
            
            Log::info('File properties before upload', [
                'path' => $localPath,
                'size' => $fileSize,
                'image_info' => $fileInfo,
                'mime_type' => $mimeType,
                'is_gif' => $mimeType === 'image/gif'
            ]);
            
                            // Determine if it's a video or image (GIFs are treated as images)
                $isVideo = str_starts_with($mimeType, 'video/');
                $isGif = $mimeType === 'image/gif';
                
                if ($isVideo) {
                // Check if video is too large for Twitter
                if ($fileSize > 15 * 1024 * 1024) { // 15MB limit
                    throw new \Exception('Video file is too large. Twitter supports videos up to 15MB.');
                }
                
                // For now, let's try a simple approach - just use regular upload
                // The chunked upload seems to have issues with the package
                Log::info('Trying regular video upload', ['size' => $fileSize]);
                try {
                    $media_info = $this->client->uploadMedia()->upload($localPath);
                    if (isset($media_info['media_id'])) {
                        Log::info('Regular video upload successful', ['media_id' => $media_info['media_id']]);
                    } else {
                        throw new \Exception('No media_id returned from regular upload');
                    }
                } catch (\Exception $e) {
                    Log::error('Regular video upload failed', ['error' => $e->getMessage()]);
                    // For now, let's not try chunked upload as it's not working
                    throw new \Exception('Video upload failed: ' . $e->getMessage());
                }
            } else {
                                // For images and GIFs, try different approaches based on type
                if ($isGif) {
                    Log::info('Attempting GIF upload with tweet_gif media category', ['path' => $localPath]);
                    // For GIFs, we need to use the chunked upload with media_category=tweet_gif
                    // This enables async processing which is required for GIFs
                    try {
                        // Try to use chunked upload for GIFs with proper media category
                        $media_info = $this->uploadGifWithChunkedMethod($localPath);
                        if ($media_info) {
                            Log::info('GIF chunked upload successful', ['media_info' => $media_info]);
                        } else {
                            throw new \Exception('Chunked upload returned null');
                        }
                    } catch (\Exception $e) {
                        Log::warning('GIF chunked upload failed, trying base64', ['error' => $e->getMessage()]);
                        // Fallback to base64 encoding
                        $file_data = base64_encode(file_get_contents($localPath));
                        $media_info = $this->client->uploadMedia()->upload($file_data);
                    }
                } else {
                    // For regular images, use base64 encoding for better compatibility
        $file_data = base64_encode(file_get_contents($localPath));
        $media_info = $this->client->uploadMedia()->upload($file_data);
                }
            }
            
            Log::info('Media upload response', [
                'media_info' => $media_info,
                'media_id' => $media_info['media_id'] ?? 'not_found',
                'is_video' => $isVideo
            ]);
            
            if (isset($media_info['media_id'])) {
                $mediaId = (string)$media_info['media_id'];
                
                // Wait for media processing (Twitter needs time to process the media)
                Log::info('Waiting for media processing', [
                    'media_id' => $mediaId, 
                    'is_video' => $isVideo,
                    'is_gif' => $isGif
                ]);
                
                // Different wait times based on media type
                if ($isVideo) {
                    $waitTime = 10; // Videos need more time
                } elseif ($isGif) {
                    $waitTime = 8; // GIFs need more time than regular images
                } else {
                    $waitTime = 3; // Regular images
                }
                
                sleep($waitTime);
                
                // For GIFs, check processing status before proceeding
                if ($isGif) {
                    Log::info('Checking GIF processing status', ['media_id' => $mediaId]);
                    
                    // Check if GIF meets Twitter's requirements
                    $this->validateGifRequirements($localPath);
                    
                    // For GIFs, wait much longer since Twitter needs time to process animated GIFs
                    $gifWaitTime = 60; // 60 seconds for GIFs (increased from 15)
                    Log::info('Waiting for GIF processing', [
                        'media_id' => $mediaId,
                        'wait_time' => $gifWaitTime
                    ]);
                    
                    sleep($gifWaitTime);
                    
                    // Try to validate the media ID by attempting a status check
                    // This will help us know if the media is ready
                    Log::info('GIF processing wait completed', ['media_id' => $mediaId]);
                    
                    // For GIFs, let's also try a different approach - validate the media ID
                    // by attempting to use it in a test request
                    Log::info('GIF validation completed', ['media_id' => $mediaId]);
                    
                    // Additional wait for GIFs to ensure they're fully processed
                    Log::info('Additional GIF processing wait', ['media_id' => $mediaId]);
                    sleep(30); // Extra 30 seconds for GIF processing
                }
                
                Log::info('Media upload successful', [
                    'media_id' => $mediaId,
                    'media_info' => $media_info,
                    'is_video' => $isVideo,
                    'is_gif' => $isGif
                ]);
                
                return $mediaId;
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Media upload failed', [
                'path' => $localPath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Upload video using manual chunked upload for large files
     */
    private function uploadVideoChunked($localPath)
    {
        // Chunked upload is disabled due to OAuth method availability issues
        Log::warning('Chunked upload disabled - using regular upload instead');
        return null;
    }

    /**
     * Upload GIF using proper chunked method with INIT -> APPEND -> FINALIZE -> STATUS flow
     * and media_category=tweet_gif
     */
    private function uploadGifWithChunkedMethod($gifPath)
    {
        try {
            $fileSize = filesize($gifPath);
            Log::info('Starting GIF chunked upload with tweet_gif media category', [
                'gif_path' => $gifPath,
                'file_size' => $fileSize
            ]);
            
            // Use the proper chunked upload with media_category=tweet_gif
            $media_info = $this->uploadGifWithGuzzle($gifPath);
            
            Log::info('GIF chunked upload successful', [
                'media_info' => $media_info,
                'file_size' => $fileSize
            ]);
            
            return $media_info;
            
        } catch (\Exception $e) {
            Log::error('GIF chunked upload failed', [
                'gif_path' => $gifPath,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Upload GIF using direct OAuth 1.0a chunked upload with media_category=tweet_gif
     * This bypasses the Twitter package entirely for GIF uploads
     */
    private function uploadGifWithGuzzle($gifPath)
    {
        try {
            $fileSize = filesize($gifPath);
            $mediaType = 'image/gif';
            $chunkSize = 4 * 1024 * 1024; // 4 MB
            
            Log::info('Starting direct OAuth 1.0a GIF chunked upload with media_category=tweet_gif', [
                'file_size' => $fileSize,
                'media_type' => $mediaType
            ]);
            
            // Get OAuth credentials
            $user = Auth::user();
            if (!$user || !$user->twitter_account_connected) {
                throw new \Exception('User not authenticated with Twitter');
            }
            
            $oauthSettings = [
                'consumer_key'    => config('services.twitter.api_key'),
                'consumer_secret' => config('services.twitter.api_key_secret'),
                'access_token'    => $user->twitter_access_token,
                'access_token_secret' => $user->twitter_access_token_secret,
            ];
            
            Log::info('OAuth credentials loaded', [
                'consumer_key' => substr($oauthSettings['consumer_key'], 0, 8) . '...',
                'has_access_token' => !empty($oauthSettings['access_token']),
                'has_access_token_secret' => !empty($oauthSettings['access_token_secret'])
            ]);

            // 1. INIT
            $initParams = [
                'command'        => 'INIT',
                'media_type'     => $mediaType,
                'total_bytes'    => $fileSize,
                'media_category' => 'tweet_gif' // 🔑 REQUIRED for GIFs
            ];
            
            $initResponse = $this->makeOAuthRequest('POST', 'https://upload.twitter.com/1.1/media/upload.json', $initParams, $oauthSettings);
            $initData = json_decode($initResponse, true);
            $mediaId = $initData['media_id_string'] ?? null;
            
            if (!$mediaId) {
                throw new \Exception('Failed to INIT GIF upload: ' . json_encode($initData));
            }
            
            Log::info('GIF upload INIT successful', [
                'media_id' => $mediaId,
                'init_data' => $initData
            ]);

            // 2. APPEND
            $handle = fopen($gifPath, 'rb');
            $segmentIndex = 0;
            while (!feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                if ($chunk === false) break;

                $appendParams = [
                    'command' => 'APPEND',
                    'media_id' => $mediaId,
                    'segment_index' => $segmentIndex,
                ];
                
                $this->makeOAuthMultipartRequest('POST', 'https://upload.twitter.com/1.1/media/upload.json', $appendParams, $chunk, $oauthSettings);

                Log::info('GIF chunk uploaded', [
                    'segment_index' => $segmentIndex,
                    'chunk_size' => strlen($chunk)
                ]);

                $segmentIndex++;
            }
            fclose($handle);

            // 3. FINALIZE
            $finalizeParams = [
                'command'  => 'FINALIZE',
                'media_id' => $mediaId
            ];
            
            $finalizeResponse = $this->makeOAuthRequest('POST', 'https://upload.twitter.com/1.1/media/upload.json', $finalizeParams, $oauthSettings);
            $finalizeData = json_decode($finalizeResponse, true);
            
            Log::info('GIF upload FINALIZE successful', [
                'media_id' => $mediaId,
                'finalize_data' => $finalizeData
            ]);

            // 4. If processing_info exists → poll until done
            if (isset($finalizeData['processing_info'])) {
                $state = $finalizeData['processing_info']['state'];
                while ($state === 'in_progress' || $state === 'pending') {
                    sleep($finalizeData['processing_info']['check_after_secs'] ?? 10);
                    
                    $statusParams = [
                        'command'  => 'STATUS',
                        'media_id' => $mediaId
                    ];
                    
                    $statusResponse = $this->makeOAuthRequest('GET', 'https://upload.twitter.com/1.1/media/upload.json', $statusParams, $oauthSettings);
                    $statusData = json_decode($statusResponse, true);
                    $state = $statusData['processing_info']['state'] ?? 'succeeded';

                    Log::info('GIF status check', [
                        'media_id' => $mediaId,
                        'state' => $state,
                        'status_data' => $statusData
                    ]);

                    if ($state === 'failed') {
                        throw new \Exception('GIF processing failed: ' . json_encode($statusData));
                    }
                }
            }

            // Return in the same format as the regular upload method
            return [
                'media_id' => $mediaId,
                'media_id_string' => $mediaId,
                'media_key' => '3_' . $mediaId,
                'size' => $fileSize,
                'expires_after_secs' => 86400,
                'image' => [
                    'image_type' => $mediaType,
                    'w' => 212, // We'll get this from getimagesize if needed
                    'h' => 256
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Direct OAuth GIF chunked upload failed', [
                'gif_path' => $gifPath,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Make OAuth 1.0a request manually (x-www-form-urlencoded body)
     */
    private function makeOAuthRequest($method, $url, $params, $settings)
    {
        $oauthParams = $this->buildOAuthParams($settings);
        $allParams = array_merge($params, $oauthParams);
        
        $signature = $this->buildOAuthSignature($method, $url, $allParams, $settings);
        $allParams['oauth_signature'] = $signature;
        
        $header = $this->buildOAuthHeader($allParams);
        
        $guzzleClient = new GuzzleClient();
        $response = $guzzleClient->request($method, $url, [
            'headers' => [
                'Authorization' => $header,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => $params,
        ]);
        
        return $response->getBody()->getContents();
    }

    /**
     * Make OAuth 1.0a multipart request manually (for media uploads)
     */
    private function makeOAuthMultipartRequest($method, $url, $params, $chunk, $settings)
    {
        $oauthParams = $this->buildOAuthParams($settings);
        $allParams = array_merge($params, $oauthParams);
        
        $signature = $this->buildOAuthSignature($method, $url, $allParams, $settings);
        $allParams['oauth_signature'] = $signature;
        
        $header = $this->buildOAuthHeader($allParams);
        
        $guzzleClient = new GuzzleClient();
        $response = $guzzleClient->request($method, $url, [
            'headers' => [
                'Authorization' => $header,
            ],
            'multipart' => [
                ['name' => 'command', 'contents' => $params['command']],
                ['name' => 'media_id', 'contents' => $params['media_id']],
                ['name' => 'segment_index', 'contents' => $params['segment_index']],
                ['name' => 'media', 'contents' => $chunk],
            ],
        ]);
        
        return $response->getBody()->getContents();
    }

    /**
     * Make OAuth 1.0a request with JSON body (used for DMs).
     * JSON body parameters are NOT included in the signature base string, as per Twitter docs.
     */
    private function makeOAuthJsonRequest($method, $url, array $body, array $settings)
    {
        $oauthParams = $this->buildOAuthParams($settings);

        // Only OAuth params are signed when using JSON body (no query/form params)
        $signature = $this->buildOAuthSignature($method, $url, $oauthParams, $settings);
        $oauthParams['oauth_signature'] = $signature;

        $header = $this->buildOAuthHeader($oauthParams);

        $guzzleClient = new GuzzleClient();
        $response = $guzzleClient->request($method, $url, [
            'headers' => [
                'Authorization' => $header,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => $body,
        ]);

        return $response->getBody()->getContents();
    }

    /**
     * Build OAuth 1.0a parameters
     */
    private function buildOAuthParams($settings)
    {
        return [
            'oauth_consumer_key' => $settings['consumer_key'],
            'oauth_nonce' => uniqid(),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => time(),
            'oauth_token' => $settings['access_token'],
            'oauth_version' => '1.0',
        ];
    }

    /**
     * Build OAuth 1.0a signature
     */
    private function buildOAuthSignature($method, $url, $params, $settings)
    {
        // Sort parameters
        ksort($params);
        
        // Build query string
        $queryString = http_build_query($params);
        
        // Build signature base string
        $signatureBaseString = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($queryString);
        
        // Build signing key
        $signingKey = rawurlencode($settings['consumer_secret']) . '&' . rawurlencode($settings['access_token_secret']);
        
        // Generate signature
        return base64_encode(hash_hmac('sha1', $signatureBaseString, $signingKey, true));
    }

    /**
     * Build OAuth 1.0a header
     */
    private function buildOAuthHeader($params)
    {
        $header = 'OAuth ';
        $headerParams = [];
        
        foreach ($params as $key => $value) {
            if (strpos($key, 'oauth_') === 0) {
                $headerParams[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
            }
        }
        
        $header .= implode(', ', $headerParams);
        return $header;
    }





    /**
     * Validate GIF against Twitter's requirements
     */
    private function validateGifRequirements($gifPath)
    {
        try {
            $fileSize = filesize($gifPath);
            $imageInfo = getimagesize($gifPath);
            
            if ($imageInfo === false) {
                Log::warning('Could not get GIF image info', ['path' => $gifPath]);
                return;
            }
            
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            
            Log::info('GIF validation', [
                'path' => $gifPath,
                'file_size' => $fileSize,
                'width' => $width,
                'height' => $height,
                'size_mb' => round($fileSize / 1024 / 1024, 2)
            ]);
            
            // Check Twitter's requirements
            $issues = [];
            
            // File size <= 15MB
            if ($fileSize > 15 * 1024 * 1024) {
                $issues[] = 'File size exceeds 15MB limit';
            }
            
            // Resolution <= 1280x1080
            if ($width > 1280 || $height > 1080) {
                $issues[] = "Resolution {$width}x{$height} exceeds 1280x1080 limit";
            }
            
            if (!empty($issues)) {
                Log::warning('GIF does not meet Twitter requirements', [
                    'path' => $gifPath,
                    'issues' => $issues
                ]);
            } else {
                Log::info('GIF meets Twitter requirements', ['path' => $gifPath]);
            }
            
        } catch (\Exception $e) {
            Log::warning('Could not validate GIF requirements', [
                'path' => $gifPath,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Convert GIF to static image as fallback
     */
    private function convertGifToStaticImage($gifPath)
    {
        try {
            Log::info('Converting GIF to static image', ['gif_path' => $gifPath]);
            
            // Create a temporary file for the converted image
            $tempPath = tempnam(sys_get_temp_dir(), 'gif_converted_') . '.jpg';
            
            // Use GD to convert GIF to JPEG
            $gif = imagecreatefromgif($gifPath);
            if ($gif === false) {
                throw new \Exception('Failed to create image from GIF');
            }
            
            // Convert to JPEG
            $result = imagejpeg($gif, $tempPath, 90);
            imagedestroy($gif);
            
            if ($result === false) {
                throw new \Exception('Failed to save converted image');
            }
            
            Log::info('GIF converted to static image', [
                'original' => $gifPath,
                'converted' => $tempPath,
                'size' => filesize($tempPath)
            ]);
            
            return $tempPath;
        } catch (\Exception $e) {
            Log::error('Failed to convert GIF to static image', [
                'gif_path' => $gifPath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    // Tweet/Quotes endpoints
    /**
     * Get quote tweets for a tweet.
     */
    public function getQuoteTweets($tweetId)
    {
        return $this->client->tweetQuotes()->getQuoteTweets($tweetId)->performRequest();
    }

    // Retweet endpoints
    /**
     * Retweet a tweet.
     */
    public function retweet($tweetId)
    {
        try {
            return $this->client->retweet()->performRequest(['tweet_id' => $tweetId]);
        } catch (\Exception $e) {
            // Check if it's the "already retweeted" error
            if (strpos($e->getMessage(), 'already retweeted') !== false || 
                strpos($e->getMessage(), 'You cannot retweet a Tweet that you have already retweeted') !== false) {
                // Return a success response for already retweeted tweets
                return (object) ['data' => ['retweeted' => true, 'message' => 'Tweet was already retweeted']];
            }
            
            // Check if it's the "Too Many Requests" error (429)
            if (strpos($e->getMessage(), '429 Too Many Requests') !== false || 
                strpos($e->getMessage(), 'Too Many Requests') !== false) {
                // Return a user-friendly response for rate limiting
                return (object) ['data' => ['retweeted' => false, 'message' => 'Rate limit exceeded. Please wait a moment before retweeting again.']];
            }
            
            // Re-throw other errors
            throw $e;
        }
    }

    /**
     * Like a tweet.
     */
    public function likeTweet($tweetId)
    {
        try {
            Log::info('🔴 API CALL: likeTweet', [
                'tweet_id' => $tweetId,
                'settings_keys' => array_keys($this->settings),
                'has_access_token' => !empty($this->settings['access_token']),
                'account_id' => $this->settings['account_id'] ?? 'not_set',
                'timestamp' => now()->format('Y-m-d H:i:s')
            ]);
            
            // Ensure account_id is set
            if (empty($this->settings['account_id'])) {
                throw new \Exception('Account ID is required for liking tweets');
            }
            
            // Twitter API v2 expects tweet_id as a string
            $tweetIdString = (string) $tweetId;

            /**
             * IMPORTANT: For Twitter API v2, the like endpoint is:
             *   POST /2/users/:id/likes
             * with JSON body: { "tweet_id": "..." }
             *
             * The Noweh client already knows the user (from auth/account_id),
             * so we should NOT pass the tweet ID as a positional argument.
             * Instead, we must always send it in the request body as "tweet_id".
             */

            Log::info('Attempting like with tweet_id in request body', [
                'tweet_id'   => $tweetIdString,
                'account_id' => $this->settings['account_id'],
            ]);

            // Correct pattern: send tweet_id in the body
            return $this->client
                ->tweetLikes()
                ->like()
                ->performRequest(['tweet_id' => $tweetIdString]);
            
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            
            // Read the body and rewind it so it can be read again if needed
            $body = $response->getBody()->getContents();
            $response->getBody()->rewind();
            
            // Parse the JSON body to get the full error detail
            $errorData = json_decode($body, true);
            
            // Extract full error message
            $fullErrorDetail = $errorData['detail'] ?? null;
            $errorTitle = $errorData['title'] ?? null;
            $errorType = $errorData['type'] ?? null;
            $clientId = $errorData['client_id'] ?? null;
            $registrationUrl = $errorData['registration_url'] ?? null;
            
            Log::error('❌ likeTweet ClientException - Full Error Details', [
                'tweet_id' => $tweetId,
                'status_code' => $statusCode,
                'error_body' => $body,
                'error_detail' => $fullErrorDetail,
                'error_title' => $errorTitle,
                'error_type' => $errorType,
                'client_id' => $clientId,
                'registration_url' => $registrationUrl,
                'settings_keys' => array_keys($this->settings),
                'has_all_required' => !empty($this->settings['consumer_key']) && 
                                       !empty($this->settings['consumer_secret']) &&
                                       !empty($this->settings['access_token']) &&
                                       !empty($this->settings['access_token_secret']),
                'access_token_length' => strlen($this->settings['access_token'] ?? ''),
                'access_token_secret_length' => strlen($this->settings['access_token_secret'] ?? ''),
                'account_id' => $this->settings['account_id'] ?? 'not_set'
            ]);
            
            // Check for client-not-enrolled error specifically
            if ($statusCode === 403 && ($errorTitle === 'Client Forbidden' || strpos($fullErrorDetail ?? '', 'client-not-enrolled') !== false || strpos($fullErrorDetail ?? '', 'When authenticating requests to the Twitter API v2 endpoints') !== false)) {
                $userMessage = "Twitter API configuration error: Your app needs to be attached to a Project in the Twitter Developer Portal. ";
                if ($registrationUrl) {
                    $userMessage .= "Visit: " . $registrationUrl;
                } else {
                    $userMessage .= "Go to https://developer.twitter.com/en/portal/dashboard and ensure your app is attached to a Project.";
                }
                
                return (object) [
                    'data' => [
                        'liked' => false, 
                        'message' => $userMessage
                    ], 
                    'error' => $fullErrorDetail ?? 'Client not enrolled'
                ];
            }
            
            $errorMessage = $fullErrorDetail ?? $body ?: $e->getMessage();
            
            // Return immediately with the parsed error detail
            return (object) [
                'data' => [
                    'liked' => false, 
                    'message' => "Twitter API error: " . $errorMessage
                ], 
                'error' => $errorMessage
            ];
            
        } catch (\Exception $e) {
            // Try to extract the full error message from nested exceptions
            $errorMessage = $e->getMessage();
            $fullErrorBody = null;
            $errorData = null;
            $statusCode = null;
            
            // If it's a RuntimeException wrapping a ClientException, try to get the response body
            if ($e instanceof \RuntimeException && $e->getPrevious() instanceof \GuzzleHttp\Exception\ClientException) {
                try {
                    $previous = $e->getPrevious();
                    $response = $previous->getResponse();
                    $statusCode = $response->getStatusCode();
                    
                    // Read the body and rewind it
                    $fullErrorBody = $response->getBody()->getContents();
                    $response->getBody()->rewind();
                    
                    $errorData = json_decode($fullErrorBody, true);
                    
                    // Extract full error details
                    $fullErrorDetail = $errorData['detail'] ?? null;
                    $errorTitle = $errorData['title'] ?? null;
                    $clientId = $errorData['client_id'] ?? null;
                    $registrationUrl = $errorData['registration_url'] ?? null;
                    
                    if ($fullErrorDetail) {
                        $errorMessage = $fullErrorDetail;
                    }
                    
                    Log::error('❌ likeTweet General Error (from wrapped ClientException)', [
                        'tweet_id' => $tweetId,
                        'status_code' => $statusCode,
                        'error_body' => $fullErrorBody,
                        'error_detail' => $fullErrorDetail,
                        'error_title' => $errorTitle,
                        'client_id' => $clientId,
                        'registration_url' => $registrationUrl,
                        'error_message' => $errorMessage,
                        'class' => get_class($e),
                        'previous_class' => get_class($previous)
                    ]);
                    
                    // Check for client-not-enrolled error specifically
                    if ($statusCode === 403 && ($errorTitle === 'Client Forbidden' || strpos($fullErrorDetail ?? '', 'client-not-enrolled') !== false || strpos($fullErrorDetail ?? '', 'When authenticating requests to the Twitter API v2 endpoints') !== false)) {
                        $userMessage = "Twitter API configuration error: Your app needs to be attached to a Project in the Twitter Developer Portal. ";
                        if ($registrationUrl) {
                            $userMessage .= "Visit: " . $registrationUrl;
                        } else {
                            $userMessage .= "Go to https://developer.twitter.com/en/portal/dashboard and ensure your app is attached to a Project. The error indicates your app (client_id: {$clientId}) is not enrolled for API v2 endpoints.";
                        }
                        
                        return (object) [
                            'data' => [
                                'liked' => false, 
                                'message' => $userMessage
                            ], 
                            'error' => $fullErrorDetail ?? 'Client not enrolled'
                        ];
                    }
                    
                } catch (\Exception $ex) {
                    // If we can't extract, use the original message
                    Log::warning('Failed to extract error from wrapped exception', [
                        'error' => $ex->getMessage()
                    ]);
                }
            }
            
            // If we didn't extract from wrapped exception, log the original
            if (!$fullErrorBody) {
                Log::error('❌ likeTweet General Error', [
                    'tweet_id' => $tweetId,
                    'error' => $errorMessage,
                    'full_error_body' => $fullErrorBody,
                    'class' => get_class($e),
                    'previous_class' => $e->getPrevious() ? get_class($e->getPrevious()) : null,
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            // Extract user-friendly error from response
            $userMessage = "Failed to like tweet";
            
            if (strpos($errorMessage, '403 Forbidden') !== false || strpos($errorMessage, 'client-not-enrolled') !== false || strpos($errorMessage, 'When authenticating requests to the Twitter API v2 endpoints') !== false) {
                $userMessage = "Twitter API configuration error: Your app needs to be attached to a Project in the Twitter Developer Portal. Go to https://developer.twitter.com/en/portal/dashboard and ensure your app is attached to a Project.";
            } elseif (strpos($errorMessage, '429 Too Many Requests') !== false || strpos($errorMessage, 'Too Many Requests') !== false) {
                $userMessage = 'Rate limit exceeded. Please wait a moment before liking again.';
            } elseif (strpos($errorMessage, '401') !== false) {
                $userMessage = "Twitter authentication failed. Please reconnect your Twitter account in settings.";
            } elseif (strpos($errorMessage, '404') !== false) {
                $userMessage = "Tweet not found. It may have been deleted.";
            }
            
            // Return error instead of throwing
            return (object) ['data' => ['liked' => false, 'message' => $userMessage], 'error' => $userMessage];
        }
    }

    // Tweet/Replies endpoints
    /**
     * Hide a reply to a tweet.
     */
    public function hideReply($tweetId)
    {
        return $this->client->tweetReplies()->hideReply($tweetId)->performRequest(['hidden' => true]);
    }

    /**
     * Unhide a reply to a tweet.
     */
    public function unhideReply($tweetId)
    {
        return $this->client->tweetReplies()->hideReply($tweetId)->performRequest(['hidden' => false]);
    }

    // Tweet/Bookmarks endpoints
    /**
     * Lookup a user's bookmarks.
     */
    public function getBookmarks($userId = null)
    {
        if ($userId) {
            return $this->client->tweetBookmarks()->lookup($userId)->performRequest();
        }
        return $this->client->tweetBookmarks()->lookup()->performRequest();
    }

    /**
     * Add a bookmark to a tweet.
     */
    public function addBookmark($tweetId)
    {
        $user = Auth::user();
        if (!$user || !$user->twitter_account_id) {
            throw new \Exception('User not authenticated or Twitter account not connected');
        }

        // Bookmarks require OAuth 2.0 which is not available with current setup
        throw new \Exception('Bookmark functionality requires OAuth 2.0 authorization. This feature is not available with your current Twitter API access level. Please upgrade your Twitter API access or contact support.');
    }

    /**
     * Remove a bookmark from a tweet.
     */
    public function removeBookmark($tweetId)
    {
        $user = Auth::user();
        if (!$user || !$user->twitter_account_id) {
            throw new \Exception('User not authenticated or Twitter account not connected');
        }

        // Bookmarks require OAuth 2.0 which is not available with current setup
        throw new \Exception('Bookmark functionality requires OAuth 2.0 authorization. This feature is not available with your current Twitter API access level. Please upgrade your Twitter API access or contact support.');
    }

    // Users endpoints
    // User/Blocks endpoints
    /**
     * Retrieve the users which you've blocked.
     */
    public function getBlockedUsers()
    {
        // Get the authenticated user's ID first
        $me = $this->findMe();
        if (!$me || !isset($me->data->id)) {
            throw new \Exception('Unable to get authenticated user ID');
        }
        
        $userId = $me->data->id;
        return $this->getBlockedUsersDirect($userId);
    }

    private function getBlockedUsersDirect($userId)
    {
        $bearerToken = $this->settings['bearer_token'] ?? config('services.twitter.bearer_token');
        
        if (!$bearerToken) {
            throw new \Exception('Bearer token is required for this endpoint');
        }

        $url = "https://api.twitter.com/2/users/{$userId}/blocking";
        $params = [
            'max_results' => 100,
            'user.fields' => 'description,public_metrics,profile_image_url,verified'
        ];

        $client = new \GuzzleHttp\Client();
        
        try {
            $response = $client->get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $bearerToken,
                    'Accept' => 'application/json',
                ],
                'query' => $params
            ]);

            return json_decode($response->getBody()->getContents());
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $errorBody = $e->getResponse()->getBody()->getContents();
            throw new \Exception('Twitter API error: ' . $errorBody);
        }
    }

    // User/Follows endpoints
    /**
     * Retrieve the users which are following you.
     */
    public function getFollowers()
    {
        // Get the authenticated user's ID first
        $me = $this->findMe();
        if (!$me || !isset($me->data->id)) {
            throw new \Exception('Unable to get authenticated user ID');
        }
        
        $userId = $me->data->id;
        return $this->getFollowersDirect($userId);
    }

    private function getFollowersDirect($userId)
    {
        $bearerToken = $this->settings['bearer_token'] ?? config('services.twitter.bearer_token');
        
        if (!$bearerToken) {
            throw new \Exception('Bearer token is required for this endpoint');
        }

        // Try API v2 first
        try {
            $url = "https://api.twitter.com/2/users/{$userId}/followers";
            $params = [
                'max_results' => 100,
                'user.fields' => 'description,public_metrics,profile_image_url,verified'
            ];

            $client = new \GuzzleHttp\Client();
            
            $response = $client->get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $bearerToken,
                    'Accept' => 'application/json',
                ],
                'query' => $params
            ]);

            // Track rate limit from response headers
            $rateLimitKey = 'twitter_rate_limit_followers_' . md5($this->settings['consumer_key'] ?? 'default');
            if ($response->hasHeader('x-rate-limit-reset')) {
                $resetTime = (int) $response->getHeaderLine('x-rate-limit-reset');
                $remaining = $response->hasHeader('x-rate-limit-remaining') ? (int) $response->getHeaderLine('x-rate-limit-remaining') : 0;
                $limit = $response->hasHeader('x-rate-limit-limit') ? (int) $response->getHeaderLine('x-rate-limit-limit') : 0;
                
                \Illuminate\Support\Facades\Cache::put($rateLimitKey, [
                    'reset_time' => $resetTime,
                    'reset_datetime' => date('Y-m-d H:i:s', $resetTime),
                    'remaining' => $remaining,
                    'limit' => $limit
                ], 900); // Cache for 15 minutes
            }

            return json_decode($response->getBody()->getContents());
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $errorBody = $response->getBody()->getContents();
            $errorData = json_decode($errorBody, true);
            
            // Track rate limit from error response headers
            if ($statusCode === 429) {
                $rateLimitKey = 'twitter_rate_limit_followers_' . md5($this->settings['consumer_key'] ?? 'default');
                if ($response->hasHeader('x-rate-limit-reset')) {
                    $resetTime = (int) $response->getHeaderLine('x-rate-limit-reset');
                    $remaining = $response->hasHeader('x-rate-limit-remaining') ? (int) $response->getHeaderLine('x-rate-limit-remaining') : 0;
                    $limit = $response->hasHeader('x-rate-limit-limit') ? (int) $response->getHeaderLine('x-rate-limit-limit') : 0;
                    
                    \Illuminate\Support\Facades\Cache::put($rateLimitKey, [
                        'reset_time' => $resetTime,
                        'reset_datetime' => date('Y-m-d H:i:s', $resetTime),
                        'remaining' => $remaining,
                        'limit' => $limit
                    ], 900);
                }
            }
            
            // Check if it's a client-not-enrolled error
            if (isset($errorData['reason']) && $errorData['reason'] === 'client-not-enrolled') {
                throw new \Exception('Twitter API Setup Required: ' . $errorData['detail'] . ' Visit: ' . ($errorData['registration_url'] ?? 'https://developer.twitter.com'));
            }
            
            throw new \Exception('Twitter API error: ' . $errorBody);
        }
    }

    /**
     * Retrieve the users which you are following.
     */
    public function getFollowing()
    {
        // Get the authenticated user's ID first
        $me = $this->findMe();
        if (!$me || !isset($me->data->id)) {
            throw new \Exception('Unable to get authenticated user ID');
        }
        
        $userId = $me->data->id;
        return $this->getFollowingDirect($userId);
    }

    private function getFollowingDirect($userId)
    {
        $bearerToken = $this->settings['bearer_token'] ?? config('services.twitter.bearer_token');
        
        if (!$bearerToken) {
            throw new \Exception('Bearer token is required for this endpoint');
        }

        $url = "https://api.twitter.com/2/users/{$userId}/following";
        $params = [
            'max_results' => 100,
            'user.fields' => 'description,public_metrics,profile_image_url,verified'
        ];

        $client = new \GuzzleHttp\Client();
        
        try {
            $response = $client->get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $bearerToken,
                    'Accept' => 'application/json',
                ],
                'query' => $params
            ]);

            // Track rate limit from response headers
            $rateLimitKey = 'twitter_rate_limit_followers_' . md5($this->settings['consumer_key'] ?? 'default');
            if ($response->hasHeader('x-rate-limit-reset')) {
                $resetTime = (int) $response->getHeaderLine('x-rate-limit-reset');
                $remaining = $response->hasHeader('x-rate-limit-remaining') ? (int) $response->getHeaderLine('x-rate-limit-remaining') : 0;
                $limit = $response->hasHeader('x-rate-limit-limit') ? (int) $response->getHeaderLine('x-rate-limit-limit') : 0;
                
                \Illuminate\Support\Facades\Cache::put($rateLimitKey, [
                    'reset_time' => $resetTime,
                    'reset_datetime' => date('Y-m-d H:i:s', $resetTime),
                    'remaining' => $remaining,
                    'limit' => $limit
                ], 900); // Cache for 15 minutes
            }

            return json_decode($response->getBody()->getContents());
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $errorBody = $response->getBody()->getContents();
            
            // Track rate limit from error response headers
            if ($statusCode === 429) {
                $rateLimitKey = 'twitter_rate_limit_followers_' . md5($this->settings['consumer_key'] ?? 'default');
                if ($response->hasHeader('x-rate-limit-reset')) {
                    $resetTime = (int) $response->getHeaderLine('x-rate-limit-reset');
                    $remaining = $response->hasHeader('x-rate-limit-remaining') ? (int) $response->getHeaderLine('x-rate-limit-remaining') : 0;
                    $limit = $response->hasHeader('x-rate-limit-limit') ? (int) $response->getHeaderLine('x-rate-limit-limit') : 0;
                    
                    \Illuminate\Support\Facades\Cache::put($rateLimitKey, [
                        'reset_time' => $resetTime,
                        'reset_datetime' => date('Y-m-d H:i:s', $resetTime),
                        'remaining' => $remaining,
                        'limit' => $limit
                    ], 900);
                }
            }
            
            throw new \Exception('Twitter API error: ' . $errorBody);
        }
    }

    /**
     * Follow a user.
     */
    public function followUser($userId)
    {
        // Get the authenticated user's ID first
        $me = $this->findMe();
        if (!$me || !isset($me->data->id)) {
            throw new \Exception('Unable to get authenticated user ID');
        }
        
        $sourceUserId = $me->data->id;
        return $this->client->userFollows()->follow($sourceUserId)->performRequest(['target_user_id' => $userId]);
    }

    /**
     * Unfollow a user.
     */
    public function unfollowUser($userId)
    {
        // Get the authenticated user's ID first
        $me = $this->findMe();
        if (!$me || !isset($me->data->id)) {
            throw new \Exception('Unable to get authenticated user ID');
        }
        
        $sourceUserId = $me->data->id;
        return $this->client->userFollows()->unfollow($sourceUserId)->performRequest(['target_user_id' => $userId]);
    }

    // User/Lookup endpoints
    /**
     * Find me (the authenticated user).
     */
    public function findMe()
    {
        return $this->client->userMeLookup()->performRequest();
    }

    /**
     * Find Twitter users by username or ID.
     */
    public function findUser($value, $mode)
    {
        return $this->client->userLookup()->findByIdOrUsername($value, $mode)->performRequest();
    }

    // User/Mutes endpoints
    /**
     * Retrieve the users which you've muted.
     */
    public function getMutedUsers()
    {
        // Get the authenticated user's ID first
        $me = $this->findMe();
        if (!$me || !isset($me->data->id)) {
            throw new \Exception('Unable to get authenticated user ID');
        }
        
        $userId = $me->data->id;
        return $this->getMutedUsersDirect($userId);
    }

    private function getMutedUsersDirect($userId)
    {
        $bearerToken = $this->settings['bearer_token'] ?? config('services.twitter.bearer_token');
        
        if (!$bearerToken) {
            throw new \Exception('Bearer token is required for this endpoint');
        }

        $url = "https://api.twitter.com/2/users/{$userId}/muting";
        $params = [
            'max_results' => 100,
            'user.fields' => 'description,public_metrics,profile_image_url,verified'
        ];

        $client = new \GuzzleHttp\Client();
        
        try {
            $response = $client->get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $bearerToken,
                    'Accept' => 'application/json',
                ],
                'query' => $params
            ]);

            return json_decode($response->getBody()->getContents());
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $errorBody = $e->getResponse()->getBody()->getContents();
            throw new \Exception('Twitter API error: ' . $errorBody);
        }
    }

    /**
     * Mute a user by ID.
     */
    public function muteUser($userId)
    {
        // Get the authenticated user's ID first
        $me = $this->findMe();
        if (!$me || !isset($me->data->id)) {
            throw new \Exception('Unable to get authenticated user ID');
        }
        
        $sourceUserId = $me->data->id;
        return $this->client->userMutes()->mute($sourceUserId)->performRequest(['target_user_id' => $userId]);
    }

    /**
     * Unmute a user by ID.
     */
    public function unmuteUser($userId)
    {
        // Get the authenticated user's ID first
        $me = $this->findMe();
        if (!$me || !isset($me->data->id)) {
            throw new \Exception('Unable to get authenticated user ID');
        }
        
        $sourceUserId = $me->data->id;
        return $this->client->userMutes()->unmute($sourceUserId)->performRequest(['target_user_id' => $userId]);
    }

    // User/Blocks endpoints
    /**
     * Block a user by ID.
     */
    public function blockUser($userId)
    {
        // Get the authenticated user's ID first
        $me = $this->findMe();
        if (!$me || !isset($me->data->id)) {
            throw new \Exception('Unable to get authenticated user ID');
        }
        
        $sourceUserId = $me->data->id;
        return $this->client->userBlocks()->block($sourceUserId)->performRequest(['target_user_id' => $userId]);
    }

    /**
     * Unblock a user by ID.
     */
    public function unblockUser($userId)
    {
        // Get the authenticated user's ID first
        $me = $this->findMe();
        if (!$me || !isset($me->data->id)) {
            throw new \Exception('Unable to get authenticated user ID');
        }
        
        $sourceUserId = $me->data->id;
        return $this->client->userBlocks()->unblock($sourceUserId)->performRequest(['target_user_id' => $userId]);
    }

    /**
     * Truncate text to fit Twitter's character limit
     */
    public function truncateForTwitter($text, $maxLength = 280)
    {
        $charCount = mb_strlen($text, 'UTF-8');
        
        if ($charCount <= $maxLength) {
            return $text;
        }
        
        // Truncate and add ellipsis
        $truncated = mb_substr($text, 0, $maxLength - 3, 'UTF-8') . '...';
        
        Log::info('Text truncated for Twitter', [
            'original_length' => $charCount,
            'truncated_length' => mb_strlen($truncated, 'UTF-8'),
            'max_length' => $maxLength
        ]);
        
        return $truncated;
    }

    /**
     * Check Twitter API status and rate limits
     */
    public function checkApiStatus()
    {
        try {
            // Try to get user info to check API status
            $result = $this->client->userMeLookup()->performRequest();
            
            Log::info('Twitter API status check successful', [
                'user_id' => $result->data->id ?? 'unknown',
                'username' => $result->data->username ?? 'unknown'
            ]);
            
            return [
                'status' => 'ok',
                'user_id' => $result->data->id ?? null,
                'username' => $result->data->username ?? null,
                'message' => 'API is working correctly'
            ];
            
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $body = $e->getResponse()->getBody()->getContents();
            
            Log::error('Twitter API status check failed', [
                'status_code' => $statusCode,
                'error_body' => $body
            ]);
            
            if ($statusCode === 429) {
                return [
                    'status' => 'rate_limited',
                    'message' => 'Rate limit exceeded. Please wait before making more requests.',
                    'status_code' => $statusCode
                ];
            }
            
            return [
                'status' => 'error',
                'message' => "HTTP {$statusCode}: {$e->getMessage()}",
                'status_code' => $statusCode
            ];
            
        } catch (\Exception $e) {
            Log::error('Twitter API status check unexpected error', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'error',
                'message' => "Unexpected error: {$e->getMessage()}"
            ];
        }
    }

    /**
     * Get character count for text
     */
    public function getCharacterCount($text)
    {
        return mb_strlen($text, 'UTF-8');
    }

    /**
     * Stream tweets in real-time using Twitter Streaming API
     * This is much more efficient than polling and doesn't count toward normal rate limits
     */
    public function streamMentions($username, callable $callback, $timeout = 30)
    {
        try {
            if (empty($this->bearerToken)) {
                throw new \Exception('Bearer token required for streaming');
            }

            $url = 'https://api.twitter.com/2/tweets/search/stream';
            
            // Build stream rules/rules
            $params = [
                'tweet.fields' => 'created_at,author_id,public_metrics',
                'expansions' => 'author_id',
                'user.fields' => 'name,username,profile_image_url'
            ];

            $streamUrl = $url . '?' . http_build_query($params);

            Log::info('Starting Twitter stream', [
                'url' => $streamUrl,
                'timeout' => $timeout
            ]);

            // Create stream connection
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $streamUrl,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->bearerToken,
                    'Content-Type: application/json',
                ],
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
            ]);

            // Set up stream reading
            $handle = fopen('php://temp', 'w+');
            curl_setopt($ch, CURLOPT_FILE, $handle);
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use ($callback) {
                // Twitter streams JSON lines - each line is a separate JSON object
                $lines = explode("\n", $data);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    try {
                        $tweet = json_decode($line, true);
                        if ($tweet && isset($tweet['data'])) {
                            $callback($tweet);
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to parse stream tweet', ['error' => $e->getMessage()]);
                    }
                }
                return strlen($data);
            });

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($httpCode !== 200) {
                $error = curl_error($ch);
                Log::error('Stream error', ['http_code' => $httpCode, 'error' => $error]);
                throw new \Exception("Stream failed: HTTP {$httpCode}");
            }

            curl_close($ch);
            fclose($handle);

        } catch (\Exception $e) {
            Log::error('Twitter stream error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
} 