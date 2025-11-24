<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessAutoPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_auto_profile_id',
        'post_id',
        'post_date',
        'scheduled_for',
        'content',
        'image_url',
        'asset_code',
        'status',
        'error_message',
        'meta',
        'posted_at',
    ];

    protected $casts = [
        'post_date' => 'date',
        'scheduled_for' => 'datetime',
        'posted_at' => 'datetime',
        'meta' => 'array',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(BusinessAutoProfile::class, 'business_auto_profile_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function statusBadgeColor(): string
    {
        return match ($this->status) {
            'posted' => 'bg-green-100 text-green-700',
            'scheduled' => 'bg-blue-100 text-blue-700',
            'generating' => 'bg-amber-100 text-amber-700',
            'failed' => 'bg-red-100 text-red-700',
            default => 'bg-gray-100 text-gray-700',
        };
    }
}

