<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TelegramParseChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_id',
        'username',
        'title',
        'parse_frequency',
        'start_date',
        'last_parse_at',
        'is_active',
        'messages_count',
        'parse_status',
        'error_message'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'last_parse_at' => 'datetime',
        'is_active' => 'boolean',
        'messages_count' => 'integer'
    ];

    /**
     * Получить все сообщения канала
     */
    public function messages()
    {
        return $this->hasMany(TelegramMessage::class, 'channel_id', 'channel_id');
    }

    /**
     * Обновить статистику канала
     */
    public function updateStats()
    {
        $this->messages_count = $this->messages()->count();
        $this->save();
    }

    /**
     * Обновить статус парсинга
     */
    public function updateParseStatus($status, $errorMessage = null)
    {
        $this->parse_status = $status;
        $this->error_message = $errorMessage;
        $this->last_parse_at = now();
        $this->save();
    }
}
