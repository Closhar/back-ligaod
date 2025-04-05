<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Exceptions;

use MoonShine\Core\Exceptions\MoonShineException;
use MoonShine\Laravel\Enums\Ability;

final class CrudResourceException extends MoonShineException
{
    public static function resourceOrFieldRequired(): self
    {
        return new self('Resource or Field is required');
    }

    public static function abilityNotFound(Ability $ability): self
    {
        return new self("ability '$ability->value' not found in the system");
    }

    public static function relationNotFound(string $relationName): self
    {
        return new self("Relation $relationName not found for current resource");
    }
}
