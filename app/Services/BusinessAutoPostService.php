<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\BusinessAutoPost;
use App\Models\BusinessAutoProfile;
use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BusinessAutoPostService
{
    public function __construct(
        protected ChatGptService $chatGptService
    ) {
    }

    /**
     * Generate (or regenerate) a daily post for a business profile.
     */
    public function generateForDate(BusinessAutoProfile $profile, Carbon $date, bool $force = false): BusinessAutoPost
    {
        $postDate = $date->copy()->startOfDay();

        $existing = $profile->posts()
            ->whereDate('post_date', $postDate)
            ->first();

        if ($existing && !$force && in_array($existing->status, ['scheduled', 'posted'])) {
            return $existing;
        }

        return DB::transaction(function () use ($profile, $postDate, $existing, $force) {
            $rawContent = $this->chatGptService->generateContent(
                $this->buildPrompt($profile, $postDate)
            );

            $content = $this->normalizeContent($rawContent);

            if (!$content) {
                throw new \RuntimeException('GPT did not return usable content.');
            }

            $imageUrl = null;
            $assetCode = null;

            if ($profile->include_images) {
                try {
                    $imageUrl = $this->chatGptService->generateImage(
                        $this->buildImagePrompt($profile),
                        '1024x1024',
                        $profile->image_style ?: 'natural'
                    );

                    if ($imageUrl) {
                        $assetCode = $this->storeGeneratedImage($profile->user_id, $imageUrl);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to generate image for auto post', [
                        'profile_id' => $profile->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $autoPost = $existing ?? new BusinessAutoPost([
                'user_id' => $profile->user_id,
                'business_auto_profile_id' => $profile->id,
                'post_date' => $postDate,
            ]);

            $autoPost->fill([
                'content' => $content,
                'image_url' => $imageUrl,
                'asset_code' => $assetCode,
                'status' => 'generating',
                'error_message' => null,
                'meta' => [
                    'raw' => $rawContent,
                    'force' => $force,
                ],
            ])->save();

            $scheduledFor = $this->determineSchedule($profile, $postDate);
            $media = $assetCode ? [$assetCode] : null;

            $post = $autoPost->post ?? new Post();

            $post->fill([
                'user_id' => $profile->user_id,
                'content' => $content,
                'media' => $media,
                'scheduled_at' => $scheduledFor,
                'status' => 'scheduled',
            ])->save();

            $autoPost->update([
                'post_id' => $post->id,
                'scheduled_for' => $scheduledFor,
                'status' => 'scheduled',
            ]);

            $profile->updateQuietly([
                'last_generated_at' => now(),
            ]);

            return $autoPost->fresh(['post']);
        });
    }

    protected function buildPrompt(BusinessAutoProfile $profile, Carbon $date): string
    {
        $keywords = collect($profile->keywords ?? [])->filter()->implode(', ');
        $description = trim($profile->description ?? '');
        $tone = $profile->tone ?: 'Friendly';

        $dateString = $date->isoFormat('MMMM D, YYYY');

        return <<<PROMPT
You are writing a single engaging social media post that can be published on X (Twitter) for {$profile->name}.

Business overview: {$description}
Focus keywords: {$keywords}
Desired tone: {$tone}
Date context: {$dateString}

Constraints:
- Max 270 characters
- Use natural human language with a clear hook and CTA
- Include 1-2 relevant hashtags at the end (if they fit naturally)
- Avoid emojis unless they feel authentic
- Do NOT number or label the response
- Return only the final post copy with no prefixes or explanations
PROMPT;
    }

    protected function buildImagePrompt(BusinessAutoProfile $profile): string
    {
        $keywords = collect($profile->keywords ?? [])->filter()->implode(', ');

        return "Create a clean, modern promotional illustration that represents {$profile->name}. Focus on {$keywords}. Use the same mood as the copy, make it brand-friendly and suitable for social media.";
    }

    protected function normalizeContent(?string $content): ?string
    {
        if (!$content) {
            return null;
        }

        $sanitized = trim($content);
        $sanitized = preg_replace('/^\d+[\).\s-]+/', '', $sanitized);
        $sanitized = preg_replace('/\s+/', ' ', $sanitized);
        $sanitized = trim($sanitized);

        if (mb_strlen($sanitized) > 280) {
            $sanitized = mb_substr($sanitized, 0, 277) . '...';
        }

        return $sanitized;
    }

    protected function determineSchedule(BusinessAutoProfile $profile, Carbon $postDate): Carbon
    {
        $timezone = $profile->timezone ?: config('app.timezone');

        $scheduled = Carbon::parse(
            $postDate->format('Y-m-d') . ' ' . ($profile->posting_time ?? '09:00'),
            $timezone
        );

        $scheduledAppTz = $scheduled->clone()->setTimezone(config('app.timezone'));

        if ($scheduledAppTz->isPast()) {
            $scheduledAppTz = now()->addMinutes(5);
        }

        return $scheduledAppTz;
    }

    protected function storeGeneratedImage(int $userId, string $imageUrl): ?string
    {
        try {
            $response = Http::timeout(60)->get($imageUrl);

            if (!$response->successful()) {
                return null;
            }

            $extension = $this->guessExtension($response->header('Content-Type'), $imageUrl);
            $filename = 'auto-posts/' . now()->format('Y/m') . '/' . $userId . '-' . Str::random(12) . '.' . $extension;

            Storage::disk('public')->put($filename, $response->body());

            $asset = Asset::create([
                'user_id' => $userId,
                'type' => 'image',
                'path' => $filename,
                'original_name' => 'auto-post-' . now()->timestamp . '.' . $extension,
                'code' => Str::uuid()->toString(),
            ]);

            return $asset->code;
        } catch (\Throwable $e) {
            Log::warning('Failed to store GPT image', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function guessExtension(?string $contentType, string $url): string
    {
        if ($contentType === 'image/png' || str_contains($url, '.png')) {
            return 'png';
        }
        if ($contentType === 'image/gif' || str_contains($url, '.gif')) {
            return 'gif';
        }

        return 'jpg';
    }
}

