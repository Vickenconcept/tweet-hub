<?php

namespace App\Jobs;

use App\Models\AutoReply;
use App\Models\User;
use App\Services\ChatGptService;
use App\Services\TwitterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessMentionAutoReplies implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The user ID owning these mentions/tweets.
     *
     * @var int
     */
    protected int $userId;

    /**
     * Simplified mention payloads (id, text, author_id).
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $mentions;

    /**
     * Source type for auto replies: 'mention' or 'keyword'.
     *
     * @var string
     */
    protected string $sourceType;

    /**
     * Create a new job instance.
     *
     * @param int    $userId
     * @param array  $mentions
     * @param string $sourceType
     */
    public function __construct(int $userId, array $mentions, string $sourceType = 'mention')
    {
        $this->userId = $userId;
        $this->mentions = $mentions;
        $this->sourceType = in_array($sourceType, ['mention', 'keyword'], true) ? $sourceType : 'mention';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            Log::warning('🤖 ProcessMentionAutoReplies: user not found, aborting', [
                'user_id' => $this->userId,
            ]);
            return;
        }

        // If user has turned off auto-reply since job was queued, respect that.
        $mentionsEnabled = (bool) ($user->auto_reply_mentions_enabled ?? false);
        $keywordsEnabled = (bool) ($user->auto_reply_keywords_enabled ?? false);

        if (
            ($this->sourceType === 'mention' && !$mentionsEnabled) ||
            ($this->sourceType === 'keyword' && !$keywordsEnabled)
        ) {
            Log::info('🤖 ProcessMentionAutoReplies: auto-reply disabled for user, skipping', [
                'user_id' => $user->id,
                'source_type' => $this->sourceType,
            ]);
            return;
        }

        $chatGptService = app(ChatGptService::class);

        // Safety: limit how many auto replies we send per job run.
        $maxAutoRepliesPerJob = 5;
        $autoRepliesSent = 0;
        $skippedNoNeed = 0;
        $skippedSelf = 0;
        $skippedAlready = 0;
        $skippedAlreadyProcessed = 0;
        $rateLimited = false;

        Log::info('🤖 ProcessMentionAutoReplies started', [
            'user_id' => $user->id,
            'source_type' => $this->sourceType,
            'items_count' => count($this->mentions),
            'max_auto_replies_per_job' => $maxAutoRepliesPerJob,
        ]);

        $settings = [
            'account_id' => $user->twitter_account_id,
            'access_token' => $user->twitter_access_token,
            'access_token_secret' => $user->twitter_access_token_secret,
            'consumer_key' => config('services.twitter.api_key'),
            'consumer_secret' => config('services.twitter.api_key_secret'),
            'bearer_token' => config('services.twitter.bearer_token'),
        ];

        $twitterService = new TwitterService($settings);

        // Load list of tweets we've already evaluated (either replied or decided to skip)
        $seenCacheKey = "auto_reply_seen_{$this->sourceType}_{$this->userId}";
        $seenIds = Cache::get($seenCacheKey, []);
        if (!is_array($seenIds)) {
            $seenIds = [];
        }
        $seenSet = array_flip(array_map('strval', $seenIds));

        // Randomize order so we don't always reply to the same "top" items first
        if (!empty($this->mentions)) {
            shuffle($this->mentions);
        }

        $lastProcessedIndex = -1;

        foreach ($this->mentions as $index => $mention) {
            if ($autoRepliesSent >= $maxAutoRepliesPerJob) {
                break;
            }

            $mentionId = $mention['id'] ?? null;
            $mentionText = $mention['text'] ?? '';
            $authorId = $mention['author_id'] ?? null;

            if (!$mentionId || !$mentionText) {
                continue;
            }

            // Skip anything we've already processed in a previous job run (reply or explicit skip)
            if (isset($seenSet[(string) $mentionId])) {
                $skippedAlreadyProcessed++;
                Log::info('🤖 [auto-reply] Skipping tweet - already processed in earlier batch', [
                    'user_id' => $user->id,
                    'source_type' => $this->sourceType,
                    'tweet_id' => $mentionId,
                ]);
                continue;
            }

            Log::info('🤖 [auto-reply] Evaluating tweet', [
                'user_id' => $user->id,
                'source_type' => $this->sourceType,
                'tweet_id' => $mentionId,
                'text_preview' => Str::limit($mentionText, 120),
            ]);

            // Don't auto-reply to our own tweets.
            if ($authorId && $user->twitter_account_id && (string) $authorId === (string) $user->twitter_account_id) {
                $skippedSelf++;
                Log::info('🤖 [auto-reply] Skipping tweet - self authored', [
                    'user_id' => $user->id,
                    'source_type' => $this->sourceType,
                    'tweet_id' => $mentionId,
                ]);
                continue;
            }

            // Skip if we've already auto-replied to this mention.
            $alreadyReplied = AutoReply::where('user_id', $user->id)
                ->where('tweet_id', (string) $mentionId)
                ->where('source_type', $this->sourceType)
                ->exists();

            if ($alreadyReplied) {
                $skippedAlready++;
                Log::info('🤖 [auto-reply] Skipping tweet - already auto-replied', [
                    'user_id' => $user->id,
                    'source_type' => $this->sourceType,
                    'tweet_id' => $mentionId,
                ]);
                continue;
            }

            try {
                // Step 1: let AI decide if this tweet actually needs a reply
                $brandName = $user->twitter_name ?: $user->name ?: 'your personal brand';
                $handleUsername = ltrim((string) $user->twitter_username, '@');
                $handle = $handleUsername ? '@' . $handleUsername : '@brand';

                // Different logic for keywords vs mentions
                if ($this->sourceType === 'keyword') {
                    // For keywords: Be more lenient - if it matches the keyword, engage unless it's spam/offensive
                    $shouldReplyPrompt = "You are an assistant for {$brandName} ({$handle}) on Twitter (X).\n"
                        . "This tweet was found because it contains a keyword we're monitoring.\n"
                        . "Decide if we should reply. Reply ONLY with 'yes' or 'no'.\n"
                        . "Reply 'yes' if the tweet is about the topic and we can add value with a helpful, positive comment.\n"
                        . "Reply 'no' ONLY if it's clearly spam, offensive, or completely unrelated to the keyword topic.\n"
                        . "Be lenient - if it's relevant to the keyword, we want to engage.\n\n"
                        . "Tweet:\n\"{$mentionText}\"";
                } else {
                    // For mentions: Be more selective - is this person actually talking to us?
                    $shouldReplyPrompt = "You are an assistant for {$brandName} ({$handle}) on Twitter (X).\n"
                        . "Decide if we should reply to this tweet. Reply ONLY with 'yes' or 'no'.\n"
                        . "Reply 'yes' only if:\n"
                        . "- The tweet is relevant to the brand or its audience, AND\n"
                        . "- A short, helpful, positive reply would add value (e.g., question, feedback, comment we can acknowledge).\n"
                        . "Reply 'no' if it looks like spam, pure promo, random tag list, unrelated content, offensive, or doesn't really need a reply.\n\n"
                        . "Tweet:\n\"{$mentionText}\"";
                }

                $decision = strtolower(trim($chatGptService->generateContent($shouldReplyPrompt) ?? ''));

            if (!str_starts_with($decision, 'y')) {
                $skippedNoNeed++;
                $seenSet[(string) $mentionId] = true;
                Log::info('🤖 [auto-reply] Skipping tweet - AI judged no reply needed', [
                    'user_id' => $user->id,
                    'source_type' => $this->sourceType,
                    'tweet_id' => $mentionId,
                    'decision_raw' => $decision,
                ]);
                continue;
            }

                Log::info('🤖 [auto-reply] AI approved tweet for reply', [
                    'user_id' => $user->id,
                    'source_type' => $this->sourceType,
                    'tweet_id' => $mentionId,
                    'decision_raw' => $decision,
                ]);

                // Step 2: generate the actual reply
                if ($this->sourceType === 'keyword') {
                    $replyPrompt = "You are helping {$brandName} reply on Twitter (X) as {$handle}.\n"
                        . "This tweet was found via keyword monitoring - we want to join the conversation about this topic.\n"
                        . "Write ONE short, human, warm, and genuinely helpful reply (max 220 characters) that adds value to the discussion.\n"
                        . "Tone: positive, respectful, and professional. No slang, no sarcasm, no negativity.\n"
                        . "Do NOT include hashtags, links, or emojis unless absolutely necessary. Avoid sounding like AI.\n"
                        . "If the tweet is offensive, political, or unsafe, politely decline instead of engaging.\n\n"
                        . "Tweet content:\n\"{$mentionText}\"";
                } else {
                    $replyPrompt = "You are helping {$brandName} reply on Twitter (X) as {$handle}.\n"
                        . "Write ONE short, human, warm, and genuinely helpful reply (max 220 characters) to this tweet.\n"
                        . "Tone: positive, respectful, and professional. No slang, no sarcasm, no negativity.\n"
                        . "Do NOT include hashtags, links, or emojis unless absolutely necessary. Avoid sounding like AI.\n"
                        . "If the tweet is offensive, political, or unsafe, politely decline instead of engaging.\n\n"
                        . "Tweet content:\n\"{$mentionText}\"";
                }

                $replyText = trim($chatGptService->generateContent($replyPrompt) ?? '');
            } catch (\Throwable $e) {
                Log::error('🤖 ProcessMentionAutoReplies: GPT generation failed', [
                    'user_id' => $user->id,
                    'source_type' => $this->sourceType,
                    'tweet_id' => $mentionId,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if ($replyText === '' || mb_strlen($replyText) > 280) {
                continue;
            }

            try {
                Log::info('🤖 ProcessMentionAutoReplies: sending AI reply', [
                    'user_id' => $user->id,
                    'source_type' => $this->sourceType,
                    'tweet_id' => $mentionId,
                ]);

                $response = $twitterService->createTweet(
                    $replyText,
                    [],
                    $mentionId
                );

                if ($response && isset($response->data)) {
                    AutoReply::create([
                        'user_id' => $user->id,
                        'tweet_id' => (string) $mentionId,
                        'source_type' => $this->sourceType,
                        'original_text' => $mentionText,
                        'reply_text' => $replyText,
                        'replied_at' => now(),
                    ]);

                    $autoRepliesSent++;
                    $lastProcessedIndex = $index;
                    $seenSet[(string) $mentionId] = true;
                }
            } catch (\Throwable $e) {
                Log::error('🤖 ProcessMentionAutoReplies: failed to send AI reply', [
                    'user_id' => $user->id,
                    'source_type' => $this->sourceType,
                    'tweet_id' => $mentionId,
                    'error' => $e->getMessage(),
                ]);

                // If we hit a rate limit, stop this job and don't chain immediately
                $message = $e->getMessage();
                if (strpos($message, '429') !== false || stripos($message, 'Rate limit') !== false) {
                    $rateLimited = true;
                    Log::warning('🤖 ProcessMentionAutoReplies: rate limit detected while sending replies, stopping job early', [
                        'user_id' => $user->id,
                        'source_type' => $this->sourceType,
                    ]);
                    break;
                }
            }
        }

        // Persist updated seen set (both replied and explicit "no need" decisions)
        $finalSeenIds = array_keys($seenSet);
        Cache::put($seenCacheKey, $finalSeenIds, now()->addDays(30));

        Log::info('🤖 ProcessMentionAutoReplies finished', [
            'user_id' => $user->id,
            'source_type' => $this->sourceType,
            'auto_replies_sent' => $autoRepliesSent,
            'skipped_no_need_for_reply' => $skippedNoNeed,
            'skipped_self_mentions' => $skippedSelf,
            'skipped_already_replied' => $skippedAlready,
            'skipped_already_processed' => $skippedAlreadyProcessed,
            'total_seen_count' => count($finalSeenIds),
        ]);

        // If we hit the per-job limit and still have unprocessed items, chain another job.
        // Do NOT chain if we detected a rate limit in this run.
        if (!$rateLimited && $autoRepliesSent >= $maxAutoRepliesPerJob && $lastProcessedIndex >= 0) {
            $remaining = array_slice($this->mentions, $lastProcessedIndex + 1);

            if (!empty($remaining)) {
                Log::info('🤖 Chaining another ProcessMentionAutoReplies job for remaining items', [
                    'user_id' => $user->id,
                    'source_type' => $this->sourceType,
                    'remaining_count' => count($remaining),
                ]);

                // Delay the next batch to spread out API calls and reduce rate-limit risk.
                // Use a per-user jitter so different users don't all fire at the same time.
                $baseDelayMinutes = 5;
                $jitter = crc32((string) $this->userId) % 10; // 0–9 minutes
                $delayMinutes = $baseDelayMinutes + $jitter;

                ProcessMentionAutoReplies::dispatch($this->userId, $remaining, $this->sourceType)
                    ->delay(now()->addMinutes($delayMinutes));
            }
        }
    }
}


