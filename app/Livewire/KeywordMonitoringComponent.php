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
        
        // Show loading animation immediately
        $this->searchLoading = true;
        $this->successMessage = 'Preparing to search tweets...';
        
        // Schedule delayed loading after 2 seconds
        $this->dispatch('delayed-load-tweets');
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

    public function loadTweets()
    {
        if (empty($this->keywords)) {
            $this->tweets = [];
            $this->lastRefresh = now()->format('M j, Y g:i A');
            return;
        }

        $this->searchLoading = true;
        $this->errorMessage = '';
        $this->successMessage = '';

        $user = Auth::user();
        if (!$user) {
            $this->errorMessage = 'User not authenticated.';
            $this->searchLoading = false;
            return;
        }

        if (!$user->twitter_account_connected || !$user->twitter_access_token || !$user->twitter_access_token_secret) {
            $this->errorMessage = 'Please connect your Twitter account first.';
            $this->searchLoading = false;
            return;
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
            
            // Search tweets for each keyword separately and combine results
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

            $this->lastRefresh = now()->format('M j, Y g:i A');
            $this->currentPage = 1;

            $tweetCount = count($this->tweets);
            if ($tweetCount > 0) {
                $this->successMessage = "Found {$tweetCount} tweets containing your monitored keywords!";
            } else {
                $this->successMessage = "No tweets found containing your monitored keywords. Try adding more keywords or check back later.";
            }

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            
            if ($statusCode === 429) {
                $this->errorMessage = 'Rate limit exceeded. Please wait a few minutes before trying again.';
            } else {
                $this->errorMessage = "Failed to search tweets: HTTP {$statusCode}";
            }
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to search tweets: ' . $e->getMessage();
        }

        $this->searchLoading = false;
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

    public function clearMessage()
    {
        $this->errorMessage = '';
        $this->successMessage = '';
    }

    public function refreshTweets()
    {
        $this->loadTweets();
    }

    public function delayedLoadTweets()
    {
        // Add a 2-second delay before loading tweets
        $this->searchLoading = true;
        $this->successMessage = 'Loading tweets...';
        
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
