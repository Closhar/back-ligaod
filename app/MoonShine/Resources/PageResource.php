<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Traits\TranslMorphManyTrait;
use App\Models\Page;

use MoonShine\CKEditor\Fields\CKEditor;
use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Fields\ID;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<Page>
 */
class PageResource extends ModelResource implements HasImportExportContract
{
    protected string $model = Page::class;

    protected string $title = 'title';

    use TranslMorphManyTrait, ImportExportConcern;

    public string $column = 'title';

    protected bool $createInModal = false;

    protected bool $editInModal = false;

    protected bool $detailInModal = false;

    //protected array $with = ['sex'];

    // записей на страницу в таблице
    protected int $itemsPerPage = 50;

    // выбор отображаемых полей пользователем
    protected bool $columnSelection = false;

    // фиксированая шапка
    protected bool $stickyTable = true;

    function __construct()
    {
        $this->title = __('admin.table.pages');
    }

    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Image::make(__('admin.field.image'), 'image'),
            Text::make('', 'icon')
                ->changePreview(fn(?string $data) => $data
                    ? '<iconify-icon icon="' . $data . '" style="font-size: 30px;"></iconify-icon>'
                    : ''),
            Text::make(__('admin.field.title'), 'title')
                ->sortable()
                ->updateOnPreview(),
            Text::make(__('admin.field.slug'), 'slug')
                ->sortable()
                ->updateOnPreview(),
            Text::make(__('admin.field.icon'), 'icon')
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
                    Text::make(__('admin.field.slug'), 'slug'),
                ]),
                Text::make(__('admin.field.description'), 'description')
                    ->required(),
                Text::make(__('admin.field.keywords'), 'keywords')
                    ->required(),
                Flex::make([
                    Grid::make([
                        Column::make([
                            Image::make(__('admin.field.image'), 'image')
                                ->removable()
                                ->dir('pages'),
                        ], colSpan: 4),

                        Column::make([
                            Image::make(__('admin.field.image_default'), 'image_default')
                                ->removable()
                                ->dir('pages'),
                        ], colSpan: 4),

                        Column::make([
                            Text::make('', 'icon')
                                ->link('https://icon-sets.iconify.design/', __('admin.field.icon') . ' Iconify', blank: true),
                        ], colSpan: 3),
                        Column::make([
                            Text::make('', 'icon')
                                ->previewMode()
                                ->changePreview(fn(?string $data) => $data ? '<iconify-icon icon="' . $data . '" style="font-size: 50px;"></iconify-icon>' : ' ')
                        ], colSpan: 1),
                    ]),
                ]),
                CKEditor::make(__('admin.field.html'), "html")
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
     * @param Page $item
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
        return ['title', 'slug'];
    }

    // поля в экспорте
    protected function exportFields(): iterable
    {
        return [
            ID::make(),
            Text::make('title'),
            Text::make('slug'),
            Text::make('description'),
            Text::make('keywords'),
            Text::make('image'),
            Text::make('icon'),
        ];
    }

    // поля в импорте
    protected function importFields(): iterable
    {
        return [
            ID::make(),
            Text::make('title'),
            Text::make('slug'),
            Text::make('description'),
            Text::make('keywords'),
            Text::make('image'),
            Text::make('icon'),
        ];
    }

}
