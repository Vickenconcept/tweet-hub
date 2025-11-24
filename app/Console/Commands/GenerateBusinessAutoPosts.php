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

        $query->chunkById(50, function ($profiles) use (&$count, $targetDate) {
            foreach ($profiles as $profile) {
                GenerateBusinessAutoPost::dispatch($profile->id, $targetDate->toDateString());
                $count++;
            }
        });

        $this->info("Queued {$count} auto-post jobs for {$targetDate->toDateString()}");
    }
}

