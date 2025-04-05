<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\MoonShine\Resources\ArticleResource;
use App\MoonShine\Resources\ClubResource;
use App\MoonShine\Resources\SportPropertyResource;
use App\Traits\ArticleableTrait;
use MoonShine\CKEditor\Fields\CKEditor;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Fields\Relationships\BelongsToMany;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Fields\Relationships\MorphToMany;
use MoonShine\Laravel\Fields\Slug;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Divider;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Text;

class SportFormPage extends FormPage
{
    use ArticleableTrait;

    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Grid::make([
                Column::make([
                    Flex::make([
                        Text::make(__('admin.field.title'), 'title')
                            ->reactive()
                            ->required(),
                        Text::make(__('admin.field.title_short'), 'title_short')
                            ->required(),
                        Text::make(__('admin.field.vin') . ' (Какие? футбольные)', 'vin'),
                    ]),
                    CKEditor::make(__('admin.field.annotation'), 'annotation')
                ],
                    colSpan: 8),
                Column::make([
                    Image::make(__('admin.field.image'), 'image'),
                    Grid::make([
                        Column::make([
                            Text::make('', 'icon')
                                ->link('https://icon-sets.iconify.design/', __('admin.field.icon') . ' Iconify', blank: true),
                        ], colSpan: 10),
                        Column::make([
                            Text::make('', 'icon')
                                ->previewMode()
                                ->changePreview(fn(?string $data) => $data ? '<iconify-icon icon="' . $data . '" style="font-size: 50px;"></iconify-icon>' : ' ')
                        ], colSpan: 2),
                    ]),
                    Slug::make(__('admin.field.slug'), 'slug')
                        ->from('title')
                        ->live(),
                    Divider::make(),
                    Divider::make(__('admin.field.sport_property')),
                    BelongsToMany::make('', 'sport_properties', resource: SportPropertyResource::class),
                    Divider::make(),
                    Divider::make(__('admin.field.arenas')),
                    MorphToMany::make('', 'arenas')
                        ->selectMode()
                        ->modifyTable(
                            fn(TableBuilder $table, bool $preview) => $table
                                ->tdAttributes(fn(?DataWrapperContract $data, int $row, int $cell): array => ['width' => match ($cell) {
                                    0 => '40px',
                                    default => 'auto', // Остальные столбцы
                                }])
                        ),
                    Divider::make(),
                    Divider::make(__('admin.field.articles')),
                    $this->ArticleableTrait()
                ],
                    colSpan: 4)
            ]),
            //$this->getClubsField()
        ];
    }

//    private function getClubsField(): HasMany
//    {
//        return HasMany::make(__('admin.field.clubs'), 'clubs', resource: ClubResource::class)
//            ->fillData($this->getResource()->getItem())
//            ->creatable()
//            ->searchable(false)
//            ->modifyItemButtons(
//                fn(ActionButton $detail, $edit, $delete, $massDelete, HasMany $ctx) => []
//            );
//    }

    protected function mainLayer(): array
    {
        return [
            Tabs::make([
                Tab::make(__('admin.field.main'), parent::mainLayer()),
                Tab::make(__('admin.field.clubs'), [
                    //$this->getClubsField(),
                ]),
            ]),
        ];
    }

    protected function bottomLayer(): array
    {
        return [];
    }
}
