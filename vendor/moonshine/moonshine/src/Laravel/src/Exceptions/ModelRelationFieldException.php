<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Exceptions;

use MoonShine\Core\Exceptions\MoonShineException;

final class ModelRelationFieldException extends MoonShineException
{
    public static function notFound(): self
    {
        return new self('Field not found on page');
    }

    public static function relationRequired(): self
    {
        return new self('Relation is required');
    }

    public static function morphTypesRequired(): self
    {
        return new self('Morph types is required');
    }

    public static function parentResourceRequired(): self
    {
        return new self('Parent resource is required');
    }

    public static function hasFieldsContractRequired(): self
    {
        return new self('Field is not a HasFieldsContract');
    }
}
