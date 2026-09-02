<?php

namespace App\Services\WhatsApp;

class WhatsAppCommandParser
{
    public function parse(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return ['action' => 'unknown', 'raw' => $text];
        }

        $lower = strtolower($text);

        $compound = $this->parseCompoundIntent($text);
        if ($compound !== null) {
            return $compound;
        }

        if (in_array($lower, ['help', 'commands', '?'], true)) {
            return ['action' => 'help'];
        }

        if (preg_match('/^(help\s+shortcuts|shortcuts?|command\s+codes?)$/i', $text)) {
            return ['action' => 'help_shortcuts'];
        }

        if (in_array($lower, ['stuck', 'lost', 'confused', 'menu', 'options'], true)
            || preg_match('/^(i\s)?(don\'t|do not)\s+know$/i', $text)) {
            return ['action' => 'help'];
        }

        if (preg_match('/^(what can you do|what can i do|how does this work|how do i use this)$/i', $text)) {
            return ['action' => 'help'];
        }

        if ($lower === 'status') {
            return ['action' => 'status'];
        }

        if (preg_match('/^(what\'?s|what is)\s+my\s+status$/i', $text) || preg_match('/^account\s+status$/i', $text)) {
            return ['action' => 'status'];
        }

        if ($lower === 'settings') {
            return ['action' => 'settings'];
        }

        if (preg_match('/^(show\s+)?(my\s+)?settings?$/i', $text)) {
            return ['action' => 'settings'];
        }

        if ($lower === 'queue') {
            return ['action' => 'queue'];
        }

        if (preg_match('/^(show\s+)?(my\s+)?queue$/i', $text)
            || preg_match('/^(what\'?s|what is)\s+(in\s+)?my\s+(queue|schedule)/i', $text)
            || preg_match('/^scheduled\s+posts?$/i', $text)) {
            return ['action' => 'queue'];
        }

        if ($this->isIdeasFollowUp($text)) {
            return ['action' => 'ideas', 'follow_up' => true];
        }

        if ($lower === 'ideas') {
            return $this->parseIdeasRequest($text);
        }

        if (preg_match('/^(give me |get )?(some )?(post )?ideas?$/i', $text)
            || preg_match('/^ideas?\s+for\s+(my )?posts?$/i', $text)) {
            return $this->parseIdeasRequest($text);
        }

        if ($lower === 'drafts') {
            return ['action' => 'drafts'];
        }

        if (in_array($lower, ['mentions', 'mention'], true)) {
            return ['action' => 'mentions'];
        }

        if (preg_match('/^(show|check|list|any)\s+(my\s+)?mentions?$/i', $text)
            || preg_match('/^(my\s+)?mentions?$/i', $text)) {
            return ['action' => 'mentions'];
        }

        if (in_array($lower, ['keywords', 'keyword'], true)) {
            return ['action' => 'keywords'];
        }

        if (preg_match('/^(show|list)\s+(my\s+)?keywords?$/i', $text)) {
            return ['action' => 'keywords'];
        }

        if ($lower === 'confirm') {
            return ['action' => 'confirm'];
        }

        if ($lower === 'unlink') {
            return ['action' => 'unlink'];
        }

        if (preg_match('/^auto\s+mentions\s+(on|off)$/i', $text, $matches)) {
            return ['action' => 'auto_mentions_'.strtolower($matches[1])];
        }

        if (preg_match('/^auto\s+keywords\s+(on|off)$/i', $text, $matches)) {
            return ['action' => 'auto_keywords_'.strtolower($matches[1])];
        }

        if (preg_match('/^verify\s+(\d{6})$/i', $text, $matches)) {
            return ['action' => 'verify', 'code' => $matches[1]];
        }

        if (preg_match('/^\d{6}$/', $text)) {
            return ['action' => 'verify', 'code' => $text];
        }

        if (preg_match('/^post:\s*(.+)$/is', $text, $matches)) {
            return ['action' => 'post', 'content' => trim($matches[1])];
        }

