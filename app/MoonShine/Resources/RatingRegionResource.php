<?php

namespace App\MoonShine\Resources;

use App\Models\RatingRegion;
use Illuminate\Database\Eloquent\Model;
use MoonShine\Actions\FiltersAction;
use MoonShine\Decorations\Block;
use MoonShine\Decorations\Column;
use MoonShine\Decorations\Grid;
use MoonShine\Fields\ID;
use MoonShine\Fields\Relationships\HasMany;
use MoonShine\Fields\SwitchBoolean;
use MoonShine\Fields\Text;
use MoonShine\Fields\Textarea;
use MoonShine\Resources\Resource;

class RatingRegionResource extends Resource
{
    public static string $model = RatingRegion::class;

    public static string $title = 'Регионы рейтинга SRRR';

    public static string $navigationIcon = 'heroicons-outline:map';

    public static int $itemsPerPage = 20;

    public static string $orderType = 'DESC';

    public static string $orderColumn = 'id';

    public function fields(): array
    {
        return [
            Block::make([
                Column::make([
                    ID::make()->sortable(),
                    Text::make('Название', 'name')
                        ->required()
                        ->sortable()
                        ->searchable(),
                    Text::make('Код', 'code')
                        ->required()
                        ->sortable()
                        ->searchable()
                        ->unique(),
                ])->columnSpan(6),
                Column::make([
                    SwitchBoolean::make('Активен', 'is_active')
                        ->default(true),
                    Textarea::make('Описание', 'description')
                        ->rows(3),
                ])->columnSpan(6),
            ]),

            Block::make([
                HasMany::make('Клубы', 'clubs', new ClubResource())
                    ->hideOnForm(),
                HasMany::make('Рейтинги', 'ratings', new RegionRatingResource())
                    ->hideOnForm(),
            ]),
        ];
    }

    public function rules(Model $item): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', 'unique:rating_regions,code,' . $item->id],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    public function search(): array
    {
        return ['id', 'name', 'code'];
    }

    public function filters(): array
    {
        return [
            FiltersAction::make(trans('moonshine::ui.filters')),
        ];
    }

    public function actions(): array
    {
        return [
            //
        ];
    }
}
