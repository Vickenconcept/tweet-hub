<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TwitterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestTwitterMentions extends Command
{
    protected $signature = 'twitter:test-mentions {user_id?}';
    protected $description = 'Test Twitter mentions API for debugging';

    public function handle()
    {
        $userId = $this->argument('user_id');
        
        if ($userId) {
            $user = User::find($userId);
            if (!$user) {
                $this->error("User with ID {$userId} not found.");
                return 1;
            }
        } else {
            $user = User::where('twitter_account_connected', true)->first();
            if (!$user) {
                $this->error('No connected Twitter users found.');
                return 1;
            }
        }

        $this->info("Testing Twitter mentions for user: {$user->name} (ID: {$user->id})");
        $this->info("Twitter Account ID: {$user->twitter_account_id}");
        $this->info("Connected: " . ($user->twitter_account_connected ? 'Yes' : 'No'));
        $this->info("Has Access Token: " . (!empty($user->twitter_access_token) ? 'Yes' : 'No'));
        $this->info("Has Access Token Secret: " . (!empty($user->twitter_access_token_secret) ? 'Yes' : 'No'));
        $this->info("Has Refresh Token: " . (!empty($user->twitter_refresh_token) ? 'Yes' : 'No'));

        if (!$user->isTwitterConnected()) {
            $this->error('User is not properly connected to Twitter.');
            return 1;
        }

        try {
            $settings = [
                'account_id' => $user->twitter_account_id,
                'access_token' => $user->twitter_access_token,
                'access_token_secret' => $user->twitter_access_token_secret,
                'consumer_key' => config('services.twitter.api_key'),
                'consumer_secret' => config('services.twitter.api_key_secret'),
                'bearer_token' => config('services.twitter.bearer_token'),
            ];

            // Check for missing settings
            $missingSettings = [];
            foreach ($settings as $key => $value) {
                if (empty($value)) {
                    $missingSettings[] = $key;
                }
            }

            if (!empty($missingSettings)) {
                $this->error('Missing Twitter configuration: ' . implode(', ', $missingSettings));
                return 1;
            }

            $this->info('Twitter configuration looks good. Testing API call...');

            $twitterService = new TwitterService($settings);
            
            $this->info('Fetching recent mentions...');
            $mentionsResponse = $twitterService->getRecentMentions($user->twitter_account_id);
            
            $this->info('API call successful!');
            $this->info('Response type: ' . get_class($mentionsResponse));
            
            if (isset($mentionsResponse->data)) {
                $mentionsCount = count($mentionsResponse->data);
                $this->info("Found {$mentionsCount} mentions");
                
                if ($mentionsCount > 0) {
                    $this->info('First mention details:');
                    $firstMention = $mentionsResponse->data[0];
                    $this->info('- ID: ' . ($firstMention->id ?? 'N/A'));
                    $this->info('- Text: ' . (substr($firstMention->text ?? 'N/A', 0, 100)) . '...');
                    $this->info('- Created: ' . ($firstMention->created_at ?? 'N/A'));
                }
            } else {
                $this->warn('No data field in response');
            }
            
            if (isset($mentionsResponse->meta)) {
                $this->info('Meta information: ' . json_encode($mentionsResponse->meta));
            }
            
            Log::info('Twitter mentions test successful', [
                'user_id' => $user->id,
                'mentions_count' => isset($mentionsResponse->data) ? count($mentionsResponse->data) : 0,
                'response_type' => get_class($mentionsResponse)
            ]);

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $body = $e->getResponse()->getBody()->getContents();
            
            $this->error("Twitter API Error: HTTP {$statusCode}");
            $this->error("Error body: {$body}");
            
            if ($statusCode === 429) {
                $this->error('Rate limit exceeded. This is the main issue you\'re experiencing.');
                $this->info('Solutions:');
                $this->info('1. Wait 15 minutes before making another call');
                $this->info('2. Check your Twitter API rate limits');
                $this->info('3. Implement better caching and rate limiting');
            }
            
            Log::error('Twitter API test failed', [
                'user_id' => $user->id,
                'status_code' => $statusCode,
                'error_body' => $body,
                'exception' => $e->getMessage()
            ]);
            
            return 1;
            
        } catch (\Exception $e) {
            $this->error('Unexpected error: ' . $e->getMessage());
            Log::error('Twitter test unexpected error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        $this->info('Test completed successfully!');
        return 0;
    }
} 