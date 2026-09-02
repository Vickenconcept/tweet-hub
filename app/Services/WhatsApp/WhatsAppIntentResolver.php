<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;

class WhatsAppIntentResolver
{
    public function __construct(
        protected WhatsAppCommandParser $commandParser,
        protected WhatsAppNaturalLanguageParser $naturalLanguageParser,
    ) {}

    /**
     * Resolve user message → bot action.
     * 1. Exact commands (post:, verify, etc.) — instant, no AI
     * 2. Local natural-language patterns — instant, no AI
     * 3. OpenAI intent parsing — for free-form questions
     */
    public function resolve(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return ['action' => 'unknown', 'raw' => $text];
        }

        if ($this->isExplicitCommand($text)) {
            $parsed = $this->commandParser->parse($text);

            return $parsed + ['resolved_by' => 'command'];
        }

        $parsed = $this->commandParser->parse($text);
        if (($parsed['action'] ?? '') !== 'unknown') {
            return $parsed + ['resolved_by' => 'local'];
        }

        if ($this->shouldUseAi($text)) {
            $aiParsed = $this->naturalLanguageParser->parse($text);
            if ($aiParsed) {
                return $aiParsed + ['resolved_by' => 'ai'];
            }

            Log::warning('WhatsApp AI intent unavailable; using friendly fallback', [
                'message_preview' => mb_substr($text, 0, 120),
            ]);
        }

        return [
            'action' => 'chat',
            'type' => 'fallback',
            'raw' => $text,
            'resolved_by' => 'fallback',
        ];
    }

    protected function isExplicitCommand(string $text): bool
    {
        $lower = strtolower($text);

        if (preg_match('/^(post|schedule|thread|draft|generate|image|bookmark|reply|search|add keyword|remove keyword|delete queue|verify|lang|notify|auto)\b/i', $text)) {
            return true;
        }

        if (preg_match('/^(post|schedule|thread|draft|generate|image|bookmark|search|reply|add keyword|remove keyword):/i', $text)) {
            return true;
        }

        if (preg_match('/^\d{6}$/', $text)) {
            return true;
        }

        return in_array($lower, [
            'help', 'commands', '?', 'shortcuts', 'help shortcuts',
            'status', 'settings', 'queue', 'ideas', 'drafts', 'mentions', 'mention',
            'keywords', 'keyword', 'confirm', 'unlink', 'assets', 'bookmarks',
            'start', 'onboard', 'hello', 'hi',
        ], true);
    }

    protected function shouldUseAi(string $text): bool
    {
        return ! empty(env('OPENAI_API_KEY'));
    }
}
