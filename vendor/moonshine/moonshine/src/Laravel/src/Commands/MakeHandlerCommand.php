<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moonshine:handler')]
class MakeHandlerCommand extends MoonShineCommand
{
    protected $signature = 'moonshine:handler {className?} {--base-dir=} {--base-namespace=}';

    protected $description = 'Create handler class';

    public function handle(): int
    {
        $this->fastCreateFromStub('Handler', 'Handlers');

        return self::SUCCESS;
    }
}
