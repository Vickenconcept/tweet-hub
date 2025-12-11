<?php

namespace App\Console\Commands;

use App\Jobs\ProcessAutoDms;
use App\Models\AutoDm;
use App\Models\User;
use App\Services\TwitterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessInteractionAutoDms extends Command
{
    protected $signature = 'interactions:process-auto-dms {--tweets=5 : Number of recent tweets to check} {--days=30 : Only check tweets from the last N days (default: 30)}';
    protected $description = 'Check recent tweets for interactions and send auto DMs. Only processes tweets from within the date range to avoid checking old tweets.';
    
    // Track API call statistics
    private $apiCallCount = 0;
    private $rateLimitHit = false;

    public function handle()
    {
        Log::info('🔄 Starting interaction auto DM processing');

        // Get all users who have interaction auto DM enabled
        $users = User::where('interaction_auto_dm_enabled', true)
            ->where('twitter_account_connected', true)
            ->whereNotNull('twitter_account_id')
            ->whereNotNull('twitter_access_token')
            ->get();

        if ($users->isEmpty()) {
            $this->info('No users with interaction auto DM enabled.');
            Log::info('📩 No users with interaction auto DM enabled');
            return;
        }

        $this->info("Processing {$users->count()} user(s) with auto DM enabled...");

        $processedCount = 0;
        $dmQueuedCount = 0;

        foreach ($users as $user) {
            if (!$user->isTwitterConnected()) {
                continue;
            }

            // Check daily limit
            $sentToday = AutoDm::where('user_id', $user->id)
                ->where('source_type', 'interaction')
                ->whereDate('sent_at', today())
                ->where('status', 'sent')
                ->count();

            $dailyLimit = $user->interaction_auto_dm_daily_limit ?? 50;
            
            if ($sentToday >= $dailyLimit) {
                Log::info('📩 Daily limit reached for user', [
                    'user_id' => $user->id,
                    'sent_today' => $sentToday,
                    'daily_limit' => $dailyLimit,
                ]);
                continue;
            }

            try {
                // Validate that we have all required tokens
                if (empty($user->twitter_access_token) || empty($user->twitter_access_token_secret)) {
                    Log::error('📩 Missing Twitter access tokens for user', [
                        'user_id' => $user->id,
                        'has_access_token' => !empty($user->twitter_access_token),
                        'has_access_token_secret' => !empty($user->twitter_access_token_secret),
                    ]);
                    continue;
                }

                $settings = [
                    'account_id' => $user->twitter_account_id,
                    'consumer_key' => config('services.twitter.api_key'),
                    'consumer_secret' => config('services.twitter.api_key_secret'),
                    'access_token' => $user->twitter_access_token,
                    'access_token_secret' => $user->twitter_access_token_secret,
                    'bearer_token' => config('services.twitter.bearer_token'),
                ];

                // Log authentication settings (without sensitive data)
                Log::info('📩 Initializing TwitterService for interaction processing', [
                    'user_id' => $user->id,
                    'account_id' => $user->twitter_account_id,
                    'has_access_token' => !empty($settings['access_token']),
                    'has_access_token_secret' => !empty($settings['access_token_secret']),
                    'has_consumer_key' => !empty($settings['consumer_key']),
                    'has_bearer_token' => !empty($settings['bearer_token']),
                ]);

                $twitter = new TwitterService($settings);
                
                // Get number of tweets to check (default 5 to avoid rate limits)
                $tweetsToCheck = (int) $this->option('tweets');
                if ($tweetsToCheck < 1 || $tweetsToCheck > 20) {
                    $tweetsToCheck = 5; // Safe default
                }
                
                // Get recent tweets
                $tweetsResult = $twitter->getRecentTweets($user->twitter_account_id);
                
                if (!$tweetsResult || !isset($tweetsResult->data)) {
                    continue;
                }

                // Get date range option
                $daysBack = (int) $this->option('days');
                if ($daysBack < 1 || $daysBack > 90) {
                    $daysBack = 30; // Safe default: last 30 days
                }
                
                // First, get all tweets to find the most recent one
                $allTweets = is_array($tweetsResult->data) ? $tweetsResult->data : [];
                
                // Find the most recent tweet's date (to calculate days back from there)
                $mostRecentTweetDate = null;
                foreach ($allTweets as $tweet) {
                    $tweetDate = null;
                    if (is_object($tweet)) {
                        $tweetDate = $tweet->created_at ?? null;
                    } elseif (is_array($tweet)) {
                        $tweetDate = $tweet['created_at'] ?? null;
                    }
                    
                    if ($tweetDate) {
                        if (is_string($tweetDate)) {
                            $tweetDate = \Carbon\Carbon::parse($tweetDate);
                        }
                        
                        if (!$mostRecentTweetDate || $tweetDate->gt($mostRecentTweetDate)) {
                            $mostRecentTweetDate = $tweetDate;
                        }
                    }
                }
                
                // If no tweets found or no dates, use today as reference
                if (!$mostRecentTweetDate) {
                    $mostRecentTweetDate = now();
                }
                
                // Calculate cutoff date: X days back FROM the most recent tweet's date
                // This ensures we count from the last tweet you made, not from "now"
                $daysBackCutoffDate = $mostRecentTweetDate->copy()->subDays($daysBack);
                
                // Find when user first enabled interaction auto DM (don't check tweets from before this)
                $firstInteractionDm = AutoDm::where('user_id', $user->id)
                    ->where('source_type', 'interaction')
                    ->orderBy('created_at', 'asc')
                    ->first();
                
                $featureFirstUsedAt = null;
                if ($firstInteractionDm && $firstInteractionDm->created_at) {
                    $featureFirstUsedAt = $firstInteractionDm->created_at;
                }
                
                // Use the MORE RECENT date (don't check tweets older than either limit)
                // If feature was enabled recently, only check from then forward
                // If days limit is more recent, use that limit
                if ($featureFirstUsedAt && $featureFirstUsedAt->gt($daysBackCutoffDate)) {
                    $finalCutoffDate = $featureFirstUsedAt; // Feature enabled more recently, use that date
                } else {
                    $finalCutoffDate = $daysBackCutoffDate; // Days limit is the constraint
                }
                
                // Filter tweets by date
                $filteredTweets = [];
                
                foreach ($allTweets as $tweet) {
                    // Get tweet date
                    $tweetDate = null;
                    if (is_object($tweet)) {
                        $tweetDate = $tweet->created_at ?? null;
                    } elseif (is_array($tweet)) {
                        $tweetDate = $tweet['created_at'] ?? null;
                    }
                    
                    // Skip if no date found (shouldn't happen, but be safe)
                    if (!$tweetDate) {
                        continue;
                    }
                    
                    // Parse date if it's a string
                    if (is_string($tweetDate)) {
                        $tweetDate = \Carbon\Carbon::parse($tweetDate);
                    } elseif (!$tweetDate instanceof \Carbon\Carbon) {
                        try {
                            $tweetDate = \Carbon\Carbon::parse($tweetDate);
                        } catch (\Exception $e) {
                            continue; // Skip if we can't parse the date
                        }
                    }
                    
                    // Only include tweets from after the cutoff date
                    if ($tweetDate->gte($finalCutoffDate)) {
                        $filteredTweets[] = $tweet;
                    }
                }
                
                // Limit to requested number of tweets (most recent first)
                $recentTweets = array_slice($filteredTweets, 0, $tweetsToCheck);
                
                $recipients = [];
                $remaining = $dailyLimit - $sentToday;
                $this->apiCallCount = 0; // Reset for this user
                $this->rateLimitHit = false;
                
                Log::info('📩 Processing tweets for interactions', [
                    'user_id' => $user->id,
                    'total_tweets_fetched' => count($allTweets),
                    'most_recent_tweet_date' => $mostRecentTweetDate->format('Y-m-d H:i:s'),
                    'days_back_from_recent' => $daysBack,
                    'tweets_after_date_filter' => count($filteredTweets),
                    'tweets_to_check' => count($recentTweets),
                    'days_back_cutoff' => $daysBackCutoffDate->format('Y-m-d H:i:s'),
                    'final_cutoff_date' => $finalCutoffDate->format('Y-m-d H:i:s'),
                    'first_dm_sent_at' => $featureFirstUsedAt ? $featureFirstUsedAt->format('Y-m-d H:i:s') : 'never (new user)',
                    'date_filter_active' => $featureFirstUsedAt ? 'yes (using more recent date)' : 'no (using days limit only)',
                    'max_api_calls' => count($recentTweets) * 3, // 3 calls per tweet
                ]);
                
                if (count($allTweets) > count($filteredTweets)) {
                    $skippedCount = count($allTweets) - count($filteredTweets);
                    Log::info('📩 Skipped old tweets', [
                        'user_id' => $user->id,
                        'skipped_count' => $skippedCount,
                        'reason' => "Tweets older than {$daysBack} days or before feature was enabled",
                    ]);
                }

                // Log which tweets will be checked with full details including metrics
                Log::info('📩 Tweets to be checked for interactions', [
                    'user_id' => $user->id,
                    'total_tweets' => count($recentTweets),
                    'tweet_details' => array_map(function($tweet) {
                        $tweetId = is_array($tweet) ? ($tweet['id'] ?? '') : ($tweet->id ?? '');
                        $tweetText = is_array($tweet) ? ($tweet['text'] ?? '') : ($tweet->text ?? '');
                        $tweetDate = is_array($tweet) ? ($tweet['created_at'] ?? null) : ($tweet->created_at ?? null);
                        
                        // Get public metrics if available
                        $publicMetrics = null;
                        if (is_array($tweet) && isset($tweet['public_metrics'])) {
                            $publicMetrics = $tweet['public_metrics'];
                        } elseif (is_object($tweet) && isset($tweet->public_metrics)) {
                            $publicMetrics = (array) $tweet->public_metrics;
                        }
                        
                        return [
                            'tweet_id' => $tweetId,
                            'text_preview' => \Illuminate\Support\Str::limit($tweetText, 100),
                            'date' => $tweetDate,
                            'url' => $tweetId ? "https://twitter.com/i/web/status/{$tweetId}" : null,
                            'public_metrics' => $publicMetrics ? [
                                'like_count' => $publicMetrics['like_count'] ?? $publicMetrics->like_count ?? 'N/A',
                                'retweet_count' => $publicMetrics['retweet_count'] ?? $publicMetrics->retweet_count ?? 'N/A',
                                'reply_count' => $publicMetrics['reply_count'] ?? $publicMetrics->reply_count ?? 'N/A',
                                'quote_count' => $publicMetrics['quote_count'] ?? $publicMetrics->quote_count ?? 'N/A',
                            ] : 'not available',
                            'full_tweet_structure' => [
                                'type' => gettype($tweet),
                                'keys' => is_array($tweet) ? array_keys($tweet) : (is_object($tweet) ? array_keys((array) $tweet) : []),
                            ],
                        ];
                    }, array_slice($recentTweets, 0, 10)), // Limit to first 10 for log size
                ]);

                foreach ($recentTweets as $tweetIndex => $tweet) {
                    // Stop if we hit rate limit
                    if ($this->rateLimitHit) {
                        Log::warning('📩 Rate limit hit - stopping tweet processing', [
                            'user_id' => $user->id,
                            'tweets_processed' => $tweetIndex,
                            'api_calls_made' => $this->apiCallCount,
                        ]);
                        break;
                    }
                    
                    if (count($recipients) >= $remaining) {
                        break;
                    }

                    $tweetId = is_array($tweet) ? ($tweet['id'] ?? '') : ($tweet->id ?? '');
                    $tweetText = is_array($tweet) ? ($tweet['text'] ?? '') : ($tweet->text ?? '');
                    $tweetDate = is_array($tweet) ? ($tweet['created_at'] ?? null) : ($tweet->created_at ?? null);
                    
                    if (!$tweetId) {
                        continue;
                    }
                    
                    // Log which tweet we're checking now
                    Log::info('📩 Checking tweet for interactions', [
                        'tweet_number' => $tweetIndex + 1,
                        'tweet_id' => $tweetId,
                        'tweet_text_preview' => \Illuminate\Support\Str::limit($tweetText, 150),
                        'tweet_date' => $tweetDate,
                        'tweet_url' => "https://twitter.com/i/web/status/{$tweetId}",
                    ]);

                    // Check for likes (only if enabled in user settings)
                    // Note: The UI checkboxes (monitorLikes, etc.) are not currently saved to database
                    // For now, we check all interaction types if interaction_auto_dm_enabled is true
                    if ($user->interaction_auto_dm_enabled) {
                        // Add delay between API calls to avoid rate limits (except first call)
                        if ($this->apiCallCount > 0) {
                            sleep(2); // 2 second delay between API calls
                        }
                        
                        $likes = $this->getUsersWhoLiked($twitter, $tweetId);
                        $this->apiCallCount++;
                        
                        if (count($likes) > 0) {
                            Log::info('📩 Found users who liked this tweet', [
                                'tweet_id' => $tweetId,
                                'likes_count' => count($likes),
                                'tweet_url' => "https://twitter.com/i/web/status/{$tweetId}",
                            ]);
                        }
                        
                        foreach ($likes as $userId) {
                            // Skip if it's the user's own account (don't DM yourself)
                            if ((string) $userId === (string) $user->twitter_account_id) {
                                continue;
                            }
                            
                            if (count($recipients) >= $remaining) {
                                break 2;
                            }
                            if (!$this->alreadyDmSent($user->id, $userId, 'interaction', $tweetId)) {
                                $recipients[] = [
                                    'twitter_recipient_id' => $userId,
                                    'tweet_id' => $tweetId,
                                    'interaction_type' => 'like',
                                    'context' => "liked your tweet",
                                    'dm_text' => $user->interaction_auto_dm_template ?? "Hey! Thanks for engaging with my tweet. I'd love to connect!",
                                ];
                            }
                        }

                        // Add delay before next API call
                        $replies = [];
                        if (!$this->rateLimitHit) {
                            sleep(2);
                            $replies = $this->getReplies($twitter, $tweetId, $user->twitter_account_id);
                            $this->apiCallCount++;
                        }
                        
                        foreach ($replies as $reply) {
                            if (count($recipients) >= $remaining) {
                                break 2;
                            }
                            $userId = is_array($reply) ? ($reply['author_id'] ?? null) : ($reply->author_id ?? null);
                            if ($userId && !$this->alreadyDmSent($user->id, (string) $userId, 'interaction', $tweetId)) {
                                $replyText = is_array($reply) ? ($reply['text'] ?? '') : ($reply->text ?? '');
                                $recipients[] = [
                                    'twitter_recipient_id' => (string) $userId,
                                    'tweet_id' => $tweetId,
                                    'interaction_type' => 'reply',
                                    'context' => "replied to your tweet: " . \Illuminate\Support\Str::limit($replyText, 50),
                                    'dm_text' => $user->interaction_auto_dm_template ?? "Hey! Thanks for engaging with my tweet. I'd love to connect!",
                                ];
                            }
                        }

                        // Add delay before next API call
                        $quotes = [];
                        if (!$this->rateLimitHit) {
                            sleep(2);
                            $quotes = $this->getQuoteTweets($twitter, $tweetId, $user->twitter_account_id);
                            $this->apiCallCount++;
                        }
                        
                        foreach ($quotes as $quote) {
                            if (count($recipients) >= $remaining) {
                                break 2;
                            }
                            $userId = is_array($quote) ? ($quote['author_id'] ?? null) : ($quote->author_id ?? null);
                            if ($userId && !$this->alreadyDmSent($user->id, (string) $userId, 'interaction', $tweetId)) {
                                $recipients[] = [
                                    'twitter_recipient_id' => (string) $userId,
                                    'tweet_id' => $tweetId,
                                    'interaction_type' => 'quote',
                                    'context' => "quoted your tweet",
                                    'dm_text' => $user->interaction_auto_dm_template ?? "Hey! Thanks for engaging with my tweet. I'd love to connect!",
                                ];
                            }
                        }
                    }
                }
                
                Log::info('📩 Finished processing tweets', [
                    'user_id' => $user->id,
                    'tweets_checked' => count($recentTweets),
                    'api_calls_made' => $this->apiCallCount,
                    'rate_limit_hit' => $this->rateLimitHit,
                    'recipients_found' => count($recipients),
                ]);

                if (!empty($recipients)) {
                    ProcessAutoDms::dispatch($user->id, $recipients, 'interaction', 'Auto Interaction');
                    $dmQueuedCount += count($recipients);
                    
                    Log::info('📩 Queued interaction DMs', [
                        'user_id' => $user->id,
                        'recipients_count' => count($recipients),
                    ]);
                }

                $processedCount++;

            } catch (\Throwable $e) {
                Log::error('📩 Error processing user interactions', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Processed {$processedCount} user(s), queued {$dmQueuedCount} DM(s).");
        
        // Provide helpful feedback
        if ($dmQueuedCount === 0 && $processedCount > 0) {
            $this->warn("⚠️  No DMs were queued. This could mean:");
            $this->line("   - No new interactions found on your recent tweets");
            $this->line("   - Daily limit already reached");
            $this->line("   - API rate limit hit (check logs for 429 errors)");
            $this->line("   - API authentication errors (check logs for 401 errors)");
            $this->line("   - All interactions already processed");
            $this->newLine();
            $this->line("💡 Tip: Use --tweets=5 to check fewer tweets and avoid rate limits");
        }
        
        if ($this->rateLimitHit) {
            $this->error("🚫 Rate limit was hit during processing!");
            $this->line("   - Twitter API returned 429 Too Many Requests");
            $this->line("   - The system stopped making further API calls");
            $this->line("   - Wait 15 minutes before running again");
            $this->line("   - Consider using --tweets=3 to check fewer tweets");
        }
        
        Log::info('✅ Finished interaction auto DM processing', [
            'processed_count' => $processedCount,
            'dm_queued_count' => $dmQueuedCount,
        ]);
    }

    private function getUsersWhoLiked(TwitterService $twitter, string $tweetId): array
    {
        try {
            Log::info('📩 Attempting to get users who liked tweet', [
                'tweet_id' => $tweetId,
                'tweet_url' => "https://twitter.com/i/web/status/{$tweetId}",
            ]);
            
            $result = $twitter->getUsersWhoLiked($tweetId, 100);
            
            // Log the full API response structure for debugging
            $resultType = gettype($result);
            $resultKeys = [];
            if (is_object($result)) {
                $resultKeys = array_keys((array) $result);
            } elseif (is_array($result)) {
                $resultKeys = array_keys($result);
            }
            
            // Safe logging - check type before accessing properties
            $resultDataType = 'not set';
            $resultDataCount = 'not set';
            if (is_object($result) && isset($result->data)) {
                $resultDataType = gettype($result->data);
                $resultDataCount = is_array($result->data) ? count($result->data) : (is_object($result->data) ? 'object' : 'unknown');
            } elseif (is_array($result) && isset($result['data'])) {
                $resultDataType = gettype($result['data']);
                $resultDataCount = is_array($result['data']) ? count($result['data']) : 'unknown';
            }
            
            Log::info('📩 API Response structure for likes', [
                'tweet_id' => $tweetId,
                'tweet_url' => "https://twitter.com/i/web/status/{$tweetId}",
                'result_type' => $resultType,
                'result_is_null' => is_null($result),
                'result_keys' => $resultKeys,
                'has_data_property' => is_object($result) ? property_exists($result, 'data') : (is_array($result) ? isset($result['data']) : false),
                'result_data_type' => $resultDataType,
                'result_data_count' => $resultDataCount,
                'full_result_preview' => is_object($result) ? json_encode($result, JSON_PARTIAL_OUTPUT_ON_ERROR) : (is_array($result) ? json_encode(array_slice($result, 0, 3), JSON_PARTIAL_OUTPUT_ON_ERROR) : 'null'),
            ]);
            
            if ($result && is_object($result) && isset($result->data)) {
                $dataArray = is_array($result->data) ? $result->data : [];
                $userIds = array_map(function($user) {
                    if (is_object($user)) {
                        return (string) ($user->id ?? '');
                    } elseif (is_array($user)) {
                        return (string) ($user['id'] ?? '');
                    }
                    return '';
                }, $dataArray);
                
                if (count($userIds) > 0) {
                    Log::info('✅ Found users who liked tweet!', [
                        'tweet_id' => $tweetId,
                        'tweet_url' => "https://twitter.com/i/web/status/{$tweetId}",
                        'likes_count' => count($userIds),
                        'user_ids' => array_slice($userIds, 0, 5), // Show first 5 user IDs
                    ]);
                } else {
                    Log::info('📩 No users found who liked tweet (empty result)', [
                        'tweet_id' => $tweetId,
                        'tweet_url' => "https://twitter.com/i/web/status/{$tweetId}",
                        'note' => 'API returned result but no users in data array',
                    ]);
                }
                
                return $userIds;
            }
            
            // Also check for array access pattern
            if (is_array($result) && isset($result['data'])) {
                $userIds = array_map(function($user) {
                    if (is_object($user)) {
                        return (string) ($user->id ?? '');
                    } elseif (is_array($user)) {
                        return (string) ($user['id'] ?? '');
                    }
                    return '';
                }, is_array($result['data']) ? $result['data'] : []);
                
                if (count($userIds) > 0) {
                    Log::info('✅ Found users who liked tweet! (array access)', [
                        'tweet_id' => $tweetId,
                        'tweet_url' => "https://twitter.com/i/web/status/{$tweetId}",
                        'likes_count' => count($userIds),
                        'user_ids' => array_slice($userIds, 0, 5),
                    ]);
                }
                
                return $userIds;
            }
            
            $hasDataField = 'no';
            if ($result) {
                if (is_object($result) && isset($result->data)) {
                    $hasDataField = 'yes';
                } elseif (is_array($result) && isset($result['data'])) {
                    $hasDataField = 'yes';
                }
            }
            
            Log::info('📩 No users found who liked tweet', [
                'tweet_id' => $tweetId,
                'tweet_url' => "https://twitter.com/i/web/status/{$tweetId}",
                'result_structure' => $result ? 'result exists but no data field' : 'null result from API',
                'has_data_field' => $hasDataField,
            ]);
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            $is401Error = str_contains($errorMessage, '401') || str_contains($errorMessage, 'Unauthorized');
            
            $is429Error = str_contains($errorMessage, '429') || str_contains($errorMessage, 'Too Many Requests');
            
            Log::warning('📩 Failed to get users who liked', [
                'tweet_id' => $tweetId,
                'error' => $errorMessage,
                'is_401_error' => $is401Error,
                'is_429_error' => $is429Error,
                'error_class' => get_class($e),
            ]);
            
            // If it's a 401 error, log more details for troubleshooting
            if ($is401Error) {
                Log::error('📩 Authentication failed - 401 Unauthorized', [
                    'tweet_id' => $tweetId,
                    'endpoint' => 'GET /2/tweets/{tweet_id}/liking_users',
                    'issue' => 'This endpoint requires OAuth 1.0a user authentication with valid access tokens',
                    'possible_causes' => [
                        'Access tokens expired or invalid',
                        'Twitter account needs to be reconnected',
                        'API plan does not have access to this endpoint',
                        'OAuth credentials not properly configured',
                    ],
                ]);
            }
            
            // If it's a 429 error, mark rate limit as hit
            if ($is429Error) {
                $this->rateLimitHit = true;
                Log::error('🚫 Rate limit hit (429) - stopping further API calls', [
                    'tweet_id' => $tweetId,
                    'endpoint' => 'GET /2/tweets/{tweet_id}/liking_users',
                    'api_calls_made' => $this->apiCallCount,
                    'action' => 'Will skip remaining tweets to avoid further rate limit issues',
                ]);
            }
        }
        return [];
    }

    private function getReplies(TwitterService $twitter, string $tweetId, string $excludeUserId): array
    {
        try {
            Log::info('📩 Attempting to get replies for tweet', [
                'tweet_id' => $tweetId,
                'tweet_url' => "https://twitter.com/i/web/status/{$tweetId}",
            ]);
            
            $result = $twitter->getReplies($tweetId);
            
            // Handle object response
            if ($result && is_object($result) && isset($result->data)) {
                $replies = is_array($result->data) ? $result->data : (is_object($result->data) ? [$result->data] : []);
                // Filter out replies from the tweet author
                $filteredReplies = array_values(array_filter($replies, function($reply) use ($excludeUserId) {
                    $authorId = is_array($reply) ? ($reply['author_id'] ?? '') : (is_object($reply) ? ($reply->author_id ?? '') : '');
                    return (string) $authorId !== (string) $excludeUserId && !empty($authorId);
                }));
                
                if (count($filteredReplies) > 0) {
                    Log::info('✅ Found replies for tweet!', [
                        'tweet_id' => $tweetId,
                        'tweet_url' => "https://twitter.com/i/web/status/{$tweetId}",
                        'replies_count' => count($filteredReplies),
                    ]);
                } else {
                    Log::info('📩 No replies found for tweet', [
                        'tweet_id' => $tweetId,
                        'tweet_url' => "https://twitter.com/i/web/status/{$tweetId}",
                    ]);
                }
                
                return $filteredReplies;
            }
            
            // Handle array response
            if ($result && is_array($result) && isset($result['data'])) {
                $replies = is_array($result['data']) ? $result['data'] : [];
                // Filter out replies from the tweet author
                $filteredReplies = array_values(array_filter($replies, function($reply) use ($excludeUserId) {
                    $authorId = is_array($reply) ? ($reply['author_id'] ?? '') : (is_object($reply) ? ($reply->author_id ?? '') : '');
                    return (string) $authorId !== (string) $excludeUserId && !empty($authorId);
                }));
                
                if (count($filteredReplies) > 0) {
                    Log::info('✅ Found replies for tweet!', [
                        'tweet_id' => $tweetId,
                        'tweet_url' => "https://twitter.com/i/web/status/{$tweetId}",
                        'replies_count' => count($filteredReplies),
                    ]);
                } else {
                    Log::info('📩 No replies found for tweet', [
                        'tweet_id' => $tweetId,
                        'tweet_url' => "https://twitter.com/i/web/status/{$tweetId}",
                    ]);
                }
                
                return $filteredReplies;
            }
            
            Log::info('📩 No replies found for tweet (no data in response)', [
                'tweet_id' => $tweetId,
                'tweet_url' => "https://twitter.com/i/web/status/{$tweetId}",
            ]);
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            $is429Error = str_contains($errorMessage, '429') || str_contains($errorMessage, 'Too Many Requests');
            
            Log::warning('📩 Failed to get replies', [
                'tweet_id' => $tweetId,
                'error' => $errorMessage,
                'is_429_error' => $is429Error,
            ]);
            
            if ($is429Error) {
                $this->rateLimitHit = true;
                Log::error('🚫 Rate limit hit (429) when getting replies - stopping further API calls', [
                    'tweet_id' => $tweetId,
                    'api_calls_made' => $this->apiCallCount,
                ]);
            }
        }
        return [];
    }

    private function getQuoteTweets(TwitterService $twitter, string $tweetId, string $excludeUserId): array
    {
        try {
            Log::info('📩 Attempting to get quote tweets for tweet', [
                'tweet_id' => $tweetId,
                'tweet_url' => "https://twitter.com/i/web/status/{$tweetId}",
            ]);
            
            $result = $twitter->getQuoteTweets($tweetId);
            
            // Handle object response
            if ($result && is_object($result) && isset($result->data)) {
                $quotes = is_array($result->data) ? $result->data : (is_object($result->data) ? [$result->data] : []);
                // Filter out quotes from the tweet author
                $filteredQuotes = array_values(array_filter($quotes, function($quote) use ($excludeUserId) {
                    $authorId = is_array($quote) ? ($quote['author_id'] ?? '') : (is_object($quote) ? ($quote->author_id ?? '') : '');
                    return (string) $authorId !== (string) $excludeUserId && !empty($authorId);
                }));
                
                if (count($filteredQuotes) > 0) {
                    Log::info('✅ Found quote tweets!', [
                        'tweet_id' => $tweetId,
                        'tweet_url' => "https://twitter.com/i/web/status/{$tweetId}",
                        'quotes_count' => count($filteredQuotes),
                    ]);
                } else {
                    Log::info('📩 No quote tweets found', [
                        'tweet_id' => $tweetId,
                        'tweet_url' => "https://twitter.com/i/web/status/{$tweetId}",
                    ]);
                }
                
                return $filteredQuotes;
            }
            
            // Handle array response
            if ($result && is_array($result) && isset($result['data'])) {
                $quotes = is_array($result['data']) ? $result['data'] : [];
                // Filter out quotes from the tweet author
                $filteredQuotes = array_values(array_filter($quotes, function($quote) use ($excludeUserId) {
                    $authorId = is_array($quote) ? ($quote['author_id'] ?? '') : (is_object($quote) ? ($quote->author_id ?? '') : '');
                    return (string) $authorId !== (string) $excludeUserId && !empty($authorId);
                }));
                
                if (count($filteredQuotes) > 0) {
                    Log::info('✅ Found quote tweets!', [
                        'tweet_id' => $tweetId,
                        'tweet_url' => "https://twitter.com/i/web/status/{$tweetId}",
                        'quotes_count' => count($filteredQuotes),
                    ]);
                } else {
                    Log::info('📩 No quote tweets found', [
                        'tweet_id' => $tweetId,
                        'tweet_url' => "https://twitter.com/i/web/status/{$tweetId}",
                    ]);
                }
                
                return $filteredQuotes;
            }
            
            Log::info('📩 No quote tweets found (no data in response)', [
                'tweet_id' => $tweetId,
                'tweet_url' => "https://twitter.com/i/web/status/{$tweetId}",
            ]);
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            $is429Error = str_contains($errorMessage, '429') || str_contains($errorMessage, 'Too Many Requests');
            
            Log::warning('📩 Failed to get quote tweets', [
                'tweet_id' => $tweetId,
                'error' => $errorMessage,
                'is_429_error' => $is429Error,
            ]);
            
            if ($is429Error) {
                $this->rateLimitHit = true;
                Log::error('🚫 Rate limit hit (429) when getting quote tweets - stopping further API calls', [
                    'tweet_id' => $tweetId,
                    'api_calls_made' => $this->apiCallCount,
                ]);
            }
        }
        return [];
    }

    private function alreadyDmSent(int $userId, string $recipientId, string $sourceType, string $tweetId): bool
    {
        return AutoDm::where('user_id', $userId)
            ->where('twitter_recipient_id', $recipientId)
            ->where('source_type', $sourceType)
            ->where('campaign_name', 'Auto Interaction')
            ->whereIn('status', ['sent', 'pending'])
            ->exists();
    }
}
