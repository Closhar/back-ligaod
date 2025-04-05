<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Traits\TranslMorphManyTrait;
use App\Models\Article;

use MoonShine\CKEditor\Fields\CKEditor;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Fields\Relationships\MorphMany;
use MoonShine\Laravel\Fields\Relationships\MorphToMany;
use MoonShine\Laravel\Fields\Slug;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\ClickAction;
use MoonShine\Support\Enums\PageType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Divider;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends ModelResource<Article>
 */
class ArticleResource extends ModelResource implements HasImportExportContract
{
    protected string $model = Article::class;

    use TranslMorphManyTrait, ImportExportConcern;

    public string $column = 'title';

    protected bool $createInModal = false;

    protected bool $editInModal = false;

    protected bool $detailInModal = true;

    //protected array $with = ['sex'];

    // записей на страницу в таблице
    protected int $itemsPerPage = 50;

    // выбор отображаемых полей пользователем
    protected bool $columnSelection = false;

    // фиксированая шапка
    protected bool $stickyTable = true;

    // после сохранения переадресация на index
    protected ?PageType $redirectAfterSave = PageType::INDEX;

    // редактировать при клике на строку
    protected ?ClickAction $clickAction = ClickAction::SELECT;

    function __construct()
    {
        $this->title = __('admin.table.articles');
    }

    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Image::make(__('admin.field.image'), 'image'),
            Date::make('data')
                ->withTime()
                ->sortable()
                ->required()
                ->updateOnPreview(),
            Text::make(__('admin.field.title'), 'title')
                ->sortable()
                ->updateOnPreview()
                ->required(),
            Text::make(__('admin.field.slug'), 'slug')
        ];
    }

    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function formFields(): iterable
    {
        return [
            Grid::make([
                Column::make([
                    Grid::make([
                        Column::make([
                            Date::make('data')
                                ->withTime()
                                ->sortable()
                                ->required()
                                ->updateOnPreview(),
                        ], colSpan: 4),
                        Column::make([
                            Text::make(__('admin.field.title'), 'title')
                                ->sortable()
                                ->updateOnPreview()
                                ->required()
                                ->reactive(),
                        ], colSpan: 8)
                    ]),
                    Textarea::make(__('admin.field.description'), 'description'),
                    CKEditor::make(__('admin.field.content'), 'content'),
                ], colSpan: 8),
                Column::make([
                    Image::make(__('admin.field.image'), 'image')
                        ->removable()
                        ->dir('articles'),
                    Slug::make(__('admin.field.slug'), 'slug')
                        ->from('title')
                        ->live(),
                    Divider::make(),
                    Divider::make(__('admin.field.galleries')),
                    MorphToMany::make('', 'galleries', resource: GalleryResource::class)
                        ->selectMode(),
                    Divider::make(),
                    Divider::make(__('admin.field.videos')),
                    MorphToMany::make('', 'videos', resource: VideoResource::class)
                        ->selectMode(),
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
                    Divider::make(__('admin.field.arenas')),
                    MorphToMany::make('',
                        'arenas',
                        formatted: function ($item) {
                            return $item->title . ' (' . $item->city->title_short . ') ';
                        },
                        resource: ArenaResource::class)
                        ->selectMode(),
                    Divider::make(),
                    Divider::make(__('admin.field.competitions')),
                    MorphToMany::make('', 'competitions', resource: ArenaResource::class)
                        ->selectMode(),
                    Divider::make(),
                    Divider::make(__('admin.field.events')),
                    MorphToMany::make('',
                        'events',
                        formatted: function ($item) {
                            // Проверяем, что competition, club1 и club2 существуют
                            $competitionTitle = $item->competition ? $item->competition->title_short : '';
                            $club1Title = $item->club1 ? $item->club1->title_short : '';
                            $club2Title = $item->club2 ? $item->club2->title_short : '';

                            return $item->date_from . '. ' . $competitionTitle . '. ' . $club1Title . ' - ' . $club2Title;
                        },
                        resource: ArenaResource::class)
                        ->selectMode(),
                ], colSpan: 4),
            ])
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function detailFields(): iterable
    {
        return [
            ID::make(),
        ];
    }


    protected function rules(mixed $item): array
    {
        return [
            'title' => ['required', 'string', 'min:2'],
            'data' => ['required'],
        ];
    }


    // кнопки действия на index
    protected function indexButtons(): ListOf
    {
        // убираем detail кнопку
        return parent::indexButtons()->except(fn(ActionButton $btn) => $btn->getName() === 'resource-detail-button');
    }

    // кнопки действия в форме
    protected function formButtons(): ListOf
    {
        // Убираем кнопки удаления и detail
        return parent::formButtons()->except(fn(ActionButton $btn) => in_array($btn->getName(), ['resource-delete-button', 'resource-detail-button'])
        );
    }

    // поиск по полям
    public function search(): array
    {
        return ['title', 'slug', 'description', 'content'];
    }

    // атрибуты td
//        protected function tdAttributes(): Closure
//        {
//            return fn(?DataWrapperContract $data, int $row, int $cell) => [
//                // (индексы начинаются с 0)
//                'width' => match ($cell) {
//                    6 => '70px',
//                    7 => '250px',
//                    9 => '180px',
//                    default => 'auto', // Остальные столбцы
//                },
//            ];
//        }

    // поля в экспорте
    protected function exportFields(): iterable
    {
        return [
            ID::make(),
            Text::make('title'),
            Text::make('data'),
            Text::make('description'),
            Text::make('content'),
            Text::make('slug'),
            Text::make('image'),
        ];
    }

    // поля в импорте
    protected function importFields(): iterable
    {
        return [
            ID::make(),
            Text::make('title'),
            Text::make('data'),
            Text::make('description'),
            Text::make('content'),
            Text::make('slug'),
            Text::make('image'),
        ];
    }
}
