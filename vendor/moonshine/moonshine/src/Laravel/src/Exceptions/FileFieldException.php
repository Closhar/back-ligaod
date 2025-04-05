<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Exceptions;

use MoonShine\Core\Exceptions\MoonShineException;

final class FileFieldException extends MoonShineException
{
    public static function extensionNotAllowed(string $extension): self
    {
        return new self("$extension not allowed");
    }

    public static function failedSave(): self
    {
        return new self('Failed to save file, check your permissions');
    }
}
