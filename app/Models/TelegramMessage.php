<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramMessage extends Model
{
    protected $fillable = [
        'channel_id',
        'message_id',
        'content',
        'media',
        'message_date',
        'raw_data'
    ];

    protected $casts = [
        'media' => 'array',
        'raw_data' => 'array',
        'message_date' => 'datetime'
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(TelegramChannel::class, 'channel_id');
    }
}
