<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessAutoProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'keywords',
        'tone',
        'posting_time',
        'timezone',
        'include_images',
        'image_style',
        'is_active',
        'last_generated_at',
    ];

    protected $casts = [
        'keywords' => 'array',
        'include_images' => 'boolean',
        'is_active' => 'boolean',
        'last_generated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(BusinessAutoPost::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    protected function keywordsList(): Attribute
    {
        return Attribute::make(
            get: fn () => collect($this->keywords ?? [])->implode(', ')
        );
    }
}

