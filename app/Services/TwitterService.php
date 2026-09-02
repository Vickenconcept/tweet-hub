<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * X/Twitter operations via Zernio API (replaces direct X Developer API).
 */
class TwitterService
{
    public const MAX_THREAD_PARTS = 2;

    protected ZernioService $zernio;

    protected ?string $zernioAccountId;

    protected array $settings;

    public function __construct(array|User $settings)
    {
        if ($settings instanceof User) {
            $settings = $settings->getTwitterServiceSettings();
        }

        $this->zernio = app(ZernioService::class);
        $this->settings = $settings;
        $this->zernioAccountId = $settings['zernio_account_id']
            ?? $settings['account_id']
            ?? null;
    }

    public static function forUser(User $user): self
    {
        return new self($user);
    }

    public function getRecentMentionsFast($accountId)
    {
        return $this->getRecentMentions($accountId);
    }

    public function isRateLimitedForMentions(): array
    {
        return ['rate_limited' => false];
    }

    public function isRateLimitedForSearch(): array
    {
        return ['rate_limited' => false];
    }

    public function isRateLimitedForFollowers(): array
    {
        return ['rate_limited' => false];
    }

    public function getRecentMentions($accountId)
    {
        $this->assertAccount();

        $tweets = [];
        $users = [];
        $seenIds = [];

        $account = $this->zernio->getAccount($this->zernioAccountId);
        $username = ltrim($account['username'] ?? '', '@');

        if ($username) {
            try {
                $query = "@{$username} -from:{$username} -is:retweet";
                $result = $this->searchTwitterWithCapabilityRetry($query, 25);
                $mapped = $this->mapSearchResults($result);

                foreach ($mapped->data as $tweet) {
                    if (! isset($seenIds[$tweet->id])) {
                        $tweets[] = $tweet;
                        $seenIds[$tweet->id] = true;
                    }
                }

                foreach ($mapped->includes->users as $user) {
                    $users[$user->id] = $user;
                }
            } catch (\Throwable $e) {
                Log::warning('Zernio global mention search failed', [
                    'username' => $username,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $posts = $this->zernio->listInboxComments('twitter', $this->zernioAccountId, 15);
        $items = $posts['data'] ?? [];

        foreach (array_slice($items, 0, 10) as $post) {
            $postId = $post['id'] ?? null;
            if (! $postId) {
                continue;
            }

            try {
                $thread = $this->zernio->getPostComments((string) $postId, $this->zernioAccountId);
                $comments = $thread['data'] ?? $thread['comments'] ?? [];
            } catch (\Throwable $e) {
                Log::warning('Zernio getPostComments failed', ['post_id' => $postId, 'error' => $e->getMessage()]);
                continue;
            }

            foreach ($comments as $comment) {
                $mapped = $this->mapCommentToTweet($comment);
                if (! $mapped) {
                    continue;
                }

                $tweetId = $mapped['tweet']->id;
                if (isset($seenIds[$tweetId])) {
                    continue;
                }

                $tweets[] = $mapped['tweet'];
                $seenIds[$tweetId] = true;

                if ($mapped['user']) {
                    $users[$mapped['user']->id] = $mapped['user'];
                }
            }
        }

        usort($tweets, function ($a, $b) {
            $dateA = $a->created_at ?? null;
            $dateB = $b->created_at ?? null;

            if (! $dateA || ! $dateB) {
                return 0;
            }

            return strtotime($dateB) <=> strtotime($dateA);
        });

        return (object) [
            'data' => array_values($tweets),
            'includes' => (object) ['users' => array_values($users)],
            'meta' => (object) ['result_count' => count($tweets)],
        ];
    }

    public function searchTweets($usernames = [], $keywords = [], $locales = [], $pageSize = 10)
    {
        $query = '';
        if (! empty($keywords)) {
            $query = is_array($keywords) ? implode(' ', $keywords) : (string) $keywords;
        } elseif (! empty($usernames)) {
            $query = '@'.ltrim((string) $usernames[0], '@');
        }

        return $this->searchTweetsDirect($query, $pageSize);
    }

    public function searchTweetsByKeyword($keyword, $pageSize = 10)
    {
        return $this->searchTweetsDirect($keyword, $pageSize);
    }

    public function searchTweetsDirect($query, $pageSize = 10)
    {
        $this->assertAccount();

        $result = $this->searchTwitterWithCapabilityRetry((string) $query, (int) $pageSize);

        return $this->mapSearchResults($result);
    }

    public function getRecentTweets($accountId)
    {
        $this->assertAccount();

        $posts = $this->zernio->listAccountPosts($this->zernioAccountId);
        $data = [];

        foreach ($posts as $post) {
            $id = $this->extractTweetIdFromPermalink($post['permalink'] ?? '') ?? ($post['id'] ?? null);
            if (! $id) {
                continue;
            }

            $data[] = (object) [
                'id' => (string) $id,
                'text' => $post['message'] ?? '',
                'created_at' => $post['createdTime'] ?? null,
                'public_metrics' => (object) [
                    'like_count' => $post['likeCount'] ?? 0,
                    'reply_count' => $post['commentCount'] ?? 0,
                    'retweet_count' => $post['shareCount'] ?? 0,
                    'quote_count' => 0,
                    'impression_count' => 0,
                ],
            ];
        }

        return (object) ['data' => $data];
    }

    public function getReverseChronological()
    {
        return $this->getRecentTweets($this->zernioAccountId);
    }

    public function getLikedTweets($accountId, $pageSize = 10)
    {
        return (object) ['data' => []];
    }

    public function getUsersWhoLiked($tweetId, $pageSize = 10)
    {
        return (object) ['data' => []];
    }

    public function getReplies($tweetId)
    {
        $this->assertAccount();

        try {
            $thread = $this->zernio->getPostComments((string) $tweetId, $this->zernioAccountId);
            $comments = $thread['data'] ?? $thread['comments'] ?? [];
            $data = [];

            foreach ($comments as $comment) {
                $mapped = $this->mapCommentToTweet($comment);
                if ($mapped) {
                    $data[] = $mapped['tweet'];
                }
            }

            return (object) ['data' => $data];
        } catch (\Throwable $e) {
            Log::warning('Zernio getReplies failed', ['tweet_id' => $tweetId, 'error' => $e->getMessage()]);

            return (object) ['data' => []];
        }
    }

    public function fetchTweet($tweetId)
    {
        $this->assertAccount();

        try {
            $tweet = $this->zernio->getTwitterTweet($this->zernioAccountId, (string) $tweetId);

            return (object) [
                'data' => (object) [
                    'id' => (string) ($tweet['id'] ?? $tweetId),
                    'text' => $tweet['text'] ?? '',
                    'public_metrics' => (object) [
                        'like_count' => $tweet['likeCount'] ?? 0,
                        'reply_count' => $tweet['replyCount'] ?? 0,
                        'retweet_count' => $tweet['retweetCount'] ?? 0,
                        'quote_count' => $tweet['quoteCount'] ?? 0,
                    ],
                ],
            ];
        } catch (\Throwable $e) {
            Log::warning('Zernio getTwitterTweet failed', ['tweet_id' => $tweetId, 'error' => $e->getMessage()]);

            return (object) [
                'data' => (object) [
                    'id' => (string) $tweetId,
                    'text' => '',
                    'public_metrics' => (object) [
                        'like_count' => 0,
                        'reply_count' => 0,
                        'retweet_count' => 0,
                        'quote_count' => 0,
                    ],
                ],
            ];
        }
    }

    public function createTweet($text, $mediaIds = [], $inReplyToTweetId = null, bool $strictVerify = false)
    {
        return $this->publishTweet($text, $mediaIds, $inReplyToTweetId ? [
            'replyToTweetId' => (string) $inReplyToTweetId,
        ] : [], isReply: (bool) $inReplyToTweetId, strictVerify: $strictVerify);
    }

    /**
     * Publish a thread using Zernio threadItems (single API call = 1 post toward hourly limit).
     *
     * @param  array<int, string>  $parts
     * @param  array<int, array<int, string>>  $mediaIdsByPart
     */
    public function createThread(array $parts, array $mediaIdsByPart = []): object
    {
        $this->assertAccount();

        $threadItems = [];

        foreach (array_values($parts) as $index => $text) {
            $text = trim($text);
            if ($text === '') {
                continue;
            }

            if (mb_strlen($text, 'UTF-8') > 280) {
                $text = $this->truncateForTwitter($text);
            }

            $threadItems[] = [
                'index' => $index,
                'content' => $text,
                'mediaIds' => $mediaIdsByPart[$index] ?? [],
            ];
        }

        if ($threadItems === []) {
            throw new \RuntimeException('Thread has no content.');
        }

        if (count($threadItems) > self::MAX_THREAD_PARTS) {
            throw new \RuntimeException('Threads are limited to '.self::MAX_THREAD_PARTS.' tweets.');
        }

        if (count($threadItems) === 1) {
            $response = $this->createTweet(
                $threadItems[0]['content'],
                $threadItems[0]['mediaIds']
            );
            $response->data->tweet_ids = [$response->data->id];
            $response->data->parts = 1;

            return $response;
        }

        return $this->publishThreadViaThreadItems($threadItems);
    }

    /**
     * @param  array<int, array{index: int, content: string, mediaIds: array<int, string>}>  $threadItems
     */
    protected function publishThreadViaThreadItems(array $threadItems): object
    {
        $zernioItems = [];

        foreach ($threadItems as $item) {
            $entry = ['content' => $item['content']];
            $mediaItems = $this->buildMediaItems($item['mediaIds'] ?? []);
            if ($mediaItems !== []) {
                $entry['mediaItems'] = $mediaItems;
            }
            $zernioItems[] = $entry;
        }

        Log::info('Publishing thread via Zernio threadItems', [
            'parts' => count($zernioItems),
        ]);

        $payload = [
            'content' => $zernioItems[0]['content'],
            'publishNow' => true,
            'platforms' => [[
                'platform' => 'twitter',
                'accountId' => $this->zernioAccountId,
                'platformSpecificData' => [
                    'threadItems' => $zernioItems,
                ],
            ]],
        ];

        $result = $this->zernio->createPost($payload, Str::uuid()->toString());
        $postId = $result['post']['_id'] ?? $result['_id'] ?? null;
        $lastPost = $result['post'] ?? $result;

        $this->throwIfThreadRateLimited($lastPost);

        $tweetIds = $this->pollThreadPostBriefly($postId, count($zernioItems), $result);

        if ($tweetIds === []) {
            throw new \RuntimeException('Thread was submitted but could not be confirmed on X.');
        }

        while (count($tweetIds) < count($threadItems)) {
            $tweetIds[] = $tweetIds[0];
        }

        Log::info('Thread published via threadItems', [
            'tweet_ids' => $tweetIds,
            'parts' => count($threadItems),
            'zernio_post_id' => $postId,
        ]);

        return (object) [
            'data' => (object) [
                'id' => $tweetIds[0] ?? null,
                'tweet_ids' => $tweetIds,
                'text' => $threadItems[0]['content'],
                'parts' => count($threadItems),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $initialResult
     * @return array<int, string>
     */
    protected function pollThreadPostBriefly(?string $postId, int $expectedParts, array $initialResult): array
    {
        $lastPost = $initialResult['post'] ?? $initialResult;

        for ($attempt = 0; $attempt < 6; $attempt++) {
            if ($attempt > 0) {
                sleep(2);
                if ($postId) {
                    $lastPost = $this->zernio->getPost((string) $postId);
                }
            }

            $this->throwIfThreadRateLimited($lastPost);

            $tweetIds = $this->extractThreadTweetIdsFromPost($lastPost);
            if ($tweetIds === []) {
                $rootId = $this->extractTweetIdFromPostResult(['post' => $lastPost]);
                if ($rootId) {
                    $tweetIds = [$rootId];
                }
            }

            if (count($tweetIds) >= $expectedParts) {
                return array_slice($tweetIds, 0, $expectedParts);
            }

            $platformError = $this->extractPlatformErrorFromPost($lastPost);
            if ($platformError && ! $this->isRetryableThreadError($platformError)) {
                throw new \RuntimeException($this->formatThreadError($platformError));
            }

            $platformStatus = $this->extractPlatformPublishStatus($lastPost);
            if (in_array($platformStatus, ['failed', 'error', 'rejected'], true)) {
                $message = $this->extractPlatformErrorFromPost($lastPost) ?? 'Thread failed to publish on X.';

                throw new \RuntimeException($this->formatThreadError($message));
            }

            if ($this->isPlatformPublishComplete($platformStatus) && $tweetIds !== []) {
                return $tweetIds;
            }
        }

        $tweetIds = $this->extractThreadTweetIdsFromPost($lastPost);
        if ($tweetIds === []) {
            $rootId = $this->extractTweetIdFromPostResult(['post' => $lastPost]);
            if ($rootId) {
                return [$rootId];
            }
        }

        return $tweetIds;
    }

    /**
     * @param  array<string, mixed>  $post
     */
    protected function throwIfThreadRateLimited(array $post): void
    {
        $error = $this->extractPlatformErrorFromPost($post);
        if ($error && $this->isRateLimitError($error)) {
            throw new \RuntimeException($this->formatThreadError($error));
        }
    }

    protected function isRateLimitError(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'hourly limit')
            || str_contains($message, '25/25')
            || str_contains($message, 'rate limit');
    }

    protected function isRetryableThreadError(string $message): bool
    {
        return $this->isRateLimitError($message);
    }

    protected function formatThreadError(string $message): string
    {
        if ($this->isRateLimitError($message)) {
            return 'X posting limit reached (25 posts/hour on this account). Wait for the limit to reset and try again.';
        }

        return $message;
    }

    /**
     * X API (since Feb 2026) only allows in-thread replies when the author @mentioned
     * or quoted you, or when replying to your own posts.
     */
    public static function canApiReplyToTweet(?string $tweetText, ?string $connectedUsername, ?string $authorUsername): bool
    {
        $connectedUsername = strtolower(ltrim((string) $connectedUsername, '@'));
        if ($connectedUsername === '') {
            return false;
        }

        $authorUsername = strtolower(ltrim((string) $authorUsername, '@'));
        if ($authorUsername !== '' && $authorUsername === $connectedUsername) {
            return true;
        }

        if ($tweetText !== null && $tweetText !== '') {
            return (bool) preg_match('/@'.preg_quote($connectedUsername, '/').'\b/i', $tweetText);
        }

        return false;
    }

    public function replyToKeywordTweet(string $text, string $targetTweetId): object
    {
        return $this->createTweet($text, [], $targetTweetId);
    }

    public function createQuoteTweet(string $text, string $quoteTweetId, array $mediaIds = []): object
    {
        return $this->publishTweet($text, $mediaIds, [
            'quoteTweetId' => (string) $quoteTweetId,
        ], isReply: false);
    }

    /**
     * @param  array<string, mixed>  $platformData
     */
    protected function publishTweet(string $text, array $mediaIds, array $platformData, ?bool $isReply = null, bool $strictVerify = false): object
    {
        $this->assertAccount();

        $charCount = mb_strlen($text, 'UTF-8');
        if ($charCount > 280) {
            throw new \Exception("Tweet exceeds character limit. Current: {$charCount}, Limit: 280");
        }

        $isReply = $isReply ?? isset($platformData['replyToTweetId']);

        $mediaItems = $this->buildMediaItems($mediaIds);

        $payload = [
            'content' => $text,
            'publishNow' => true,
            'platforms' => [[
                'platform' => 'twitter',
                'accountId' => $this->zernioAccountId,
                'platformSpecificData' => $platformData ?: null,
            ]],
        ];

        if (! empty($mediaItems)) {
            $payload['mediaItems'] = $mediaItems;
        }

        $payload['platforms'][0] = array_filter($payload['platforms'][0]);

        $result = $this->zernio->createPost(array_filter($payload), Str::uuid()->toString());

        Log::info('Zernio createPost accepted', [
            'zernio_post_id' => $result['post']['_id'] ?? $result['_id'] ?? null,
            'is_reply' => $isReply,
            'platform_data' => array_keys($platformData),
        ]);

        $tweetId = $this->verifyPostPublished($result, $isReply, $strictVerify);

        Log::info('Zernio tweet published', [
            'tweet_id' => $tweetId,
            'zernio_post_id' => $result['post']['_id'] ?? null,
            'is_reply' => $isReply,
            'strict_verify' => $strictVerify,
        ]);

        return (object) [
            'data' => (object) [
                'id' => $tweetId,
                'text' => $text,
            ],
            'zernio' => $result,
        ];
    }

    public function sendPublicReply(string $tweetId, string $recipientUsername, string $text, bool $asThreadReply = true)
    {
        $recipientUsername = ltrim($recipientUsername, '@');
        $replyText = "@{$recipientUsername} {$text}";

        if (mb_strlen($replyText) > 280) {
            $maxTextLength = 280 - mb_strlen("@{$recipientUsername} ", 'UTF-8') - 1;
            $text = mb_substr($text, 0, max(0, $maxTextLength));
            $replyText = "@{$recipientUsername} {$text}";
        }

        $replyToId = $asThreadReply ? $tweetId : null;
        $result = $this->createTweet($replyText, [], $replyToId);

        return (object) [
            'data' => [
                'sent' => true,
                'message' => 'Public reply sent successfully',
                'reply_tweet_id' => $result->data->id ?? null,
                'method' => 'public_reply',
            ],
            'raw' => $result,
        ];
    }

    public function sendDirectMessage(string $recipientId, string $text)
    {
        $this->assertAccount();

        if (trim($text) === '') {
            throw new \Exception('DM text cannot be empty');
        }

        if (mb_strlen($text) > 10000) {
            $text = $this->truncateForTwitter($text, 10000);
        }

        $conversations = $this->zernio->listInboxConversations($this->zernioAccountId, 100);
        $items = $conversations['data'] ?? $conversations['conversations'] ?? [];

        $conversationId = null;
        foreach ($items as $conversation) {
            $participantId = data_get($conversation, 'participant.id')
                ?? data_get($conversation, 'participantId')
                ?? data_get($conversation, 'participantPlatformId');

            if ((string) $participantId === (string) $recipientId) {
                $conversationId = data_get($conversation, 'id') ?? data_get($conversation, '_id');
                break;
            }
        }

        if (! $conversationId) {
            throw new \Exception('No existing DM conversation found for this recipient. They must message you first on X.');
        }

        $sent = $this->zernio->sendInboxMessage((string) $conversationId, $text, $this->zernioAccountId);
        if (! $sent) {
            throw new \Exception('Failed to send direct message.');
        }

        return (object) ['data' => ['sent' => true]];
    }

    public function uploadMedia($file)
    {
        return $this->uploadLocalMedia($file);
    }

    public function uploadLocalMedia($localPath)
    {
        if (Str::startsWith($localPath, ['http://', 'https://'])) {
            return $localPath;
        }

        $publicUrl = $this->zernio->uploadLocalFile($localPath);
        if (! $publicUrl) {
            Log::warning('Zernio media upload failed', ['path' => $localPath]);

            return null;
        }

        return $publicUrl;
    }

    public function getQuoteTweets($tweetId)
    {
        return (object) ['data' => []];
    }

    public function retweet($tweetId)
    {
        $this->assertAccount();

        $result = $this->zernio->retweetTwitterPost($this->zernioAccountId, (string) $tweetId);

        return (object) [
            'data' => (object) [
                'retweeted' => (bool) ($result['retweeted'] ?? true),
                'id' => $result['tweetId'] ?? $tweetId,
                'message' => $result['message'] ?? null,
            ],
        ];
    }

    public function likeTweet($tweetId)
    {
        $this->assertAccount();

        $result = $this->zernio->likeTwitterPost($this->zernioAccountId, (string) $tweetId);

        return (object) [
            'data' => (object) [
                'liked' => (bool) ($result['liked'] ?? $result['success'] ?? true),
                'message' => $result['message'] ?? 'Tweet liked successfully',
            ],
        ];
    }

    public function hideReply($tweetId)
    {
        throw new \RuntimeException('Hide reply is not available yet.');
    }

    public function unhideReply($tweetId)
    {
        throw new \RuntimeException('Unhide reply is not available yet.');
    }

    public function getBlockedUsers()
    {
        return (object) ['data' => []];
    }

    public function getFollowerStats(?string $fromDate = null, ?string $toDate = null, string $granularity = 'daily'): array
    {
        $this->assertAccount();

        return $this->zernio->getFollowerStats(array_filter([
            'accountIds' => $this->zernioAccountId,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'granularity' => $granularity,
        ]));
    }

    public function followUser($userId)
    {
        $this->assertAccount();

        $result = $this->zernio->followTwitterUser($this->zernioAccountId, (string) $userId);

        return (object) [
            'data' => (object) [
                'following' => (bool) ($result['following'] ?? true),
                'pending_follow' => (bool) ($result['pending_follow'] ?? false),
                'target_user_id' => $result['targetUserId'] ?? $userId,
            ],
        ];
    }

    public function unfollowUser($userId)
    {
        $this->assertAccount();

        $result = $this->zernio->unfollowTwitterUser($this->zernioAccountId, (string) $userId);

        return (object) [
            'data' => (object) [
                'following' => (bool) ($result['following'] ?? false),
                'target_user_id' => $result['targetUserId'] ?? $userId,
            ],
        ];
    }

    public function findMe()
    {
        $this->assertAccount();
        $account = $this->zernio->getAccount($this->zernioAccountId);
        $username = ltrim($account['username'] ?? '', '@');

        return (object) [
            'data' => (object) [
                'id' => $account['metadata']['platformUserId'] ?? $account['_id'] ?? null,
                'username' => $username,
                'name' => $account['displayName'] ?? $username,
                'profile_image_url' => $account['profilePicture'] ?? null,
            ],
        ];
    }

    public function findUser($value, $mode)
    {
        $userId = $this->resolveTwitterUserId((string) $value);

        return (object) [
            'data' => (object) [
                'id' => $userId,
                'username' => null,
                'name' => null,
            ],
        ];
    }

    /**
     * Resolve a numeric X user ID or @handle to a user ID for follow/unfollow APIs.
     */
    public function resolveTwitterUserId(string $input): string
    {
        $this->assertAccount();

        $input = trim($input);
        if ($input === '') {
            throw new \RuntimeException('Enter an @handle or numeric user ID.');
        }

        if (preg_match('/^\d+$/', $input)) {
            return $input;
        }

        $username = $this->extractTwitterUsername($input);
        if ($username === null) {
            throw new \RuntimeException('Enter a valid @handle, profile URL, or numeric user ID.');
        }

        $queries = [
            "from:{$username} -is:retweet",
            "from:{$username}",
            "@{$username}",
            $username,
        ];

        foreach ($queries as $query) {
            try {
                $result = $this->searchTwitterWithCapabilityRetry($query, 10);
                $mapped = $this->mapSearchResults($result);

                foreach ($mapped->data as $tweet) {
                    if (strtolower($tweet->author_username ?? '') === strtolower($username)
                        && ($tweet->author_id ?? '') !== ''
                        && $tweet->author_id !== 'unknown') {
                        return (string) $tweet->author_id;
                    }
                }

                foreach ($mapped->includes->users ?? [] as $user) {
                    if (strtolower(ltrim($user->username ?? '', '@')) === strtolower($username)) {
                        return (string) $user->id;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Username lookup search failed', [
                    'username' => $username,
                    'query' => $query,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $profileUserId = $this->lookupTwitterUserIdFromPublicProfile($username);
        if ($profileUserId) {
            Log::info('Resolved X username from public profile', [
                'username' => $username,
                'user_id' => $profileUserId,
            ]);

            return $profileUserId;
        }

        throw new \RuntimeException(
            "Could not find @{$username}. Check the handle is correct and the profile is public."
        );
    }

    protected function extractTwitterUsername(string $input): ?string
    {
        $input = trim($input);

        if (preg_match('#(?:https?://)?(?:www\.)?(?:x|twitter)\.com/([A-Za-z0-9_]{1,15})(?:/)?(?:\?.*)?$#i', $input, $matches)) {
            $candidate = $matches[1];
            if (! in_array(strtolower($candidate), ['status', 'i', 'intent', 'search', 'home'], true)) {
                return $candidate;
            }
        }

        $username = ltrim($input, '@');
        if (preg_match('/^[A-Za-z0-9_]{1,15}$/', $username)) {
            return $username;
        }

        return null;
    }

    protected function lookupTwitterUserIdFromPublicProfile(string $username): ?string
    {
        $cacheKey = 'twitter_user_id:'.strtolower($username);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        foreach ([
            'https://x.com/'.$username,
            'https://mobile.twitter.com/'.$username,
        ] as $url) {
            $userId = $this->fetchTwitterUserIdFromProfileUrl($url);
            if ($userId) {
                Cache::put($cacheKey, $userId, now()->addDays(7));

                return $userId;
            }
        }

        return null;
    }

    protected function fetchTwitterUserIdFromProfileUrl(string $url): ?string
    {
        try {
            $response = Http::connectTimeout(5)
                ->timeout(20)
                ->withOptions(['stream' => true])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $stream = $response->toPsrResponse()->getBody();
            $buffer = '';
            $maxBytes = 256 * 1024;

            while (! $stream->eof() && strlen($buffer) < $maxBytes) {
                $buffer .= $stream->read(8192);

                if ($userId = $this->parseTwitterUserIdFromProfileHtml($buffer)) {
                    $stream->close();

                    return $userId;
                }
            }

            return $this->parseTwitterUserIdFromProfileHtml($buffer);
        } catch (\Throwable $e) {
            Log::warning('Public profile lookup failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    protected function parseTwitterUserIdFromProfileHtml(string $html): ?string
    {
        if (preg_match('/profile_banners\/(\d+)\//', $html, $matches)) {
            return $matches[1];
        }

        if (preg_match('/"rest_id":"(\d+)"/', $html, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function getMutedUsers()
    {
        return (object) ['data' => []];
    }

    public function muteUser($userId)
    {
        throw new \RuntimeException('Mute is not available yet.');
    }

    public function unmuteUser($userId)
    {
        throw new \RuntimeException('Unmute is not available yet.');
    }

    public function blockUser($userId)
    {
        throw new \RuntimeException('Block is not available yet.');
    }

    public function unblockUser($userId)
    {
        throw new \RuntimeException('Unblock is not available yet.');
    }

    public function truncateForTwitter($text, $maxLength = 280)
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength - 1).'…';
    }

    public function checkApiStatus()
    {
        return ['status' => 'ok', 'provider' => 'zernio'];
    }

    public function getCharacterCount($text)
    {
        return mb_strlen($text, 'UTF-8');
    }

    public function streamMentions($username, callable $callback, $timeout = 30)
    {
        throw new \RuntimeException('Streaming mentions is not supported.');
    }

    protected function assertAccount(): void
    {
        if (! $this->zernioAccountId) {
            throw new \RuntimeException('No X account connected. Please connect your X account in settings.');
        }

        if (! $this->zernio->hasApiKey()) {
            throw new \RuntimeException('X connection is not configured.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function searchTwitterWithCapabilityRetry(string $query, int $limit): array
    {
        try {
            return $this->zernio->searchTwitterTweets($this->zernioAccountId, $query, $limit);
        } catch (\RuntimeException $e) {
            if (! $this->isXAnalyticsNotEnabledError($e)) {
                throw $e;
            }

            Log::info('Enabling X analytics capability and retrying search', [
                'account_id' => $this->zernioAccountId,
            ]);

            $this->zernio->enableXAccountCapabilities($this->zernioAccountId);

            return $this->zernio->searchTwitterTweets($this->zernioAccountId, $query, $limit);
        }
    }

    protected function isXAnalyticsNotEnabledError(\Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'X_ANALYTICS_NOT_ENABLED')
            || str_contains($message, 'X analytics is not enabled');
    }

    /**
     * @param  array<int, string>  $mediaIds
     * @return array<int, array<string, string>>
     */
    protected function buildMediaItems(array $mediaIds): array
    {
        $items = [];

        foreach ($mediaIds as $media) {
            if (! $media) {
                continue;
            }

            $url = (string) $media;
            if (! Str::startsWith($url, ['http://', 'https://'])) {
                $uploaded = $this->uploadLocalMedia($url);
                if (! $uploaded) {
                    continue;
                }
                $url = $uploaded;
            }

            $type = Str::contains($url, ['.mp4', '.mov', 'video']) ? 'video' : 'image';
            $items[] = ['type' => $type, 'url' => $url];
        }

        return $items;
    }

    protected function extractTweetIdFromPostResult(array $result): ?string
    {
        $post = $result['post'] ?? $result;
        $platforms = $post['platforms'] ?? [];

        foreach ($platforms as $platform) {
            if (($platform['platform'] ?? null) !== 'twitter') {
                continue;
            }

            $url = $platform['platformPostUrl'] ?? $platform['platformPostURL'] ?? null;
            $fromUrl = $url ? $this->extractTweetIdFromPermalink($url) : null;
            if ($fromUrl) {
                return $fromUrl;
            }

            if (! empty($platform['platformPostId'])) {
                return (string) $platform['platformPostId'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<int, string>
     */
    protected function verifyThreadPublished(array $result, int $expectedParts): array
    {
        $postId = $result['post']['_id'] ?? $result['_id'] ?? null;
        $lastPost = $result['post'] ?? $result;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            if ($attempt > 0) {
                usleep(500000);
                if ($postId) {
                    $lastPost = $this->zernio->getPost((string) $postId);
                }
            }

            $tweetIds = $this->extractThreadTweetIdsFromPost($lastPost);
            if (count($tweetIds) >= $expectedParts) {
                return $tweetIds;
            }

            $platformError = $this->extractPlatformErrorFromPost($lastPost);
            if ($platformError) {
                throw new \RuntimeException($platformError);
            }

            $platformStatus = $this->extractPlatformPublishStatus($lastPost);
            if (in_array($platformStatus, ['failed', 'error', 'rejected'], true)) {
                throw new \RuntimeException('Thread failed to publish on X.');
            }

            if ($attempt === 4 && $tweetIds !== []) {
                Log::warning('Zernio thread partially confirmed', [
                    'expected_parts' => $expectedParts,
                    'confirmed_ids' => $tweetIds,
                    'zernio_post_id' => $postId,
                ]);

                return $tweetIds;
            }
        }

        throw new \RuntimeException('Thread was submitted but could not be confirmed on X.');
    }

    /**
     * @param  array<string, mixed>  $post
     * @return array<int, string>
     */
    protected function extractThreadTweetIdsFromPost(array $post): array
    {
        $ids = [];

        foreach ($post['platforms'] ?? [] as $platform) {
            if (($platform['platform'] ?? null) !== 'twitter') {
                continue;
            }

            foreach ($platform['threadItems'] ?? [] as $item) {
                $id = $item['platformPostId'] ?? null;
                $url = $item['platformPostUrl'] ?? $item['platformPostURL'] ?? null;
                if ($id) {
                    $ids[] = (string) $id;
                } elseif ($url && ($fromUrl = $this->extractTweetIdFromPermalink($url))) {
                    $ids[] = $fromUrl;
                }
            }

            foreach ($platform['publishedThreadItems'] ?? $platform['threadPosts'] ?? [] as $item) {
                $id = is_array($item)
                    ? ($item['platformPostId'] ?? $item['id'] ?? null)
                    : null;
                $url = is_array($item)
                    ? ($item['platformPostUrl'] ?? $item['platformPostURL'] ?? null)
                    : null;
                if ($id) {
                    $ids[] = (string) $id;
                } elseif ($url && ($fromUrl = $this->extractTweetIdFromPermalink($url))) {
                    $ids[] = $fromUrl;
                }
            }

            if ($ids !== []) {
                return array_values(array_unique($ids));
            }

            $rootId = $this->extractTweetIdFromPostResult(['post' => ['platforms' => [$platform]]]);
            if ($rootId) {
                return [$rootId];
            }
        }

        return $ids;
    }

    /**
     * Confirm the post actually published on X before returning a tweet ID.
     *
     * @param  array<string, mixed>  $result
     */
    protected function verifyPostPublished(array $result, bool $isReply = false, bool $strictVerify = false): string
    {
        $postId = $result['post']['_id'] ?? $result['_id'] ?? null;
        if (! $postId) {
            throw new \RuntimeException('Post was created but no post ID was returned.');
        }

        $lastPost = $result['post'] ?? $result;
        $maxAttempts = $strictVerify ? ($isReply ? 12 : 8) : ($isReply ? 8 : 6);
        $pollDelayMicros = $strictVerify ? 750000 : 500000;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            if ($attempt > 0) {
                usleep($pollDelayMicros);
                $lastPost = $this->zernio->getPost((string) $postId);
            }

            $platformError = $this->extractPlatformErrorFromPost($lastPost);
            if ($platformError) {
                Log::warning('Zernio post platform error', [
                    'zernio_post_id' => $postId,
                    'error' => $platformError,
                    'is_reply' => $isReply,
                    'attempt' => $attempt + 1,
                    'platform' => $this->extractTwitterPlatformPayload($lastPost),
                ]);
                throw new \RuntimeException($platformError);
            }

            $platformStatus = $this->extractPlatformPublishStatus($lastPost);
            if (in_array($platformStatus, ['failed', 'error', 'rejected'], true)) {
                throw new \RuntimeException('Post failed to publish on X.');
            }

            $tweetId = $this->extractTweetIdFromPostResult(['post' => $lastPost]);
            if (! $tweetId) {
                continue;
            }

            if ($strictVerify) {
                if (! $this->isPlatformPublishComplete($platformStatus)) {
                    continue;
                }

                if ($this->confirmTweetExistsOnX($tweetId)) {
                    Log::info('Zernio post confirmed on X', [
                        'zernio_post_id' => $postId,
                        'tweet_id' => $tweetId,
                        'platform_status' => $platformStatus,
                        'attempt' => $attempt + 1,
                    ]);

                    return $tweetId;
                }

                continue;
            }

            if ($this->isPlatformPublishComplete($platformStatus)) {
                return $tweetId;
            }

            if ($platformStatus === '' && $attempt >= 2) {
                return $tweetId;
            }
        }

        Log::warning('Zernio post publish unconfirmed', [
            'zernio_post_id' => $postId,
            'is_reply' => $isReply,
            'strict_verify' => $strictVerify,
            'platform_status' => $this->extractPlatformPublishStatus($lastPost),
            'platform' => $this->extractTwitterPlatformPayload($lastPost),
        ]);

        if ($isReply) {
            throw new \RuntimeException(
                'X did not publish this reply. You can only reply in-thread to posts that mention you or your own posts.'
            );
        }

        throw new \RuntimeException('Post was submitted but could not be confirmed on X. Please check your profile.');
    }

    protected function isPlatformPublishComplete(string $status): bool
    {
        return in_array($status, ['published', 'success', 'completed', 'posted', 'sent', 'live'], true);
    }

    protected function confirmTweetExistsOnX(string $tweetId): bool
    {
        try {
            $tweet = $this->zernio->getTwitterTweet($this->zernioAccountId, $tweetId);
            $id = data_get($tweet, 'id')
                ?? data_get($tweet, 'data.id')
                ?? data_get($tweet, 'tweet.id');

            return $id !== null && (string) $id === (string) $tweetId;
        } catch (\Throwable $e) {
            Log::warning('Tweet existence check failed', [
                'tweet_id' => $tweetId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $post
     */
    protected function extractPlatformPublishStatus(array $post): string
    {
        foreach ($post['platforms'] ?? [] as $platform) {
            if (($platform['platform'] ?? null) !== 'twitter') {
                continue;
            }

            return strtolower((string) (
                $platform['status']
                ?? $platform['publishStatus']
                ?? $platform['state']
                ?? ''
            ));
        }

        return strtolower((string) ($post['status'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $post
     */
    protected function extractPlatformErrorFromPost(array $post): ?string
    {
        foreach ($post['platforms'] ?? [] as $platform) {
            if (($platform['platform'] ?? null) !== 'twitter') {
                continue;
            }

            $error = $platform['error']
                ?? $platform['platformError']
                ?? $platform['failureReason']
                ?? $platform['errorMessage']
                ?? $platform['message']
                ?? data_get($platform, 'publishResult.error')
                ?? data_get($platform, 'publishResult.message')
                ?? data_get($platform, 'response.error')
                ?? data_get($platform, 'response.message')
                ?? data_get($platform, 'response.detail')
                ?? null;

            if (is_array($error)) {
                $error = $error['message'] ?? $error['error'] ?? $error['detail'] ?? json_encode($error);
            }

            if (is_string($error) && trim($error) !== '') {
                return trim($error);
            }

            $status = strtolower((string) ($platform['status'] ?? $platform['publishStatus'] ?? ''));
            if (in_array($status, ['failed', 'error', 'rejected'], true)) {
                Log::warning('Zernio twitter platform failed without message', [
                    'platform' => $platform,
                ]);

                return 'Post failed to publish on X.';
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $post
     * @return array<string, mixed>|null
     */
    protected function extractTwitterPlatformPayload(array $post): ?array
    {
        foreach ($post['platforms'] ?? [] as $platform) {
            if (($platform['platform'] ?? null) === 'twitter') {
                return $platform;
            }
        }

        return null;
    }

    protected function extractTweetIdFromPermalink(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (preg_match('/status\/(\d+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @return object{data: array<int, object>, includes: object{users: array<int, object>}}
     */
    protected function mapSearchResults(array $result): object
    {
        $tweets = [];
        $users = [];

        foreach ($result['tweets'] ?? [] as $tweet) {
            $author = $tweet['author'] ?? [];
            $authorId = (string) ($author['id'] ?? 'unknown');

            $tweets[] = (object) [
                'id' => (string) ($tweet['id'] ?? ''),
                'text' => (string) ($tweet['text'] ?? ''),
                'author_id' => $authorId,
                'author_username' => ltrim((string) ($author['username'] ?? ''), '@'),
                'created_at' => $tweet['created'] ?? $tweet['createdAt'] ?? null,
            ];

            if ($authorId !== 'unknown') {
                $users[$authorId] = (object) [
                    'id' => $authorId,
                    'username' => ltrim((string) ($author['username'] ?? 'unknown'), '@'),
                    'name' => $author['displayName'] ?? $author['username'] ?? 'unknown',
                ];
            }
        }

        return (object) [
            'data' => $tweets,
            'includes' => (object) ['users' => array_values($users)],
            'meta' => (object) [
                'result_count' => count($tweets),
                'next_cursor' => $result['pagination']['nextCursor'] ?? $result['nextCursor'] ?? null,
            ],
        ];
    }

    /**
     * @return array{tweet: object, user: ?object}|null
     */
    protected function mapCommentToTweet(array $comment): ?array
    {
        $id = $comment['id'] ?? $comment['commentId'] ?? $comment['platformCommentId'] ?? null;
        $text = $comment['text'] ?? $comment['message'] ?? '';
        $author = $comment['author'] ?? $comment['from'] ?? [];

        if (! $id) {
            return null;
        }

        $authorId = is_array($author)
            ? ($author['id'] ?? $author['platformId'] ?? 'unknown')
            : 'unknown';
        $username = is_array($author)
            ? ($author['username'] ?? $author['name'] ?? 'unknown')
            : 'unknown';

        $userObj = (object) [
            'id' => (string) $authorId,
            'username' => ltrim((string) $username, '@'),
            'name' => is_array($author) ? ($author['name'] ?? $username) : $username,
        ];

        $tweet = (object) [
            'id' => (string) $id,
            'text' => (string) $text,
            'author_id' => (string) $authorId,
            'created_at' => $comment['createdTime'] ?? $comment['created_at'] ?? now()->toIso8601String(),
        ];

        return ['tweet' => $tweet, 'user' => $userObj];
    }
}
