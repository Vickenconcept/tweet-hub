<?php

namespace App\Services\WhatsApp;

/**
 * Second step: turn a parsed intent into a concrete app action using session/media context.
 */
class WhatsAppIntentPlanner
{
    public function __construct(
        protected WhatsAppAssetAttachments $assetAttachments,
    ) {}

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array{has_attached_image?: bool}  $context
     * @return array<string, mixed>
     */
    public function plan(array $parsed, array $context = []): array
    {
        $action = (string) ($parsed['action'] ?? 'unknown');
        if ($action === 'unknown') {
            return $parsed;
        }

        if ($this->shouldAttachInboundImage($parsed, $context)) {
            $parsed['content'] = $this->appendImageAttachment(
                (string) ($parsed['content'] ?? ''),
            );
        }

        return $parsed + ['planned_by' => 'intent_planner'];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array{has_attached_image?: bool}  $context
     */
    protected function shouldAttachInboundImage(array $parsed, array $context): bool
    {
        if (! ($context['has_attached_image'] ?? false)) {
            return false;
        }

        $action = (string) ($parsed['action'] ?? '');
        if (! in_array($action, ['post', 'schedule', 'create_and_schedule', 'create_with_image_and_post', 'create_with_image_and_schedule'], true)) {
            return false;
        }

        $content = strtolower((string) ($parsed['content'] ?? ''));

        return ! preg_match('/\bwith\s+(?:the|this|my)\s+image\b|\bwith\s+image\s+\d+\b/', $content);
    }

    protected function appendImageAttachment(string $content): string
    {
        $content = trim($content);
        if ($content === '' || strtolower($content) === 'with the image') {
            return 'with the image';
        }

        return $content.' with the image';
    }
}
