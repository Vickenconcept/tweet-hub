<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
        'twitter_access_token',
        'twitter_access_token_secret',
        'twitter_refresh_token',
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
}
