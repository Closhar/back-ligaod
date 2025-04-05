<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Traits\TranslMorphManyTrait;
use App\Models\Gallery;

use Illuminate\Database\Eloquent\Builder;
use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Fields\Field;
use MoonShine\UI\Fields\ID;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<Gallery>
 */
class GalleryResource extends ModelResource implements HasImportExportContract
{
    protected string $model = Gallery::class;

    use TranslMorphManyTrait, ImportExportConcern;

    public string $column = 'title';

    protected bool $createInModal = true;

    protected bool $editInModal = false;

    protected bool $detailInModal = true;

    //protected array $with = ['sex'];

    // записей на страницу в таблице
    protected int $itemsPerPage = 50;

    // выбор отображаемых полей пользователем
    protected bool $columnSelection = false;

    // фиксированая шапка
    protected bool $stickyTable = true;

    function __construct()
    {
        $this->title = __('admin.table.galleries');
    }

    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Image::make(__('admin.field.mainpic'), 'main_image.image'),
            Text::make(__('admin.field.title'), 'title')
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
        if (isset($this->getItem()->id))
            return [
                Tabs::make([
                    Tab::make(__('admin.field.main'), [
                        Box::make([
                            Flex::make([
                                Text::make(__('admin.field.title'), 'title')
                                    ->required(),
                                BelongsTo::make(
                                    __('admin.field.mainpic'),
                                    'main_image',
                                    formatted: fn($item) => "$item->id - $item->title",
                                    resource: ImageResource::class)
                                    ->valuesQuery(fn(Builder $query, Field $field) => $query->where('gallery_id', $this->getItem()->id))
                                    ->withImage('image')
                                    ->nullable(),
                            ]),
                        ]),
                    ]),
                    Tab::make(__('admin.field.gallery'), [
                        Box::make()->customView('components.dropzone', ['gallery_id' => $this->getItem()->id]),
                    ])
                ]),


            ];

        return [
            Box::make([
                Text::make(__('admin.field.title'), 'title')
                    ->required(),
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
     * @param Gallery $item
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
