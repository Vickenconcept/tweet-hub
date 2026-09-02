<?php

namespace App\Services\WhatsApp;

class WhatsAppInboundMedia
{
    /**
     * @return array{image_urls: array<int, string>, unsupported_media: bool}
     */
    public function analyze(array $payload): array
    {
        $imageUrls = $this->extractUrls($payload);

        if ($imageUrls !== []) {
            return [
                'image_urls' => $imageUrls,
                'unsupported_media' => false,
            ];
        }

        return [
            'image_urls' => [],
            'unsupported_media' => $this->detectUnsupportedMedia($payload),
        ];
    }

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

        $accountId = $this->accountIdFromPayload($payload);
        $urls = [];

        foreach ($this->attachmentLists($message, $data, $payload) as $attachments) {
            foreach ($attachments as $attachment) {
                $url = $this->urlFromAttachment($attachment, $accountId);
                if ($url !== null && $this->isImageAttachment($attachment, $url)) {
                    $urls[] = $url;
                }
            }
        }

        foreach ($this->directUrlPaths() as $path) {
            $url = data_get($message, $path) ?? data_get($data, 'message.'.$path) ?? data_get($payload, $path);
            if (is_string($url) && $url !== '') {
                $urls[] = $this->normalizeMediaUrl($url, $accountId);
            }
        }

        $messageType = strtolower((string) (data_get($message, 'type') ?? data_get($message, 'messageType') ?? ''));
        if (in_array($messageType, ['image', 'photo', 'picture', 'sticker'], true)) {
            $url = data_get($message, 'url')
                ?? data_get($message, 'mediaUrl')
                ?? data_get($message, 'media.url');
            if (is_string($url) && $url !== '') {
                $urls[] = $this->normalizeMediaUrl($url, $accountId);
            }
        }

