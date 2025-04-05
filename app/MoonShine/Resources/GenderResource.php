<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Traits\TranslMorphManyTrait;
use App\Models\Gender;

use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\ClickAction;
use MoonShine\Support\Enums\PageType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Fields\ID;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<Gender>
 */
class GenderResource extends ModelResource implements HasImportExportContract
{
    use TranslMorphManyTrait, ImportExportConcern;

    protected string $model = Gender::class;

    public string $column = 'title';

    protected bool $createInModal = true;

    protected bool $editInModal = false;

    protected bool $detailInModal = true;

    protected array $with = ['clubs'];

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
        $this->title = __('admin.table.genders');
    }

    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('', 'icon')
                ->previewMode()
                ->changePreview(fn(?string $data) => $data ? '<iconify-icon icon="' . $data . '" style="font-size: 50px;"></iconify-icon>' : ' '),
            Text::make(__('admin.field.title'), 'title')
                ->sortable()
                ->updateOnPreview()
                ->required(),
            Text::make(__('admin.field.title_short'), 'title_short')
                ->sortable()
                ->updateOnPreview()
                ->required(),
            Text::make(__('admin.field.icon') . "<a href='https://icon-sets.iconify.design/' target='_blank'>Iconify</a>", 'icon')
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
                    Text::make(__('admin.field.icon'), 'icon')
                        ->required(),
                    Text::make('', 'icon')
                        ->previewMode()
                        ->changePreview(fn(?string $data) => $data ? '<iconify-icon icon="' . $data . '" style="font-size: 50px;"></iconify-icon>' : ' '),
                ])
            ]),

            HasMany::make(__('admin.field.clubs'), 'clubs')
                ->creatable()
                ->searchable(false)
                ->modifyItemButtons(
                    fn(ActionButton $detail, $edit, $delete, $massDelete, HasMany $ctx) => []
                ),
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
     * @param Gender $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [
            'title' => ['required', 'string', 'min:2'],
            'title_short' => ['required', 'string'],
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
        return ['title', 'title_short'];
    }

    // поля в экспорте
    protected function exportFields(): iterable
    {
        return [
            ID::make(),
            Text::make('title'),
            Text::make('title_short'),
        ];
    }

    // поля в импорте
    protected function importFields(): iterable
    {
        return [
            ID::make(),
            Text::make('title'),
            Text::make('title_short'),
        ];
    }
}
