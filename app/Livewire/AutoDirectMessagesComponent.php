<?php

namespace App\Livewire;

use App\Jobs\ProcessAutoDms;
use App\Services\TwitterService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class AutoDirectMessagesComponent extends Component
{
    public string $campaignName = '';
    public string $dmTemplate = '';
    public int $dailyLimit = 20;

    public string $searchQuery = '';
    public array $searchResults = [];
    public array $selectedUserIds = [];
    public bool $searchRateLimited = false;
    public ?string $searchRateLimitMessage = null;

    public function mount()
    {
        // Sensible defaults
        $this->campaignName = 'Welcome new followers';
        $this->dmTemplate = "Hey there! Thanks for connecting. If you're into Laravel and dev content, feel free to say hi anytime.";
        $this->dailyLimit = 20;

        // Restore cached search results for this user (so refresh doesn't lose them)
        $user = Auth::user();
        if ($user) {
            $cacheKey = 'auto_dm_search_results_' . $user->id;
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && !empty($cached['results'])) {
                $this->searchQuery = (string) ($cached['query'] ?? '');
                $this->searchResults = $cached['results'];
                $this->selectedUserIds = [];
            }
        }
    }

    public function saveSettings()
    {
        // For now we only log and keep values in component state.
        Log::info('📩 AutoDirectMessages: settings saved (in-memory)', [
            'user_id' => Auth::id(),
            'campaign_name' => $this->campaignName,
            'daily_limit' => $this->dailyLimit,
        ]);

        session()->flash('message', 'Auto DM settings saved (for this session).');
    }

    public function searchUsers()
    {
        $query = trim($this->searchQuery);
        if (mb_strlen($query) < 2) {
            $this->searchResults = [];
            session()->flash('error', 'Type at least 2 characters to search.');
            return;
        }

        $user = Auth::user();
        if (!$user) {
            return;
        }

        if (empty($user->twitter_account_connected) || empty($user->twitter_access_token)) {
            session()->flash('error', 'Connect your Twitter account first to search people to DM.');
            return;
        }

        $settings = [
            'account_id' => $user->twitter_account_id,
            'consumer_key' => config('services.twitter.api_key'),
            'consumer_secret' => config('services.twitter.api_key_secret'),
            'access_token' => $user->twitter_access_token,
            'access_token_secret' => $user->twitter_access_token_secret,
            'bearer_token' => config('services.twitter.bearer_token'),
        ];

        try {
            $twitter = new TwitterService($settings);

            // Check cached search rate limit before hitting the endpoint again
            $rate = $twitter->isRateLimitedForSearch();
            if (!empty($rate['rate_limited'])) {
                $this->searchRateLimited = true;
                $waitMinutes = $rate['wait_minutes'] ?? null;
                $resetTime = $rate['reset_time'] ?? 'later';

                $this->searchRateLimitMessage = $waitMinutes
                    ? "Twitter search rate limit hit. Try again in ~{$waitMinutes} minute(s) (reset at {$resetTime})."
                    : "Twitter search rate limit hit. Try again later (reset at {$resetTime}).";

                session()->flash('error', $this->searchRateLimitMessage);
                return;
            }

            // Re-use keyword search: find tweets that match the query, then extract unique authors.
            $response = $twitter->searchTweetsByKeyword($query, 20);

            $tweets = is_array($response->data ?? null) ? $response->data : [];
            $includes = $response->includes ?? [];
            $users = $includes['users'] ?? [];

            // Index users by id for quick lookup.
            $userIndex = [];
            foreach ($users as $u) {
                if (!isset($u['id'])) {
                    continue;
                }
                $userIndex[(string) $u['id']] = $u;
            }

            $found = [];
            foreach ($tweets as $tweet) {
                $authorId = isset($tweet['author_id']) ? (string) $tweet['author_id'] : null;
                if (!$authorId || isset($found[$authorId])) {
                    continue;
                }

                if (!isset($userIndex[$authorId])) {
                    continue;
                }

                $u = $userIndex[$authorId];

                $found[$authorId] = [
                    'id' => $authorId,
                    'name' => $u['name'] ?? '',
                    'username' => $u['username'] ?? '',
                    'profile_image_url' => $u['profile_image_url'] ?? null,
                    'last_tweet_text' => $tweet['text'] ?? '',
                ];
            }

            $this->searchResults = array_values($found);
            $this->selectedUserIds = [];
            $this->searchRateLimited = false;
            $this->searchRateLimitMessage = null;

            // Cache the results so a page refresh can restore them without hitting the API again
            $cacheKey = 'auto_dm_search_results_' . $user->id;
            Cache::put($cacheKey, [
                'query' => $query,
                'results' => $this->searchResults,
            ], now()->addMinutes(30));

            Log::info('📩 AutoDirectMessages: user search completed', [
                'user_id' => $user->id,
                'query' => $query,
                'results_count' => count($this->searchResults),
            ]);

            if (empty($this->searchResults)) {
                session()->flash('message', 'No users found for that search.');
            }
        } catch (\Throwable $e) {
            Log::error('📩 AutoDirectMessages: user search failed', [
                'user_id' => $user->id,
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            $msg = $e->getMessage();
            if (str_contains($msg, 'Rate limit exceeded (429)')) {
                // When we get a 429, the TwitterService already cached the limit; mark as rate-limited in UI too
                $this->searchRateLimited = true;
                $this->searchRateLimitMessage = $msg;
            }

            session()->flash('error', 'Search failed: ' . $msg);
        }
    }

    public function queueDmsForSelected()
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        if (empty($this->selectedUserIds)) {
            session()->flash('error', 'Select at least one person to DM.');
            return;
        }

        if (trim($this->dmTemplate) === '') {
            session()->flash('error', 'Write a DM template before queuing messages.');
            return;
        }

        $recipients = [];
        foreach ($this->selectedUserIds as $selectedId) {
            $selectedId = (string) $selectedId;

            foreach ($this->searchResults as $result) {
                if ((string) ($result['id'] ?? '') === $selectedId) {
                    $recipients[] = [
                        'twitter_recipient_id' => $selectedId,
                        'context' => $result['last_tweet_text'] ?? null,
                        'dm_text' => $this->dmTemplate,
                    ];
                    break;
                }
            }
        }

        if (empty($recipients)) {
            session()->flash('error', 'Could not resolve selected users from search results.');
            return;
        }

        // Apply a simple cap based on dailyLimit, to avoid queuing too many at once.
        $recipients = array_slice($recipients, 0, max(1, $this->dailyLimit));

        Log::info('📩 AutoDirectMessages: dispatching ProcessAutoDms for selected users', [
            'user_id' => $user->id,
            'campaign_name' => $this->campaignName,
            'selected_count' => count($this->selectedUserIds),
            'queued_count' => count($recipients),
        ]);

        ProcessAutoDms::dispatch($user->id, $recipients, 'campaign', $this->campaignName);

        session()->flash('message', 'Queued ' . count($recipients) . ' DMs (stub). Check auto_dms table and logs for details.');
    }

    public function triggerTestCampaign()
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        // For now, create a single dummy recipient using your own account ID
        if (empty($user->twitter_account_id)) {
            session()->flash('error', 'Connect your Twitter account first to run Auto DM campaigns.');
            return;
        }

        $recipients = [[
            'twitter_recipient_id' => $user->twitter_account_id,
            'context' => 'Test Auto DM to self',
            'dm_text' => $this->dmTemplate,
        ]];

        Log::info('📩 AutoDirectMessages: dispatching ProcessAutoDms test job', [
            'user_id' => $user->id,
            'campaign_name' => $this->campaignName,
        ]);

        ProcessAutoDms::dispatch($user->id, $recipients, 'campaign', $this->campaignName);

        session()->flash('message', 'Test Auto DM job dispatched. Check logs for details.');
    }

    public function render()
    {
        return view('livewire.auto-direct-messages-component');
    }
}


