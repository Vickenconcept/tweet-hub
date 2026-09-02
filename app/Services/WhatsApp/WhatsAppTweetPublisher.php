<?php

namespace App\Services\WhatsApp;

use App\Models\Asset;
use App\Models\Post;
use App\Models\User;
use App\Services\TwitterService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppTweetPublisher
{
    /**
     * @return array{tweet_id: ?string, url: ?string, content: string, parts: int}
     */
    public function publish(User $user, string $content, ?string $inReplyToTweetId = null): array
    {
        $twitter = $this->twitterService($user);
        [$text, $mediaIds, $mediaCodes] = $this->prepareContent($user, $content, $twitter);

        $response = $inReplyToTweetId
            ? $twitter->createTweet($text, $mediaIds, $inReplyToTweetId)
            : $twitter->createTweet($text, $mediaIds);

        $tweetId = $response->data->id ?? null;

        Post::create([
            'user_id' => $user->id,
            'content' => $text,
            'media' => $mediaCodes ?: null,
            'twitter_post_id' => $tweetId,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $url = $tweetId && $user->twitter_username
            ? "https://x.com/{$user->twitter_username}/status/{$tweetId}"
            : null;

        return [
            'tweet_id' => $tweetId,
            'url' => $url,
            'content' => $text,
            'parts' => 1,
        ];
    }

    /**
     * @param  array<int, string>  $parts
     * @return array{tweet_id: ?string, url: ?string, content: string, parts: int}
     */
    public function publishThread(User $user, array $parts): array
    {
        $twitter = $this->twitterService($user);
        $prevTweetId = null;
        $prevLocalPostId = null;
        $firstUrl = null;
        $firstTweetId = null;
        $published = 0;

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            [$text, $mediaIds, $mediaCodes] = $this->prepareContent($user, $part, $twitter);

            $response = $prevTweetId
                ? $twitter->createTweet($text, $mediaIds, $prevTweetId)
                : $twitter->createTweet($text, $mediaIds);

            $tweetId = $response->data->id ?? null;
            if (! $tweetId) {
                throw new \RuntimeException('Thread failed part '.($published + 1));
            }

            $post = Post::create([
                'user_id' => $user->id,
                'content' => $text,
                'media' => $mediaCodes ?: null,
                'twitter_post_id' => $tweetId,
                'in_reply_to_post_id' => $prevLocalPostId,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            if (! $firstTweetId) {
                $firstTweetId = $tweetId;
                $firstUrl = $user->twitter_username
                    ? "https://x.com/{$user->twitter_username}/status/{$tweetId}"
                    : null;
            }

            $prevTweetId = $tweetId;
            $prevLocalPostId = $post->id;
            $published++;
        }

        if ($published === 0) {
            throw new \RuntimeException('Thread has no content.');
        }

        return [
            'tweet_id' => $firstTweetId,
            'url' => $firstUrl,
            'content' => $parts[0] ?? '',
            'parts' => $published,
        ];
    }

    /**
     * @return array{0: string, 1: array<int, string>, 2: array<int, string>}
     */
    protected function prepareContent(User $user, string $content, TwitterService $twitter): array
    {
        $mediaIds = [];
        $mediaCodes = [];

        if (preg_match_all('/\[(img|vid|gif):([a-zA-Z0-9]+)\]/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $code = $match[2];
                $mediaCodes[] = $code;
                $asset = Asset::where('user_id', $user->id)->where('code', $code)->first();
                if (! $asset) {
                    continue;
                }

                $path = $this->resolveAssetPath($asset);
                if (! $path) {
                    continue;
                }

                $mediaId = str_contains($asset->path, 'cloudinary.com')
                    ? $this->uploadCloudinaryMedia($twitter, $asset->path, $path)
                    : $twitter->uploadLocalMedia($path);

                if ($mediaId) {
                    $mediaIds[] = $mediaId;
                }
            }

            $content = preg_replace('/\[(img|vid|gif):([a-zA-Z0-9]+)\]/', '', $content);
        }

        $content = trim($content);
        if ($content === '' && ! empty($mediaIds)) {
            $content = '📸';
        }

        if (mb_strlen($content) > 280) {
            $content = $twitter->truncateForTwitter($content);
        }

        return [$content, $mediaIds, $mediaCodes];
    }

    protected function uploadCloudinaryMedia(TwitterService $twitter, string $url, ?string $localPath = null): ?string
    {
        $path = $localPath;

        if (! $path) {
            try {
                $response = Http::timeout(60)->get($url);
                if (! $response->successful()) {
                    return null;
                }
                $ext = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'jpg';
                $path = tempnam(sys_get_temp_dir(), 'wa_media_').'.'.$ext;
                file_put_contents($path, $response->body());
            } catch (\Throwable $e) {
                Log::warning('WhatsApp media download failed', ['url' => $url, 'error' => $e->getMessage()]);

                return null;
            }
        }

        $mediaId = $twitter->uploadLocalMedia($path);

        if (str_starts_with($path, sys_get_temp_dir())) {
            @unlink($path);
        }

        return $mediaId;
    }

    protected function resolveAssetPath(Asset $asset): ?string
    {
        $path = $asset->path;

        if (Str::startsWith($path, ['http://', 'https://'])) {
            try {
                $response = Http::timeout(60)->get($path);
                if (! $response->successful()) {
                    return null;
                }
                $ext = pathinfo(parse_url($path, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'jpg';
                $temp = tempnam(sys_get_temp_dir(), 'wa_asset_').'.'.$ext;
                file_put_contents($temp, $response->body());

                return $temp;
            } catch (\Throwable) {
                return null;
            }
        }

        $local = storage_path('app/public/'.ltrim($path, '/'));

        return file_exists($local) ? $local : null;
    }

    protected function twitterService(User $user): TwitterService
    {
        return new TwitterService([
            'account_id' => $user->twitter_account_id,
            'access_token' => $user->twitter_access_token,
            'access_token_secret' => $user->twitter_access_token_secret,
            'consumer_key' => config('services.twitter.api_key'),
            'consumer_secret' => config('services.twitter.api_key_secret'),
            'bearer_token' => config('services.twitter.bearer_token'),
        ]);
    }
}
