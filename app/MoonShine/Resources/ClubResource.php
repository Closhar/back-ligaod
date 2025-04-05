<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\MoonShine\Pages\ClubFormPage;
use App\Traits\TranslMorphManyTrait;
use App\Models\Club;

use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\ClickAction;
use MoonShine\Support\Enums\PageType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Fields\ID;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<Club>
 */
class ClubResource extends ModelResource implements HasImportExportContract
{
    use TranslMorphManyTrait, ImportExportConcern;

    protected string $model = Club::class;

    public string $column = 'title';

    protected bool $createInModal = false;

    protected bool $editInModal = false;

    protected bool $detailInModal = true;

    protected array $with = ['city', 'gender', 'age', 'sport'];

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
        $this->title = __('admin.table.clubs');
    }

    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Image::make(__('admin.field.image'), 'image'),
            BelongsTo::make(__('admin.field.sport'), 'sport', SportResource::class)
                ->badge('secondary'),
            BelongsTo::make(__('admin.field.gender'), 'gender', formatted: 'title_short', resource: GenderResource::class)
                ->badge('red'),
            BelongsTo::make(__('admin.field.age'), 'age', formatted: 'title_short', resource: AgeResource::class)
                ->badge('green'),
            Text::make(__('admin.field.title'), 'title')
                ->sortable()
                ->updateOnPreview()
                ->required(),
            Text::make(__('admin.field.title_short'), 'title_short')
                ->sortable()
                ->updateOnPreview()
                ->required(),
            Text::make(__('admin.field.slug'), 'slug')
                ->sortable()
                ->updateOnPreview()
                ->required(),
            Switcher::make(__('admin.field.is_alien'), 'is_alien')
                ->updateOnPreview()
        ];
    }

    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function formFields(): iterable
    {
        return [];
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
            IndexPage::class,
            ClubFormPage::class,
            DetailPage::class
        ];
    }

    /**
     * @param Club $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [
            'title' => ['required', 'string', 'min:2'],
            'slug' => ['required', 'string', 'unique:clubs,slug,' . $item->id],
            'title_short' => ['required', 'string', 'min:2'],
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
            Text::make('title_short'),
            Text::make('city.title'),
            Text::make('gender.title'),
            Text::make('age.title'),
        ];
    }

    // поля в импорте
    protected function importFields(): iterable
    {
        return [
            ID::make(),
            Text::make('title'),
            Text::make('title_short'),
            Text::make('city.title'),
            Text::make('gender.title'),
            Text::make('age.title'),
        ];
    }
}
