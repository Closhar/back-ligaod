<?php

declare(strict_types=1);

namespace MoonShine\Contracts\Core\DependencyInjection;

use MoonShine\Contracts\UI\ApplyContract;
use MoonShine\Contracts\UI\FormElementContract;

/**
 * @template-covariant I
 * @mixin I
 */
interface AppliesRegisterContract
{
    public function type(string $type): static;

    /**
     * @param  class-string  $for
     */
    public function for(string $for): static;

    /**
     * @return class-string
     */
    public function getFor(): string;

    /**
     * @param  class-string  $for
     */
    public function defaultFor(string $for): static;

    /**
     * @return class-string
     */
    public function getDefaultFor(): string;

    /**
     * @param  ?class-string  $for
     */
    public function findByField(
        FormElementContract $field,
        string $type = 'fields',
        ?string $for = null
    ): ?ApplyContract;

    /**
     * @param  class-string<FormElementContract>  $fieldClass
     * @param  class-string<ApplyContract>  $applyClass
     */
    public function add(string $fieldClass, string $applyClass): static;

    /**
     * @param  array<class-string<FormElementContract>, class-string<ApplyContract>>  $data
     */
    public function push(array $data): static;

    /**
     * @param  class-string<FormElementContract>  $fieldClass
     */
    public function get(string $fieldClass, ?ApplyContract $default = null): ?ApplyContract;
}
