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
