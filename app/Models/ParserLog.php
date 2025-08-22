<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParserLog extends Model
{
    protected $fillable = [
        'parser_template_id',
        'url',
        'raw_data',
        'parsed_data',
        'errors',
        'status',
        'records_created',
        'records_updated',
    ];

    protected $casts = [
        'parsed_data' => 'array',
        'errors' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ParserTemplate::class, 'parser_template_id');
    }

    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeError($query)
    {
        return $query->where('status', 'error');
    }

    public function scopePartial($query)
    {
        return $query->where('status', 'partial');
    }
}
