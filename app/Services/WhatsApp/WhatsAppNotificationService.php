<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use App\Services\ZernioService;
use Illuminate\Support\Facades\Log;

class WhatsAppNotificationService
{
    public function __construct(
        protected ZernioService $zernio,
    ) {}

    public function canNotify(User $user): bool
    {
        return $user->isWhatsAppBotActive() && ! empty($user->zernio_conversation_id);
    }

    public function notify(User $user, string $message): bool
    {
        if (! $user->isWhatsAppBotActive()) {
            return false;
        }

        if (! empty($user->zernio_conversation_id)) {
            if ($this->zernio->sendInboxMessage($user->zernio_conversation_id, $message)) {
                return true;
            }
        }

        $template = config('services.zernio.alert_template');
        if ($template && $user->whatsapp_phone) {
            return $this->zernio->sendTemplateMessage(
                $user->whatsapp_phone,
                $template,
                [mb_substr($message, 0, 900)]
            );
        }

        return false;
    }

    public function notifyPostPublished(User $user, string $content, ?string $url = null): void
    {
        if (! $user->whatsapp_notify_post_published) {
            return;
        }

        $message = "✅ *Post published*\n\n".mb_substr($content, 0, 180);
        if ($url) {
            $message .= "\n{$url}";
        }

        $this->notify($user, $message);
    }

    public function notifyPostFailed(User $user, string $content, string $error): void
    {
        if (! $user->whatsapp_notify_post_failed) {
            return;
        }

        $message = "❌ *Post failed*\n\n".mb_substr($content, 0, 120)."\n\nError: ".mb_substr($error, 0, 200);

        $this->notify($user, $message);
    }

    public function notifyNewMention(User $user, string $author, string $text, string $url): void
    {
        if (! $user->whatsapp_notify_new_mentions) {
            return;
        }

        $message = "📬 *New mention* from @{$author}\n\n".mb_substr($text, 0, 200)."\n\n{$url}";

        $this->notify($user, $message);
    }
}
