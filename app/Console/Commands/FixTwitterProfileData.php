<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Abraham\TwitterOAuth\TwitterOAuth;

class FixTwitterProfileData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'twitter:fix-profile-data {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix Twitter profile data for users who are connected but missing profile information';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made');
        }

        // Find users who are connected but missing profile data
        $users = User::where('twitter_account_connected', true)
            ->where(function($query) {
                $query->whereNull('twitter_username')
                      ->orWhereNull('twitter_name')
                      ->orWhereNull('twitter_profile_image_url');
            })
            ->whereNotNull('twitter_access_token')
            ->whereNotNull('twitter_access_token_secret')
            ->get();

        if ($users->isEmpty()) {
            $this->info('No users found with missing Twitter profile data.');
            return 0;
        }

        $this->info("Found {$users->count()} users with missing Twitter profile data.");

        $successCount = 0;
        $errorCount = 0;

        foreach ($users as $user) {
            $this->info("Processing user: {$user->name} ({$user->email})");
            
            try {
                // Use the same TwitterService that the Update Profile button uses
                $settings = [
                    'account_id' => $user->twitter_account_id,
                    'consumer_key' => config('services.twitter.api_key'),
                    'consumer_secret' => config('services.twitter.api_key_secret'),
                    'access_token' => $user->twitter_access_token,
                    'access_token_secret' => $user->twitter_access_token_secret,
                    'bearer_token' => config('services.twitter.bearer_token'),
                ];

                $twitterService = new \App\Services\TwitterService($settings);
                
                // Use the same findMe() method that the Update Profile button uses
                $me = $twitterService->findMe();

                if ($me && isset($me->data)) {
                    $oldUsername = $user->twitter_username;
                    $oldName = $user->twitter_name;
                    $oldImageUrl = $user->twitter_profile_image_url;

                    $user->twitter_username = $me->data->username ?? $user->twitter_username;
                    $user->twitter_name = $me->data->name ?? $user->twitter_name;
                    $user->twitter_profile_image_url = $me->data->profile_image_url ?? $user->twitter_profile_image_url;

                    if (!$dryRun) {
                        $user->save();
                    }

                    $this->info("  ✓ Updated profile data:");
                    $this->info("    Username: " . ($oldUsername ?: 'null') . " → {$user->twitter_username}");
                    $this->info("    Name: " . ($oldName ?: 'null') . " → " . ($user->twitter_name ?: 'null'));
                    $this->info("    Profile Image: " . ($oldImageUrl ? 'updated' : 'added'));

                    $successCount++;
                } else {
                    $this->error("  ✗ Failed to fetch profile data for user {$user->name}");
                    $errorCount++;
                }

            } catch (\Exception $e) {
                $this->error("  ✗ Error processing user {$user->name}: " . $e->getMessage());
                Log::error('FixTwitterProfileData error', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                $errorCount++;
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->info("  Successfully processed: {$successCount} users");
        $this->info("  Errors: {$errorCount} users");
        
        if ($dryRun) {
            $this->info("  (This was a dry run - no changes were made)");
        }

        return 0;
    }
}
