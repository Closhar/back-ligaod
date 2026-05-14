<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactPage extends Model
{
    protected $guarded = [];

    protected $casts = [
        'notify_email_enabled' => 'boolean',
        'notify_telegram_enabled' => 'boolean',
    ];
}
