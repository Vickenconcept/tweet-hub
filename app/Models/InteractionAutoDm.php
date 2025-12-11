<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InteractionAutoDm extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tweet_id',
        'enabled',
        'monitor_likes',
        'monitor_retweets',
        'monitor_replies',
        'monitor_quotes',
        'dm_template',
        'last_checked_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'monitor_likes' => 'boolean',
        'monitor_retweets' => 'boolean',
        'monitor_replies' => 'boolean',
        'monitor_quotes' => 'boolean',
        'last_checked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
