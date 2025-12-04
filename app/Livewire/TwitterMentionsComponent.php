<?php

namespace App\Livewire;

use App\Models\TwitterAccount;
use App\Models\User;
use App\Models\AutoReply;
use App\Services\ChatGptService;
use App\Services\TwitterService;
use App\Jobs\ProcessMentionAutoReplies;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
    public $isRateLimited = false;
    public $rateLimitResetTime = '';
    public $rateLimitWaitMinutes = 0;
    public $autoReplyEnabled = false;
    public $twitterAccountConfigured = false;
    /**
     * IDs of mentions that have already been auto-replied by AI (for UI badge).
     *
     * @var array<int, string>
     */
    public $autoRepliedIds = [];

    public function mount()
    {
        // Don't load immediately - let page load first, then user can click to load
        // This prevents page delay when opening
        Log::info('Page mounted - skipping immediate API call to prevent delay');
        
        // Load TwitterAccount status
        $user = Auth::user();
        if ($user) {
            $twitterAccount = TwitterAccount::where('user_id', $user->id)->first();
            $this->twitterAccountConfigured = $twitterAccount && $twitterAccount->isConfigured();
            $this->autoReplyEnabled = $twitterAccount && $twitterAccount->isAutoEnabled();
        }
        
        // Check rate limit status on mount
        $this->checkRateLimitStatus();
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
            $rateLimitCheck = $twitterService->isRateLimitedForMentions();
            
            Log::info('🔍 Rate limit status check', [
                'rate_limited' => $rateLimitCheck['rate_limited'] ?? false,
                'reset_time' => $rateLimitCheck['reset_time'] ?? 'none',
                'wait_minutes' => $rateLimitCheck['wait_minutes'] ?? 0
            ]);
            
            if ($rateLimitCheck['rate_limited']) {
                $this->isRateLimited = true;
                $this->rateLimitResetTime = $rateLimitCheck['reset_time'];
                $this->rateLimitWaitMinutes = $rateLimitCheck['wait_minutes'];
                Log::warning('🚫 Rate limit ACTIVE - UI updated', [
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

    public function loadMentions($forceRefresh = false)
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

        // Define cache key for use throughout the method
        $cacheKey = "twitter_mentions_{$user->id}";

        // Check cache first (only if not forcing refresh)
        if (!$forceRefresh) {
            $cachedMentions = \Illuminate\Support\Facades\Cache::get($cacheKey);
            
            if ($cachedMentions) {
                Log::info('✅ CACHE HIT: Mentions loaded from cache (NO API CALL)', [
                    'cache_key' => $cacheKey,
                    'mentions_count' => count($cachedMentions['data'] ?? []),
                    'last_updated' => $cachedMentions['timestamp'] ?? 'unknown',
                    'timestamp' => now()->format('Y-m-d H:i:s')
                ]);
                $this->mentions = $cachedMentions['data'] ?? [];
                $this->users = $cachedMentions['users'] ?? [];
                
                // Filter out duplicate mentions by content before displaying
                $this->deduplicateMentionsByContent();
                
                $this->lastRefresh = $cachedMentions['timestamp'] ?? now()->format('M j, Y g:i A');
                $this->currentPage = 1; // Reset to first page when loading from cache
                $this->successMessage = 'Mentions loaded from cache (updated ' . \Carbon\Carbon::parse($this->lastRefresh)->diffForHumans() . '). Click Sync for fresh data.';
                
                // Update local cache of which mentions have been auto-replied already
                $this->loadAutoRepliedIds($user);
                
                // Trigger AI auto-replies even when loading from cache
                $mentionsCount = count($this->mentions);
                $this->triggerAutoReplyIfEnabled($user, $mentionsCount);
                
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
            
            // Filter out duplicate mentions by content before displaying
            $this->deduplicateMentionsByContent();
            
            $this->lastRefresh = now()->format('M j, Y g:i A');
            $this->currentPage = 1; // Reset to first page when new mentions are loaded

            $mentionsCount = count($this->mentions);
            Log::info('Fresh mentions loaded from Twitter API', ['mentions_count' => $mentionsCount, 'force_refresh' => $forceRefresh]);
            
            if ($mentionsCount > 0) {
                $this->successMessage = "Mentions loaded successfully! Found {$mentionsCount} fresh mentions.";
            } else {
                $this->successMessage = "No mentions found. This could mean no one has mentioned you recently, or your account hasn't been mentioned yet.";
            }

            // Update local cache of which mentions have been auto-replied already
            $this->loadAutoRepliedIds($user);

            // Trigger AI auto-replies in the background if enabled
            $this->triggerAutoReplyIfEnabled($user, $mentionsCount);

            // Cache the results for 4 hours to drastically reduce API calls
            \Illuminate\Support\Facades\Cache::put($cacheKey, [
                'data' => $this->mentions,
                'users' => $this->users,
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
            
            Log::error('Twitter API Client Error - Mentions', [
                'status_code' => $statusCode,
                'rate_limit_reset' => $rateLimitReset ? date('Y-m-d H:i:s', $rateLimitReset) : null,
                'response_body' => $responseBody
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
                    
                    Log::warning('🚫 Rate limit detected from 429 response', [
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
            } else {
                $this->errorMessage = "Failed to load mentions: HTTP {$statusCode}. Please try again later.";
            }
        } catch (\Exception $e) {
            Log::error('Unexpected error loading mentions', [
                'error' => $e->getMessage(),
                'class' => get_class($e)
            ]);
            
            // Check if error message contains rate limit info
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, '429 Too Many Requests') !== false || 
                strpos($errorMessage, 'Rate limit') !== false ||
                strpos($errorMessage, 'Rate limit active') !== false) {
                
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
                    $rateLimitCheck = $twitterService->isRateLimitedForMentions();
                    
                    if ($rateLimitCheck['rate_limited']) {
                        // Use cache data (most reliable)
                        $this->isRateLimited = true;
                        $this->rateLimitResetTime = $rateLimitCheck['reset_time'];
                        $this->rateLimitWaitMinutes = $rateLimitCheck['wait_minutes'];
                        $this->errorMessage = "Rate limit exceeded. Please wait {$rateLimitCheck['wait_minutes']} minute(s) before trying again. Reset time: {$rateLimitCheck['reset_time']}";
                        
                        Log::warning('🚫 Rate limit detected from cache after exception', [
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
                        
                        Log::warning('🚫 Rate limit detected from exception message', [
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
                
                // Remove "Client error:" prefix
                $cleanMessage = preg_replace('/Client error:\s*/i', '', $cleanMessage);
                
                // Remove "resulted in a" pattern
                $cleanMessage = preg_replace('/resulted in a\s+\d+\s+\w+\s+response:.*$/i', '', $cleanMessage);
                
                // Trim and clean up
                $cleanMessage = trim($cleanMessage);
                
                // If message is still too technical or empty, use generic message
                if (empty($cleanMessage) || strlen($cleanMessage) > 200 || strpos($cleanMessage, 'GET https://') !== false) {
                    $this->errorMessage = 'Failed to load mentions. Please try again later or contact support if the problem persists.';
                } else {
                    $this->errorMessage = 'Failed to load mentions: ' . $cleanMessage;
                }
            }
        }

        $this->loading = false;
        $this->isLoading = false;
    }

    /**
     * Automatically reply to new mentions using AI, ensuring we don't reply twice.
     */
    protected function handleAutoRepliesForMentions(User $user): void
    {
        try {
            $chatGptService = app(ChatGptService::class);

            // Safety: limit how many auto replies we send per load to avoid rate limits
            $maxAutoRepliesPerLoad = 5;
            $autoRepliesSent = 0;
            $skippedNoQuestion = 0;
            $skippedSelf = 0;
            $skippedAlready = 0;

            Log::info('🤖 Starting AI auto-reply pass for mentions', [
                'user_id' => $user->id,
                'mentions_count' => count($this->mentions),
                'max_auto_replies_per_load' => $maxAutoRepliesPerLoad,
            ]);

            foreach ($this->mentions as $mention) {
                if ($autoRepliesSent >= $maxAutoRepliesPerLoad) {
                    break;
                }

                $mentionId = is_object($mention) ? ($mention->id ?? null) : ($mention['id'] ?? null);
                $mentionText = is_object($mention) ? ($mention->text ?? '') : ($mention['text'] ?? '');
                $authorId = is_object($mention) ? ($mention->author_id ?? null) : ($mention['author_id'] ?? null);

                if (!$mentionId || !$mentionText) {
                    continue;
                }

                // Simple safeguard: only reply to question-like / engaged tweets
                if (strpos($mentionText, '?') === false) {
                    $skippedNoQuestion++;
                    continue;
                }

                // Don't auto-reply to our own tweets
                if ($authorId && $user->twitter_account_id && (string) $authorId === (string) $user->twitter_account_id) {
                    $skippedSelf++;
                    continue;
                }

                // Skip if we've already auto-replied to this mention
                $alreadyReplied = AutoReply::where('user_id', $user->id)
                    ->where('tweet_id', (string) $mentionId)
                    ->where('source_type', 'mention')
                    ->exists();

                if ($alreadyReplied) {
                    $skippedAlready++;
                    continue;
                }

                // Build a prompt for a short, positive, thoughtful reply tailored to THIS user's brand
                $brandName = $user->twitter_name ?: $user->name ?: 'your personal brand';
                $handleUsername = ltrim((string) $user->twitter_username, '@');
                $handle = $handleUsername ? '@' . $handleUsername : '@brand';
                $prompt = "You are helping {$brandName} reply on Twitter (X) as {$handle}.\n"
                    . "Write ONE short, human, warm, and genuinely helpful reply (max 220 characters) to this tweet.\n"
                    . "Tone: positive, respectful, and professional. No slang, no sarcasm, no negativity.\n"
                    . "Do NOT include hashtags, links, or emojis unless absolutely necessary. Avoid sounding like AI.\n"
                    . "If the tweet is offensive, political, or unsafe, politely decline instead of engaging.\n\n"
                    . "Tweet content:\n\"{$mentionText}\"";

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

                Log::info('🤖 Sending AI auto-reply to mention', [
                    'user_id' => $user->id,
                    'tweet_id' => $mentionId,
                ]);

                $response = $twitterService->createTweet(
                    $replyText,
                    [],
                    $mentionId
                );

                if ($response && isset($response->data)) {
                    AutoReply::create([
                        'user_id' => $user->id,
                        'tweet_id' => (string) $mentionId,
                        'source_type' => 'mention',
                        'original_text' => $mentionText,
                        'reply_text' => $replyText,
                        'replied_at' => now(),
                    ]);

                    $autoRepliesSent++;
                    $this->autoRepliedIds[] = (string) $mentionId;
                }
            }

            Log::info('🤖 Finished AI auto-reply pass for mentions', [
                'user_id' => $user->id,
                'auto_replies_sent' => $autoRepliesSent,
                'skipped_no_question' => $skippedNoQuestion,
                'skipped_self_mentions' => $skippedSelf,
                'skipped_already_replied' => $skippedAlready,
            ]);

            if ($autoRepliesSent > 0) {
                $this->dispatch('show-success', "{$autoRepliesSent} mention(s) auto-replied with AI.");
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle AI auto-replies for mentions', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Filter out duplicate mentions by text content (normalizes and deduplicates)
     */
    protected function deduplicateMentionsByContent(): void
    {
        $uniqueMentions = [];
        $uniqueUsers = [];
        $seenTextHashes = [];
        $duplicateCount = 0;
        
        foreach ($this->mentions as $mention) {
            $mentionId = is_object($mention) ? ($mention->id ?? null) : ($mention['id'] ?? null);
            $mentionText = is_object($mention) ? ($mention->text ?? '') : ($mention['text'] ?? '');
            
            // Normalize text: trim, lowercase, remove RT prefix, normalize whitespace
            $normalizedText = mb_strtolower(trim($mentionText));
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
            $seenTextHashes[$textHash] = $mentionId;
            $uniqueMentions[] = $mention;
            
            // Also track unique users if available
            if (!empty($this->users)) {
                $authorId = is_object($mention) ? ($mention->author_id ?? null) : ($mention['author_id'] ?? null);
                if ($authorId) {
                    foreach ($this->users as $user) {
                        $userId = is_object($user) ? ($user->id ?? null) : ($user['id'] ?? null);
                        if ($userId && (string) $userId === (string) $authorId) {
                            // Only add user if not already in uniqueUsers
                            $userExists = false;
                            foreach ($uniqueUsers as $uniqueUser) {
                                $uniqueUserId = is_object($uniqueUser) ? ($uniqueUser->id ?? null) : ($uniqueUser['id'] ?? null);
                                if ($uniqueUserId && (string) $uniqueUserId === (string) $authorId) {
                                    $userExists = true;
                                    break;
                                }
                            }
                            if (!$userExists) {
                                $uniqueUsers[] = $user;
                            }
                            break;
                        }
                    }
                }
            }
        }
        
        if ($duplicateCount > 0) {
            Log::info('🔄 Filtered duplicate mentions by content (UI)', [
                'user_id' => Auth::id(),
                'original_count' => count($this->mentions),
                'unique_count' => count($uniqueMentions),
                'duplicates_removed' => $duplicateCount,
            ]);
        }
        
        $this->mentions = $uniqueMentions;
        if (!empty($uniqueUsers)) {
            $this->users = $uniqueUsers;
        }
    }

    /**
     * Load IDs of mentions that already have an AI auto-reply (for UI badges).
     */
    /**
     * Trigger auto-reply job if conditions are met
     */
    protected function triggerAutoReplyIfEnabled(User $user, int $mentionsCount): void
    {
        Log::info('🔍 Checking if auto-reply should be triggered', [
            'user_id' => $user->id,
            'auto_reply_enabled' => $this->autoReplyEnabled,
            'mentions_count' => $mentionsCount,
            'twitter_account_configured' => $this->twitterAccountConfigured,
        ]);

        if ($this->autoReplyEnabled && $mentionsCount > 0) {
            // Verify TwitterAccount is actually enabled
            $twitterAccount = TwitterAccount::where('user_id', $user->id)->first();
            $isActuallyEnabled = $twitterAccount && $twitterAccount->isAutoEnabled();

            Log::info('✅ Auto-reply conditions met, preparing job dispatch', [
                'user_id' => $user->id,
                'mentions_count' => $mentionsCount,
                'source_type' => 'mention',
                'twitter_account_exists' => $twitterAccount !== null,
                'is_configured' => $twitterAccount?->isConfigured() ?? false,
                'is_auto_enabled' => $isActuallyEnabled,
                'can_post' => $twitterAccount?->canPost() ?? false,
                'comments_posted_today' => $twitterAccount?->comments_posted_today ?? 0,
                'daily_limit' => $twitterAccount?->daily_comment_limit ?? 0,
            ]);

            if (!$isActuallyEnabled) {
                Log::warning('⚠️ Auto-reply UI shows enabled but TwitterAccount is not actually enabled', [
                    'user_id' => $user->id,
                    'ui_shows_enabled' => $this->autoReplyEnabled,
                ]);
                return;
            }

            // Prepare a lightweight payload for the job
            $mentionPayloads = [];
            foreach ($this->mentions as $mention) {
                $mentionPayloads[] = [
                    'id' => is_object($mention) ? ($mention->id ?? null) : ($mention['id'] ?? null),
                    'text' => is_object($mention) ? ($mention->text ?? '') : ($mention['text'] ?? ''),
                    'author_id' => is_object($mention) ? ($mention->author_id ?? null) : ($mention['author_id'] ?? null),
                ];
            }

            Log::info('🚀 Dispatching ProcessMentionAutoReplies job', [
                'user_id' => $user->id,
                'mentions_count' => $mentionsCount,
                'payloads_count' => count($mentionPayloads),
                'source_type' => 'mention',
            ]);

            ProcessMentionAutoReplies::dispatch($user->id, $mentionPayloads, 'mention');

            Log::info('✅ ProcessMentionAutoReplies job dispatched successfully', [
                'user_id' => $user->id,
                'source_type' => 'mention',
            ]);
        } else {
            Log::info('⏭️ Auto-reply not triggered', [
                'user_id' => $user->id,
                'auto_reply_enabled' => $this->autoReplyEnabled,
                'mentions_count' => $mentionsCount,
                'reason' => !$this->autoReplyEnabled ? 'auto-reply disabled in UI' : 'no mentions found',
            ]);
        }
    }

    protected function loadAutoRepliedIds(User $user): void
    {
        $ids = [];
        foreach ($this->mentions as $mention) {
            $mentionId = is_object($mention) ? ($mention->id ?? null) : ($mention['id'] ?? null);
            if ($mentionId) {
                $ids[] = (string) $mentionId;
            }
        }

        if (empty($ids)) {
            $this->autoRepliedIds = [];
            return;
        }

        $this->autoRepliedIds = AutoReply::where('user_id', $user->id)
            ->where('source_type', 'mention')
            ->whereIn('tweet_id', $ids)
            ->pluck('tweet_id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
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
            Log::info('🔴 UI ACTION: Like mention', ['mention_id' => $mentionId]);
            
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
                Log::info('✅ Like response received', ['mention_id' => $mentionId, 'response' => $response]);
                
                // Check if the like actually succeeded
                $liked = isset($response->data->liked) ? $response->data->liked : false;
                $hasMessage = isset($response->data->message) && !empty($response->data->message);
                $message = $response->data->message ?? '';
                
                if ($liked === true) {
                    // Only clear cache if like actually succeeded
                    $this->successMessage = 'Tweet liked successfully!';
                    $this->dispatch('show-success', 'Tweet liked successfully!');
                    $this->clearMentionsCache();
                    Log::info('✅ Like successful - cache cleared', ['mention_id' => $mentionId]);
                } elseif ($hasMessage && (strpos($message, 'Rate limit exceeded') !== false || strpos($message, 'Too Many Requests') !== false)) {
                    // Rate limit error
                    $this->errorMessage = $message;
                    $this->dispatch('show-error', $message);
                    Log::warning('⚠️ Like rate limited - cache NOT cleared', ['mention_id' => $mentionId]);
                } elseif ($hasMessage || $liked === false) {
                    // Like failed - show error but don't clear cache
                    $this->errorMessage = $message ?: 'Failed to like tweet';
                    $this->dispatch('show-error', $this->errorMessage);
                    Log::warning('⚠️ Like failed - cache NOT cleared', [
                        'mention_id' => $mentionId,
                        'liked' => $liked,
                        'error' => $message ?: 'Unknown error'
                    ]);
                } else {
                    // Unknown response - don't clear cache
                    $this->errorMessage = 'Unexpected response from Twitter API';
                    Log::warning('⚠️ Unexpected like response - cache NOT cleared', [
                        'mention_id' => $mentionId,
                        'response' => $response
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('❌ Failed to like mention', [
                'mention_id' => $mentionId,
                'error' => $e->getMessage()
            ]);
            $this->errorMessage = 'Failed to like tweet: ' . $e->getMessage();
        }
    }

    public function retweetMention($mentionId)
    {
        try {
            Log::info('🔴 UI ACTION: Retweet from mentions page', ['mention_id' => $mentionId]);
            
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
                // Log the response for debugging
                Log::info('Retweet response received', [
                    'mention_id' => $mentionId,
                    'response' => $response,
                    'has_data' => isset($response->data),
                    'has_message' => isset($response->data->message),
                    'has_retweeted' => isset($response->data->retweeted),
                    'retweeted_value' => isset($response->data->retweeted) ? $response->data->retweeted : 'not_set'
                ]);
                
                // Check if it was already retweeted
                if (isset($response->data->message) && strpos($response->data->message, 'already retweeted') !== false) {
                    $this->successMessage = 'Tweet was already retweeted!';
                    $this->dispatch('show-success', 'Tweet was already retweeted!');
                    // Clear cache to show updated state
                    $this->clearMentionsCache();
                } 
                // Check if it's a rate limit message
                elseif (isset($response->data->message) && strpos($response->data->message, 'Rate limit exceeded') !== false) {
                    $this->errorMessage = $response->data->message;
                    $this->dispatch('show-error', $response->data->message);
                    // Don't clear cache on rate limit
                } 
                // If there's no error message, treat it as success (same logic as keyword page)
                // This handles cases where Twitter API returns success without a "retweeted" field
                else {
                    // Retweet succeeded - clear cache
                    $this->successMessage = 'Tweet retweeted successfully!';
                    $this->dispatch('show-success', 'Tweet retweeted successfully!');
                    $this->clearMentionsCache();
                    Log::info('✅ Retweet successful from mentions page', ['mention_id' => $mentionId]);
                }
            } else {
                // No response at all
                $this->errorMessage = 'Failed to retweet: No response from Twitter API';
                Log::warning('⚠️ Retweet failed - no response', ['mention_id' => $mentionId]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Failed to retweet mention', [
                'mention_id' => $mentionId,
                'error' => $e->getMessage()
            ]);
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
        // Check rate limit before making request
        $this->checkRateLimitStatus();
        
        if ($this->isRateLimited) {
            $this->errorMessage = "Rate limit active. Please wait {$this->rateLimitWaitMinutes} minute(s) before refreshing. Reset time: {$this->rateLimitResetTime}";
            // Don't clear cache when rate limited - try to load from existing cache
            Log::warning('🚫 Refresh blocked - rate limited, attempting to load from existing cache');
            
            // Try to load from cache if available
            $user = Auth::user();
            if ($user) {
                $cacheKey = 'twitter_mentions_' . $user->id;
                $cachedMentions = \Illuminate\Support\Facades\Cache::get($cacheKey);
                
                if ($cachedMentions) {
                    Log::info('✅ Loading from cache while rate limited', [
                        'mentions_count' => count($cachedMentions['data'] ?? []),
                        'last_updated' => $cachedMentions['timestamp'] ?? 'unknown'
                    ]);
                    $this->mentions = $cachedMentions['data'] ?? [];
                    $this->users = $cachedMentions['users'] ?? [];
                    
                    // Filter out duplicate mentions by content before displaying
                    $this->deduplicateMentionsByContent();
                    
                    $this->lastRefresh = $cachedMentions['timestamp'] ?? now()->format('M j, Y g:i A');
                    $this->currentPage = 1;
                    $this->successMessage = 'Showing cached data (rate limited). Cache updated ' . \Carbon\Carbon::parse($this->lastRefresh)->diffForHumans() . '.';
                }
            }
            return;
        }
        
        // Clear only this user's cache to ensure fresh data
        $this->clearMentionsCache();
        
        // Add some debugging info
        Log::info('Refreshing mentions - user cache cleared, forcing fresh data fetch with fast method');
        
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
            Log::info('Before cache clear - cache exists', [
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
            
            Log::info('Cache clear result', [
                'user_id' => $userId,
                'keys_attempted' => count($userSpecificKeys),
                'keys_cleared' => $clearedCount
            ]);
            
            // Also clear from database cache table directly (only for this user)
            try {
                $deletedRows = DB::table('cache')
                    ->where(function($query) use ($userId) {
                        $query->where('key', 'like', "%twitter_mentions_{$userId}%")
                              ->orWhere('key', 'like', "%mentions_{$userId}%")
                              ->orWhere('key', 'like', "%twitter_mentions_user_{$userId}%")
                              ->orWhere('key', 'like', "%mentions_cache_{$userId}%");
                    })
                    ->delete();
                    
                Log::info('Cleared cache from database table', [
                    'user_id' => $userId,
                    'deleted_rows' => $deletedRows
                ]);
            } catch (\Exception $e) {
                Log::warning('Could not clear cache from database table', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Verify cache is actually cleared for this user only
            $cachedDataAfter = \Illuminate\Support\Facades\Cache::get($cacheKey);
            Log::info('After cache clear - cache exists', [
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
            
            Log::info('Cleared all user cache', [
                'user_id' => $userId,
                'keys_attempted' => count($allUserCacheKeys),
                'keys_cleared' => $clearedCount
            ]);
            
            // Clear from database cache table
            try {
                $deletedRows = DB::table('cache')
                    ->where('key', 'like', "%{$userId}%")
                    ->delete();
                    
                Log::info('Cleared all user cache from database', [
                    'user_id' => $userId,
                    'deleted_rows' => $deletedRows
                ]);
            } catch (\Exception $e) {
                Log::warning('Could not clear all user cache from database', [
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