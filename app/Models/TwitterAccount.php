<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class TwitterAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'api_key',
        'api_secret',
        'access_token',
        'access_token_secret',
        'auto_comment_enabled',
        'daily_comment_limit',
        'comments_posted_today',
        'last_comment_at',
    ];

    protected $casts = [
        'auto_comment_enabled' => 'boolean',
        'daily_comment_limit' => 'integer',
        'comments_posted_today' => 'integer',
        'last_comment_at' => 'datetime',
    ];

    /**
     * Relationship to User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if all required credentials are configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->api_key) &&
               !empty($this->api_secret) &&
               !empty($this->access_token) &&
               !empty($this->access_token_secret);
    }

    /**
     * Check if auto-comment is enabled
     */
    public function isAutoEnabled(): bool
    {
        return $this->auto_comment_enabled && $this->isConfigured();
    }

    /**
     * Check if user can post a comment (not at daily limit)
     */
    public function canPost(): bool
    {
        if (!$this->isAutoEnabled()) {
            return false;
        }

        return $this->comments_posted_today < $this->daily_comment_limit;
    }

    /**
     * Reduce quota by 1 after posting
     */
    public function reduceQuota(): void
    {
        $this->increment('comments_posted_today');
        $this->last_comment_at = now();
        $this->save();

        Log::info('TwitterAccount quota reduced', [
            'user_id' => $this->user_id,
            'comments_posted_today' => $this->comments_posted_today,
            'daily_limit' => $this->daily_comment_limit,
        ]);
    }

    /**
     * Reset daily counter (called by scheduler)
     */
    public function resetDailyCounter(): void
    {
        $this->comments_posted_today = 0;
        $this->save();

        Log::info('TwitterAccount daily counter reset', [
            'user_id' => $this->user_id,
        ]);
    }

    /**
     * Get decrypted API key
     */
    public function getDecryptedApiKey(): ?string
    {
        if (empty($this->api_key)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->api_key);
        } catch (\Exception $e) {
            Log::error('Failed to decrypt API key', [
                'user_id' => $this->user_id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get decrypted API secret
     */
    public function getDecryptedApiSecret(): ?string
    {
        if (empty($this->api_secret)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->api_secret);
        } catch (\Exception $e) {
            Log::error('Failed to decrypt API secret', [
                'user_id' => $this->user_id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get decrypted access token
     */
    public function getDecryptedAccessToken(): ?string
    {
        if (empty($this->access_token)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->access_token);
        } catch (\Exception $e) {
            Log::error('Failed to decrypt access token', [
                'user_id' => $this->user_id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get decrypted access token secret
     */
    public function getDecryptedAccessTokenSecret(): ?string
    {
        if (empty($this->access_token_secret)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->access_token_secret);
        } catch (\Exception $e) {
            Log::error('Failed to decrypt access token secret', [
                'user_id' => $this->user_id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Set encrypted API key
     */
    public function setApiKeyAttribute($value): void
    {
        if (empty($value)) {
            $this->attributes['api_key'] = null;
            return;
        }

        $this->attributes['api_key'] = Crypt::encryptString($value);
    }

    /**
     * Set encrypted API secret
     */
    public function setApiSecretAttribute($value): void
    {
        if (empty($value)) {
            $this->attributes['api_secret'] = null;
            return;
        }

        $this->attributes['api_secret'] = Crypt::encryptString($value);
    }

    /**
     * Set encrypted access token
     */
    public function setAccessTokenAttribute($value): void
    {
        if (empty($value)) {
            $this->attributes['access_token'] = null;
            return;
        }

        $this->attributes['access_token'] = Crypt::encryptString($value);
    }

    /**
     * Set encrypted access token secret
     */
    public function setAccessTokenSecretAttribute($value): void
    {
        if (empty($value)) {
            $this->attributes['access_token_secret'] = null;
            return;
        }

        $this->attributes['access_token_secret'] = Crypt::encryptString($value);
    }

    /**
     * Get settings array for TwitterService
     */
    public function getTwitterServiceSettings(): array
    {
        return [
            'account_id' => $this->user->twitter_account_id ?? null,
            'consumer_key' => $this->getDecryptedApiKey(),
            'consumer_secret' => $this->getDecryptedApiSecret(),
            'access_token' => $this->getDecryptedAccessToken(),
            'access_token_secret' => $this->getDecryptedAccessTokenSecret(),
            'bearer_token' => $this->getDecryptedApiKey(), // For v2 API endpoints, bearer token is typically the API key
        ];
    }
}

