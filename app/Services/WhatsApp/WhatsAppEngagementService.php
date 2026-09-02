<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use App\Services\TwitterService;
use Illuminate\Support\Facades\Cache;

class WhatsAppEngagementService
{
    public function fetchMentions(User $user, bool $forceRefresh = false): array
    {
        $cacheKey = "twitter_mentions_{$user->id}";

        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);
            if ($cached && ! empty($cached['data'])) {
                return $this->normalizeTweets($cached['data'], $cached['users'] ?? []);
            }
        }

        $twitter = $this->twitterService($user);
        $response = $twitter->getRecentMentions($user->twitter_account_id);

        $tweets = [];
        $users = [];

        if (is_object($response)) {
            $tweets = isset($response->data) ? (array) $response->data : [];
            $users = isset($response->includes->users) ? (array) $response->includes->users : [];
        } elseif (is_array($response)) {
            $tweets = $response['data'] ?? [];
            $users = $response['includes']['users'] ?? [];
        }

        Cache::put($cacheKey, [
            'data' => $tweets,
            'users' => $users,
            'timestamp' => now()->format('M j, Y g:i A'),
        ], now()->addMinutes(15));

        return $this->normalizeTweets($tweets, $users);
    }

    public function searchTweets(User $user, string $query, int $limit = 3): array
    {
        $twitter = $this->twitterService($user);
        $response = $twitter->searchTweetsDirect($query, $limit);

        $tweets = [];
        $users = [];

        if (is_object($response)) {
            $tweets = isset($response->data) ? (array) $response->data : [];
            $users = isset($response->includes->users) ? (array) $response->includes->users : [];
        } elseif (is_array($response)) {
            $tweets = $response['data'] ?? [];
            $users = $response['includes']['users'] ?? [];
        }

        return $this->normalizeTweets($tweets, $users);
    }

    public function extractTweetIdFromUrl(string $url): ?string
    {
        if (preg_match('/(?:twitter|x)\.com\/\w+\/status\/(\d+)/i', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function replyToTweet(User $user, string $tweetId, string $text): object
    {
        $twitter = $this->twitterService($user);

        return $twitter->createTweet($text, [], $tweetId);
    }

    public function retweet(User $user, string $tweetId): void
    {
        $this->twitterService($user)->retweet($tweetId);
    }

    public function like(User $user, string $tweetId): void
    {
        $this->twitterService($user)->likeTweet($tweetId);
    }

    /**
     * @return array{label: string, following: bool, pending: bool}
     */
    public function followTarget(User $user, string $target): array
    {
        $twitter = $this->twitterService($user);
        $userId = $twitter->resolveTwitterUserId($target);
        $result = $twitter->followUser($userId);
        $label = str_starts_with($target, '@') ? $target : '@'.ltrim($target, '@');

        return [
            'label' => $label,
            'following' => (bool) ($result->data->following ?? true),
            'pending' => (bool) ($result->data->pending_follow ?? false),
        ];
    }

    /**
     * @return array{label: string}
     */
    public function unfollowTarget(User $user, string $target): array
    {
        $twitter = $this->twitterService($user);
        $userId = $twitter->resolveTwitterUserId($target);
        $twitter->unfollowUser($userId);
        $label = str_starts_with($target, '@') ? $target : '@'.ltrim($target, '@');

        return ['label' => $label];
    }

    public function getKeywords(User $user): array
    {
        if (! $user->monitored_keywords) {
            return [];
        }

        $decoded = json_decode($user->monitored_keywords, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    public function addKeyword(User $user, string $keyword): array
    {
        $keyword = trim($keyword);
        $keywords = $this->getKeywords($user);

        if (in_array($keyword, $keywords, true)) {
            throw new \RuntimeException("Keyword \"{$keyword}\" is already monitored.");
        }

        $keywords[] = $keyword;
        $user->update(['monitored_keywords' => json_encode($keywords)]);

        return $keywords;
    }

    public function removeKeyword(User $user, string $keyword): array
    {
        $keyword = trim($keyword);
        $keywords = array_values(array_filter(
            $this->getKeywords($user),
            fn ($k) => strcasecmp($k, $keyword) !== 0
        ));

        if (count($keywords) === count($this->getKeywords($user))) {
            throw new \RuntimeException("Keyword \"{$keyword}\" not found.");
        }

        $user->update(['monitored_keywords' => json_encode($keywords)]);

        return $keywords;
    }

    public function analyticsSummary(User $user, string $tweetId): array
    {
        $twitter = $this->twitterService($user);

        $likes = $twitter->getUsersWhoLiked($tweetId, 100);
        $quotes = $twitter->getQuoteTweets($tweetId);
        $replies = $twitter->getReplies($tweetId);

        return [
            'likes' => $this->countResults($likes),
            'quotes' => $this->countResults($quotes),
            'replies' => $this->countResults($replies),
        ];
    }

    protected function normalizeTweets(array $tweets, array $users = []): array
    {
        $userMap = [];
        foreach ($users as $user) {
            $id = is_object($user) ? ($user->id ?? null) : ($user['id'] ?? null);
            if ($id) {
                $userMap[$id] = $user;
            }
        }

        $normalized = [];
        foreach ($tweets as $tweet) {
            $id = is_object($tweet) ? ($tweet->id ?? null) : ($tweet['id'] ?? null);
            $text = is_object($tweet) ? ($tweet->text ?? '') : ($tweet['text'] ?? '');
            $authorId = is_object($tweet) ? ($tweet->author_id ?? null) : ($tweet['author_id'] ?? null);

            $author = $userMap[$authorId] ?? null;
            $username = is_object($author)
                ? ($author->username ?? 'unknown')
                : ($author['username'] ?? 'unknown');

            if (! $id) {
                continue;
            }

            $normalized[] = [
                'id' => (string) $id,
                'text' => $text,
                'author' => $username,
                'url' => "https://x.com/{$username}/status/{$id}",
            ];
        }

        return $normalized;
    }

    protected function countResults(mixed $result): int
    {
        if (is_object($result) && isset($result->data)) {
            return count((array) $result->data);
        }

        if (is_array($result) && isset($result['data'])) {
            return count($result['data']);
        }

        return 0;
    }

    protected function twitterService(User $user): TwitterService
    {
        return TwitterService::forUser($user);
    }
}
