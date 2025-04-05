<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\MoonShine\Fields\HtmlField;
use App\MoonShine\Pages\ArenaFormPage;
use App\MoonShine\Pages\CompetitionFormPage;
use App\Traits\TranslMorphManyTrait;
use App\Models\Competition;

use Closure;
use MoonShine\CKEditor\Fields\CKEditor;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\MorphToMany;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Quill\Fields\Quill;
use MoonShine\Support\Enums\ClickAction;
use MoonShine\Support\Enums\PageType;
use MoonShine\Support\ListOf;
use MoonShine\Trix\Fields\Trix;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Divider;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Layout\Html;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends ModelResource<Competition>
 */
class CompetitionResource extends ModelResource implements HasImportExportContract
{
    use TranslMorphManyTrait, ImportExportConcern;

    protected string $model = Competition::class;

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
        $this->title = __('admin.table.competitions');
    }

    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Image::make(__('admin.field.image'), 'image'),
            Text::make(__('admin.field.title'), 'title')
                ->sortable()
                ->updateOnPreview()
                ->required(),
            Text::make(__('admin.field.title_short'), 'title_short')
                ->sortable()
                ->updateOnPreview()
                ->required(),
            BelongsTo::make(__('admin.field.sport'), 'sport', SportResource::class)
                ->badge('secondary'),
            Date::make(__('admin.field.date_from'), 'date_from')
                ->sortable()
                ->updateOnPreview(),
            Date::make(__('admin.field.date_to'), 'date_to')
                ->sortable()
                ->updateOnPreview(),
            //$this->TranslMorphMany()
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
            Image::make(__('admin.field.image'), 'image')
                ->removable(),
            Text::make(__('admin.field.title'), 'title')
                ->required(),
            Date::make(__('admin.field.date_from'), 'date_from'),
            Date::make(__('admin.field.date_to'), 'date_to'),
            Text::make(__('admin.field.title_short'), 'title_short'),
            HtmlField::make(__('admin.field.about_competition'), 'about'),
        ];
    }

    protected function pages(): array
    {
        return [
            IndexPage::class,
            CompetitionFormPage::class,
            DetailPage::class
        ];
    }

    /**
     * @param Competition $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [
            'title' => ['required', 'string', 'min:2'],
        ];
    }

    // кнопки действия на index
//    protected function indexButtons(): ListOf
//    {
//        // убираем detail кнопку
//        return parent::indexButtons()->except(fn(ActionButton $btn) => $btn->getName() === 'resource-detail-button');
//    }

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
//    protected function tdAttributes(): Closure
//    {
//        return fn(?DataWrapperContract $data, int $row, int $cell) => [
//            // (индексы начинаются с 0)
//            'width' => match ($cell) {
//                0 => '50px',
//                1 => '100px',
//                3 => '200px',
//                4 => '150px',
//                5 => '150px',
//                6 => '200px',
//                default => 'auto', // Остальные столбцы
//            },
//        ];
//    }

    // поля в экспорте
    protected function exportFields(): iterable
    {
        return [
            ID::make(),
            Text::make('title'),
            Text::make('title_short'),
            Text::make('about'),
        ];
    }

    // поля в импорте
    protected function importFields(): iterable
    {
        return [
            ID::make(),
            Text::make('title'),
            Text::make('title_short'),
            Text::make('about'),
        ];
    }

}
