<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Pages;

use Closure;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\View\View;
use MoonShine\Contracts\Core\CrudResourceContract;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Contracts\Core\ResourceContract;
use MoonShine\Core\Pages\Page as CorePage;
use MoonShine\Laravel\Contracts\WithResponseModifierContract;
use MoonShine\Laravel\DependencyInjection\MoonShine;
use MoonShine\Laravel\Http\Responses\MoonShineJsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @template TResource of CrudResourceContract|null
 * @extends CorePage<MoonShine, TResource>
 */
abstract class Page extends CorePage implements WithResponseModifierContract
{
    protected function prepareBeforeRender(): void
    {
        $withoutQuery = trim(parse_url($this->getUrl(), PHP_URL_PATH), '/');
        $currentPath = trim(moonshine()->getRequest()->getPath(), '/');

        if ($this->isCheckUrl() && ! str_contains($currentPath, $withoutQuery)) {
            oops404();
        }

        $this->simulateRoute();
    }

    public function simulateRoute(?PageContract $page = null, ?ResourceContract $resource = null): static
    {
        $targetPage = $page ?? $this;
        $targetResource = $resource ?? $targetPage->getResource();

        request()
            ->route()
            ?->setParameter('pageUri', $targetPage->getUriKey());

        if (! \is_null($targetResource)) {
            $this->setResource($targetResource);

            request()
                ->route()
                ?->setParameter('resourceUri', $targetResource->getUriKey());
        }

        return $this;
    }

    protected function prepareRender(Renderable|Closure|string $view): Renderable|Closure|string
    {
        /** @var View $view */
        return $view->fragmentIf(
            moonshineRequest()->isFragmentLoad(),
            moonshineRequest()->getFragmentLoad(),
        );
    }

    public function nowOn(): static
    {
        return $this;
    }

    public function isResponseModified(): bool
    {
        return $this->modifyResponse() instanceof Response;
    }

    public function getModifiedResponse(): ?Response
    {
        return $this->isResponseModified() ? $this->modifyResponse() : null;
    }

    protected function modifyResponse(): ?Response
    {
        $fragments = moonshineRequest()->getFragmentLoad();

        if ($fragments === null) {
            return null;
        }

        if (str_contains($fragments, ',')) {
            $fragments = explode(',', $fragments);
            $data = [];
            foreach ($fragments as $fragment) {
                [$selector, $name] = explode(':', $fragment);
                /** @var View $view */
                $view = $this->renderView();
                $data[$selector] = $view->fragment($name);
            }

            return MoonShineJsonResponse::make()->html($data);
        }

        return null;
    }
}
