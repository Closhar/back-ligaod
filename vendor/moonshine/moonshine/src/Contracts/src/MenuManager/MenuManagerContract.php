<?php

declare(strict_types=1);

namespace MoonShine\Contracts\MenuManager;

use Closure;
use MoonShine\Contracts\Core\StatefulContract;

interface MenuManagerContract extends StatefulContract
{
    public function add(array|MenuElementContract $data): static;

    public function remove(Closure $condition): static;

    public function addBefore(Closure $before, array|MenuElementContract|Closure $data): static;

    public function addAfter(Closure $after, array|MenuElementContract|Closure $data): static;

    public function topMode(?Closure $condition = null): self;

    public function all(?iterable $items = null): MenuElementsContract;
}
