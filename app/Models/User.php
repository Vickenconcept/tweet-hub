<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'twitter_account_connected',
        'twitter_account_id',
        'twitter_username',
        'twitter_name',
        'twitter_profile_image_url',
        'twitter_access_token',
        'twitter_access_token_secret',
        'twitter_refresh_token',
        'default_topic',
        'default_niche',
        'last_daily_ideas_generated',
        'monitored_keywords',
        'timezone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'twitter_account_connected' => 'boolean',
            'last_daily_ideas_generated' => 'datetime',
        ];
    }

    public function isTwitterConnected()
    {
        return $this->twitter_account_connected && 
               $this->twitter_account_id && 
               $this->twitter_access_token && 
               $this->twitter_access_token_secret;
    }

    public function hasValidTwitterTokens()
    {
        return $this->isTwitterConnected() && 
               $this->twitter_refresh_token && 
               !empty($this->twitter_access_token) && 
               !empty($this->twitter_access_token_secret);
    }

    public function getDefaultTopic()
    {
        return $this->default_topic ?: 'Digital Marketing';
    }

    public function getDefaultNiche()
    {
        return $this->default_niche ?: 'B2B';
    }

    public function needsDailyIdeasGeneration()
    {
        if (!$this->last_daily_ideas_generated) {
            return true;
        }

        // Check if it's a new day
        return $this->last_daily_ideas_generated->format('Y-m-d') !== now()->format('Y-m-d');
    }

    public function updateDailyIdeasPreferences($topic, $niche)
    {
        $this->update([
            'default_topic' => $topic,
            'default_niche' => $niche,
        ]);
    }

    public function markDailyIdeasGenerated()
    {
        $this->update([
            'last_daily_ideas_generated' => now(),
        ]);
    }

    public function businessAutoProfiles(): HasMany
    {
        return $this->hasMany(BusinessAutoProfile::class);
    }

    public function businessAutoPosts(): HasMany
    {
        return $this->hasMany(BusinessAutoPost::class);
    }

    public function preferredTimezone(): string
    {
        return $this->timezone ?: config('app.timezone');
    }
}
