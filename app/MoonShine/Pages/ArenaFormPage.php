<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\MoonShine\Resources\ArticleResource;
use App\MoonShine\Resources\ClubResource;
use App\MoonShine\Resources\CompetitionResource;
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
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Divider;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Text;

class ArenaFormPage extends FormPage
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
                            ->sortable()
                            ->updateOnPreview()
                            ->reactive()
                            ->required(),
                        BelongsTo::make(__('admin.field.city'), 'city')
                            ->searchable()
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
                    Flex::make([
                        CKEditor::make(__('admin.field.about_arena'), 'about'),
                        CKEditor::make(__('admin.field.address'), 'address'),
                    ]),
                    Flex::make([
                        CKEditor::make(__('admin.field.dop_info'), 'dop_info'),
                        CKEditor::make(__('admin.field.map'), 'map'),
                    ]),


                ], colSpan: 8),
                Column::make([
                    Image::make(__('admin.field.image'), 'image')
                        ->removable()
                        ->dir('arenas'),
                    Slug::make(__('admin.field.slug'), 'slug')
                        ->from('title')
                        ->live(),
                    Divider::make(),
                    Divider::make(__('admin.field.galleries')),
                    BelongsTo::make(__('admin.field.gallery'), 'gallery')
                        ->searchable()
                        ->nullable(),
                    Divider::make(),
                    Divider::make(__('admin.field.sports')),
                    MorphToMany::make('', 'sports', resource: SportResource::class)
                        ->selectMode(),
                    Divider::make(),
                    Divider::make(__('admin.field.clubs')),
                    MorphToMany::make('',
                        'clubs',
                        formatted: function ($item) {
                            return $item->title . ' | ' . $item->sport->title_short . ' | ' . $item->gender->title_short;
                        },
                        resource: ClubResource::class)
                        ->selectMode(),
                    Divider::make(),
                    Divider::make(__('admin.field.competitions')),
                    MorphToMany::make('', 'competitions', resource: CompetitionResource::class)
                        ->selectMode(),
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
