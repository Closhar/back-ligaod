<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\Sport;

use App\MoonShine\Fields\SimpleIcon;
use App\MoonShine\Pages\SportFormPage;
use App\Traits\TranslMorphManyTrait;
use Illuminate\Support\Collection;
use MoonShine\CKEditor\Fields\CKEditor;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Fields\Relationships\BelongsToMany;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Fields\Relationships\MorphMany;
use MoonShine\Laravel\Fields\Relationships\MorphToMany;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Quill\Fields\Quill;
use MoonShine\Support\Enums\ClickAction;
use MoonShine\Support\Enums\PageType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Badge;
use MoonShine\UI\Components\Icon;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Divider;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Link;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\ID;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Text;
use PhpParser\Node\Stmt\Property;
use Str;

/**
 * @extends ModelResource<Sport>
 */
class SportResource extends ModelResource implements HasImportExportContract
{
    use TranslMorphManyTrait, ImportExportConcern;

    protected string $model = Sport::class;

    public string $column = 'title';

    protected bool $createInModal = false;

    protected bool $editInModal = false;

    protected bool $detailInModal = true;

//    protected array $with = ['arenas'];

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
        $this->title = __('admin.table.sports');
    }

    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('', 'icon')
                ->changePreview(fn(string $data) => '<iconify-icon icon="' . $data . '" style="font-size: 30px;"></iconify-icon>'),
            Text::make(__('admin.field.title'), 'title')
                ->sortable()
                ->updateOnPreview()
                ->required(),
            Text::make(__('admin.field.title_short'), 'title_short')
                ->sortable()
                ->updateOnPreview()
                ->required(),
            Text::make(__('admin.field.icon') . " <a href='https://icon-sets.iconify.design/' target='_blank'>Iconify</a>", 'icon')
                ->updateOnPreview(),
            Text::make(__('admin.field.vin'), 'vin')
                ->updateOnPreview(),
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
            SportFormPage::class,
            DetailPage::class
        ];
    }

    /**
     * @param Sport $item
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
        return ['title', 'annotation', 'icon'];
    }


    // поля в экспорте
    protected function exportFields(): iterable
    {
        return [
            ID::make(),
            Text::make('title'),
            Text::make('annotation'),
            Text::make('icon'),
        ];
    }

    // поля в импорте
    protected function importFields(): iterable
    {
        return [
            ID::make(),
            Text::make('title'),
            Text::make('annotation'),
            Text::make('icon'),
        ];
    }
}
