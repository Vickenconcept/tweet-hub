<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Post extends Model
{
    protected $fillable = [
        'user_id',
        'content',
        'media',
        'twitter_post_id',
        'in_reply_to_post_id',
        'scheduled_at',
        'sent_at',
        'status',
    ];

    protected $casts = [
        'media' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(Post::class, 'in_reply_to_post_id');
    }

    public function replies()
    {
        return $this->hasMany(Post::class, 'in_reply_to_post_id');
    }

    public function autoPost(): HasOne
    {
        return $this->hasOne(BusinessAutoPost::class);
    }
}
