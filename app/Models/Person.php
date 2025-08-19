<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Person extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'birth_date',
        'passport_series',
        'passport_number',
        'address',
        'player_number',
        'gender',
        'is_active',
        'about',
    ];

    protected $casts = [
        'birth_date' => 'datetime',
        'player_number' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'full_name',
        'short_name',
        'age'
    ];

    /**
     * Получить полное имя персоны
     */
    public function getFullNameAttribute(): string
    {
        $name = $this->last_name . ' ' . $this->first_name;
        if ($this->middle_name) {
            $name .= ' ' . $this->middle_name;
        }
        return $name;
    }

    /**
     * Получить короткое имя (фамилия и инициалы)
     */
    public function getShortNameAttribute(): string
    {
        $initials = mb_substr($this->first_name, 0, 1) . '.';
        if ($this->middle_name) {
            $initials .= mb_substr($this->middle_name, 0, 1) . '.';
        }
        return $this->last_name . ' ' . $initials;
    }

    /**
     * Получить возраст персоны
     */
    public function getAgeAttribute(): ?int
    {
        if (!$this->birth_date) {
            return null;
        }

        // birth_date теперь всегда Carbon объект благодаря casts
        return $this->birth_date->age;
    }

    /**
     * Получить дату рождения в правильном формате (без UTC конвертации)
     */
    public function getBirthDateAttribute($value)
    {
        if (!$value) {
            return null;
        }

        // Теперь birth_date всегда будет Carbon объектом благодаря casts
        // Возвращаем его как есть для совместимости
        return $value;
    }

    /**
     * Установить дату рождения
     */
    public function setBirthDateAttribute($value)
    {
        if (!$value) {
            $this->attributes['birth_date'] = null;
            return;
        }

        // Если это строка в формате Y-m-d, сохраняем как есть
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $this->attributes['birth_date'] = $value;
            return;
        }

        // Если это Carbon объект, конвертируем в строку
        if ($value instanceof \Carbon\Carbon) {
            $this->attributes['birth_date'] = $value->format('Y-m-d');
            return;
        }

        // Для других случаев пытаемся создать Carbon объект
        try {
            $carbon = \Carbon\Carbon::parse($value);
            $this->attributes['birth_date'] = $carbon->format('Y-m-d');
        } catch (\Exception $e) {
            $this->attributes['birth_date'] = $value;
        }
    }

    /**
     * Отношение к клубам через членство
     */
    public function clubMemberships(): HasMany
    {
        return $this->hasMany(PersonClubMembership::class);
    }

    /**
     * Получить активные членства в клубах
     */
    public function activeClubMemberships(): HasMany
    {
        return $this->hasMany(PersonClubMembership::class)->whereNull('left_at');
    }

    /**
     * Отношение к клубам
     */
    public function clubs(): BelongsToMany
    {
        return $this->belongsToMany(Club::class, 'person_club_memberships')
            ->withPivot(['joined_at', 'left_at', 'position', 'notes'])
            ->withTimestamps();
    }

    /**
     * Отношение к видам спорта через членство
     */
    public function sportMemberships(): HasMany
    {
        return $this->hasMany(PersonSportMembership::class);
    }

    /**
     * Получить активные членства в видах спорта
     */
    public function activeSportMemberships(): HasMany
    {
        return $this->hasMany(PersonSportMembership::class)->whereNull('ended_at');
    }

    /**
     * Отношение к видам спорта
     */
    public function sports(): BelongsToMany
    {
        return $this->belongsToMany(Sport::class, 'person_sport_memberships')
            ->withPivot(['started_at', 'ended_at', 'level', 'achievements'])
            ->withTimestamps();
    }

    /**
     * Отношение к сменам фамилий
     */
    public function surnameChanges(): HasMany
    {
        return $this->hasMany(PersonSurnameChange::class);
    }

    /**
     * Отношение к изображениям
     */
    public function images(): HasMany
    {
        return $this->hasMany(PersonImage::class)->orderBy('position');
    }

    /**
     * Отношение к должностям через членство
     */
    public function positionMemberships(): HasMany
    {
        return $this->hasMany(PersonPositionMembership::class);
    }

    /**
     * Получить активные членства в должностях
     */
    public function activePositionMemberships(): HasMany
    {
        return $this->hasMany(PersonPositionMembership::class)->whereNull('ended_at');
    }

    /**
     * Отношение к должностям
     */
    public function positions(): BelongsToMany
    {
        return $this->belongsToMany(Position::class, 'person_position_memberships')
            ->withPivot(['started_at', 'ended_at', 'notes'])
            ->withTimestamps();
    }

    /**
     * Отношение к амплуа через членство
     */
    public function ampluaMemberships(): HasMany
    {
        return $this->hasMany(PersonAmpluaMembership::class);
    }

    /**
     * Получить активные членства в амплуа
     */
    public function activeAmpluaMemberships(): HasMany
    {
        return $this->hasMany(PersonAmpluaMembership::class)->whereNull('ended_at');
    }

    /**
     * Отношение к амплуа
     */
    public function ampluas(): BelongsToMany
    {
        return $this->belongsToMany(Amplua::class, 'person_amplua_memberships')
            ->withPivot(['started_at', 'ended_at', 'notes'])
            ->withTimestamps();
    }

    /**
     * Получить основное изображение
     */
    public function mainImage(): HasOne
    {
        return $this->hasOne(PersonImage::class)->where('is_main', true);
    }

    /**
     * Scope для поиска по имени
     */
    public function scopeSearchByName($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('middle_name', 'like', "%{$search}%");
        });
    }

    /**
     * Проверить, является ли персона спортсменом
     */
    public function isSportsman(): bool
    {
        return $this->activeAmpluaMemberships()->exists();
    }

    /**
     * Проверить, является ли персона не спортсменом
     */
    public function isNonSportsman(): bool
    {
        return $this->activePositionMemberships()->exists();
    }

    /**
     * Получить текущие активные должности
     */
    public function getCurrentPositionsAttribute()
    {
        return $this->activePositionMemberships()->with('position')->get();
    }

    /**
     * Получить текущие активные амплуа
     */
    public function getCurrentAmpluasAttribute()
    {
        return $this->activeAmpluaMemberships()->with('amplua')->get();
    }

    /**
     * Scope для спортсменов
     */
    public function scopeSportsmen($query)
    {
        return $query->whereHas('activeAmpluaMemberships');
    }

    /**
     * Scope для не спортсменов
     */
    public function scopeNonSportsmen($query)
    {
        return $query->whereHas('activePositionMemberships');
    }

    /**
     * Scope для персон с активными должностями или амплуа
     */
    public function scopeWithActiveRole($query)
    {
        return $query->where(function ($q) {
            $q->whereHas('activePositionMemberships')
                ->orWhereHas('activeAmpluaMemberships');
        });
    }

    public function eventLineups()
    {
        return $this->hasMany(EventLineup::class);
    }

    public function eventActions()
    {
        return $this->hasMany(EventAction::class);
    }
}
