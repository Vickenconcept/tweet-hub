<?php

namespace App\Services;

use Noweh\TwitterApi\Client;

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
        return $this->client->timeline()->getRecentMentions($accountId)->performRequest();
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
        $data = ['text' => $text];
        if (!empty($mediaIds)) {
            $data['media'] = ['media_ids' => $mediaIds];
        }
        if ($inReplyToTweetId) {
            $data['reply'] = ['in_reply_to_tweet_id' => $inReplyToTweetId];
        }
        return $this->client->tweet()->create()->performRequest($data);
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
} 