<?php

declare(strict_types=1);

namespace MoonShine\Contracts\AssetManager;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use MoonShine\Contracts\Core\StatefulContract;

interface AssetManagerContract extends Htmlable, StatefulContract
{
    public function getAsset(string $path): string;

    public function getAssets(): AssetElementsContract;

    /** @param Closure(array $assets): array $callback */
    public function modifyAssets(Closure $callback): static;

    public function add(AssetElementContract|array $assets): static;

    public function prepend(AssetElementContract|array $assets): static;

    public function append(AssetElementContract|array $assets): static;
}
