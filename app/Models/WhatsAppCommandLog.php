<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppCommandLog extends Model
{
    protected $table = 'whatsapp_command_logs';

    protected $fillable = [
        'user_id',
        'zernio_event_id',
        'from_phone',
        'conversation_id',
        'command',
        'parsed_action',
        'status',
        'response_preview',
        'error',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
