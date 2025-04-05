<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Contracts\Notifications;

use Illuminate\Contracts\Support\Arrayable;
use MoonShine\Contracts\UI\ComponentAttributesBagContract;

/**
 * @mixin Arrayable
 */
interface NotificationButtonContract
{
    public function getLink(): string;

    public function getLabel(): string;

    public function getAttributes(): ComponentAttributesBagContract;
}
