<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoDm extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'twitter_recipient_id',
        'recipient_username',
        'recipient_name',
        'source_type',
        'campaign_name',
        'tweet_id',
        'interaction_type',
        'twitter_event_id',
        'twitter_message_id',
        'original_context',
        'dm_text',
        'sent_at',
        'status',
        'last_error',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForInteraction($query, string $tweetId)
    {
        return $query->where('source_type', 'interaction')
            ->where('campaign_name', "Tweet {$tweetId}");
    }
}


