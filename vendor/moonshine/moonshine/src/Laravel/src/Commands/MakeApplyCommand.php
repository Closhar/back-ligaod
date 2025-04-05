<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moonshine:apply')]
class MakeApplyCommand extends MoonShineCommand
{
    protected $signature = 'moonshine:apply {className?} {--base-dir=} {--base-namespace=}';

    protected $description = 'Create apply for Field';

    public function handle(): int
    {
        $this->fastCreateFromStub('Apply', 'Applies');

        return self::SUCCESS;
    }
}