        if (preg_match('/^(post|publish|tweet)\s+(.+)$/is', $text, $matches)) {
            $content = trim($matches[2]);
            if ($content !== '' && ! preg_match('/^(about|a tweet|this:?)$/i', $content)) {
                return ['action' => 'post', 'content' => $content];
            }
        }

        if (preg_match('/^schedule\s+(tomorrow|today|tonight|next\s+\w+)\s+(.+)$/is', $text, $matches)) {
            return [
                'action' => 'schedule',
                'when' => trim($matches[1]),
                'content' => trim($matches[2]),
            ];
        }

        if (preg_match('/^schedule:\s*(.+?)\s*\|\s*(.+)$/is', $text, $matches)) {
            return [
                'action' => 'schedule',
                'when' => trim($matches[1]),
                'content' => trim($matches[2]),
            ];
        }

        if (preg_match('/^delete\s+queue\s+(\d+)$/i', $text, $matches)) {
            return ['action' => 'delete_queue', 'index' => (int) $matches[1]];
        }

        if (preg_match('/^generate:\s*(.+)$/is', $text, $matches)) {
            return ['action' => 'generate', 'prompt' => trim($matches[1])];
        }

        if (preg_match('/^draft:\s*(.+)$/is', $text, $matches)) {
            return ['action' => 'draft', 'content' => trim($matches[1])];
        }

        if (preg_match('/^reply\s+(\d+):\s*(.+)$/is', $text, $matches)) {
            return [
                'action' => 'reply',
                'index' => (int) $matches[1],
                'content' => trim($matches[2]),
            ];
        }

        if (preg_match('/^reply\s+to\s+(mention\s+)?(\d+)\s*(with|:)\s*(.+)$/is', $text, $matches)) {
            return [
                'action' => 'reply',
                'index' => (int) $matches[2],
                'content' => trim($matches[4]),
            ];
        }

        if (preg_match('/^add\s+keyword:\s*(.+)$/is', $text, $matches)) {
            return ['action' => 'add_keyword', 'keyword' => trim($matches[1])];
        }

        if (preg_match('/^remove\s+keyword:\s*(.+)$/is', $text, $matches)) {
            return ['action' => 'remove_keyword', 'keyword' => trim($matches[1])];
        }

        if (preg_match('/^search:\s*(.+)$/is', $text, $matches)) {
            return ['action' => 'search', 'query' => trim($matches[1])];
        }

        if (preg_match('/^search\s+(for\s+)?(.+)$/is', $text, $matches)) {
            return ['action' => 'search', 'query' => trim($matches[2])];
        }

        if (preg_match('/^analytics\s+(\d+)$/i', $text, $matches)) {
            return ['action' => 'analytics', 'tweet_id' => $matches[1]];
        }

        if (in_array($lower, ['auto posts', 'autoposts'], true)) {
            return ['action' => 'auto_posts'];
        }

        if (preg_match('/^auto\s+posts\s+(\d+)\s+(on|off)$/i', $text, $matches)) {
            return [
                'action' => 'auto_posts_toggle',
                'index' => (int) $matches[1],
                'enabled' => strtolower($matches[2]) === 'on',
            ];
        }

        if (preg_match('/^image:\s*(.+)$/is', $text, $matches)) {
            return ['action' => 'image', 'prompt' => trim($matches[1])];
        }

        if ($lower === 'assets') {
            return ['action' => 'assets'];
        }

        if (preg_match('/^(show|list)\s+(my\s+)?(assets|images|media)$/i', $text)) {
            return ['action' => 'assets'];
        }

        if (preg_match('/^notify\s+posts\s+(on|off)$/i', $text, $matches)) {
            return ['action' => 'notify_posts_'.strtolower($matches[1])];
        }

        if (preg_match('/^notify\s+mentions\s+(on|off)$/i', $text, $matches)) {
            return ['action' => 'notify_mentions_'.strtolower($matches[1])];
        }

        if (preg_match('/^thread:\s*(.+)$/is', $text, $matches)) {
            $parts = array_values(array_filter(array_map('trim', explode('|', $matches[1]))));

            return ['action' => 'thread', 'parts' => $parts];
        }

