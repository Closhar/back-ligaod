<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Contracts\Fields;

interface HasOutsideSwitcherContract
{
    public function isOutsideComponent(): bool;
}
