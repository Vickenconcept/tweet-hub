<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'path',
        'original_name',
        'code',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
