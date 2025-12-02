<?php

namespace App\Console\Commands;

use App\Models\TwitterAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetTwitterAccountCounters extends Command
{
    protected $signature = 'twitter:reset-daily-counters';
    protected $description = 'Reset daily comment counters for all Twitter accounts at midnight';

    public function handle()
    {
        Log::info('🔄 Starting Twitter account daily counter reset');

        $accounts = TwitterAccount::where('comments_posted_today', '>', 0)->get();
        $count = $accounts->count();

        Log::info('📊 Found Twitter accounts to reset', [
            'count' => $count,
        ]);

        $resetCount = 0;
        foreach ($accounts as $account) {
            $account->resetDailyCounter();
            $resetCount++;
        }

        Log::info('✅ Finished resetting Twitter account daily counters', [
            'reset_count' => $resetCount,
        ]);

        $this->info("Reset daily counters for {$resetCount} Twitter account(s).");
    }
}

