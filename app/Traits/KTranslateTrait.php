<?php

namespace App\Traits;

use App\Models\Localization;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\App;
use RuntimeException;

trait KTranslateTrait
{
    /**
     * Получает локализованное значение из морф-связи localisations
     *
     * @param  string  $field  Имя поля
     * @return mixed Значение локализованного поля или значение из объекта
     *
     * @throws RuntimeException Если поле не существует в объекте
     */
    public function KTranslate(string $field): mixed
    {
        // Проверяем, существует ли запрашиваемое поле в объекте
        if (! property_exists($this, $field) && ! method_exists($this, $field)) {
            throw new RuntimeException("Поле '{$field}' не существует в объекте ".static::class);
        }

        // Получаем текущую локаль и локаль по умолчанию
        $locale = App::getLocale();
        $defaultLocale = config('app.locale');

        // Если локаль текущая совпадает с локалью по умолчанию, возвращаем значение из объекта
        if ($locale === $defaultLocale) {
            return $this->{$field};
        }

        // Ищем локализованное значение через связь localizations
        $localization = $this->localization()
            ->where('language', $locale)
            ->where('field', $field)
            ->first();

        // Возвращаем локализованное значение, если оно найдено, иначе значение из объекта
        return $localization?->value ?? $this->{$field};
    }

    /**
     * Определение связи MorphOne с таблицей localisations
     */
    public function localization($field = 'title', $language = 'en'): MorphMany
    {
        return $this->morphMany(Localization::class, 'localizable');
    }
}
