<?php

namespace App\Models;

use App\Traits\KTranslateTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Article extends Model
{
    use KTranslateTrait, HasFactory;

    protected $guarded = [];

    // Явно указываем fillable поля для надежности
    protected $fillable = [
        'region_id',
        'title',
        'data',
        'slug',
        'description',
        'content',
        'published',
        'image',
        'views',
        'photo_info'
    ];

    protected $hidden = ['pivot', 'created_at', 'updated_at'];
    protected $appends = ['date_formatted', 'article_image_path', 'event_name'];

    protected static function booted(): void
    {
        static::addGlobalScope('order', function ($builder) {
            $builder->orderBy('title', 'asc'); // или 'desc' для сортировки по убыванию
        });
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function sports(): MorphToMany
    {
        return $this->morphedByMany(Sport::class, 'articleable');
    }

    public function clubs(): MorphToMany
    {
        return $this->morphedByMany(Club::class, 'articleable');
    }

    public function arenas(): MorphToMany
    {
        return $this->morphedByMany(Arena::class, 'articleable');
    }

    public function competitions(): MorphToMany
    {
        return $this->morphedByMany(Competition::class, 'articleable');
    }

    public function events(): MorphToMany
    {
        return $this->morphedByMany(Event::class, 'articleable');
    }

    public function galleries(): MorphToMany
    {
        return $this->morphedByMany(Gallery::class, 'articleable');
    }

    public function videos(): MorphToMany
    {
        return $this->morphedByMany(Video::class, 'articleable');
    }

    public function people(): MorphToMany
    {
        return $this->morphedByMany(Person::class, 'articleable');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ArticleTag::class);
    }

    // Связь с просмотрами
    public function views(): HasMany
    {
        return $this->hasMany(ArticleView::class);
    }

    public function getDateFormattedAttribute()
    {
        return \Carbon\Carbon::parse($this->data)->format('d.m.Y. H:i');
    }

    public function getArticleImagePathAttribute()
    {
        if ($this->image) return Storage::disk('public')->url($this->image);
        return null;
    }

    public function getEventNameAttribute()
    {
        return $this->title;
    }

    /**
     * Увеличить счетчик просмотров
     */
    public function incrementViews(): void
    {
        $this->increment('views');
    }

    /**
     * Получить количество просмотров
     */
    public function getViewsCount(): int
    {
        // Проверяем, существует ли поле views в модели
        if (isset($this->views)) {
            return (int) $this->views;
        }

        // Если поле не загружено, возвращаем 0
        return 0;
    }

    /**
     * Записать просмотр с дополнительной информацией
     */
    public function recordView(string $ipAddress = null, string $userAgent = null, string $sessionId = null): void
    {
        // Увеличиваем общий счетчик
        $this->incrementViews();

        // Записываем детальную информацию о просмотре
        $this->views()->create([
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'session_id' => $sessionId,
            'viewed_at' => now(),
        ]);
    }

    /**
     * Проверить, был ли просмотр с данного IP в течение последних 24 часов
     */
    public function hasRecentViewFromIp(string $ipAddress): bool
    {
        return $this->views()
            ->where('ip_address', $ipAddress)
            ->where('viewed_at', '>=', now()->subDay())
            ->exists();
    }

    /**
     * Получить статистику просмотров за период
     */
    public function getViewsStats(string $period = 'day'): array
    {
        $query = $this->views();

        switch ($period) {
            case 'hour':
                $query->where('viewed_at', '>=', now()->subHour());
                break;
            case 'day':
                $query->where('viewed_at', '>=', now()->subDay());
                break;
            case 'week':
                $query->where('viewed_at', '>=', now()->subWeek());
                break;
            case 'month':
                $query->where('viewed_at', '>=', now()->subMonth());
                break;
        }

        $totalViews = $query->count();
        $uniqueViews = $query->distinct('ip_address')->count('ip_address');

        return [
            'total' => $totalViews,
            'unique' => $uniqueViews,
            'period' => $period
        ];
    }
}
