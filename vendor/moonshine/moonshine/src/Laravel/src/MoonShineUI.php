<?php

declare(strict_types=1);

namespace MoonShine\Laravel;

use MoonShine\Support\Enums\ToastType;

class MoonShineUI
{
    public static function toast(string $message, ToastType $type = ToastType::INFO, null|int|false $duration = null): void
    {
        session()->flash('toast', [
            'type' => $type->value,
            'message' => $message,
            'duration' => $duration === false ? -1 : $duration,
        ]);
    }
}
