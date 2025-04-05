<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Components;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;
use Illuminate\Contracts\Support\Arrayable;
use MoonShine\Laravel\TypeCasts\PaginatorCaster;
use MoonShine\UI\Components\MoonShineComponent;

/**
 * @method static static make(PaginatorContract|CursorPaginator $paginator)
 */
final class Paginator extends MoonShineComponent
{
    protected string $view = 'moonshine::components.pagination';

    public function getTranslates(): array
    {
        return $this->getCore()->getTranslator()->get('moonshine::pagination');
    }

    public function __construct(
        private readonly PaginatorContract|CursorPaginator $paginator
    ) {
        parent::__construct();
    }

    protected function viewData(): array
    {
        /**
         * @phpstan-var (PaginatorContract|CursorPaginator)&Arrayable $data
         */
        $data = $this->paginator;

        $paginator = (new PaginatorCaster(
            $data->appends(
                $this->getCore()->getRequest()->getExcept('page')
            )->toArray(),
            $data->items()
        ))->cast();

        return $paginator->toArray();
    }
}
