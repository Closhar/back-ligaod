<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Commands;

use Illuminate\Filesystem\Filesystem;

use function Laravel\Prompts\info;

use MoonShine\Contracts\Core\DependencyInjection\OptimizerCollectionContract;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moonshine:optimize-clear')]
class OptimizeClearCommand extends MoonShineCommand
{
    protected $signature = 'moonshine:optimize-clear';

    protected $description = 'Remove the cached bootstrap files';

    public function handle(OptimizerCollectionContract $autoload, Filesystem $files): int
    {
        $this->components->info('Clearing cached moonshine file.');

        if ($files->exists($file = $autoload->getCachePath())) {
            $files->delete($file);

            info('MoonShine\'s cache has been cleared.');
        }

        return self::SUCCESS;
    }
}
