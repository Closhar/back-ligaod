<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Http\Controllers;

use MoonShine\Core\Exceptions\ResourceException;
use MoonShine\Laravel\Contracts\Resource\HasHandlersContract;
use MoonShine\Laravel\MoonShineRequest;
use Symfony\Component\HttpFoundation\Response;

final class HandlerController extends MoonShineController
{
    public function __invoke(string $resourceUri, string $handlerUri, MoonShineRequest $request): Response
    {
        $resource = $request->getResource();

        if (! $resource) {
            throw ResourceException::required();
        }

        if (! $resource instanceof HasHandlersContract) {
            throw ResourceException::handlerContractRequired();
        }

        $handler = $resource
            ->getHandlers()
            ->findByUri($handlerUri);

        if (! \is_null($handler)) {
            return $handler->handle();
        }

        return redirect(
            $request->getResource()?->getUrl() ?? moonshineRouter()->getEndpoints()->home()
        );
    }
}
