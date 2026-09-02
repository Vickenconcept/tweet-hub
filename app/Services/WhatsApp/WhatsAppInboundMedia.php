<?php

namespace App\Services\WhatsApp;

class WhatsAppInboundMedia
{
    /**
     * @return array<int, string>
     */
    public function extractUrls(array $payload): array
    {
        $data = $payload['data'] ?? $payload;
        $message = $payload['message'] ?? data_get($data, 'message', []);

        if (! is_array($message)) {
            $message = [];
        }

        $urls = [];

        foreach ($this->attachmentLists($message, $data, $payload) as $attachments) {
            foreach ($attachments as $attachment) {
                $url = $this->urlFromAttachment($attachment);
                if ($url !== null && $this->isImageAttachment($attachment, $url)) {
                    $urls[] = $url;
                }
            }
        }

        foreach ($this->directUrlPaths() as $path) {
            $url = data_get($message, $path) ?? data_get($data, 'message.'.$path) ?? data_get($payload, $path);
            if (is_string($url) && $url !== '') {
                $urls[] = $url;
            }
        }

        $messageType = strtolower((string) (data_get($message, 'type') ?? data_get($message, 'messageType') ?? ''));
        if (in_array($messageType, ['image', 'photo', 'picture', 'sticker'], true)) {
            $url = data_get($message, 'url')
                ?? data_get($message, 'mediaUrl')
                ?? data_get($message, 'media.url');
            if (is_string($url) && $url !== '') {
                $urls[] = $url;
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * @return array<int, array<mixed>>
     */
    protected function attachmentLists(array $message, array $data, array $payload): array
    {
        $lists = [];

        foreach ([
            data_get($message, 'attachments'),
            data_get($message, 'media'),
            data_get($message, 'mediaAttachments'),
            data_get($data, 'message.attachments'),
            data_get($data, 'message.media'),
            data_get($payload, 'attachments'),
        ] as $list) {
            if (is_array($list) && $list !== []) {
                $lists[] = $list;
            }
        }

        return $lists;
    }

    protected function urlFromAttachment(mixed $attachment): ?string
    {
        if (is_string($attachment) && str_starts_with($attachment, 'http')) {
            return $attachment;
        }

        if (! is_array($attachment)) {
            return null;
        }

        foreach (['url', 'mediaUrl', 'media_url', 'link', 'downloadUrl', 'src'] as $key) {
            $value = $attachment[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return data_get($attachment, 'media.url') ?? data_get($attachment, 'image.url');
    }

    protected function isImageAttachment(mixed $attachment, string $url): bool
    {
        if (! is_array($attachment)) {
            return $this->looksLikeImageUrl($url);
        }

        $type = strtolower((string) (
            $attachment['type']
            ?? $attachment['mimeType']
            ?? $attachment['mime_type']
            ?? $attachment['contentType']
            ?? ''
        ));

        if ($type !== '') {
            return str_starts_with($type, 'image/') || in_array($type, ['image', 'photo', 'picture', 'sticker'], true);
        }

        return $this->looksLikeImageUrl($url);
    }

    protected function looksLikeImageUrl(string $url): bool
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');

        return (bool) preg_match('/\.(jpe?g|png|gif|webp)(\?|$)/', $path)
            || str_contains($url, '/image/upload/')
            || str_contains($url, 'mimetype=image');
    }

    /**
     * @return array<int, string>
     */
    protected function directUrlPaths(): array
    {
        return [
            'mediaUrl',
            'media_url',
            'imageUrl',
            'image.url',
            'media.url',
            'attachment.url',
        ];
    }
}
