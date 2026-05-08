<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

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
        'yandex_id',
        'is_admin',
        'is_blocked',
        'blocked_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'boolean',
        'is_blocked' => 'boolean',
        'blocked_at' => 'datetime',
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
            'is_admin' => 'boolean',
            'is_blocked' => 'boolean',
            'blocked_at' => 'datetime',
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

        return Storage::disk('public')->url($this->avatar);
    }

    protected static function booted(): void
    {
        static::created(function (User $user) {
            if (! Schema::hasTable('admin_roles') || ! Schema::hasTable('admin_role_user')) {
                return;
            }

            $defaultRoleId = AdminRole::query()->where('slug', 'user')->value('id');

            if ($defaultRoleId) {
                $user->adminRoles()->syncWithoutDetaching([$defaultRoleId]);
            }
        });
    }

    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    public function adminRoles(): BelongsToMany
    {
        return $this->belongsToMany(AdminRole::class, 'admin_role_user')->withTimestamps();
    }

    public function activeAdminRoles(): BelongsToMany
    {
        return $this->adminRoles()->where('admin_roles.is_active', true);
    }

    public function canAccessAdminPage(string $slug): bool
    {
        if ($this->is_blocked) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        return $this->activeAdminRoles()
            ->whereHas('adminPages', function ($query) use ($slug) {
                $query->where('admin_pages.slug', $slug)->where('admin_pages.menu', true);
            })
            ->exists();
    }

    public function hasAnyAdminAccess(): bool
    {
        if ($this->is_blocked) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        return $this->activeAdminRoles()
            ->whereHas('adminPages', function ($query) {
                $query->where('admin_pages.menu', true);
            })
            ->exists();
    }

}
