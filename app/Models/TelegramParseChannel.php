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
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
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
