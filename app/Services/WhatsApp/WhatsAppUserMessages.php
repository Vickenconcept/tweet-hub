<?php

namespace App\Services\WhatsApp;

use Throwable;

class WhatsAppUserMessages
{
    public static function fromException(Throwable $e): string
    {
        $raw = trim($e->getMessage());

        if ($raw !== '' && self::isUserFacing($raw)) {
            return self::prefix($raw);
        }

        return self::prefix(self::mapTechnicalError($raw));
    }

    public static function isUserFacing(string $message): bool
    {
        if (self::looksTechnical($message)) {
            return false;
        }

        $prefixes = [
            'Use:',
            'Connect your',
            'Permission denied',
            'Could not generate ideas',
            'Could not load bookmarks',
            'Could not bookmark',
            'Verification code',
            'Invalid verification',
            'No queued post',
            'Post content cannot',
            'Schedule time must',
            'Nothing to confirm',
            'Supported: lang',
            'Maximum ',
            'Minimum ',
            'Post not found',
            'Could not parse time',
            'Invalid reply',
            'Tweet exceeds',
            'Thread failed',
            'Thread has no',
            'Image prompt',
            'Image generation failed',
            'Failed to download',
            'Keyword ',
            'No tweet #',
            'Send *mentions*',
            'WhatsApp remote control',
            'Rate limit active',
            'Rate limit reached',
            'Unknown pending action',
        ];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($message, $prefix)) {
                return true;
            }
        }

        return mb_strlen($message) <= 200
            && ! str_contains($message, 'http')
            && ! str_contains($message, '{')
            && ! str_contains($message, '`');
    }

    public static function looksTechnical(string $message): bool
    {
        $lower = strtolower($message);

        $needles = [
            'client error:',
            'server error:',
            'curl error',
            'guzzle',
            'resulted in a `',
            '403 forbidden',
            '401 unauthorized',
            '429 too many',
            'post https://',
            'get https://',
            'api.twitter.com',
            'api.x.com',
            'oauth',
            'client_id',
            'truncated',
            'stack trace',
            'exception',
            'sqlstate',
            '{"error',
        ];

        foreach ($needles as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return (bool) preg_match('/\b(4\d{2}|5\d{2})\b/', $message)
            && str_contains($lower, 'error');
    }

    protected static function mapTechnicalError(string $raw): string
    {
        $lower = strtolower($raw);

        if (str_contains($lower, '403') || str_contains($lower, 'forbidden') || str_contains($lower, 'authenticating requests')) {
            return 'Could not post to X. Open the app and reconnect your X/Twitter account, then try again.';
        }

        if (str_contains($lower, '401') || str_contains($lower, 'unauthorized')) {
            return 'Your X session expired. Reconnect X/Twitter in the app, then try again.';
        }

        if (str_contains($lower, '429') || str_contains($lower, 'too many requests')) {
            return 'X rate limit reached. Wait a few minutes and try again.';
        }

        if (str_contains($lower, 'rate limit')) {
            return 'Too many requests right now. Please wait a bit and try again.';
        }

        if (str_contains($lower, 'oauth') || str_contains($lower, 'not authenticated')) {
            return 'Connect your X/Twitter account in the app first.';
        }

        if (str_contains($lower, 'bookmark')) {
            return 'Bookmarks are not available on your current X plan. Use the app instead.';
        }

        if (str_contains($lower, 'timeout') || str_contains($lower, 'curl') || str_contains($lower, 'resolve host') || str_contains($lower, 'connection')) {
            return 'Connection problem. Please try again in a moment.';
        }

        if (str_contains($lower, 'character limit') || str_contains($lower, '280')) {
            return 'Your tweet is too long. X allows up to 280 characters.';
        }

        return 'Something went wrong. Try again, or send *status* to check your connection.';
    }

    protected static function prefix(string $message): string
    {
        return str_starts_with($message, '❌') ? $message : '❌ '.$message;
    }
}
