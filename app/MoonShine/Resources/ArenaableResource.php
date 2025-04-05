<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\Arenaable;

use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Fields\Hidden;
use MoonShine\UI\Fields\ID;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<Arenaable>
 */
class ArenaableResource extends ModelResource
{
    protected string $model = Arenaable::class;

    public string $column = 'arenaable_id';

    protected bool $createInModal = false;

    protected bool $editInModal = false;

    protected bool $detailInModal = false;

    // фиксированая шапка
    protected bool $stickyTable = true;


    function __construct()
    {
        $this->title = __('admin.table.arenaables');
    }

    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            Hidden::make('id'),
            Text::make('', 'icon')
                ->previewMode()
                ->changePreview(fn(?string $data) => $data ? '<iconify-icon icon="' . $data . '" style="font-size: 30px;"></iconify-icon>' : ' '),
            Text::make('', 'title'),
//            Hidden::make('localizable_id'),
//            Hidden::make('localizable_type'),
        ];
    }

    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function formFields(): iterable
    {
        return [
//            Hidden::make('localizable_id'),
//            Hidden::make('localizable_type'),

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
     * @param Arenaable $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [];
    }
}