        if (preg_match('/^(add|track)\s+keyword\s+(.+)$/i', $text, $matches)) {
            return ['action' => 'add_keyword', 'keyword' => trim($matches[2])];
        }

        if (preg_match('/^(remove|delete|stop tracking)\s+keyword\s+(.+)$/i', $text, $matches)) {
            return ['action' => 'remove_keyword', 'keyword' => trim($matches[2])];
        }

        if (preg_match('/^lang\s+(en|es|fr)$/i', $text, $matches)) {
            return ['action' => 'lang', 'language' => strtolower($matches[1])];
        }

        if (in_array($lower, ['start', 'onboard', 'hello', 'hi'], true)) {
            return ['action' => 'start'];
        }

        $conversational = $this->parseConversational($text);
        if ($conversational !== null) {
            return $conversational;
        }

        return ['action' => 'unknown', 'raw' => $text];
    }

    /**
     * Local natural-language patterns (no AI). Handles common question phrasing.
     */
    protected function parseConversational(string $text): ?array
    {
        $lower = strtolower($text);

        if ($this->matchesIntent($lower, ['queue', 'scheduled'], ['queued', 'scheduled', 'queue', 'waiting to post', 'waiting to go out'])
            || preg_match('/\b(do i|do we|have i|have we|any|are there|is there|got any)\b.*\b(post|posts|tweet|tweets)\b.*\b(queue|queued|scheduled|waiting|line|pipeline)\b/i', $text)
            || preg_match('/\b(queue|queued|scheduled)\b.*\b(post|posts|tweet|tweets)\b/i', $text)
            || preg_match('/\bwhat(\'s| is) (in )?my (queue|schedule|scheduled posts?)\b/i', $text)) {
            return ['action' => 'queue'];
        }

        if ($this->matchesIntent($lower, ['mentions'], ['mention', 'mentions', 'tagged', 'talking about me', '@ me'])
            || preg_match('/\b(any|new|recent|do i have|check|got)\b.*\bmentions?\b/i', $text)
            || preg_match('/\bmentions?\b.*\b(yet|today|new|any)\b/i', $text)) {
            return ['action' => 'mentions'];
        }

        if ($this->matchesIntent($lower, ['ideas'], ['idea', 'ideas', 'what should i post', 'what can i post', 'post about', 'content ideas', 'something to post'])
            || preg_match('/\b(what|give me|suggest|need).*\b(post|tweet|content|ideas?)\b/i', $text)) {
            return $this->parseIdeasRequest($text);
        }

        if ($this->matchesIntent($lower, ['keywords'], ['keyword', 'keywords', 'tracking', 'monitoring'])
            || preg_match('/\bwhat keywords\b/i', $text)
            || preg_match('/\b(show|list|my)\b.*\bkeywords?\b/i', $text)) {
            return ['action' => 'keywords'];
        }

        if ($this->matchesIntent($lower, ['status'], ['status', 'connected', 'am i linked', 'is twitter connected', 'is x connected', 'account info'])
            || preg_match('/\bhow am i doing\b/i', $text)) {
            return ['action' => 'status'];
        }

        if ($this->matchesIntent($lower, ['settings'], ['settings', 'preferences', 'toggles', 'notifications', 'alerts'])) {
            return ['action' => 'settings'];
        }

        if ($this->matchesIntent($lower, ['drafts'], ['draft', 'drafts', 'saved posts'])) {
            return ['action' => 'drafts'];
        }

        if ($this->matchesIntent($lower, ['assets'], ['assets', 'images', 'media library', 'my images'])) {
            return ['action' => 'assets'];
        }

        if ($this->matchesIntent($lower, ['help'], ['help', 'what can you do', 'what do you do', 'how does this work', 'how do i use', 'commands', 'menu'])) {
            return ['action' => 'help'];
        }

        if (preg_match('/\b(what\'?s|what is) your name\b/i', $text)
            || preg_match('/\bwho are you\b/i', $text)
            || preg_match('/\bwhat should i call you\b/i', $text)) {
            return ['action' => 'chat', 'type' => 'identity'];
        }

        if (preg_match('/^(thanks|thank you|thx|ty|cheers|appreciated)\b/i', $lower)) {
            return ['action' => 'chat', 'type' => 'thanks'];
        }

        if (preg_match('/^(hi|hello|hey|howdy|yo|good morning|good afternoon|good evening)\b/i', $lower)) {
            return ['action' => 'chat', 'type' => 'greeting'];
        }

        if (preg_match('/\b(bye|goodbye|see you|later)\b/i', $lower)) {
            return ['action' => 'chat', 'type' => 'goodbye'];
        }

        if (preg_match('/\b(post|publish|tweet)\b.+\b(on x|on twitter|to x|to twitter|now|today)\b/i', $text, $matches)) {
            $content = preg_replace('/^(please\s+)?(can you\s+)?(post|publish|tweet)\s+/i', '', $text);
            $content = preg_replace('/\s+(on x|on twitter|to x|to twitter|now|today|please)\.?$/i', '', trim($content));
            if (mb_strlen($content) >= 3) {
                return ['action' => 'post', 'content' => $content];
            }
        }

        if (preg_match('/\bturn (on|off)\b.*\bmention\b.*\b(notif|alert)/i', $text, $matches)) {
            return ['action' => strtolower($matches[1]) === 'on' ? 'notify_mentions_on' : 'notify_mentions_off'];
        }

        if (preg_match('/\bturn (on|off)\b.*\bpost\b.*\b(notif|alert)/i', $text, $matches)) {
            return ['action' => strtolower($matches[1]) === 'on' ? 'notify_posts_on' : 'notify_posts_off'];
        }

        return null;
    }

    protected function matchesIntent(string $lower, array $requiredTerms, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if (str_contains($lower, $phrase)) {
                return true;
            }
        }

        foreach ($requiredTerms as $term) {
            if (str_contains($lower, $term)) {
                return true;
            }
        }

        return false;
    }

    protected function parseIdeasRequest(string $text): array
    {
        $result = ['action' => 'ideas'];
        $topic = $this->extractIdeasTopic($text);

        if ($topic !== null && $topic !== '') {
            $result['topic'] = $topic;
        }

        return $result;
    }

    protected function extractIdeasTopic(string $text): ?string
    {
        $patterns = [
            '/\b(?:post|tweet|content)\s+ideas?\s+(?:about|for|on|regarding)\s+(.+)/iu',
            '/\bideas?\s+(?:about|for|on|regarding)\s+(.+)/iu',
            '/\b(?:give me|get|suggest|need|want)\s+(?:some\s+)?(?:post|tweet|content\s+)?ideas?\s+(?:about|for|on|regarding)\s+(.+)/iu',
            '/\bwhat\s+(?:should|can)\s+i\s+post\s+(?:about|on)\s+(.+)/iu',
            '/\bi(?:\'m| am)\s+(?:a|an)\s+(.+?)(?:\s*[,.\!]|\s+(?:give|get|suggest|need|want|can you))/iu',
            '/\b(?:give|get|suggest|need|want).*\bideas?\b.*\bi(?:\'m| am)\s+(?:a|an)\s+(.+)/iu',
            '/\b(?:as|being)\s+(?:a|an)\s+(.+?)(?:\s*[,.\!]|\s+(?:give|get|suggest|need|want))/iu',
            '/\b(?:posts?|tweets?|something)\s+(?:about|on|for)\s+(.+)/iu',
            '/\bi(?:\'m| am)\s+(?:a|an)\s+([^.!\n,]+)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $topic = $this->cleanIdeasTopic($matches[1]);
                if ($topic !== null) {
                    return $topic;
                }
            }
        }

        return null;
    }

    protected function cleanIdeasTopic(string $topic): ?string
    {
        $topic = trim($topic);
        $topic = preg_replace('/\s*(please|thanks|thank you|right now|today)[.!?\s]*$/iu', '', $topic);
        $topic = trim($topic, " \t\n\r\0\x0B.,!?\"'");

        if ($topic === '' || mb_strlen($topic) < 2 || mb_strlen($topic) > 200) {
            return null;
        }

        if (preg_match('/^(ideas?|posts?|tweets?)$/iu', $topic)) {
            return null;
        }

        return $topic;
    }

    protected function isIdeasFollowUp(string $text): bool
    {
        $lower = strtolower(trim($text));

        return (bool) preg_match(
            '/^(more ideas?|give me more|another (one|batch|set)|more please|\d+\s+more|few more|some more)$/i',
            $lower
        );
    }

    protected function parseCompoundIntent(string $text): ?array
    {
        if (preg_match(
            '/\b(?:create|write|make|compose|draft|generate)\s+(?:a\s+)?(?:post|tweet)\s+about\s+(.+?)\s+(?:then|and)\s+schedul(?:e|le)(?:\s+it)?(?:\s+(?:by|at|for))?\s+(.+)/iu',
            $text,
            $matches
        )) {
            return [
                'action' => 'create_and_schedule',
                'topic' => $this->cleanCompoundTopic($matches[1]),
                'when' => trim($matches[2]),
            ];
        }

        if (preg_match(
            '/\bschedule\s+(?:a\s+)?(?:post|tweet)\s+about\s+(.+?)\s+(?:at|by|for)\s+(.+)/iu',
            $text,
            $matches
        )) {
            return [
                'action' => 'create_and_schedule',
                'topic' => $this->cleanCompoundTopic($matches[1]),
                'when' => trim($matches[2]),
            ];
        }

        if (preg_match(
            '/\b(?:alright|ok(?:ay)?|yes|yeah|sure|please|go ahead)?[,.\s]*(?:post|publish|tweet)\s+(?:the\s+)?(?:(first|second|third|\d+(?:st|nd|rd|th)?)\s+)?(?:idea|one)(?:\s+you\s+(?:generated|gave|sent))?\b/iu',
            $text,
            $matches
        )) {
            return [
                'action' => 'post_idea',
                'index' => $this->wordToIndex($matches[1] ?? 'first'),
            ];
        }

        if (preg_match('/^post\s+idea\s+(\d+)\b/iu', $text, $matches)) {
            return [
                'action' => 'post_idea',
                'index' => max(1, (int) $matches[1]),
            ];
        }

        if (preg_match(
            '/\bschedul(?:e|le)\s+(?:the\s+)?(?:(first|second|third|\d+(?:st|nd|rd|th)?)\s+)?idea(?:\s+(?:at|by|for))?\s+(.+)/iu',
            $text,
            $matches
        )) {
            return [
                'action' => 'schedule_idea',
                'index' => $this->wordToIndex($matches[1] ?? 'first'),
                'when' => trim($matches[2]),
            ];
        }

        if (preg_match('/^follow\s+(@?[A-Za-z0-9_]{1,15})\b/iu', $text, $matches)) {
            return ['action' => 'follow', 'target' => ltrim($matches[1], '@')];
        }

        if (preg_match('/^unfollow\s+(@?[A-Za-z0-9_]{1,15})\b/iu', $text, $matches)) {
            return ['action' => 'unfollow', 'target' => ltrim($matches[1], '@')];
        }

        if (preg_match('/^(?:retweet|rt)\s+(\d+)\b/iu', $text, $matches)) {
            return ['action' => 'retweet', 'index' => max(1, (int) $matches[1])];
        }

        if (preg_match('/^like\s+(\d+)\b/iu', $text, $matches)) {
            return ['action' => 'like', 'index' => max(1, (int) $matches[1])];
        }

        return null;
    }

    protected function cleanCompoundTopic(string $topic): string
    {
        $topic = trim($topic);
        $topic = preg_replace('/\s*(please|thanks|thank you)[.!?\s]*$/iu', '', $topic);

        return trim($topic, " \t\n\r\0\x0B.,!?\"'");
    }

    protected function wordToIndex(string $word): int
    {
        $word = strtolower(trim($word));

        return match ($word) {
            'first', '1', '1st' => 1,
            'second', '2', '2nd' => 2,
            'third', '3', '3rd' => 3,
            default => max(1, (int) preg_replace('/\D/', '', $word) ?: 1),
        };
    }
}
