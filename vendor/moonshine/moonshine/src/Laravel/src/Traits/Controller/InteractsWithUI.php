<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Traits\Controller;

use MoonShine\Laravel\Contracts\Notifications\NotificationButtonContract;
use MoonShine\Laravel\MoonShineUI;
use MoonShine\Support\Enums\Color;
use MoonShine\Support\Enums\ToastType;

trait InteractsWithUI
{
    protected function toast(string $message, ToastType $type = ToastType::INFO): void
    {
        MoonShineUI::toast($message, $type);
    }

    /**
     * @param  array<int|string>  $ids
     */
    protected function notification(
        string $message,
        ?NotificationButtonContract $buttons = null,
        array $ids = [],
        string|Color|null $color = null
    ): void {
        $this->notification->notify(
            $message,
            $buttons,
            $ids,
            $color
        );
    }
}
