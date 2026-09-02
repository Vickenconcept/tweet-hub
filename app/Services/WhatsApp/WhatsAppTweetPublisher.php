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
        $preparedTexts = [];
        $mediaByPart = [];
        $mediaCodesByPart = [];

        foreach (array_values($parts) as $index => $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            [$text, $mediaIds, $mediaCodes] = $this->prepareContent($user, $part, $twitter);
            $preparedTexts[] = $text;
            $partIndex = count($preparedTexts) - 1;
            if (! empty($mediaIds)) {
                $mediaByPart[$partIndex] = $mediaIds;
            }
            if (! empty($mediaCodes)) {
                $mediaCodesByPart[$partIndex] = $mediaCodes;
            }
        }

        if ($preparedTexts === []) {
            throw new \RuntimeException('Thread has no content.');
        }

        $response = count($preparedTexts) > 1
            ? $twitter->createThread($preparedTexts, $mediaByPart)
            : $twitter->createTweet($preparedTexts[0], $mediaByPart[0] ?? []);

        $tweetIds = $response->data->tweet_ids ?? [$response->data->id ?? null];
        $firstTweetId = $tweetIds[0] ?? null;
        $prevLocalPostId = null;

        foreach ($preparedTexts as $index => $text) {
            $post = Post::create([
                'user_id' => $user->id,
                'content' => $text,
                'media' => $mediaCodesByPart[$index] ?? null,
                'twitter_post_id' => $tweetIds[$index] ?? $firstTweetId,
                'in_reply_to_post_id' => $index > 0 ? $prevLocalPostId : null,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
            $prevLocalPostId = $post->id;
        }

        $firstUrl = $firstTweetId && $user->twitter_username
            ? "https://x.com/{$user->twitter_username}/status/{$firstTweetId}"
            : null;

        return [
            'tweet_id' => $firstTweetId,
            'url' => $firstUrl,
            'content' => $preparedTexts[0],
            'parts' => count($preparedTexts),
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
        return TwitterService::forUser($user);
    }
}
