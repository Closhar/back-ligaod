<?php

declare(strict_types=1);

namespace App\MoonShine\Components;

use Closure;
use MoonShine\UI\Components\MoonShineComponent;

/**
 * @method static static make()
 */
final class Dropzone extends MoonShineComponent
{
    protected string $view = 'components.dropzone';

    public function __construct()
    {
        parent::__construct();

        //
    }

    /*
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        return [];
    }
}
