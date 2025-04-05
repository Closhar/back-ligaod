<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moonshine:type-cast')]
class MakeTypeCastCommand extends MoonShineCommand
{
    protected $signature = 'moonshine:type-cast {className?} {--base-dir=} {--base-namespace=}';

    protected $description = 'Create type cast class';

    public function handle(): int
    {
        $this->fastCreateFromStub('TypeCast', 'TypeCasts');

        return self::SUCCESS;
    }
}
