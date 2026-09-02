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

        return $text !== '' ? $text : '📸 (with image)';
    }
}
