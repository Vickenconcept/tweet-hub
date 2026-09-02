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
        'zernio_profile_id',
        'zernio_twitter_account_id',
        'default_topic',
        'default_niche',
        'last_daily_ideas_generated',
        'monitored_keywords',
        'auto_reply_mentions_enabled',
        'auto_reply_keywords_enabled',
        'interaction_auto_dm_enabled',
        'interaction_auto_dm_template',
        'interaction_auto_dm_daily_limit',
        'timezone',
        'whatsapp_phone',
        'whatsapp_verified_at',
        'whatsapp_bot_enabled',
        'whatsapp_permissions',
        'whatsapp_quick_mode',
        'whatsapp_verification_code',
        'whatsapp_verification_expires_at',
        'zernio_conversation_id',
        'whatsapp_notify_post_published',
        'whatsapp_notify_post_failed',
        'whatsapp_notify_new_mentions',
        'whatsapp_language',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'whatsapp_verification_code',
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
            'whatsapp_verified_at' => 'datetime',
            'whatsapp_bot_enabled' => 'boolean',
            'whatsapp_permissions' => 'array',
            'whatsapp_quick_mode' => 'boolean',
            'whatsapp_verification_expires_at' => 'datetime',
            'whatsapp_notify_post_published' => 'boolean',
            'whatsapp_notify_post_failed' => 'boolean',
            'whatsapp_notify_new_mentions' => 'boolean',
        ];
    }

    public function isTwitterConnected(): bool
    {
        return $this->twitter_account_connected
            && ! empty($this->zernio_twitter_account_id);
    }

    public function hasValidTwitterTokens(): bool
    {
        return $this->isTwitterConnected();
    }

    /**
     * @return array<string, mixed>
     */
    public function getTwitterServiceSettings(): array
    {
        return [
            'zernio_account_id' => $this->zernio_twitter_account_id,
            'zernio_profile_id' => $this->zernio_profile_id,
            'account_id' => $this->twitter_account_id,
        ];
    }

    public function ensureZernioProfile(\App\Services\ZernioService $zernio): string
    {
        if ($this->zernio_profile_id) {
            return $this->zernio_profile_id;
        }

        $legacyName = trim($this->name ?: $this->email ?: 'User '.$this->id);
        $uniqueName = 'XEngager User '.$this->id;

        foreach ([$legacyName, $uniqueName] as $name) {
            $existing = $zernio->findProfileByName($name);
            if ($existing) {
                $profileId = $existing['_id'] ?? $existing['id'] ?? null;
                if ($profileId && $this->claimZernioProfileId($profileId)) {
                    return $profileId;
                }
            }
        }

        foreach ([$legacyName, $uniqueName] as $name) {
            $profile = $zernio->createProfile($name, 'XEngager user profile');
            $profileId = $profile['_id'] ?? $profile['id'] ?? null;

            if (! $profileId) {
                continue;
            }

            if ($this->claimZernioProfileId($profileId)) {
                return $profileId;
            }
        }

        throw new \RuntimeException('Could not create or link your account profile.');
    }

    protected function claimZernioProfileId(string $profileId): bool
    {
        $ownedByOther = static::query()
            ->where('zernio_profile_id', $profileId)
            ->where('id', '!=', $this->id)
            ->exists();

        if ($ownedByOther) {
            return false;
        }

        $this->update(['zernio_profile_id' => $profileId]);

        return true;
    }

    public function syncZernioTwitterAccount(\App\Services\ZernioService $zernio, ?string $profileId = null): void
    {
        $profileId = $profileId ?: $this->zernio_profile_id;
        if (! $profileId) {
            throw new \RuntimeException('Missing account profile. Please try connecting again.');
        }

        $accounts = $zernio->listAccounts('twitter', $profileId);
        $account = collect($accounts)->first(fn ($row) => ($row['platform'] ?? '') === 'twitter');

        if (! $account) {
            throw new \RuntimeException('No X account found yet. Please try connecting again.');
        }

        $this->applyZernioTwitterAccount($account);
    }

    public function syncZernioTwitterAccountById(
        \App\Services\ZernioService $zernio,
        string $accountId,
        ?string $profileId = null,
        ?array $fallback = null
    ): void {
        try {
            $account = $zernio->getAccount($accountId, $profileId ?: $this->zernio_profile_id);
            $this->applyZernioTwitterAccount($account);
        } catch (\Throwable $e) {
            if ($fallback && ($fallback['username'] ?? $fallback['profileId'] ?? null)) {
                \Illuminate\Support\Facades\Log::warning('Zernio getAccount failed, applying callback account data', [
                    'account_id' => $accountId,
                    'error' => $e->getMessage(),
                ]);

                $this->applyZernioTwitterAccount(array_merge([
                    '_id' => $accountId,
                    'platform' => 'twitter',
                    'profileId' => $profileId ?: $this->zernio_profile_id,
                ], $fallback));

                return;
            }

            throw $e;
        }
    }

    public function disconnectZernioTwitter(): void
    {
        $this->update([
            'twitter_account_connected' => false,
            'twitter_account_id' => null,
            'zernio_twitter_account_id' => null,
            'twitter_access_token' => null,
            'twitter_access_token_secret' => null,
            'twitter_refresh_token' => null,
            'twitter_username' => null,
            'twitter_name' => null,
            'twitter_profile_image_url' => null,
        ]);
    }

    protected function applyZernioTwitterAccount(array $account): void
    {
        $username = ltrim($account['username'] ?? '', '@');
        $profileId = $account['profileId'] ?? $this->zernio_profile_id;

        if (is_array($profileId)) {
            $profileId = $profileId['_id'] ?? $profileId['id'] ?? $this->zernio_profile_id;
        }

        $accountId = $account['_id'] ?? $account['id'] ?? null;

        $this->update([
            'twitter_account_connected' => true,
            'zernio_twitter_account_id' => $accountId,
            'zernio_profile_id' => $profileId ?: $this->zernio_profile_id,
            'twitter_username' => $username ?: $this->twitter_username,
            'twitter_name' => $account['displayName'] ?? $this->twitter_name,
            'twitter_profile_image_url' => $account['profilePicture'] ?? $this->twitter_profile_image_url,
            'twitter_account_id' => data_get($account, 'metadata.platformUserId')
                ?? data_get($account, 'metadata.userId')
                ?? $this->twitter_account_id,
            'twitter_access_token' => null,
            'twitter_access_token_secret' => null,
            'twitter_refresh_token' => null,
        ]);

        if ($accountId && ($account['platform'] ?? 'twitter') === 'twitter') {
            try {
                app(\App\Services\ZernioService::class)->enableXAccountCapabilities((string) $accountId);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to enable X account capabilities on connect', [
                    'user_id' => $this->id,
                    'account_id' => $accountId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
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

    public function whatsappCommandLogs(): HasMany
    {
        return $this->hasMany(WhatsAppCommandLog::class);
    }

    public function isWhatsAppVerified(): bool
    {
        return $this->whatsapp_verified_at !== null && ! empty($this->whatsapp_phone);
    }

    public function isWhatsAppBotActive(): bool
    {
        return $this->isWhatsAppVerified() && $this->whatsapp_bot_enabled;
    }

    public function defaultWhatsAppPermissions(): array
    {
        return [
            'post' => true,
            'schedule' => true,
            'queue' => true,
            'delete' => true,
            'ideas' => true,
            'generate' => true,
            'draft' => true,
            'mentions' => true,
            'reply' => true,
            'keywords' => true,
            'search' => true,
            'analytics' => true,
            'automation' => false,
            'auto_posts' => true,
            'image' => true,
            'assets' => true,
            'notifications' => true,
            'thread' => true,
            'follow' => true,
        ];
    }

    public function whatsAppPermissions(): array
    {
        return array_merge($this->defaultWhatsAppPermissions(), $this->whatsapp_permissions ?? []);
    }

    public function hasWhatsAppPermission(string $key): bool
    {
        return (bool) ($this->whatsAppPermissions()[$key] ?? false);
    }
}
