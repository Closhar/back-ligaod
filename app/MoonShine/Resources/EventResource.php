<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\Event;
use App\Traits\ArticleableTrait;
use App\Traits\TranslMorphManyTrait;

use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Enums\ClickAction;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\Support\Enums\PageType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Divider;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<Event>
 */
class EventResource extends ModelResource implements HasImportExportContract
{
    use TranslMorphManyTrait, ImportExportConcern, ArticleableTrait;

    protected string $model = Event::class;

    public string $column = 'id';

    protected bool $createInModal = false;

    protected bool $editInModal = false;

    protected bool $detailInModal = true;

    protected array $with = ['competition', 'club1', 'club2', 'arena'];

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
        $this->title = __('admin.table.events');
    }

    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Date::make(__('admin.field.date_from'), 'date_from')
                ->withTime()
                ->sortable()
                ->updateOnPreview()
                ->required(),
            Date::make(__('admin.field.date_to'), 'date_to')
                ->withTime()
                ->sortable()
                ->updateOnPreview(),
            BelongsTo::make(__('admin.field.competition'), 'competition'),
            BelongsTo::make(__('admin.field.arena'), 'arena'),
            BelongsTo::make(__('admin.field.club1'), 'club1',
                formatted: function ($item) {
                    if ($item->title) return $item->title . ' | ' . $item->sport->title_short . ' | ' . $item->gender->title_short;
                    return $item;
                }, resource: ClubResource::class),
            BelongsTo::make(__('admin.field.club2'), 'club2',
                formatted: function ($item) {
                    if ($item->title) return $item->title . ' | ' . $item->sport->title_short . ' | ' . $item->gender->title_short;
                    return $item;
                }, resource: ClubResource::class),
            Text::make(__('admin.field.result'), 'result')
                ->updateOnPreview(),
            Text::make(__('admin.field.title'), 'title')
                ->sortable()
                ->updateOnPreview(),
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
                    Flex::make([
                        Date::make(__('admin.field.date_from'), 'date_from')
                            ->withTime()
                            ->required(),
                        Date::make(__('admin.field.date_to'), 'date_to')
                            ->withTime(),
                        BelongsTo::make(__('admin.field.competition'), 'competition')
                            ->searchable(),
                    ]),
                    Text::make(__('admin.field.title'), 'title'),
                    Flex::make([
                        BelongsTo::make(__('admin.field.arena'), 'arena')
                            ->searchable()
                            ->nullable(),
                        BelongsTo::make(
                            __('admin.field.club1'),
                            'club1',
                            formatted: function ($item) {
                                return $item->title . ' | ' . $item->city->title_short . ' | ' . $item->sport->title_short . ' | ' . $item->gender->title_short;
                            },
                            resource: new ClubResource)
                            ->searchable()
                            ->nullable(),
                        BelongsTo::make(
                            __('admin.field.club2'),
                            'club2',
                            formatted: function ($item) {
                                return $item->title . ' | ' . $item->city->title_short . ' | ' . $item->sport->title_short . ' | ' . $item->gender->title_short;
                            },
                            resource: new ClubResource)
                            ->searchable()
                            ->nullable(),
                        Text::make(__('admin.field.result'), 'result'),
                    ]),
                    BelongsTo::make(__('admin.field.gallery'), 'gallery')
                        ->searchable()
                        ->nullable(),
                ], colSpan: 10),
                Column::make([
                    Divider::make(),
                    Divider::make(__('admin.field.articles')),
                    $this->ArticleableTrait()
                ], colSpan: 2),
            ]),


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

    /**
     * @param Event $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return ['date_from' => ['required'],];
    }

    // дополнительные кнопки
    public function actions(): array
    {
        return [
            // кнопка обновление таблицы
            ActionButton::make(__('admin.refresh'), '#')
                ->dispatchEvent(AlpineJs::event(JsEvent::TABLE_UPDATED, 'index-table'))
                ->icon('heroicons.arrow-path')
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

    // поиск по паолям
    public function search(): array
    {
        return ['title'];
    }

    // атрибуты td
//            protected function tdAttributes(): Closure
//            {
//                return fn(?DataWrapperContract $data, int $row, int $cell) => [
//                    // (индексы начинаются с 0)
//                    'width' => match ($cell) {
//                        6 => '70px',
//                        7 => '250px',
//                        9 => '180px',
//                        default => 'auto', // Остальные столбцы
//                    },
//                ];
//            }

    // поля в экспорте
    protected function exportFields(): iterable
    {
        return [
            ID::make(),
            Text::make('title'),
        ];
    }

    // поля в импорте
    protected function importFields(): iterable
    {
        return [
            ID::make(),
            Text::make('title'),
        ];
    }
}
