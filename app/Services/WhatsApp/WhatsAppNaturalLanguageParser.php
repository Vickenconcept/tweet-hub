<?php

namespace App\Services\WhatsApp;

use App\Services\ChatGptService;

class WhatsAppNaturalLanguageParser
{
    protected const SYSTEM_PROMPT = <<<'PROMPT'
You are the intent parser for XEngager WhatsApp bot. Map the user's message to ONE action.

Return JSON only: {"action":"...", ...fields}

Actions:
- queue — scheduled/queued posts ("do I have posts waiting?", "what's scheduled?")
- mentions — check @mentions
- ideas — post ideas / what to tweet
- post — publish now {"content":"tweet text"}
- schedule — {"when":"time phrase","content":"tweet text"}
- reply — {"index":1,"content":"reply text"}
- search — {"query":"..."}
- keywords, drafts, status, settings, assets, bookmarks, help
- add_keyword {"keyword":"..."} · remove_keyword {"keyword":"..."}
- generate {"prompt":"..."} · draft {"content":"..."} · image {"prompt":"..."}
- notify_mentions_on/off · notify_posts_on/off
- auto_mentions_on/off · auto_keywords_on/off
- unknown — only if truly unclear

Prefer a concrete action over unknown. Extract tweet content verbatim for post/schedule/reply.
PROMPT;

    public function __construct(
        protected ChatGptService $chatGptService,
        protected WhatsAppCommandParser $commandParser,
    ) {}

    public function parse(string $text): ?array
    {
        $parsed = $this->chatGptService->parseJsonIntent(self::SYSTEM_PROMPT, $text);

        if (! is_array($parsed) || empty($parsed['action'])) {
            return null;
        }

        $action = $parsed['action'];
        if ($action === 'unknown') {
            return null;
        }

        if ($action === 'add_keyword') {
            return ['action' => 'add_keyword', 'keyword' => $parsed['keyword'] ?? ''];
        }
        if ($action === 'remove_keyword') {
            return ['action' => 'remove_keyword', 'keyword' => $parsed['keyword'] ?? ''];
        }
        if ($action === 'reply') {
            return [
                'action' => 'reply',
                'index' => (int) ($parsed['index'] ?? 1),
                'content' => $parsed['content'] ?? '',
            ];
        }
        if ($action === 'search') {
            return ['action' => 'search', 'query' => $parsed['query'] ?? ''];
        }
        if ($action === 'analytics') {
            return ['action' => 'analytics', 'tweet_id' => (string) ($parsed['tweet_id'] ?? '')];
        }
        if ($action === 'help') {
            return ['action' => 'help'];
        }

        $reconstructed = match ($action) {
            'post' => 'post: '.($parsed['content'] ?? ''),
            'schedule' => 'schedule: '.($parsed['when'] ?? '').' | '.($parsed['content'] ?? ''),
            'generate' => 'generate: '.($parsed['prompt'] ?? ''),
            'draft' => 'draft: '.($parsed['content'] ?? ''),
            default => $action,
        };

        $fromParser = $this->commandParser->parse($reconstructed);
        if (($fromParser['action'] ?? '') !== 'unknown') {
            if ($fromParser['action'] === $action || in_array($action, ['queue', 'mentions', 'keywords', 'drafts', 'status', 'ideas', 'help', 'settings'], true)) {
                return $fromParser['action'] === 'unknown'
                    ? ['action' => $action] + array_diff_key($parsed, ['action' => 0])
                    : $fromParser;
            }
        }

        return ['action' => $action] + array_diff_key($parsed, ['action' => 0]);
    }
}
