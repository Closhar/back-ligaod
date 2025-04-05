<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Components;

use Illuminate\Http\RedirectResponse;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Contracts\Core\ResourceContract;
use MoonShine\Contracts\UI\HasAsyncContract;
use MoonShine\Core\Traits\NowOn;
use MoonShine\Laravel\Resources\CrudResource;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\DTOs\AsyncCallback;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\UI\Components\AbstractWithComponents;
use MoonShine\UI\Traits\HasAsync;
use Throwable;

class Fragment extends AbstractWithComponents implements HasAsyncContract
{
    use HasAsync;
    use NowOn;

    protected string $view = 'moonshine::components.fragment';

    public function __construct(iterable $components = [])
    {
        parent::__construct($components);

        $this->async(static function (Fragment $fragment): RedirectResponse|string {
            $page = $fragment->getNowOnPage() ?? moonshineRequest()->findPage();
            $resource = $fragment->getNowOnResource() ?? moonshineRequest()->getResource();

            if (\is_null($page) && \is_null($resource)) {
                return moonshineRequest()->fullUrlWithQuery([
                    '_fragment-load' => $fragment->getName(),
                ]);
            }

            return toPage(
                page: $fragment->getNowOnPage() ?? moonshineRequest()->getPage(),
                resource: $fragment->getNowOnResource() ?? moonshineRequest()->getResource(),
                params: $fragment->getNowOnQueryParams(),
                fragment: $fragment->getName()
            );
        });
    }

    /**
     * @param class-string<ResourceContract>|ResourceContract|null $resource
     * @param class-string<PageContract>|PageContract|null $page
     * @throws Throwable
     */
    public function updateWith(
        array $params = [],
        string|ResourceContract|null $resource = null,
        string|PageContract|null $page = null,
        string|array|null $events = null,
        ?AsyncCallback $callback = null,
    ): static {
        /** @var ?CrudResource $resource */
        $resource ??= moonshineRequest()->getResource();
        $page ??= moonshineRequest()->getPage();

        $this->asyncEvents = $events;
        $this->asyncCallback = $callback;

        return $this->nowOn(
            $page,
            $resource,
            $params
        );
    }

    public function withQueryParams(): static
    {
        return $this->customAttributes(
            AlpineJs::asyncWithQueryParamsAttributes()
        );
    }

    /**
     * @param  array<string, string> $selectors
     */
    public function withSelectorsParams(array $selectors): static
    {
        return $this->customAttributes(
            AlpineJs::asyncSelectorsParamsAttributes($selectors)
        );
    }

    protected function prepareBeforeRender(): void
    {
        parent::prepareBeforeRender();

        $this->xDataMethod('fragment', $this->getAsyncUrl());
        $this->customAttributes([
            AlpineJs::eventBlade(JsEvent::FRAGMENT_UPDATED, $this->getName())
            => 'fragmentUpdate(`' . $this->getAsyncEvents() . '`,' . json_encode($this->getAsyncCallback()) . ')',
        ]);
    }
}
