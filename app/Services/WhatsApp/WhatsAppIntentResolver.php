<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;

class WhatsAppIntentResolver
{
    public function __construct(
        protected WhatsAppCommandParser $commandParser,
        protected WhatsAppNaturalLanguageParser $naturalLanguageParser,
        protected WhatsAppConversationalIntentParser $conversationalParser,
        protected WhatsAppIntentPlanner $intentPlanner,
    ) {}

    /**
     * Resolve user message → bot action.
     * 1. Exact commands (post:, verify, etc.) — instant, no AI
     * 2. Local natural-language patterns — instant, no AI
     * 3. Conversational parser — messy real-world captions
     * 4. OpenAI intent parsing — for free-form questions
     *
     * @param  array{has_attached_image?: bool}  $context
     */
    public function resolve(string $text, array $context = []): array
    {
        $text = trim($text);

        if ($text === '') {
            return ['action' => 'unknown', 'raw' => $text];
        }

        if ($this->looksLikeConversationalPostSchedule($text)) {
            $conversational = $this->conversationalParser->parse($text, $context);
            if ($conversational !== null) {
                return $this->intentPlanner->plan($conversational, $context) + ['resolved_by' => 'conversational'];
            }
        }

        if ($this->looksMultiStep($text) && $this->shouldUseAi($text)) {
            $aiParsed = $this->naturalLanguageParser->parse($text);
            if ($aiParsed) {
                return $this->intentPlanner->plan($aiParsed, $context) + ['resolved_by' => 'ai'];
            }
        }

        if ($this->isExplicitCommand($text)) {
            $parsed = $this->commandParser->parse($text);
            if (($parsed['action'] ?? '') !== 'unknown') {
                return $this->intentPlanner->plan($parsed, $context) + ['resolved_by' => 'command'];
            }
        }

        $parsed = $this->commandParser->parse($text);
        if (($parsed['action'] ?? '') !== 'unknown') {
            return $this->intentPlanner->plan($parsed, $context) + ['resolved_by' => 'local'];
        }

        $conversational = $this->conversationalParser->parse($text, $context);
        if ($conversational !== null) {
            return $this->intentPlanner->plan($conversational, $context) + ['resolved_by' => 'conversational'];
        }

        if ($this->shouldUseAi($text)) {
            $aiParsed = $this->naturalLanguageParser->parse($text);
            if ($aiParsed) {
                return $this->intentPlanner->plan($aiParsed, $context) + ['resolved_by' => 'ai'];
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

        if (preg_match('/^(post|schedule|thread|draft|generate|image|reply|search|add keyword|remove keyword|delete queue|verify|lang|notify|auto|follow|unfollow|retweet|rt|like)\b/i', $text)) {
            return true;
        }

        if (preg_match('/^post\s+image\s+#?\d+/i', $text) || preg_match('/^schedule\s+image\s+#?\d+/i', $text)) {
            return true;
        }

        if (preg_match('/^(post|schedule)\s+(?:this|the|my)\s+image\b/i', $text)) {
            return true;
        }

        if (preg_match('/^(post|schedule|thread|draft|generate|image|search|reply|add keyword|remove keyword):/i', $text)) {
            return true;
        }

        if (preg_match('/^\d{6}$/', $text)) {
            return true;
        }

        return in_array($lower, [
            'help', 'commands', '?', 'shortcut', 'shortcuts', 'help shortcuts',
            'status', 'settings', 'queue', 'ideas', 'drafts', 'mentions', 'mention',
            'keywords', 'keyword', 'confirm', 'unlink', 'assets', 'my images',
            'more images', 'next images', 'previous images', 'prev images',
            'post this image', 'post image 1', 'schedule this image',
            'start', 'onboard', 'hello', 'hi',
        ], true);
    }

    protected function shouldUseAi(string $text): bool
    {
        return ! empty(env('OPENAI_API_KEY'));
    }

    protected function looksMultiStep(string $text): bool
    {
        $lower = strtolower($text);

        if (preg_match('/\b(?:create|write|make|compose|generate).*\b(?:post|tweet).*\b(?:then|and)\b.*\b(?:schedule|post|publish)/i', $text)) {
            return true;
        }

        if (preg_match('/\b(?:with\s+(?:an?\s+)?(?:image|picture|photo|graphic|visual|illustration)s?\b)/i', $text)
            && preg_match('/\b(?:post|tweet|schedule|create|write|publish)\b/i', $text)) {
            return true;
        }

        if (preg_match('/\b(?:post|publish|tweet)\s+(?:the\s+)?(?:first|second|third|\d+(?:st|nd|rd|th)?)\s+idea\b/i', $text)) {
            return true;
        }

        if (preg_match('/\b(?:then|and then|after that|also)\b/i', $text)
            && preg_match('/\b(?:schedule|post|publish|queue|draft|generate|create|write)\b/i', $text)) {
            return true;
        }

        return str_word_count($lower) >= 8
            && preg_match('/\b(?:schedule|post|publish|create|write|generate|ideas?)\b/i', $text);
    }

    protected function looksLikeConversationalPostSchedule(string $text): bool
    {
        return (bool) preg_match('/\bschedul(?:e|le)\b/i', $text)
            || (bool) preg_match('/\b(?:post|publish|tweet)\s+(?:this|it)\b/i', $text);
    }
}
