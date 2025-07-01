<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticleView extends Model
{
    use HasFactory;

    // Отключаем автоматическое управление временными метками
    public $timestamps = false;

    protected $fillable = [
        'article_id',
        'ip_address',
        'user_agent',
        'session_id',
        'viewed_at'
    ];

    protected $casts = [
        'viewed_at' => 'datetime'
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
