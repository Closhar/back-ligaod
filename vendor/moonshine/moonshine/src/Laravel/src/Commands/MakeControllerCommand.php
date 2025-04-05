<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moonshine:controller')]
class MakeControllerCommand extends MoonShineCommand
{
    protected $signature = 'moonshine:controller {className?} {--base-dir=} {--base-namespace=}';

    protected $description = 'Create controller';

    public function handle(): int
    {
        $this->fastCreateFromStub('Controller', 'Controllers');

        return self::SUCCESS;
    }
}
