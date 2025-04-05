<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Buttons;

use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Laravel\Enums\Ability;
use MoonShine\Laravel\Enums\Action;
use MoonShine\Laravel\Resources\CrudResource;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\UI\Components\ActionButton;

final class EditButton
{
    public static function for(
        CrudResource $resource,
        ?string $componentName = null,
        bool $isAsync = true,
        string $modalName = 'resource-edit-modal',
    ): ActionButtonContract {
        if (! $resource->getFormPage()) {
            return ActionButton::emptyHidden();
        }

        $action = static fn (mixed $item, ?DataWrapperContract $data): string => $resource->getFormPageUrl($data?->getKey());

        // required to create field entities and load assets
        if (! $resource->isCreateInModal() && $resource->isEditInModal()) {
            $resource->getFormFields();
        }

        if ($resource->isEditInModal() && ! $resource->isDetailPage()) {
            $action = static fn (mixed $item, ?DataWrapperContract $data): string => $resource->getFormPageUrl(
                $data?->getKey(),
                array_filter([
                    '_component_name' => $componentName ?? $resource->getListComponentName(),
                    '_async_form' => $isAsync,
                    'page' => $isAsync ? request()->getScalar('page') : null,
                    'sort' => $isAsync ? request()->getScalar('sort') : null,
                ]),
                fragment: 'crud-form'
            );
        }

        return ActionButton::make(
            '',
            url: $action
        )
            ->name('resource-edit-button')
            ->when(
                $resource->isEditInModal() && ! $resource->isDetailPage(),
                static fn (ActionButtonContract $button): ActionButtonContract => $button->async(
                    selector: "#$modalName",
                    events: [AlpineJs::event(JsEvent::MODAL_TOGGLED, $modalName)]
                )
            )
                ->primary()
                ->icon('pencil')
                ->canSee(
                    static fn (mixed $item, ?DataWrapperContract $data): bool => $data?->getKey()
                        && $resource->hasAction(Action::UPDATE)
                        && $resource->setItem($item)->can(Ability::UPDATE)
                )
                ->class('js-edit-button')
                ->showInLine();
    }
}
