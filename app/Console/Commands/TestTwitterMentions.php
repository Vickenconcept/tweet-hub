<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TwitterService;
use Illuminate\Console\Command;

class TestTwitterMentions extends Command
{
    protected $signature = 'twitter:test-mentions {user_id?}';

    protected $description = 'Test fetching X replies/comments via Zernio';

    public function handle(): int
    {
        $userId = $this->argument('user_id') ?? User::whereNotNull('zernio_twitter_account_id')->value('id');

        if (! $userId) {
            $this->error('No Zernio-connected user found.');

            return self::FAILURE;
        }

        $user = User::find($userId);
        if (! $user || ! $user->isTwitterConnected()) {
            $this->error('User is not connected via Zernio.');

            return self::FAILURE;
        }

        $this->info("User: {$user->email}");
        $this->info('Zernio account: '.$user->zernio_twitter_account_id);

        $twitterService = new TwitterService($user);

        try {
            $mentionsResponse = $twitterService->getRecentMentions($user->twitter_account_id);
            $count = is_object($mentionsResponse) && isset($mentionsResponse->data)
                ? count((array) $mentionsResponse->data)
                : 0;

            $this->info("Fetched {$count} inbox comment(s).");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
