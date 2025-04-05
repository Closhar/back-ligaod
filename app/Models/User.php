<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Str;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected $appends = ['avatar_path'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getAvatarPathAttribute()
    {
        if (!$this->avatar) {
            return null; // Если аватар не задан, возвращаем null
        }

        // Проверяем, начинается ли avatar с 'http'
        if (Str::startsWith($this->avatar, 'http')) {
            return $this->avatar; // Возвращаем avatar без изменений
        }

        // Если avatar не начинается с 'http', добавляем базовый URL
        return config('app.url') . '/storage/' . $this->avatar;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin'; // Или ваша логика проверки
    }
    
}
