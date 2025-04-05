<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Pages\Crud;

use MoonShine\Contracts\Core\CrudPageContract;
use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\Laravel\Resources\CrudResource;
use MoonShine\UI\Components\Layout\Div;
use MoonShine\UI\Components\Modal;

/**
 * @extends Page<CrudResource>
 */
abstract class CrudPage extends Page implements CrudPageContract
{
    /**
     * @return list<ComponentContract>
     */
    protected function fields(): iterable
    {
        return [];
    }

    public function getFields(): FieldsContract
    {
        return $this->getCore()->getFieldsCollection($this->fields());
    }

    public function getEmptyModals(): array
    {
        $components = [];

        if ($this->getResource()->isEditInModal()) {
            $components[] = Modal::make(
                __('moonshine::ui.edit'),
                components: [
                    Div::make()->customAttributes(['id' => 'resource-edit-modal']),
                ]
            )
                ->wide()
                ->name('resource-edit-modal');
        }

        if ($this->getResource()->isDetailInModal()) {
            $components[] = Modal::make(
                __('moonshine::ui.show'),
                components: [
                    Div::make()->customAttributes(['id' => 'resource-detail-modal']),
                ]
            )
                ->wide()
                ->name('resource-detail-modal');
        }

        return $components;
    }
}
