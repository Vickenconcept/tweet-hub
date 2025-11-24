<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\BusinessAutoPost;
use App\Models\BusinessAutoProfile;
use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Support\Arr;
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

        $recentPosts = $profile->posts()
            ->whereNotNull('content')
            ->orderBy('post_date', 'desc')
            ->limit(5)
            ->pluck('content')
            ->toArray();

        return DB::transaction(function () use ($profile, $postDate, $existing, $force, $recentPosts) {
            $rawContent = $this->chatGptService->generateContent(
                $this->buildPrompt($profile, $postDate, $recentPosts)
            );

            $content = $this->normalizeContent($rawContent);

            if (!$content) {
                throw new \RuntimeException('GPT did not return usable content.');
            }

            $imageUrl = null;
            $assetCode = null;

            if ($profile->include_images) {
                try {
                    $style = in_array($profile->image_style, ['natural', 'vivid']) ? $profile->image_style : 'natural';
                    $imageUrl = $this->chatGptService->generateImage(
                        $this->buildImagePrompt($profile, $content),
                        '1024x1024',
                        $style
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

    protected function buildPrompt(BusinessAutoProfile $profile, Carbon $date, array $recentPosts = []): string
    {
        $keywords = collect($profile->keywords ?? [])->filter()->implode(', ');
        $description = trim($profile->description ?? '');
        $tone = $profile->tone ?: 'Friendly';
        $dayName = $date->format('l');
        $dateString = $date->isoFormat('MMMM D, YYYY');
        $seasonHint = $date->format('F');

        $angles = [
            'Share a founder-style story with a personal insight or obstacle overcome.',
            'Teach a quick actionable tip or micro-framework the audience can use immediately.',
            'Highlight a client win, testimonial, or real-world result (keep specifics believable).',
            'Ask a thoughtful question that sparks replies and showcases your expertise.',
            'Deliver a contrarian take or myth-busting perspective (stay respectful).',
            'Offer a mini behind-the-scenes peek into process, tools, or culture.',
            'Provide a short checklist or numbered guidance with a catchy hook.',
        ];

        $angle = Arr::random($angles);

        $recentSummary = '';
        if (!empty($recentPosts)) {
            $recentSummary = "Recent posts to avoid repeating (no similar opens, verbs, or CTAs):\n" .
                collect($recentPosts)->map(function ($content, $index) {
                    return ($index + 1) . '. ' . $content;
                })->implode("\n");
        }

        return <<<PROMPT
You are writing a single engaging social media post that can be published on X (Twitter) for {$profile->name}.

Business overview: {$description}
Focus keywords: {$keywords}
Desired tone: {$tone}
Today is {$dayName}, {$dateString}. Consider seasonal mood for {$seasonHint}.
Angle for today's post: {$angle}

{$recentSummary}

Constraints:
- Max 270 characters
- Use natural human language with a clear hook and CTA
- Include 1–2 smart hashtags only if they feel natural
- Emojis are optional and should reinforce, not replace words
- Do NOT number or label the response
- The post must feel fresh compared to the recent posts (new hook, different verbs, new CTA)
- Mention at least one concrete detail (metric, scenario, question, or benefit) to avoid generic phrasing
- Return only the final post copy with no prefixes or explanations
PROMPT;
    }

    protected function buildImagePrompt(BusinessAutoProfile $profile, string $postCopy): string
    {
        $keywords = collect($profile->keywords ?? [])->filter()->take(5)->implode(', ');
        $summary = mb_strlen($postCopy) > 280 ? mb_substr($postCopy, 0, 280) : $postCopy;

        return <<<PROMPT
Create a single social-style image that visualizes this post:
"{$summary}"

Brand: {$profile->name}
Context keywords: {$keywords}
Requirements:
- Reflect the post's main idea (product, benefit, or scenario) rather than random symbols
- Keep it clean, modern, and suitable for Twitter
- Avoid text-heavy graphics or generic mockups.
PROMPT;
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

