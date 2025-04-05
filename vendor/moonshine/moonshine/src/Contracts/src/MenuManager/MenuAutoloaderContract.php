<?php

declare(strict_types=1);

namespace MoonShine\Contracts\MenuManager;

interface MenuAutoloaderContract
{
    public function toArray(): array;

    public function resolve(?array $cached = null): array;
}
