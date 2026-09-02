<?php

namespace App\Services\WhatsApp;

use App\Services\ChatGptService;

class WhatsAppNaturalLanguageParser
{
    protected const SYSTEM_PROMPT = <<<'PROMPT'
You are the intent parser for XEngager WhatsApp bot. Understand what the user wants and return structured JSON.

Return JSON only.

Single actions:
- queue, mentions, keywords, drafts, status, settings, assets, help, view_asset {"index":1}
- ideas {"topic":"optional subject"}
- post {"content":"tweet text"}
- schedule {"when":"time phrase","content":"tweet text"}
- generate {"prompt":"..."} · draft {"content":"..."} · image {"prompt":"..."}
- post_idea {"index":1} · schedule_idea {"index":1,"when":"10pm"}
- follow {"target":"handle"} · unfollow {"target":"handle"}
- retweet {"index":1} · like {"index":1} — index from last mentions/search list
- reply {"index":1,"content":"..."} · search {"query":"..."}
- add_keyword {"keyword":"..."} · remove_keyword {"keyword":"..."}
- notify_mentions_on/off · notify_posts_on/off · auto_mentions_on/off · auto_keywords_on/off

Multi-step (when user asks for 2+ things in one message):
- create_and_schedule {"topic":"subject","when":"time"} — e.g. "create a post about AI coding then schedule it at 10pm"
- create_with_image_and_post {"topic":"subject","image_prompt":"optional visual description"}
- create_with_image_and_schedule {"topic":"subject","when":"time","image_prompt":"optional"}
- workflow {"steps":[{"action":"compose","topic":"..."},{"action":"image","prompt":"..."},{"action":"schedule","when":"10:00 pm"}]}

Rules:
- If user mentions creating/writing content AND scheduling/posting it, use create_and_schedule or workflow (not post alone).
- If user wants a post/tweet WITH an image/picture/photo (generate visual + attach), use create_with_image_and_post or create_with_image_and_schedule — not image alone.
- image_prompt is optional; if omitted, derive a visual from topic.
- If user refers to a previously generated idea ("post the first idea", "publish idea 2"), use post_idea with index.
- Extract times verbatim: "10:00 pm", "tomorrow 9am", "in 2 hours".
- For compose steps, put the subject in topic, not full tweet text.
- Prefer create_and_schedule over workflow when it's simply create + schedule.
- unknown — only if truly unclear.
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

        if ($action === 'workflow' && ! empty($parsed['steps']) && is_array($parsed['steps'])) {
            return [
                'action' => 'workflow',
                'steps' => $parsed['steps'],
            ];
        }

        if ($action === 'create_and_schedule') {
            return [
                'action' => 'create_and_schedule',
                'topic' => trim((string) ($parsed['topic'] ?? $parsed['subject'] ?? '')),
                'when' => trim((string) ($parsed['when'] ?? $parsed['time'] ?? '')),
            ];
        }

        if ($action === 'create_with_image_and_post') {
            return [
                'action' => 'create_with_image_and_post',
                'topic' => trim((string) ($parsed['topic'] ?? $parsed['subject'] ?? '')),
                'image_prompt' => trim((string) ($parsed['image_prompt'] ?? $parsed['prompt'] ?? '')),
            ];
        }

        if ($action === 'create_with_image_and_schedule') {
            return [
                'action' => 'create_with_image_and_schedule',
                'topic' => trim((string) ($parsed['topic'] ?? $parsed['subject'] ?? '')),
                'when' => trim((string) ($parsed['when'] ?? $parsed['time'] ?? '')),
                'image_prompt' => trim((string) ($parsed['image_prompt'] ?? $parsed['prompt'] ?? '')),
            ];
        }

        if ($action === 'post_idea') {
            return [
                'action' => 'post_idea',
                'index' => max(1, (int) ($parsed['index'] ?? 1)),
            ];
        }

        if ($action === 'schedule_idea') {
            return [
                'action' => 'schedule_idea',
                'index' => max(1, (int) ($parsed['index'] ?? 1)),
                'when' => trim((string) ($parsed['when'] ?? $parsed['time'] ?? '')),
            ];
        }

        if ($action === 'follow') {
            return [
                'action' => 'follow',
                'target' => ltrim(trim((string) ($parsed['target'] ?? $parsed['handle'] ?? '')), '@'),
            ];
        }

        if ($action === 'unfollow') {
            return [
                'action' => 'unfollow',
                'target' => ltrim(trim((string) ($parsed['target'] ?? $parsed['handle'] ?? '')), '@'),
            ];
        }

        if ($action === 'retweet') {
            return ['action' => 'retweet', 'index' => max(1, (int) ($parsed['index'] ?? 1))];
        }

        if ($action === 'like') {
            return ['action' => 'like', 'index' => max(1, (int) ($parsed['index'] ?? 1))];
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

        if ($action === 'view_asset') {
            return [
                'action' => 'view_asset',
                'index' => max(1, (int) ($parsed['index'] ?? 1)),
            ];
        }

        if ($action === 'assets') {
            return ['action' => 'assets'];
        }

        $reconstructed = match ($action) {
            'post' => 'post: '.($parsed['content'] ?? ''),
            'schedule' => 'schedule: '.($parsed['when'] ?? '').' | '.($parsed['content'] ?? ''),
            'generate' => 'generate: '.($parsed['prompt'] ?? ''),
            'draft' => 'draft: '.($parsed['content'] ?? ''),
            default => $action,
        };

        $fromParser = $this->commandParser->parse($reconstructed);
        $extra = array_diff_key($parsed, ['action' => 0]);

        if (($fromParser['action'] ?? '') !== 'unknown') {
            if ($fromParser['action'] === $action || in_array($action, ['queue', 'mentions', 'keywords', 'drafts', 'status', 'ideas', 'help', 'settings'], true)) {
                return array_merge(
                    $fromParser['action'] === 'unknown' ? ['action' => $action] : $fromParser,
                    array_filter($extra),
                );
            }
        }

        return array_merge(['action' => $action], array_filter($extra));
    }
}