        if ($messageType === 'document' && $this->messageDocumentLooksLikeImage($message)) {
            $url = data_get($message, 'url')
                ?? data_get($message, 'mediaUrl')
                ?? data_get($message, 'media.url');
            if (is_string($url) && $url !== '') {
                $urls[] = $this->normalizeMediaUrl($url, $accountId);
            } elseif ($accountId !== null) {
                $mediaId = data_get($message, 'mediaId')
                    ?? data_get($message, 'media_id')
                    ?? data_get($message, 'platformMediaId')
                    ?? data_get($message, 'id');
                if ($mediaId !== null) {
                    $urls[] = $this->buildZernioMediaUrl((string) $mediaId, $accountId);
                }
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }

    protected function detectUnsupportedMedia(array $payload): bool
    {
        $data = $payload['data'] ?? $payload;
        $message = $payload['message'] ?? data_get($data, 'message', []);

        if (! is_array($message)) {
            $message = [];
        }

        foreach ($this->attachmentLists($message, $data, $payload) as $attachments) {
            foreach ($attachments as $attachment) {
                if ($this->attachmentIsNonImageMedia($attachment)) {
                    return true;
                }
            }
        }

        $messageType = strtolower((string) (data_get($message, 'type') ?? data_get($message, 'messageType') ?? ''));
        if (! in_array($messageType, ['document', 'video', 'audio', 'voice', 'ptt', 'file'], true)) {
            return false;
        }

        if ($messageType === 'document') {
            if ($this->messageDocumentLooksLikeImage($message)) {
                return false;
            }

            $mime = strtolower((string) (data_get($message, 'mimeType') ?? data_get($message, 'mime_type') ?? ''));
            $filename = strtolower((string) (data_get($message, 'filename') ?? data_get($message, 'name') ?? ''));

            return ($mime !== '' || $filename !== '')
                && ! str_starts_with($mime, 'image/')
                && ! preg_match('/\.(jpe?g|png|gif|webp)$/', $filename);
        }

        return (bool) (
            data_get($message, 'url')
            || data_get($message, 'mediaUrl')
            || data_get($message, 'media.url')
            || data_get($message, 'mediaId')
            || data_get($message, 'media_id')
            || data_get($message, 'attachments')
        );
    }

    protected function accountIdFromPayload(array $payload): ?string
    {
        return (string) (
            data_get($payload, 'account._id')
            ?? data_get($payload, 'account.id')
            ?? data_get($payload, 'account.accountId')
            ?? config('services.zernio.whatsapp_account_id')
            ?? ''
        ) ?: null;
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

    protected function urlFromAttachment(mixed $attachment, ?string $accountId): ?string
    {
        if (is_string($attachment) && str_starts_with($attachment, 'http')) {
            return $this->normalizeMediaUrl($attachment, $accountId);
        }

        if (! is_array($attachment)) {
            return null;
        }

        foreach (['url', 'mediaUrl', 'media_url', 'link', 'downloadUrl', 'src'] as $key) {
            $value = $attachment[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $this->normalizeMediaUrl($value, $accountId);
            }
        }

        $nested = data_get($attachment, 'media.url') ?? data_get($attachment, 'image.url');
        if (is_string($nested) && $nested !== '') {
            return $this->normalizeMediaUrl($nested, $accountId);
        }

        $mediaId = $attachment['mediaId']
            ?? $attachment['media_id']
            ?? $attachment['platformMediaId']
            ?? $attachment['id']
            ?? null;

        if ($mediaId !== null && $accountId !== null && $this->shouldTryMediaDownload($attachment)) {
            return $this->buildZernioMediaUrl((string) $mediaId, $accountId);
        }

        return null;
    }

    protected function attachmentLooksLikeImage(mixed $attachment): bool
    {
        if (! is_array($attachment)) {
            return true;
        }

        $mime = strtolower((string) (
            $attachment['mimeType']
            ?? $attachment['mime_type']
            ?? $attachment['contentType']
            ?? ''
        ));

        if ($mime !== '') {
            return str_starts_with($mime, 'image/');
        }

        $filename = strtolower((string) ($attachment['filename'] ?? $attachment['name'] ?? $attachment['fileName'] ?? ''));
        if ($filename !== '' && preg_match('/\.(jpe?g|png|gif|webp)$/', $filename)) {
            return true;
        }

        $type = strtolower((string) ($attachment['type'] ?? ''));

        if ($type === '') {
            return true;
        }

        return in_array($type, ['image', 'photo', 'picture', 'sticker'], true);
    }

    protected function messageDocumentLooksLikeImage(array $message): bool
    {
        $mime = strtolower((string) (data_get($message, 'mimeType') ?? data_get($message, 'mime_type') ?? ''));
        if ($mime !== '') {
            return str_starts_with($mime, 'image/');
        }

        $filename = strtolower((string) (data_get($message, 'filename') ?? data_get($message, 'name') ?? ''));

        return $filename !== '' && (bool) preg_match('/\.(jpe?g|png|gif|webp)$/', $filename);
    }

    protected function shouldTryMediaDownload(mixed $attachment): bool
    {
        if ($this->attachmentLooksLikeImage($attachment)) {
            return true;
        }

        if (! is_array($attachment)) {
            return false;
        }

        $type = strtolower((string) ($attachment['type'] ?? ''));
        if ($type !== 'document') {
            return false;
        }

        $mime = strtolower((string) (
            $attachment['mimeType']
            ?? $attachment['mime_type']
            ?? $attachment['contentType']
            ?? ''
        ));
        $filename = strtolower((string) ($attachment['filename'] ?? $attachment['name'] ?? $attachment['fileName'] ?? ''));

        // Unknown document metadata — try download; content-type check rejects non-images.
        return $mime === '' && $filename === '';
    }

    protected function attachmentIsNonImageMedia(mixed $attachment): bool
    {
        if ($this->attachmentLooksLikeImage($attachment) || $this->shouldTryMediaDownload($attachment)) {
            return false;
        }

        if (! $this->attachmentHasMedia($attachment)) {
            return false;
        }

        if (! is_array($attachment)) {
            return false;
        }

        $mime = strtolower((string) (
            $attachment['mimeType']
            ?? $attachment['mime_type']
            ?? $attachment['contentType']
            ?? ''
        ));
        if ($mime !== '' && ! str_starts_with($mime, 'image/')) {
            return true;
        }

        $type = strtolower((string) ($attachment['type'] ?? ''));
        if (in_array($type, ['video', 'audio', 'voice', 'ptt', 'file'], true)) {
            return true;
        }

        if ($type === 'document') {
            $filename = strtolower((string) ($attachment['filename'] ?? $attachment['name'] ?? $attachment['fileName'] ?? ''));

            return $filename !== '' && ! preg_match('/\.(jpe?g|png|gif|webp)$/', $filename);
        }

        return $type !== '' && ! in_array($type, ['image', 'photo', 'picture', 'sticker'], true);
    }

    protected function attachmentHasMedia(mixed $attachment): bool
    {
        if (is_string($attachment)) {
            return str_starts_with($attachment, 'http') || str_starts_with($attachment, '/');
        }

        if (! is_array($attachment)) {
            return false;
        }

        foreach (['url', 'mediaUrl', 'media_url', 'link', 'downloadUrl', 'src', 'mediaId', 'media_id', 'platformMediaId', 'id'] as $key) {
            $value = $attachment[$key] ?? null;
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return data_get($attachment, 'media.url') !== null || data_get($attachment, 'image.url') !== null;
    }

    protected function buildZernioMediaUrl(string $mediaId, string $accountId): string
    {
        $base = rtrim(config('services.zernio.base_url', 'https://zernio.com/api/v1'), '/');

        return "{$base}/whatsapp/media/{$mediaId}?accountId={$accountId}";
    }

    protected function normalizeMediaUrl(string $url, ?string $accountId): string
    {
        if (str_starts_with($url, '/')) {
            $base = rtrim(config('services.zernio.base_url', 'https://zernio.com/api/v1'), '/');
            $url = $base.$url;
        }

        if ($accountId !== null && str_contains($url, '/whatsapp/media/') && ! str_contains($url, 'accountId=')) {
            $separator = str_contains($url, '?') ? '&' : '?';

            return $url.$separator.'accountId='.$accountId;
        }

        return $url;
    }

    protected function isImageAttachment(mixed $attachment, string $url): bool
    {
        if (! is_array($attachment)) {
            return $this->looksLikeImageUrl($url);
        }

        return $this->attachmentLooksLikeImage($attachment) && (
            $this->looksLikeImageUrl($url) || $this->shouldTryMediaDownload($attachment)
        );
    }

    protected function looksLikeImageUrl(string $url): bool
    {
        if (str_contains($url, '/whatsapp/media/')) {
            return true;
        }

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
