<?php

namespace App\Services\WhatsApp;

/**
 * Local natural-language intent parsing (no AI). Handles messy WhatsApp captions like
 * "Schedule this post for me on 09/03/2026 around 12 am Hello world".
 */
class WhatsAppConversationalIntentParser
{
    /**
     * @param  array{has_attached_image?: bool}  $context
     * @return array<string, mixed>|null
     */
    public function parse(string $text, array $context = []): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        return $this->parseScheduleIntent($text)
            ?? $this->parsePostIntent($text)
            ?? $this->parsePublishNowIntent($text);
    }

    /**
     * @return array{action: string, when: string, content: string}|null
     */
    protected function parseScheduleIntent(string $text): ?array
    {
        if (! preg_match('/\bschedul(?:e|le)\b/i', $text)) {
            return null;
        }

        $working = preg_replace(
            '/^.*?\bschedul(?:e|le)\s+(?:this\s+)?(?:post|tweet|image|photo|picture)?\s*(?:for\s+me\s+)?/iu',
            '',
            $text,
        ) ?? $text;

        $whenParts = [];
        $content = $working;

        if (preg_match('/\b(?:on\s+)?(?:the\s+)?(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})\b/i', $content, $matches)) {
            $whenParts[] = trim($matches[1]);
            $content = str_replace($matches[0], ' ', $content);
        }

        if (preg_match('/\b(?:tomorrow|today|tonight|next\s+(?:monday|tuesday|wednesday|thursday|friday|saturday|sunday|week|month))\b/i', $content, $matches)) {
            $whenParts[] = strtolower(trim($matches[0]));
            $content = str_replace($matches[0], ' ', $content);
        }

        if (preg_match('/\b(?:time\s+)?(?:around|at|by|for)\s+(\d{1,2}(?::\d{2})?\s*(?:am|pm|a\.m\.|p\.m\.)?)\b/i', $content, $matches)) {
            $whenParts[] = trim($matches[1]);
            $content = str_replace($matches[0], ' ', $content);
        } elseif (preg_match('/\b(\d{1,2}:\d{2}\s*(?:am|pm|a\.m\.|p\.m\.)?)\b/i', $content, $matches)) {
            $whenParts[] = trim($matches[1]);
            $content = str_replace($matches[0], ' ', $content);
        }

        $when = trim(implode(' ', $whenParts));
        $content = trim((string) preg_replace('/\b(for me|please|thanks|time|the|on)\b/iu', ' ', $content));
        $content = trim((string) preg_replace('/\s+/u', ' ', $content));

        if ($when === '') {
            return null;
        }

        if ($content === '') {
            $content = 'with the image';
        }

        return [
            'action' => 'schedule',
            'when' => $when,
            'content' => $content,
        ];
    }

    /**
     * @return array{action: string, content: string}|null
     */
    protected function parsePostIntent(string $text): ?array
    {
        if (preg_match(
            '/\b(?:post|publish|tweet|share)\s+(?:this|it)\s*(?:post|tweet|image|photo|picture)?\s*(?:for me\s+)?(.+)$/iu',
            $text,
            $matches,
        )) {
            $content = trim($matches[1]);
            if ($content !== '' && ! preg_match('/^(please|now|today|asap)\.?$/iu', $content)) {
                return ['action' => 'post', 'content' => $content];
            }
        }

        if (preg_match(
            '/\b(?:please\s+)?(?:can you\s+)?(?:post|publish|tweet)\s+(?:this|it)\s*(?:for me)?\s*$/iu',
            $text,
        )) {
            return ['action' => 'post', 'content' => 'with the image'];
        }

        return null;
    }

    /**
     * @return array{action: string, content: string}|null
     */
    protected function parsePublishNowIntent(string $text): ?array
    {
        if (preg_match('/\b(?:put|push|send)\s+(?:this|it)\s+(?:on|to)\s+(?:x|twitter)\b/iu', $text)) {
            $content = trim((string) preg_replace('/.*?\b(?:put|push|send)\s+(?:this|it)\s+(?:on|to)\s+(?:x|twitter)\s*/iu', '', $text));

            return [
                'action' => 'post',
                'content' => $content !== '' ? $content : 'with the image',
            ];
        }

        return null;
    }
}
