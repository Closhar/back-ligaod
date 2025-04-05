<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\MoonShine\Resources\ArenaResource;
use App\MoonShine\Resources\ArticleResource;
use App\MoonShine\Resources\EventResource;
use App\MoonShine\Resources\GalleryResource;
use App\MoonShine\Resources\GenderResource;
use App\MoonShine\Resources\SportResource;
use App\Traits\ArticleableTrait;
use MoonShine\CKEditor\Fields\CKEditor;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
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
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Text;
use Throwable;

class CompetitionFormPage extends FormPage
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
                            ->required()
                            ->reactive(),
                        Text::make(__('admin.field.title_short'), 'title_short'),
                    ]),
                    Flex::make([
                        Date::make(__('admin.field.date_from'), 'date_from'),
                        Date::make(__('admin.field.date_to'), 'date_to'),
                    ]),
                    Text::make(__('admin.field.sites'), 'sites'),

                    Text::make(__('admin.field.vks'), 'vks'),
                    Text::make(__('admin.field.youtubes'), 'youtubes'),
                    Text::make(__('admin.field.telegrams'), 'telegrams'),
                    Text::make(__('admin.field.instagrams'), 'instagrams'),
                    Text::make(__('admin.field.facebooks'), 'facebooks'),
                    Text::make(__('admin.field.xs'), 'xs'),
                    CKEditor::make(__('admin.field.about_competition'), 'about')
                ], colSpan: 8),
                Column::make([
                    Image::make(__('admin.field.comp_logo'), 'image')
                        ->removable()
                        ->dir('competitions'),
                    Image::make(__('admin.field.comp_bg'), 'bg_image')
                        ->removable()
                        ->dir('competitions'),
                    Divider::make(),
                    Divider::make(__('admin.field.slug')),
                    Slug::make('', 'slug')
                        ->from('title')
                        ->live(),
                    Divider::make(),
                    Divider::make(__('admin.field.gender')),
                    BelongsTo::make('', 'gender', resource: GenderResource::class),
                    Divider::make(),
                    Divider::make(__('admin.field.sport')),
                    BelongsTo::make('', 'sport', resource: SportResource::class)
                        ->searchable(),
                    Divider::make(),
                    Divider::make(__('admin.field.gallery')),
                    BelongsTo::make('', 'gallery', resource: GalleryResource::class)
                        ->searchable()
                        ->nullable(),
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
                ], colSpan: 4),
            ]),
            $this->getEventField()
        ];
    }

    private function getEventField(): HasMany
    {
        return HasMany::make('', 'events', resource: EventResource::class)
            ->fillData($this->getResource()->getItem())
            ->modifyItemButtons(
                fn(ActionButton $detail, $edit, $delete, $massDelete, HasMany $ctx) => [$edit, $delete]
            )
            ->creatable(true)
            ->searchable(true);
    }

    protected function mainLayer(): array
    {
        return [
            Tabs::make([
                Tab::make(__('admin.field.main'), parent::mainLayer()),
                Tab::make(__('admin.field.events'), [
                    //$this->getEventField()
                ]),
            ]),
        ];
    }

    protected function bottomLayer(): array
    {
        return [];
    }
}
