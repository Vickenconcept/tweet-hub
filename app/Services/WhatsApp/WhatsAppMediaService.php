<?php

namespace App\Services\WhatsApp;

use App\Models\Asset;
use App\Models\User;
use App\Services\ChatGptService;
use App\Services\CloudinaryService;
use App\Services\ZernioService;
use Illuminate\Support\Facades\Log;

class WhatsAppMediaService
{
    public const ASSETS_PAGE_SIZE = 5;

    public const ASSETS_SESSION_LIMIT = 50;
    public function __construct(
        protected ChatGptService $chatGptService,
        protected ZernioService $zernio,
    ) {}

    protected function cloudinary(): CloudinaryService
    {
        return app(CloudinaryService::class);
    }

    public function generateImage(User $user, string $prompt): array
    {
        $prompt = trim($prompt);
        if (mb_strlen($prompt) < 10) {
            throw new \RuntimeException('Image prompt must be at least 10 characters.');
        }

        $imageUrl = $this->chatGptService->generateImage($prompt, '1024x1024', 'vivid');
        if (! $imageUrl) {
            throw new \RuntimeException('Image generation failed. Try again later.');
        }

        $imageContent = file_get_contents($imageUrl);
        if ($imageContent === false) {
            throw new \RuntimeException('Failed to download generated image.');
        }

        $code = uniqid();
        $tempFile = tempnam(sys_get_temp_dir(), 'wa_ai_').'.png';
        file_put_contents($tempFile, $imageContent);

        try {
            $uploadResult = $this->cloudinary()->uploadFileFromPath($tempFile, 'ai_generated_'.$code.'.png');

            Asset::create([
                'user_id' => $user->id,
                'type' => 'image',
                'path' => $uploadResult['file_path'],
                'original_name' => 'ai_generated_'.$code.'.png',
                'code' => $code,
            ]);

            return [
                'code' => $code,
                'url' => $uploadResult['file_path'],
            ];
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    public function recentAssets(User $user, int $limit = self::ASSETS_SESSION_LIMIT): array
    {
        return Asset::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Asset $asset) => [
                'code' => $asset->code,
                'name' => $asset->original_name,
                'url' => $asset->path,
                'type' => $asset->type ?? 'image',
            ])
            ->all();
    }

    public function syncAssetsToSession(User $user): array
    {
        $assets = $this->recentAssets($user);
        WhatsAppSessionContext::for($user)->storeAssets($assets);

        return $assets;
    }

    public function importFromUrl(User $user, string $url, ?string $originalName = null): array
    {
        $url = trim($url);
        if ($url === '') {
            throw new \RuntimeException('Could not read the image from WhatsApp.');
        }

        $response = $this->isZernioMediaUrl($url)
            ? $this->zernio->downloadAuthenticatedUrl($url)
            : \Illuminate\Support\Facades\Http::timeout(60)->get($url);
        if (! $response->successful()) {
            Log::warning('WhatsApp inbound image download failed', [
                'user_id' => $user->id,
                'url' => $url,
                'status' => $response->status(),
            ]);
            throw new \RuntimeException('Could not download your image. Please try again.');
        }

        $contentType = strtolower((string) $response->header('Content-Type', 'image/jpeg'));
        if ($contentType !== '' && ! str_starts_with($contentType, 'image/')) {
            throw new \RuntimeException('That file is not an image. Send a photo (JPEG, PNG, GIF, or WebP).');
        }

        $ext = match (true) {
            str_contains($contentType, 'png') => 'png',
            str_contains($contentType, 'gif') => 'gif',
            str_contains($contentType, 'webp') => 'webp',
            default => 'jpg',
        };

        $code = uniqid();
        $tempFile = tempnam(sys_get_temp_dir(), 'wa_in_').'.'.$ext;
        file_put_contents($tempFile, $response->body());

        try {
            $filename = $originalName ?: 'whatsapp_'.$code.'.'.$ext;
            $uploadResult = $this->cloudinary()->uploadFileFromPath($tempFile, $filename);

            Asset::create([
                'user_id' => $user->id,
                'type' => 'image',
                'path' => $uploadResult['file_path'],
                'original_name' => $filename,
                'code' => $code,
            ]);

            return [
                'code' => $code,
                'url' => $uploadResult['file_path'],
                'name' => $filename,
            ];
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    protected function isZernioMediaUrl(string $url): bool
    {
        return str_contains($url, 'zernio.com')
            || str_contains($url, '/whatsapp/media/');
    }
}
