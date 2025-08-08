<?php

namespace App\Services;

use Noweh\TwitterApi\Client;
use Illuminate\Support\Facades\Log;

class TwitterService
{
    protected $client;

    public function __construct(array $settings)
    {
        $this->client = new Client($settings);
    }

    // Tweets endpoints
    /**
     * Find recent mentions for a user.
     */
    public function getRecentMentions($accountId)
    {
        $maxRetries = 3;
        $retryDelay = 2; // seconds

        for ($retry = 0; $retry < $maxRetries; $retry++) {
            try {
                Log::info('Fetching recent mentions', ['account_id' => $accountId, 'retry' => $retry]);
                $result = $this->client->timeline()->getRecentMentions($accountId)->performRequest();
                Log::info('Recent mentions fetched successfully', ['account_id' => $accountId]);
                return $result;
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $statusCode = $e->getResponse()->getStatusCode();
                $body = $e->getResponse()->getBody()->getContents();
                
                Log::error('Twitter API Error fetching mentions', [
                    'account_id' => $accountId,
                    'status_code' => $statusCode,
                    'error_body' => $body,
                    'retry' => $retry,
                    'max_retries' => $maxRetries
                ]);
                
                if ($statusCode === 429 && $retry < $maxRetries - 1) {
                    // Wait with exponential backoff
                    $waitTime = $retryDelay * pow(2, $retry);
                    Log::info('Rate limited, waiting before retry', ['wait_time' => $waitTime, 'retry' => $retry]);
                    sleep($waitTime);
                    continue;
                }
                throw $e;
            }
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
                $data['media'] = ['media_ids' => $mediaIds];
            }
            if ($inReplyToTweetId) {
                $data['reply'] = ['in_reply_to_tweet_id' => $inReplyToTweetId];
            }
            
            Log::info('Creating tweet', [
                'char_count' => $charCount,
                'has_media' => !empty($mediaIds),
                'is_reply' => !empty($inReplyToTweetId)
            ]);
            
            $result = $this->client->tweet()->create()->performRequest($data);
            
            Log::info('Tweet created successfully', [
                'tweet_id' => $result->data->id ?? 'unknown',
                'text' => $text
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
        $file_data = base64_encode(file_get_contents($localPath));
        $media_info = $this->client->uploadMedia()->upload($file_data);
        return isset($media_info['media_id']) ? (string)$media_info['media_id'] : null;
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
        return $this->client->retweet()->performRequest(['tweet_id' => $tweetId]);
    }

    /**
     * Like a tweet.
     */
    public function likeTweet($tweetId)
    {
        return $this->client->tweetLikes()->like()->performRequest(['tweet_id' => $tweetId]);
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
    public function getBookmarks()
    {
        return $this->client->tweetBookmarks()->lookup()->performRequest();
    }

    // Users endpoints
    // User/Blocks endpoints
    /**
     * Retrieve the users which you've blocked.
     */
    public function getBlockedUsers()
    {
        return $this->client->userBlocks()->lookup()->performRequest();
    }

    // User/Follows endpoints
    /**
     * Retrieve the users which are following you.
     */
    public function getFollowers()
    {
        return $this->client->userFollows()->getFollowers()->performRequest();
    }

    /**
     * Retrieve the users which you are following.
     */
    public function getFollowing()
    {
        return $this->client->userFollows()->getFollowing()->performRequest();
    }

    /**
     * Follow a user.
     */
    public function followUser($userId)
    {
        return $this->client->userFollows()->follow()->performRequest(['target_user_id' => $userId]);
    }

    /**
     * Unfollow a user.
     */
    public function unfollowUser($userId)
    {
        return $this->client->userFollows()->unfollow($userId)->performRequest(['target_user_id' => $userId]);
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
        return $this->client->userMutes()->lookup()->performRequest();
    }

    /**
     * Mute a user by ID.
     */
    public function muteUser($userId)
    {
        return $this->client->userMutes()->mute()->performRequest(['target_user_id' => $userId]);
    }

    /**
     * Unmute a user by ID.
     */
    public function unmuteUser($userId)
    {
        return $this->client->userMutes()->unmute()->performRequest(['target_user_id' => $userId]);
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
     * Get character count for text
     */
    public function getCharacterCount($text)
    {
        return mb_strlen($text, 'UTF-8');
    }
} 