<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\SportProperty;
use App\MoonShine\Pages\SportPropertyTreePage;
use App\Traits\TranslMorphManyTrait;
use Illuminate\Database\Eloquent\Model;
use Leeto\MoonShineTree\Resources\TreeResource;
use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Fields\Relationships\BelongsToMany;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Enums\ClickAction;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\Support\Enums\PageType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Fields\ID;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends ModelResource<SportProperty>
 */
class SportPropertyResource extends TreeResource implements HasImportExportContract
{
    use TranslMorphManyTrait, ImportExportConcern;

    protected string $model = SportProperty::class;

    public string $column = 'title';

    protected string $sortColumn = 'order';

//    protected array $with = ['sports'];

    protected bool $createInModal = true;

    protected bool $editInModal = false;

    protected bool $detailInModal = true;

    // записей на страницу в таблице
    protected int $itemsPerPage = 50;

    // выбор отображаемых полей пользователем
    protected bool $columnSelection = false;

    // фиксированая шапка
    protected bool $stickyTable = true;

    // после сохранения переадресация на index
    protected ?PageType $redirectAfterSave = PageType::INDEX;

    // редактировать при клике на строку
    protected ?ClickAction $clickAction = ClickAction::EDIT;

    function __construct()
    {
        $this->title = __('admin.table.sport_properties');
    }

    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make(__('admin.field.title'), 'title'),
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
                    Text::make(__('admin.field.title'), 'title'),
                ], colSpan: 4),
                Column::make([
                    Text::make('', 'icon')
                        ->link('https://icon-sets.iconify.design/', __('admin.field.icon') . ' Iconify', blank: true),
                ], colSpan: 6),
                Column::make([
                    Text::make('', 'icon')
                        ->previewMode()
                        ->changePreview(fn(?string $data) => $data ? '<iconify-icon icon="' . $data . '" style="font-size: 50px;"></iconify-icon>' : ' ')
                ], colSpan: 2),
            ]),
            Textarea::make(__('admin.field.annotation'), 'annotation'),

//            BelongsToMany::make(__('admin.field.sports'), 'sports', resource: SportResource::class)
//                ->creatable()
//                ->tree('sport_property_id'),
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

    protected function pages(): array
    {
        return [
            SportPropertyTreePage::class,
            FormPage::class,
            DetailPage::class,
        ];
    }

    /**
     * @param SportProperty $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return ['title' => ['required', 'string', 'min:2'],];
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

    public function treeKey(): ?string
    {
        return null;
    }

    public function sortKey(): string
    {
        return 'order';
    }


    public function itemContent(Model $item): string
    {
        return '<iconify-icon icon="' . $item->icon . '" style="font-size: 30px;"></iconify-icon> | ' . $item->annotation;
    }
}
