<?php

namespace App\Services\WhatsApp;

use App\Models\User;

class WhatsAppAssetAttachments
{
    /**
     * Turn friendly image phrases into internal [img:code] tags (hidden from users in hints).
     */
    public function apply(User $user, string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            return $content;
        }

        $context = WhatsAppSessionContext::for($user);
        $attachmentCode = null;

        if (preg_match('/\s+with\s+image\s+#?(\d+)\s*$/iu', $content, $matches)) {
            $content = trim((string) preg_replace('/\s+with\s+image\s+#?(\d+)\s*$/iu', '', $content));
            $attachmentCode = $context->getAssetCodeByIndex((int) $matches[1]);
            if ($attachmentCode === null) {
                throw new \RuntimeException("I don't have image #{$matches[1]}. Send *my images* to see what's saved.");
            }
        } elseif (preg_match('/\s+with\s+(?:the|this|that|my)\s+image\s*$/iu', $content)) {
            $content = trim((string) preg_replace('/\s+with\s+(?:the|this|that|my)\s+image\s*$/iu', '', $content));
            $attachmentCode = $context->getLastImageCode();
            if ($attachmentCode === null) {
                throw new \RuntimeException('No recent image saved. Generate one with *image: your description* or send *my images*.');
            }
        }

        if ($attachmentCode !== null) {
            $content = trim($content).' [img:'.$attachmentCode.']';
        }

        return preg_replace_callback(
            '/\[(img|vid|gif):(\d+)\]/',
            function (array $matches) use ($context) {
                $code = $context->getAssetCodeByIndex((int) $matches[2]);
                if ($code === null) {
                    throw new \RuntimeException("I don't have image #{$matches[2]}. Send *my images* to see what's saved.");
                }

                return '['.$matches[1].':'.$code.']';
            },
            $content
        );
    }

    public function displayText(string $content): string
    {
        $text = preg_replace('/\s*\[(img|vid|gif):[^\]]+\]/', '', $content);
        $text = trim((string) $text);

        return $text !== '' ? $text : '📸 (image only)';
    }

    public function hasMediaTag(string $content): bool
    {
        return (bool) preg_match('/\[(img|vid|gif):[^\]]+\]/', $content);
    }

    public function isPostable(string $content): bool
    {
        $content = trim($content);
        if ($content === '') {
            return false;
        }

        if ($this->hasMediaTag($content)) {
            return true;
        }

        return trim((string) preg_replace('/\s*\[(img|vid|gif):[^\]]+\]/', '', $content)) !== '';
    }

    /**
     * Turn photo captions like "post this image" into post/schedule commands.
     */
    public function normalizePhotoCaption(string $caption): string
    {
        $caption = trim($caption);
        if ($caption === '') {
            return '';
        }

        if (preg_match('/^post\s+(?:this|the|my)\s*(?:image|photo|picture)?\s*$/iu', $caption)) {
            return 'post: with the image';
        }

        if (preg_match('/^post\s+image\s+#?(\d+)\s*$/iu', $caption, $matches)) {
            return 'post: with image '.$matches[1];
        }

        if (preg_match('/^schedule\s+(?:this|the|my)\s*(?:image|photo|picture)?\s+(?:at\s+)?(.+)$/iu', $caption, $matches)) {
            return 'schedule: '.trim($matches[1]).' | with the image';
        }

        if (preg_match('/^schedule\s+image\s+#?(\d+)\s+(?:at\s+)?(.+)$/iu', $caption, $matches)) {
            return 'schedule: '.trim($matches[2]).' | with image '.$matches[1];
        }

        return $caption;
    }

    /**
     * @return array{action: string, when?: string, content?: string}|null
     */
    public static function parseImagePostScheduleIntent(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if (preg_match('/^post\s+(?:this|the|my)\s*(?:image|photo|picture)?\s*$/iu', $text)) {
            return ['action' => 'post', 'content' => 'with the image'];
        }

        if (preg_match('/^post\s+image\s+#?(\d+)\s*$/iu', $text, $matches)) {
            return ['action' => 'post', 'content' => 'with image '.$matches[1]];
        }

        if (preg_match('/^post\s+image\s+#?(\d+):\s*(.*)$/is', $text, $matches)) {
            $caption = trim($matches[2]);

            return [
                'action' => 'post',
                'content' => ($caption !== '' ? $caption.' ' : '').'with image '.$matches[1],
            ];
        }

        if (preg_match('/^schedule\s+(?:this|the|my)\s*(?:image|photo|picture)?\s+(?:at\s+)?(.+)$/iu', $text, $matches)) {
            return [
                'action' => 'schedule',
                'when' => trim($matches[1]),
                'content' => 'with the image',
            ];
        }

        if (preg_match('/^schedule\s+image\s+#?(\d+)\s+(?:at\s+)?(.+)$/iu', $text, $matches)) {
            return [
                'action' => 'schedule',
                'when' => trim($matches[2]),
                'content' => 'with image '.$matches[1],
            ];
        }

        if (preg_match('/^schedule\s+(.+?)\s+with\s+(?:the|this|my)\s+image\s*$/iu', $text, $matches)) {
            return [
                'action' => 'schedule',
                'when' => trim($matches[1]),
                'content' => 'with the image',
            ];
        }

        if (preg_match('/^schedule\s+(.+?)\s+with\s+image\s+#?(\d+)\s*$/iu', $text, $matches)) {
            return [
                'action' => 'schedule',
                'when' => trim($matches[1]),
                'content' => 'with image '.$matches[2],
            ];
        }

        return null;
    }
}
