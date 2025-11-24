<?php

namespace App\Jobs;

use App\Models\BusinessAutoProfile;
use App\Services\BusinessAutoPostService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateBusinessAutoPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected int $profileId,
        protected string $targetDate
    ) {
    }

    public function handle(BusinessAutoPostService $generator): void
    {
        $profile = BusinessAutoProfile::find($this->profileId);

        if (!$profile || !$profile->is_active) {
            return;
        }

        try {
            $generator->generateForDate(
                $profile,
                Carbon::parse($this->targetDate, $profile->timezone ?? config('app.timezone'))
            );
        } catch (\Throwable $e) {
            Log::error('Failed to generate business auto post', [
                'profile_id' => $this->profileId,
                'date' => $this->targetDate,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

