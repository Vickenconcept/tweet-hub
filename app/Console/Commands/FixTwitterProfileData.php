<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TwitterService;
use App\Services\ZernioService;
use Illuminate\Console\Command;

class FixTwitterProfileData extends Command
{
    protected $signature = 'twitter:fix-profile-data';

    protected $description = 'Refresh X profile fields from Zernio for connected users';

    public function handle(ZernioService $zernio): int
    {
        $users = User::where('twitter_account_connected', true)
            ->whereNotNull('zernio_twitter_account_id')
            ->get();

        if ($users->isEmpty()) {
            $this->info('No Zernio-connected users found.');

            return self::SUCCESS;
        }

        foreach ($users as $user) {
            try {
                $user->syncZernioTwitterAccountById($zernio, $user->zernio_twitter_account_id);
                $twitter = new TwitterService($user);
                $me = $twitter->findMe();

                if ($me && isset($me->data)) {
                    $user->twitter_username = $me->data->username ?? $user->twitter_username;
                    $user->twitter_name = $me->data->name ?? $user->twitter_name;
                    $user->twitter_profile_image_url = $me->data->profile_image_url ?? $user->twitter_profile_image_url;
                    $user->save();
                }

                $this->info("Updated {$user->email} (@{$user->twitter_username})");
            } catch (\Throwable $e) {
                $this->error("Failed for {$user->email}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
