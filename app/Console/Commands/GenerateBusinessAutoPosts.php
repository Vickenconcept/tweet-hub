<?php

namespace App\Console\Commands;

use App\Jobs\GenerateBusinessAutoPost;
use App\Models\BusinessAutoProfile;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateBusinessAutoPosts extends Command
{
    protected $signature = 'business:auto-generate {--date=} {--profile=}';
    protected $description = 'Dispatch daily GPT-powered posts for each active business profile';

    public function handle(): void
    {
        $targetDate = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : now();

        $query = BusinessAutoProfile::active();

        if ($profileId = $this->option('profile')) {
            $query->where('id', $profileId);
        }

        $count = 0;
        $delaySeconds = 0;

       
        $query->chunkById(50, function ($profiles) use (&$count, &$delaySeconds, $targetDate) {
            foreach ($profiles as $profile) {
                
                GenerateBusinessAutoPost::dispatch($profile->id, $targetDate->toDateString())
                    ->delay(now()->addSeconds($delaySeconds));
                
                $count++;
                $delaySeconds += 8; 
            }
        });

        $estimatedMinutes = ceil(($count * 6) / 60);
        $this->info("Queued {$count} auto-post jobs for {$targetDate->toDateString()}");
        $this->info("Jobs are staggered with 6-second delays to prevent OpenAI rate limiting");
        $this->info("Estimated completion time: ~{$estimatedMinutes} minutes");
    }
}

