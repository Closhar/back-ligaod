<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\Param;

use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends ModelResource<Param>
 */
class ParamResource extends ModelResource implements HasImportExportContract
{
    protected string $model = Param::class;

    use ImportExportConcern;

    public string $column = 'title';

    protected bool $createInModal = true;

    protected bool $editInModal = true;

    protected bool $detailInModal = true;

    // записей на страницу в таблице
    protected int $itemsPerPage = 50;

    // выбор отображаемых полей пользователем
    protected bool $columnSelection = false;

    // фиксированая шапка
    protected bool $stickyTable = true;

    function __construct()
    {
        $this->title = __('admin.table.params');
    }

    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make(__('admin.field.param_title'), 'title')
                ->sortable()
                ->required()
                ->updateOnPreview(),
            Text::make(__('admin.field.param_name'), 'name')
                ->sortable()
                ->required()
                ->updateOnPreview(),
            Select::make('Тип данных', 'type')
                ->options([
                    'string' => 'Строка',
                    'text' => 'Текст',
                ])
                ->sortable()
                ->updateOnPreview(),
            Text::make(__('admin.field.param_value'), 'value')
                ->sortable()
                ->required()
                ->updateOnPreview()
        ];
    }

    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function formFields(): iterable
    {
        return [
            Box::make([
                Text::make(__('admin.field.param_title'), 'title')
                    ->required(),
                Text::make(__('admin.field.param_name'), 'name')
                    ->required(),
                Select::make('Тип данных', 'type')
                    ->options([
                        'string' => 'Строка',
                        'text' => 'Текст',
                    ])
                    ->required(),
                Textarea::make(__('admin.field.param_value'), 'value')
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

    /**
     * @param Param $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [
            'name' => ['required'],
            'value' => ['nullable'],
            'title' => ['required'],
            'type' => ['required', 'in:string,text'],
        ];
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
        return parent::indexButtons()->except(fn(ActionButton $btn) => in_array($btn->getName(), ['resource-delete-button'])
        );
    }

    // кнопки действия в форме
    protected function formButtons(): ListOf
    {
        // Убираем кнопки удаления и detail
        return parent::formButtons()->except(fn(ActionButton $btn) => in_array($btn->getName(), ['resource-delete-button'])
        );
    }

    // поиск по паолям
    public function search(): array
    {
        return ['name', 'title', 'value'];
    }

    // поля в экспорте
    protected function exportFields(): iterable
    {
        return [
            ID::make(),
            Text::make('title'),
            Text::make('name'),
            Text::make('type'),
            Text::make('value'),
        ];
    }

    // поля в импорте
    protected function importFields(): iterable
    {
        return [
            ID::make(),
            Text::make('title'),
            Text::make('name'),
            Text::make('type'),
            Text::make('value'),
        ];
    }

}
