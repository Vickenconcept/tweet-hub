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
use Illuminate\Support\Facades\RateLimiter;

class GenerateBusinessAutoPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = [30, 60, 120]; // Exponential backoff: 30s, 60s, 120s

    public function __construct(
        protected int $profileId,
        protected string $targetDate
    ) {
    }

    public function handle(BusinessAutoPostService $generator): void
    {
        // Rate limiting: Allow max 10 OpenAI API calls per minute
        // This prevents hitting OpenAI rate limits (especially for DALL-E images)
        $rateLimiterKey = 'openai_api_rate_limit';
        $maxAttempts = 10; // Max 10 API calls per minute
        $decayMinutes = 1;

        if (RateLimiter::tooManyAttempts($rateLimiterKey, $maxAttempts)) {
            $secondsUntilAvailable = RateLimiter::availableIn($rateLimiterKey);
            $minutesUntilAvailable = ceil($secondsUntilAvailable / 60);
            
            Log::warning('OpenAI API rate limit reached, releasing job back to queue', [
                'profile_id' => $this->profileId,
                'seconds_until_available' => $secondsUntilAvailable,
                'minutes_until_available' => $minutesUntilAvailable,
            ]);

            // Release job back to queue with delay
            $this->release($secondsUntilAvailable + 5); // Add 5 second buffer
            return;
        }

        // Increment rate limiter
        RateLimiter::hit($rateLimiterKey, $decayMinutes * 60);

        $profile = BusinessAutoProfile::find($this->profileId);

        if (!$profile || !$profile->is_active) {
            return;
        }

        try {
            $generator->generateForDate(
                $profile,
                Carbon::parse($this->targetDate, $profile->timezone ?? config('app.timezone'))
            );
            
            Log::info('Business auto post generated successfully', [
                'profile_id' => $this->profileId,
                'date' => $this->targetDate,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to generate business auto post', [
                'profile_id' => $this->profileId,
                'date' => $this->targetDate,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'attempt' => $this->attempts(),
            ]);

            // If it's a rate limit error (429), release with longer delay
            if (str_contains($e->getMessage(), '429') || 
                str_contains($e->getMessage(), 'rate limit') ||
                str_contains($e->getMessage(), 'Rate limit')) {
                $this->release(300); // Release for 5 minutes
                return;
            }

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }
}

