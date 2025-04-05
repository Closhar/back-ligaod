<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Commands;

use Illuminate\Contracts\Filesystem\FileNotFoundException;

use function Laravel\Prompts\{suggest};

use MoonShine\Laravel\MoonShineAuth;
use MoonShine\Laravel\Support\StubsPath;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Finder\Finder;

#[AsCommand(name: 'moonshine:policy')]
class MakePolicyCommand extends MoonShineCommand
{
    protected $signature = 'moonshine:policy {className?}';

    protected $description = 'Create policy for Model';

    /**
     * @throws FileNotFoundException
     */
    public function handle(): int
    {
        $modelPath = is_dir(app_path('Models')) ? app_path('Models') : app_path();

        $className = $this->argument('className') ?? suggest(
            label: 'Model',
            options: collect((new Finder())->files()->depth(0)->in($modelPath))
                ->map(static fn ($file) => $file->getBasename('.php'))
                ->values()
                ->all(),
            required: true,
        );

        $className = str($className)
            ->ucfirst()
            ->remove('policy', false)
            ->value();

        $model = $this->qualifyModel($className);
        $className = class_basename($model) . 'Policy';

        $stubsPath = new StubsPath($className, 'php');

        $stubsPath->prependDir(
            'app/Policies',
        )->prependNamespace(
            'App\\Policies',
        );

        $this->makeDir($stubsPath->dir);

        $this->copyStub('Policy', $stubsPath->getPath(), [
            'DummyClass' => $stubsPath->name,
            '{model-namespace}' => $model,
            '{model}' => class_basename($model),
            '{user-model-namespace}' => MoonShineAuth::getModel()::class,
            '{user-model}' => class_basename(MoonShineAuth::getModel()),
        ]);

        $this->wasCreatedInfo($stubsPath);

        return self::SUCCESS;
    }
}
