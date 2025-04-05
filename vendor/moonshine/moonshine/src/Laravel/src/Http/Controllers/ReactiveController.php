<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Http\Controllers;

use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\FormBuilderContract;
use MoonShine\Contracts\UI\HasReactivityContract;
use MoonShine\Laravel\MoonShineRequest;
use MoonShine\Laravel\TypeCasts\ModelDataWrapper;
use MoonShine\UI\Components\FieldsGroup;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ReactiveController extends MoonShineController
{
    public function __invoke(MoonShineRequest $request): JsonResponse
    {
        $page = $request->getPage();

        /** @var ?FormBuilderContract $form */
        $form = $page->getComponents()->findForm(
            $request->getComponentName()
        );

        if (\is_null($form)) {
            return $this->json();
        }

        $fields = $form
            ->getPreparedFields()
            ->onlyFields()
            ->reactiveFields();

        $casted = null;
        $except = [];

        $values = $request->collect('values')->map(function (mixed $value, string $column) use ($fields, &$casted, &$except) {
            $field = $fields->findByColumn($column);

            if (! $field instanceof HasReactivityContract) {
                return $value;
            }

            return $field->prepareReactivityValue($value, $casted, $except);
        });

        $fields->fill(
            $values->toArray(),
            $casted ? new ModelDataWrapper($casted->forceFill($values->except($except)->toArray())) : null
        );

        foreach ($fields as $field) {
            $fields = $field->formName($form->getName())->getReactiveCallback(
                $fields,
                data_get($values, $field->getColumn()),
                $values->toArray(),
            );
        }

        $values = $fields
            ->mapWithKeys(static fn (FieldContract $field): array => [$field->getColumn() => $field->getReactiveValue()]);

        $fields = $fields->mapWithKeys(
            static fn (FieldContract $field): array => [$field->getColumn() => (string) FieldsGroup::make([$field])->render()]
        );

        return $this->json(data: [
            'form' => $form,
            'fields' => $fields,
            'values' => $values,
        ]);
    }
}
