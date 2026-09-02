<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class WhatsAppSessionContext
{
    public function __construct(
        protected User $user,
    ) {}

    public static function for(User $user): self
    {
        return new self($user);
    }

    public function storeIdeas(string $rawResponse): array
    {
        $ideas = $this->parseNumberedIdeas($rawResponse);
        if ($ideas !== []) {
            Cache::put($this->key('ideas'), $ideas, now()->addHours(24));
        }

        return $ideas;
    }

    /**
     * @return array<int, string>
     */
    public function getIdeas(): array
    {
        $ideas = Cache::get($this->key('ideas'), []);

        return is_array($ideas) ? $ideas : [];
    }

    public function getIdea(int $index): ?string
    {
        $ideas = $this->getIdeas();

        return $ideas[$index - 1] ?? null;
    }

    public function storeTopic(string $topic): void
    {
        Cache::put($this->key('ideas_topic'), $topic, now()->addHours(24));
    }

    public function getTopic(): ?string
    {
        $topic = Cache::get($this->key('ideas_topic'));

        return is_string($topic) && $topic !== '' ? $topic : null;
    }

    /**
     * @param  array<int, array{code: string, name: string, url: string, type?: string}>  $assets
     */
    public function storeAssets(array $assets): void
    {
        Cache::put($this->key('assets'), array_values($assets), now()->addHours(24));
    }

    /**
     * @return array<int, array{code: string, name: string, url: string, type?: string}>
     */
    public function getAssets(): array
    {
        $assets = Cache::get($this->key('assets'), []);

        return is_array($assets) ? $assets : [];
    }

    public function getAssetByIndex(int $index): ?array
    {
        $assets = $this->getAssets();

        return $assets[$index - 1] ?? null;
    }

    public function getAssetCodeByIndex(int $index): ?string
    {
        $asset = $this->getAssetByIndex($index);

        return $asset['code'] ?? null;
    }

    public function storeAssetsPage(int $page): void
    {
        Cache::put($this->key('assets_page'), max(1, $page), now()->addHours(24));
    }

    public function getAssetsPage(): int
    {
        $page = Cache::get($this->key('assets_page'), 1);

        return max(1, (int) $page);
    }

    /**
     * @param  array{code: string, url: string, name?: string}  $image
     */
    public function storeLastImage(array $image): void
    {
        Cache::put($this->key('last_image'), $image, now()->addHours(24));
    }

    public function getLastImage(): ?array
    {
        $image = Cache::get($this->key('last_image'));

        return is_array($image) ? $image : null;
    }

    public function getLastImageCode(): ?string
    {
        $image = $this->getLastImage();

        return is_string($image['code'] ?? null) && $image['code'] !== '' ? $image['code'] : null;
    }

    /**
     * @return array<int, string>
     */
    public function parseNumberedIdeas(string $response): array
    {
        $ideas = [];

        if (preg_match_all('/^\s*(?:\d+[.)]\s*|\d️⃣\s*)(.+)$/mu', $response, $matches)) {
            foreach ($matches[1] as $idea) {
                $clean = trim($idea);
                if ($clean !== '') {
                    $ideas[] = $clean;
                }
            }
        }

        if ($ideas === [] && preg_match_all('/\n\s*(\d+)\.\s+(.+)/u', $response, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $ideas[] = trim($match[2]);
            }
        }

        return array_values(array_slice($ideas, 0, 5));
    }

    protected function key(string $type): string
    {
        return "whatsapp_context_{$type}_{$this->user->id}";
    }
}
