<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\MoonShine\Resources\ArticleResource;
use App\MoonShine\Resources\EventResource;
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
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Divider;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use Throwable;

class ClubFormPage extends FormPage
{
    use ArticleableTrait;

    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make([
                Grid::make([
                    Column::make([
                        Flex::make([
                            Text::make(__('admin.field.title'), 'title')
                                ->required()
                                ->reactive(),
                            Text::make(__('admin.field.title_short'), 'title_short')
                                ->required(),
                            BelongsTo::make(__('admin.field.gender'), 'gender')
                                ->nullable(),
                        ]),
                        Text::make(__('admin.field.sites'), 'sites'),
                        Text::make(__('admin.field.emails'), 'emails'),
                        Text::make(__('admin.field.phones'), 'phones'),

                        Text::make(__('admin.field.vks'), 'vks'),
                        Text::make(__('admin.field.youtubes'), 'youtubes'),
                        Text::make(__('admin.field.telegrams'), 'telegrams'),
                        Text::make(__('admin.field.instagrams'), 'instagrams'),
                        Text::make(__('admin.field.facebooks'), 'facebooks'),
                        Text::make(__('admin.field.xs'), 'xs'),

                        Switcher::make(__('admin.field.is_alien'), 'is_alien'),

                        Flex::make([
                            CKEditor::make(__('admin.field.about_club'), 'about'),
                            CKEditor::make(__('admin.field.address'), 'address'),
                        ]),
                        CKEditor::make(__('admin.field.map'), 'map')
                    ], colSpan: 8),
                    Column::make([
                        Image::make(__('admin.field.image'), 'image')
                            ->removable()
                            ->dir('clubs'),
                        Image::make(__('admin.field.club_bg'), 'image_bg')
                            ->removable()
                            ->dir('clubs'),
                        Slug::make(__('admin.field.slug'), 'slug')
                            ->from('title')
                            ->live(),
                        Divider::make(),
                        Divider::make(__('admin.field.sport')),
                        BelongsTo::make('', 'sport', resource: SportResource::class)
                            ->searchable(),
                        Divider::make(),
                        Divider::make(__('admin.field.city')),
                        BelongsTo::make('', 'city')
                            ->searchable(),
                        Divider::make(),
                        Divider::make(__('admin.field.age')),
                        BelongsTo::make('', 'age', formatted: fn($item) => "$item->title ($item->title_short)")
                            ->nullable(),
                        Divider::make(),
                        Divider::make(__('admin.field.gallery')),
                        BelongsTo::make('', 'gallery')
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
                    ], colSpan: 4)
                ])
            ]),
            $this->getEventClub1Field(),
            $this->getEventClub2Field(),
        ];
    }

    private function getEventClub1Field(): HasMany
    {
        return HasMany::make('', 'club1_events', resource: EventResource::class)
            ->fillData($this->getResource()->getItem())
            ->modifyItemButtons(
                fn(ActionButton $detail, $edit, $delete, $massDelete, HasMany $ctx) => [$edit, $delete]
            )
            ->creatable(true)
            ->searchable(true);
    }


    private function getEventClub2Field(): HasMany
    {
        return HasMany::make('', 'club2_events', resource: EventResource::class)
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
                Tab::make(__('admin.field.event_club1'), [
                    //$this->getEventClub1Field(),
                ]),
                Tab::make(__('admin.field.event_club2'), [
                    //$this->getEventClub2Field(),
                ]),
            ]),
        ];
    }

    protected function bottomLayer(): array
    {
        return [];
    }
}
