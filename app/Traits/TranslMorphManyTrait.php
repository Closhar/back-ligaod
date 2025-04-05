<?php

namespace App\Traits;

use App\Models\Admin\AddingLanguage;
use App\MoonShine\Resources\LocalizationResource;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Fields\Relationships\MorphMany;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;

trait TranslMorphManyTrait
{
    protected function TranslMorphMany($options): HasMany
    {
        return
            HasMany::make(__('admin.translate'), 'localization', resource: new LocalizationResource($options))
                ->fields([
                    ID::make(),
                    Select::make(__('admin.field.language'), 'language')
                        ->options(AddingLanguage::pluck('title', 'locale')->toArray())
                        ->updateOnPreview(),
                    Select::make(__('admin.field.field'), 'fld')
                        ->options($options)
                        ->updateOnPreview(),
                    Text::make(__('admin.field.value'), 'value')
                        ->customAttributes(['style' => 'margin-bottom:0'])
                        ->updateOnPreview()
                        ->required(),
                ])
                ->modifyItemButtons(
                    fn (ActionButton $detail, $edit, $delete, $massDelete, HasMany $ctx) => [$delete]
                )
                ->creatable()
                ->searchable(false);
    }

    protected function TranslTbl($onlyCount = 0, $w_ttl = '150px'): MorphMany
    {
        if ($onlyCount == 0) {
            return MorphMany::make(__('admin.translate'), 'localization')
                ->fields([
                    Text::make('', 'language')->badge('secondary'),
                    Text::make('', 'fld')->badge('green'),
                    Text::make('', 'value'),
                ])
                ->modifyTable(
                    fn (TableBuilder $table, bool $preview) => $table
                        ->tdAttributes(fn (?DataWrapperContract $data, int $row, int $cell): array => ['width' => match ($cell) {
                            0 => '40px',
                            1 => $w_ttl,
                            default => 'auto', // Остальные столбцы
                        }])
                );
        }

        return MorphMany::make(__('admin.translate'), 'localization')->relatedLink();
    }

    protected function TranslMorphManySelect($options): Select
    {
        return Select::make(__('admin.field.field'), 'fld')
            ->options($options);
    }
}
