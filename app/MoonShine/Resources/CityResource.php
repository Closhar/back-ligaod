<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Traits\TranslMorphManyTrait;
use App\Models\City;

use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\ClickAction;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Fields\ID;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<City>
 */
class CityResource extends ModelResource implements HasImportExportContract
{
    use TranslMorphManyTrait, ImportExportConcern;

    protected string $model = City::class;

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

    // после сохранения переадресация на index
    //protected ?PageType $redirectAfterSave = PageType::FORM;

    // редактировать при клике на строку
    protected ?ClickAction $clickAction = ClickAction::SELECT;

    function __construct()
    {
        $this->title = __('admin.table.cities');
    }

    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make(__('admin.field.title'), 'title')
                ->sortable()
                ->updateOnPreview()
                ->required(),
            Text::make(__('admin.field.title_short'), 'title_short')
                ->sortable()
                ->updateOnPreview()
                ->required(),
        ];
    }

    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function formFields(): iterable
    {
        return [
            Box::make([
                Flex::make([
                    Text::make(__('admin.field.title'), 'title')
                        ->required(),
                    Text::make(__('admin.field.title_short'), 'title_short')
                        ->required(),
                ])

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
     * @param City $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return ['title' => ['required', 'string', 'min:2'],];
    }

    // кнопки действия на index
    protected function indexButtons(): ListOf
    {
        // убираем detail кнопку
        return parent::indexButtons()->except(fn(ActionButton $btn) => in_array($btn->getName(), ['resource-edit-button', 'resource-detail-button']));
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
