<?php

namespace App\Services\WhatsApp;

use App\Models\Post;
use App\Models\User;
use App\Models\WhatsAppCommandLog;
use App\Services\ChatGptService;
use App\Services\TwitterService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsAppCommandExecutor
{
    public function __construct(
        protected ChatGptService $chatGptService,
        protected WhatsAppEngagementService $engagement,
        protected WhatsAppMediaService $media,
        protected WhatsAppNotificationService $notifications,
        protected WhatsAppTweetPublisher $publisher,
    ) {}

    public function execute(User $user, array $parsed, WhatsAppCommandLog $log): string
    {
        $action = $parsed['action'] ?? 'unknown';

        try {
            $response = match ($action) {
                'help' => $this->helpFor($user),
                'help_shortcuts' => $this->helpShortcutsFor($user),
                'status' => $this->status($user),
                'settings' => $this->settings($user),
                'post' => $this->post($user, $parsed['content'] ?? ''),
                'schedule' => $this->schedule($user, $parsed['when'] ?? '', $parsed['content'] ?? ''),
                'queue' => $this->queue($user),
                'delete_queue' => $this->deleteQueue($user, $parsed['index'] ?? 0),
                'ideas' => $this->ideas($user),
                'generate' => $this->generate($user, $parsed['prompt'] ?? ''),
                'draft' => $this->draft($user, $parsed['content'] ?? ''),
                'drafts' => $this->drafts($user),
                'mentions' => $this->mentions($user),
                'reply' => $this->reply($user, $parsed['index'] ?? 0, $parsed['content'] ?? ''),
                'keywords' => $this->keywords($user),
                'add_keyword' => $this->addKeyword($user, $parsed['keyword'] ?? ''),
                'remove_keyword' => $this->removeKeyword($user, $parsed['keyword'] ?? ''),
                'search' => $this->search($user, $parsed['query'] ?? ''),
                'analytics' => $this->analytics($user, $parsed['tweet_id'] ?? ''),
                'auto_mentions_on' => $this->toggleAutoMentions($user, true),
                'auto_mentions_off' => $this->toggleAutoMentions($user, false),
                'auto_keywords_on' => $this->toggleAutoKeywords($user, true),
                'auto_keywords_off' => $this->toggleAutoKeywords($user, false),
                'auto_posts' => $this->autoPosts($user),
                'auto_posts_toggle' => $this->toggleAutoPosts($user, $parsed['index'] ?? 0, $parsed['enabled'] ?? false),
                'image' => $this->generateImage($user, $parsed['prompt'] ?? ''),
                'assets' => $this->assets($user),
                'notify_posts_on' => $this->toggleNotifyPosts($user, true),
                'notify_posts_off' => $this->toggleNotifyPosts($user, false),
                'notify_mentions_on' => $this->toggleNotifyMentions($user, true),
                'notify_mentions_off' => $this->toggleNotifyMentions($user, false),
                'thread' => $this->thread($user, $parsed['parts'] ?? []),
                'bookmark' => $this->bookmark($user, $parsed['url'] ?? ''),
                'bookmarks' => $this->bookmarks($user),
                'lang' => $this->setLanguage($user, $parsed['language'] ?? 'en'),
                'start' => $this->start($user),
                'confirm' => $this->confirm($user),
                'unlink' => $this->unlink($user),
                'verify' => $this->verify($user, $parsed['code'] ?? ''),
                'chat' => $this->chat($user, $parsed['type'] ?? 'fallback'),
                default => $this->friendlyFallback(),
            };

            $log->update([
                'parsed_action' => $action,
                'status' => 'pending',
                'response_preview' => mb_substr($response, 0, 500),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::warning('WhatsApp command error', [
                'action' => $action,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            $userMessage = WhatsAppUserMessages::fromException($e);

            $log->update([
                'parsed_action' => $action,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'response_preview' => mb_substr($userMessage, 0, 500),
            ]);

            return $userMessage;
        }
    }

    protected function helpFor(User $user): string
    {
        return WhatsAppHelpMessages::help($user);
    }

    protected function helpShortcutsFor(User $user): string
    {
        return WhatsAppHelpMessages::shortcuts($user);
    }

    protected function status(User $user): string
    {
        $queued = Post::where('user_id', $user->id)->where('status', 'scheduled')->count();
        $drafts = Post::where('user_id', $user->id)->where('status', 'draft')->count();
        $xStatus = $user->isTwitterConnected()
            ? 'Connected (@'.$user->twitter_username.')'
            : 'Not connected — connect X in the app first';

        return implode("\n", [
            '*XEngager Status*',
            '',
            'X/Twitter: '.$xStatus,
            'WhatsApp: '.($user->isWhatsAppBotActive() ? 'Active' : 'Disabled'),
            'Queued posts: '.$queued,
            'Drafts: '.$drafts,
            'Timezone: '.$user->preferredTimezone(),
        ]);
    }

    protected function post(User $user, string $content): string
    {
        $this->requirePermission($user, 'post');
        $this->requireTwitter($user);

        $content = trim($content);
        if ($content === '') {
            throw new \RuntimeException('Post content cannot be empty. Use: post: your text');
        }

        if (! $user->whatsapp_quick_mode) {
            Cache::put($this->pendingKey($user), [
                'action' => 'post',
                'content' => $content,
            ], now()->addMinutes(10));

            return '⚠️ *Confirm post?*'."\n\n".mb_substr($content, 0, 200)."\n\nReply *confirm* to publish, or send a new command to cancel.";
        }

        return $this->publishTweet($user, $content);
    }

    protected function schedule(User $user, string $when, string $content): string
    {
        $this->requirePermission($user, 'schedule');
        $this->requireTwitter($user);

        $content = trim($content);
        if ($content === '' || $when === '') {
            throw new \RuntimeException('Use: schedule: tomorrow 9am | your tweet text');
        }

        $scheduledAt = $this->parseScheduleTime($when, $user);
        if ($scheduledAt->lessThanOrEqualTo(now())) {
            throw new \RuntimeException('Schedule time must be in the future.');
        }

        if (! $user->whatsapp_quick_mode) {
            Cache::put($this->pendingKey($user), [
                'action' => 'schedule',
                'content' => $content,
                'scheduled_at' => $scheduledAt->toIso8601String(),
            ], now()->addMinutes(10));

            return '⚠️ *Confirm schedule?*'."\n\n".
                $scheduledAt->timezone($user->preferredTimezone())->format('D M j, g:i A')."\n".
                mb_substr($content, 0, 180)."\n\nReply *confirm* to schedule.";
        }

        return $this->createScheduledPost($user, $content, $scheduledAt);
    }

    protected function queue(User $user): string
    {
        $this->requirePermission($user, 'queue');

        $posts = Post::where('user_id', $user->id)
            ->where('status', 'scheduled')
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get();

        if ($posts->isEmpty()) {
            return '📋 No scheduled posts in your queue.';
        }

        $lines = ['📋 *Queued Posts* ('.$posts->count().')', ''];

        foreach ($posts as $index => $post) {
            $num = $index + 1;
            $time = $post->scheduled_at?->timezone($user->preferredTimezone())->format('D g:i A') ?? 'TBD';
            $preview = mb_substr($post->content, 0, 80);
            $lines[] = "{$num}️⃣ {$time}";
            $lines[] = "   \"{$preview}\"";
            $lines[] = '';
        }

        $lines[] = 'Reply: delete queue {number}';

        return implode("\n", $lines);
    }

    protected function deleteQueue(User $user, int $index): string
    {
        $this->requirePermission($user, 'delete');

        if ($index < 1) {
            throw new \RuntimeException('Use: delete queue 1 (check numbers with *queue*)');
        }

        $posts = Post::where('user_id', $user->id)
            ->where('status', 'scheduled')
            ->orderBy('scheduled_at')
            ->get();

        $post = $posts->get($index - 1);
        if (! $post) {
            throw new \RuntimeException("No queued post #{$index}. Send *queue* to see the list.");
        }

        Cache::put($this->pendingKey($user), [
            'action' => 'delete_queue',
            'post_id' => $post->id,
            'preview' => mb_substr($post->content, 0, 100),
        ], now()->addMinutes(10));

        $time = $post->scheduled_at?->timezone($user->preferredTimezone())->format('D g:i A') ?? '';

        return "⚠️ Delete post #{$index} scheduled {$time}?\n\"{$post->content}\"\n\nReply *confirm* to delete.";
    }

    protected function ideas(User $user): string
    {
        $this->requirePermission($user, 'ideas');

        $topic = $user->getDefaultTopic();
        $niche = $user->getDefaultNiche();

        $prompt = "Generate 3 complete, ready-to-post tweets for a {$niche} account about {$topic}. "
            .'Each under 279 characters. Number them 1-3. No extra commentary.';

        $response = $this->chatGptService->generateContent($prompt);
        if (! $response) {
            throw new \RuntimeException('Could not generate ideas. Try again later.');
        }

        return "💡 *Daily Ideas* ({$topic} / {$niche})\n\n".$response."\n\nReply: post: {paste idea}";
    }

    protected function generate(User $user, string $prompt): string
    {
        $this->requirePermission($user, 'generate');

        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new \RuntimeException('Use: generate: your prompt');
        }

        $fullPrompt = "Generate 3 complete tweet ideas based on this prompt: {$prompt}. "
            .'Each under 279 characters. Number them 1-3.';

        $response = $this->chatGptService->generateContent($fullPrompt);
        if (! $response) {
            throw new \RuntimeException('Could not generate ideas. Try again later.');
        }

        return "💡 *Generated Ideas*\n\n".$response;
    }

    protected function draft(User $user, string $content): string
    {
        $this->requirePermission($user, 'draft');

        $content = trim($content);
        if ($content === '') {
            throw new \RuntimeException('Use: draft: your text');
        }

        Post::create([
            'user_id' => $user->id,
            'content' => $content,
            'status' => 'draft',
        ]);

        return '✅ Draft saved ('.mb_strlen($content).' chars). Send *drafts* to list them.';
    }

    protected function drafts(User $user): string
    {
        $this->requirePermission($user, 'draft');

        $drafts = Post::where('user_id', $user->id)
            ->where('status', 'draft')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        if ($drafts->isEmpty()) {
            return '📝 No drafts. Use: draft: your text';
        }

        $lines = ['📝 *Drafts*', ''];

        foreach ($drafts as $index => $draft) {
            $lines[] = ($index + 1).'. '.mb_substr($draft->content, 0, 100);
        }

        return implode("\n", $lines);
    }

    protected function confirm(User $user): string
    {
        $pending = Cache::get($this->pendingKey($user));
        if (! $pending) {
            throw new \RuntimeException('Nothing to confirm.');
        }

        Cache::forget($this->pendingKey($user));

        return match ($pending['action'] ?? '') {
            'post' => $this->publishTweet($user, $pending['content'] ?? ''),
            'schedule' => $this->createScheduledPost(
                $user,
                $pending['content'] ?? '',
                Carbon::parse($pending['scheduled_at'] ?? now())
            ),
            'delete_queue' => $this->performDeleteQueue($user, (int) ($pending['post_id'] ?? 0)),
            'reply' => $this->performReply($user, $pending['tweet_id'] ?? '', $pending['content'] ?? ''),
            'thread' => $this->performThread($user, $pending['parts'] ?? []),
            default => throw new \RuntimeException('Unknown pending action.'),
        };
    }

    protected function settings(User $user): string
    {
        $this->requirePermission($user, 'automation');

        return implode("\n", [
            '*XEngager Settings*',
            '',
            'Auto-reply mentions: '.($user->auto_reply_mentions_enabled ? 'ON' : 'OFF'),
            'Auto-reply keywords: '.($user->auto_reply_keywords_enabled ? 'ON' : 'OFF'),
            'Notify post published: '.($user->whatsapp_notify_post_published ? 'ON' : 'OFF'),
            'Notify post failed: '.($user->whatsapp_notify_post_failed ? 'ON' : 'OFF'),
            'Notify new mentions: '.($user->whatsapp_notify_new_mentions ? 'ON' : 'OFF'),
            'Quick mode: '.($user->whatsapp_quick_mode ? 'ON' : 'OFF'),
            '',
            'Toggle: auto mentions on/off',
            'Toggle: auto keywords on/off',
            'Toggle: notify posts on/off',
            'Toggle: notify mentions on/off',
        ]);
    }

    protected function mentions(User $user): string
    {
        $this->requirePermission($user, 'mentions');
        $this->requireTwitter($user);

        $mentions = $this->engagement->fetchMentions($user);
        Cache::put($this->contextKey($user, 'mentions'), $mentions, now()->addHours(1));

        if (empty($mentions)) {
            return '📭 No recent mentions found.';
        }

        $lines = ['📬 *Recent Mentions*', ''];

        foreach (array_slice($mentions, 0, 5) as $index => $mention) {
            $num = $index + 1;
            $preview = mb_substr($mention['text'], 0, 80);
            $lines[] = "{$num}️⃣ @{$mention['author']}";
            $lines[] = "   \"{$preview}\"";
            $lines[] = "   {$mention['url']}";
            $lines[] = '';
        }

        $lines[] = 'Reply: reply {number}: your text';

        return implode("\n", $lines);
    }

    protected function reply(User $user, int $index, string $content): string
    {
        $this->requirePermission($user, 'reply');
        $this->requireTwitter($user);

        if ($index < 1) {
            throw new \RuntimeException('Use: reply 1: your reply text (see *mentions* or *search*)');
        }

        $content = trim($content);
        if ($content === '') {
            throw new \RuntimeException('Use: reply 1: your reply text');
        }

        $context = Cache::get($this->contextKey($user, 'mentions'))
            ?? Cache::get($this->contextKey($user, 'search'));

        if (empty($context)) {
            throw new \RuntimeException('Send *mentions* or *search:* first to load tweets to reply to.');
        }

        $tweet = $context[$index - 1] ?? null;
        if (! $tweet) {
            throw new \RuntimeException("No tweet #{$index} in context. Refresh with *mentions* or *search:*.");
        }

        if (! $user->whatsapp_quick_mode) {
            Cache::put($this->pendingKey($user), [
                'action' => 'reply',
                'tweet_id' => $tweet['id'],
                'content' => $content,
                'preview' => mb_substr($tweet['text'], 0, 80),
            ], now()->addMinutes(10));

            return "⚠️ *Confirm reply* to @{$tweet['author']}?\n\nYour reply:\n\"{$content}\"\n\nReply *confirm* to send.";
        }

        return $this->performReply($user, $tweet['id'], $content);
    }

    protected function performReply(User $user, string $tweetId, string $content): string
    {
        if ($tweetId === '' || mb_strlen($content) > 280) {
            throw new \RuntimeException('Invalid reply. Max 280 characters.');
        }

        $this->engagement->replyToTweet($user, $tweetId, $content);

        return '✅ Reply sent ('.mb_strlen($content).' chars).';
    }

    protected function keywords(User $user): string
    {
        $this->requirePermission($user, 'keywords');

        $keywords = $this->engagement->getKeywords($user);

        if (empty($keywords)) {
            return "🔑 No keywords monitored.\n\nAdd one: add keyword: yourbrand";
        }

        $lines = ['🔑 *Monitored Keywords*', ''];
        foreach ($keywords as $index => $keyword) {
            $lines[] = ($index + 1).'. '.$keyword;
        }
        $lines[] = '';
        $lines[] = 'Add: add keyword: word';
        $lines[] = 'Remove: remove keyword: word';

        return implode("\n", $lines);
    }

    protected function addKeyword(User $user, string $keyword): string
    {
        $this->requirePermission($user, 'keywords');

        $keyword = trim($keyword);
        if ($keyword === '' || mb_strlen($keyword) < 2) {
            throw new \RuntimeException('Use: add keyword: your word (min 2 chars)');
        }

        $keywords = $this->engagement->addKeyword($user, $keyword);

        return '✅ Keyword added: *'.$keyword."*\n\nMonitoring: ".implode(', ', $keywords);
    }

    protected function removeKeyword(User $user, string $keyword): string
    {
        $this->requirePermission($user, 'keywords');

        $keyword = trim($keyword);
        if ($keyword === '') {
            throw new \RuntimeException('Use: remove keyword: word');
        }

        $keywords = $this->engagement->removeKeyword($user, $keyword);

        return '✅ Removed *'.$keyword."*\n\nRemaining: ".(empty($keywords) ? 'none' : implode(', ', $keywords));
    }

    protected function search(User $user, string $query): string
    {
        $this->requirePermission($user, 'search');
        $this->requireTwitter($user);

        $query = trim($query);
        if ($query === '') {
            throw new \RuntimeException('Use: search: your query or #hashtag');
        }

        $results = $this->engagement->searchTweets($user, $query, 3);
        Cache::put($this->contextKey($user, 'search'), $results, now()->addHours(1));

        if (empty($results)) {
            return "🔍 No tweets found for: {$query}";
        }

        $lines = ["🔍 *Search:* {$query}", ''];

        foreach ($results as $index => $tweet) {
            $num = $index + 1;
            $preview = mb_substr($tweet['text'], 0, 80);
            $lines[] = "{$num}️⃣ @{$tweet['author']}";
            $lines[] = "   \"{$preview}\"";
            $lines[] = '';
        }

        $lines[] = 'Reply: reply {number}: your text';

        return implode("\n", $lines);
    }

    protected function analytics(User $user, string $tweetId): string
    {
        $this->requirePermission($user, 'analytics');
        $this->requireTwitter($user);

        $tweetId = preg_replace('/\D/', '', $tweetId);
        if ($tweetId === '') {
            throw new \RuntimeException('Use: analytics {tweet_id}');
        }

        $stats = $this->engagement->analyticsSummary($user, $tweetId);

        return implode("\n", [
            "📊 *Tweet Analytics*",
            '',
            "Tweet ID: {$tweetId}",
            'Likes: '.$stats['likes'],
            'Quote tweets: '.$stats['quotes'],
            'Replies: '.$stats['replies'],
            '',
            "https://x.com/i/web/status/{$tweetId}",
        ]);
    }

    protected function toggleAutoMentions(User $user, bool $enabled): string
    {
        $this->requirePermission($user, 'automation');

        $user->update(['auto_reply_mentions_enabled' => $enabled]);

        return '✅ Auto-reply to mentions: *'.($enabled ? 'ON' : 'OFF').'*';
    }

    protected function toggleAutoKeywords(User $user, bool $enabled): string
    {
        $this->requirePermission($user, 'automation');

        $user->update(['auto_reply_keywords_enabled' => $enabled]);

        return '✅ Auto-reply to keywords: *'.($enabled ? 'ON' : 'OFF').'*';
    }

    protected function autoPosts(User $user): string
    {
        $this->requirePermission($user, 'auto_posts');

        $profiles = $user->businessAutoProfiles()->orderBy('id')->get();

        if ($profiles->isEmpty()) {
            return "📅 No business auto-post profiles.\n\nCreate one in XEngager → Auto Daily Posts.";
        }

        $lines = ['📅 *Auto Daily Posts*', ''];

        foreach ($profiles as $index => $profile) {
            $num = $index + 1;
            $status = $profile->is_active ? 'ON' : 'OFF';
            $next = $profile->posts()
                ->whereIn('status', ['scheduled', 'generating'])
                ->orderBy('scheduled_for')
                ->first();

            $nextLine = $next?->scheduled_for
                ? $next->scheduled_for->timezone($profile->timezone ?? $user->preferredTimezone())->format('D g:i A')
                : 'none scheduled';

            $lines[] = "{$num}️⃣ *{$profile->name}* ({$status})";
            $lines[] = "   Posts at {$profile->posting_time} {$profile->timezone}";
            $lines[] = "   Next: {$nextLine}";
            $lines[] = '';
        }

        $lines[] = 'Toggle: auto posts {number} on/off';

        return implode("\n", $lines);
    }

    protected function toggleAutoPosts(User $user, int $index, bool $enabled): string
    {
        $this->requirePermission($user, 'auto_posts');

        if ($index < 1) {
            throw new \RuntimeException('Use: auto posts 1 on (see *auto posts* for numbers)');
        }

        $profiles = $user->businessAutoProfiles()->orderBy('id')->get();
        $profile = $profiles->get($index - 1);

        if (! $profile) {
            throw new \RuntimeException("No auto-post profile #{$index}. Send *auto posts* to list them.");
        }

        $profile->update(['is_active' => $enabled]);

        return '✅ *'.$profile->name.'* auto posts: *'.($enabled ? 'ON' : 'OFF').'*';
    }

    protected function generateImage(User $user, string $prompt): string
    {
        $this->requirePermission($user, 'image');

        $result = $this->media->generateImage($user, $prompt);

        return implode("\n", [
            '🎨 *Image generated*',
            '',
            'Asset code: '.$result['code'],
            'Use in tweet: [img:'.$result['code'].']',
            '',
            $result['url'],
        ]);
    }

    protected function assets(User $user): string
    {
        $this->requirePermission($user, 'assets');

        $assets = $this->media->recentAssets($user);

        if (empty($assets)) {
            return '🖼 No assets yet. Generate one: image: your description';
        }

        $lines = ['🖼 *Recent Assets*', ''];

        foreach ($assets as $index => $asset) {
            $lines[] = ($index + 1).'. `'.$asset['code'].'` — '.$asset['name'];
        }

        $lines[] = '';
        $lines[] = 'Use in tweet: post: Hello [img:code]';

        return implode("\n", $lines);
    }

    protected function toggleNotifyPosts(User $user, bool $enabled): string
    {
        $this->requirePermission($user, 'notifications');

        $user->update([
            'whatsapp_notify_post_published' => $enabled,
            'whatsapp_notify_post_failed' => $enabled,
        ]);

        return '✅ Post notifications (published & failed): *'.($enabled ? 'ON' : 'OFF').'*';
    }

    protected function toggleNotifyMentions(User $user, bool $enabled): string
    {
        $this->requirePermission($user, 'notifications');

        $user->update(['whatsapp_notify_new_mentions' => $enabled]);

        if ($enabled) {
            Cache::forget("whatsapp_last_mention_ids_{$user->id}");
        }

        return '✅ New mention alerts: *'.($enabled ? 'ON' : 'OFF').'*';
    }

    protected function unlink(User $user): string
    {
        $user->update([
            'whatsapp_phone' => null,
            'whatsapp_verified_at' => null,
            'whatsapp_bot_enabled' => false,
            'whatsapp_verification_code' => null,
            'whatsapp_verification_expires_at' => null,
            'zernio_conversation_id' => null,
        ]);

        Cache::forget($this->pendingKey($user));

        return '✅ WhatsApp unlinked from XEngager. Re-link anytime in WhatsApp Settings.';
    }

    protected function verify(User $user, string $code): string
    {
        if ($user->isWhatsAppVerified()) {
            return '✅ Your WhatsApp is already linked. Send *help* for commands.';
        }

        if ($user->whatsapp_verification_code !== $code) {
            throw new \RuntimeException('Invalid verification code.');
        }

        if ($user->whatsapp_verification_expires_at && $user->whatsapp_verification_expires_at->isPast()) {
            throw new \RuntimeException('Verification code expired. Request a new one in the app.');
        }

        $user->update([
            'whatsapp_verified_at' => now(),
            'whatsapp_verification_code' => null,
            'whatsapp_verification_expires_at' => null,
            'whatsapp_bot_enabled' => true,
            'whatsapp_permissions' => $user->whatsapp_permissions ?? $user->defaultWhatsAppPermissions(),
        ]);

        return "✅ WhatsApp linked!\n\n".WhatsAppHelpMessages::welcomeLinked($user);
    }

    protected function start(User $user): string
    {
        if (! $user->isWhatsAppVerified()) {
            return implode("\n", [
                '👋 *Welcome to XEngager!*',
                '',
                '1. Log in: '.config('app.url'),
                '2. Open *WhatsApp Settings*',
                '3. Enter your number & verify',
                '',
                'Then control X from WhatsApp — post, schedule, mentions & more.',
                'Send *help* once linked.',
            ]);
        }

        $lines = [
            '👋 *XEngager Remote*',
            '',
            'You\'re linked as '.($user->twitter_username ? '@'.$user->twitter_username : $user->name),
            'Language: '.strtoupper($user->whatsapp_language ?? 'en').' (change: lang es)',
            '',
        ];

        if (! $user->isTwitterConnected()) {
            $lines[] = '⚠️ Connect X/Twitter in the app to post & engage.';
        } else {
            $lines[] = '✅ X connected · Remote: '.($user->whatsapp_bot_enabled ? 'ON' : 'OFF');
        }

        $lines[] = '';
        $lines[] = 'Try: *show my mentions* · *post: Hello world!* · *status* · *help*';

        return implode("\n", $lines);
    }

    protected function setLanguage(User $user, string $language): string
    {
        $language = strtolower($language);
        if (! in_array($language, ['en', 'es', 'fr'], true)) {
            throw new \RuntimeException('Supported: lang en · lang es · lang fr');
        }

        $user->update(['whatsapp_language' => $language]);

        return '✅ Language set to *'.strtoupper($language)."*. Send *help* for commands.";
    }

    protected function thread(User $user, array $parts): string
    {
        $this->requirePermission($user, 'thread');
        $this->requireTwitter($user);

        $parts = array_values(array_filter(array_map('trim', $parts)));
        if (count($parts) < 2) {
            throw new \RuntimeException('Use: thread: part 1 | part 2 | part 3 (min 2 parts, separate with |)');
        }

        if (count($parts) > 10) {
            throw new \RuntimeException('Maximum 10 thread parts.');
        }

        if (! $user->whatsapp_quick_mode) {
            Cache::put($this->pendingKey($user), [
                'action' => 'thread',
                'parts' => $parts,
            ], now()->addMinutes(10));

            $preview = implode("\n→ ", array_map(fn ($p) => mb_substr($p, 0, 60), array_slice($parts, 0, 3)));

            return "⚠️ *Confirm thread?* (".count($parts)." parts)\n\n→ {$preview}\n\nReply *confirm* to publish.";
        }

        return $this->performThread($user, $parts);
    }

    protected function performThread(User $user, array $parts): string
    {
        $result = $this->publisher->publishThread($user, $parts);

        $handle = $user->twitter_username ? '@'.$user->twitter_username : 'your account';
        $message = "✅ Thread posted to {$handle} ({$result['parts']} parts).";
        if ($result['url']) {
            $message .= "\n{$result['url']}";
        }

        $this->notifications->notifyPostPublished($user, $result['content'], $result['url']);

        return $message;
    }

    protected function bookmarks(User $user): string
    {
        $this->requirePermission($user, 'bookmarks');
        $this->requireTwitter($user);

        try {
            $bookmarks = $this->engagement->fetchBookmarks($user);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp bookmarks list failed', ['error' => $e->getMessage()]);

            throw new \RuntimeException('Could not load bookmarks right now. Try again from the app.');
        }

        if (empty($bookmarks)) {
            return '🔖 No bookmarks found.';
        }

        $lines = ['🔖 *Bookmarks*', ''];
        foreach ($bookmarks as $index => $bookmark) {
            $lines[] = ($index + 1).'. '.mb_substr($bookmark['text'], 0, 80);
            $lines[] = '   '.$bookmark['url'];
        }

        return implode("\n", $lines);
    }

    protected function bookmark(User $user, string $url): string
    {
        $this->requirePermission($user, 'bookmarks');
        $this->requireTwitter($user);

        $tweetId = $this->engagement->extractTweetIdFromUrl($url);
        if (! $tweetId) {
            throw new \RuntimeException('Use: bookmark: https://x.com/user/status/123...');
        }

        try {
            $twitter = $this->twitterService($user);
            $twitter->addBookmark($tweetId);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp bookmark add failed', ['error' => $e->getMessage()]);

            throw new \RuntimeException('Could not bookmark that tweet. This may not be available on your X API plan.');
        }

        return "✅ Bookmarked tweet {$tweetId}";
    }

    protected function chat(User $user, string $type): string
    {
        $firstName = trim(explode(' ', $user->name ?? '')[0] ?: 'there');

        return match ($type) {
            'greeting' => implode("\n", [
                "👋 Hey {$firstName}! I'm *XEngager* — your X assistant on WhatsApp.",
                '',
                'I can help you post tweets, check mentions, view your queue, and more.',
                '',
                'Try: *show my mentions* · *show my queue* · *help*',
            ]),
            'identity' => implode("\n", [
                "I'm *XEngager* — your WhatsApp remote for X (Twitter). 🤖",
                '',
                'Tell me what you need in plain English, for example:',
                '• *Post: Hello world!*',
                '• *Show my mentions*',
                '• *Give me post ideas*',
                '',
                'Send *help* to see everything I can do.',
            ]),
            'thanks' => "You're welcome, {$firstName}! 🙂 Message me anytime — or send *help* if you need ideas.",
            'goodbye' => "Talk soon, {$firstName}! 👋 I'm here whenever you need to manage X from WhatsApp.",
            default => $this->friendlyFallback(),
        };
    }

    protected function friendlyFallback(): string
    {
        return implode("\n", [
            "I'm *XEngager* — I help you manage X from WhatsApp.",
            '',
            'Here are things you can say right now:',
            '• *Show my queue*',
            '• *Show my mentions*',
            '• *Post: your tweet here*',
            '• *Status*',
            '',
            'Send *help* for the full guide.',
        ]);
    }

    protected function unknown(?string $raw): string
    {
        return $this->friendlyFallback();
    }

    protected function publishTweet(User $user, string $content): string
    {
        $result = $this->publisher->publish($user, $content);

        $handle = $user->twitter_username ? '@'.$user->twitter_username : 'your account';
        $message = "✅ Posted to {$handle} (".mb_strlen($result['content']).' chars).';
        if ($result['url']) {
            $message .= "\n{$result['url']}";
        }

        $this->notifications->notifyPostPublished($user, $result['content'], $result['url']);

        return $message;
    }

    protected function createScheduledPost(User $user, string $content, Carbon $scheduledAt): string
    {
        Post::create([
            'user_id' => $user->id,
            'content' => $content,
            'scheduled_at' => $scheduledAt,
            'status' => 'scheduled',
        ]);

        $local = $scheduledAt->copy()->timezone($user->preferredTimezone());

        return '✅ Scheduled for '.$local->format('D M j, g:i A')."\n\"".mb_substr($content, 0, 120).'"';
    }

    protected function performDeleteQueue(User $user, int $postId): string
    {
        $deleted = Post::where('id', $postId)
            ->where('user_id', $user->id)
            ->where('status', 'scheduled')
            ->delete();

        if (! $deleted) {
            throw new \RuntimeException('Post not found or already published.');
        }

        return '✅ Scheduled post deleted.';
    }

    protected function parseScheduleTime(string $when, User $user): Carbon
    {
        $tz = $user->preferredTimezone();
        $when = trim(strtolower($when));

        if (preg_match('/^tomorrow(?:\s+at)?\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)?$/', $when, $m)) {
            $hour = (int) $m[1];
            $minute = isset($m[2]) ? (int) $m[2] : 0;
            if (($m[3] ?? '') === 'pm' && $hour < 12) {
                $hour += 12;
            }
            if (($m[3] ?? '') === 'am' && $hour === 12) {
                $hour = 0;
            }

            return now($tz)->addDay()->setTime($hour, $minute)->timezone(config('app.timezone'));
        }

        if (preg_match('/^in\s+(\d+)\s+(minute|minutes|hour|hours)$/i', $when, $m)) {
            $amount = (int) $m[1];
            $base = now($tz);

            return str_starts_with(strtolower($m[2]), 'hour')
                ? $base->addHours($amount)->timezone(config('app.timezone'))
                : $base->addMinutes($amount)->timezone(config('app.timezone'));
        }

        try {
            return Carbon::parse($when, $tz)->timezone(config('app.timezone'));
        } catch (\Throwable) {
            throw new \RuntimeException('Could not parse time. Try: tomorrow 9am, in 2 hours, or 2026-03-20 14:30');
        }
    }

    protected function twitterService(User $user): TwitterService
    {
        return new TwitterService([
            'account_id' => $user->twitter_account_id,
            'access_token' => $user->twitter_access_token,
            'access_token_secret' => $user->twitter_access_token_secret,
            'consumer_key' => config('services.twitter.api_key'),
            'consumer_secret' => config('services.twitter.api_key_secret'),
            'bearer_token' => config('services.twitter.bearer_token'),
        ]);
    }

    protected function requireTwitter(User $user): void
    {
        if (! $user->isTwitterConnected()) {
            throw new \RuntimeException('Connect your X/Twitter account in the app first.');
        }
    }

    protected function requirePermission(User $user, string $key): void
    {
        if (! $user->hasWhatsAppPermission($key)) {
            throw new \RuntimeException("Permission denied for {$key}. Enable it in WhatsApp Settings.");
        }
    }

    protected function pendingKey(User $user): string
    {
        return 'whatsapp_pending_'.$user->id;
    }

    protected function contextKey(User $user, string $type): string
    {
        return "whatsapp_context_{$type}_{$user->id}";
    }
}
