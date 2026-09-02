<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\WhatsApp\WhatsAppEngagementService;
use App\Services\WhatsApp\WhatsAppNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CheckWhatsAppMentionAlerts extends Command
{
    protected $signature = 'whatsapp:check-mention-alerts';

    protected $description = 'Send WhatsApp alerts for new Twitter mentions';

    public function handle(
        WhatsAppEngagementService $engagement,
        WhatsAppNotificationService $notifications,
    ): int {
        $users = User::query()
            ->where('whatsapp_notify_new_mentions', true)
            ->where('whatsapp_bot_enabled', true)
            ->whereNotNull('whatsapp_verified_at')
            ->whereNotNull('zernio_conversation_id')
            ->where('twitter_account_connected', true)
            ->get();

        foreach ($users as $user) {
            try {
                $mentions = $engagement->fetchMentions($user);
                $cacheKey = "whatsapp_last_mention_ids_{$user->id}";
                $knownIds = Cache::get($cacheKey, []);
                $currentIds = array_column($mentions, 'id');

                if (! empty($knownIds)) {
                    $newMentions = array_filter(
                        $mentions,
                        fn ($m) => ! in_array($m['id'], $knownIds, true)
                    );

                    foreach (array_slice($newMentions, 0, 3) as $mention) {
                        $notifications->notifyNewMention(
                            $user,
                            $mention['author'],
                            $mention['text'],
                            $mention['url']
                        );
                    }
                }

                Cache::put($cacheKey, $currentIds, now()->addDays(7));
            } catch (\Throwable $e) {
                $this->warn("User {$user->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
