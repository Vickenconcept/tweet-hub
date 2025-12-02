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
        'source_type',
        'campaign_name',
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
}


