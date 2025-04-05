<?php

declare(strict_types=1);

namespace App\Providers;

use App\MoonShine\Pages\SportPropertyTreePage;
use App\MoonShine\Resources\MoonShineUserResource;
use App\MoonShine\Resources\MoonShineUserRoleResource;
use App\MoonShine\Resources\SportPropertyResource;
use App\MoonShine\Resources\SportResource;
use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\ConfiguratorContract;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\DependencyInjection\MoonShine;
use MoonShine\Laravel\DependencyInjection\MoonShineConfigurator;
use App\MoonShine\Resources\CityResource;
use App\MoonShine\Resources\GenderResource;
use App\MoonShine\Resources\AgeResource;
use App\MoonShine\Resources\ClubResource;
use App\MoonShine\Resources\CompetitionResource;
use App\MoonShine\Resources\ArenaResource;
use App\MoonShine\Resources\ArticleResource;
use App\MoonShine\Resources\EventResource;
use App\MoonShine\Resources\ArenaableResource;
use App\MoonShine\Pages\SportFormPage;
use App\MoonShine\Pages\ClubFormPage;
use App\MoonShine\Pages\ArenaFormPage;
use App\MoonShine\Pages\CompetitionFormPage;
use App\MoonShine\Resources\GalleryResource;
use App\MoonShine\Resources\ImageResource;
use App\MoonShine\Resources\VideoResource;
use App\MoonShine\Resources\ParamResource;
use App\MoonShine\Resources\PicParamResource;
use App\MoonShine\Resources\PageResource;

class MoonShineServiceProvider extends ServiceProvider
{
    /**
     * @param MoonShine $core
     * @param MoonShineConfigurator $config
     *
     */
    public function boot(CoreContract $core, ConfiguratorContract $config): void
    {
        $config->authEnable();

        $core
            ->resources([
                MoonShineUserResource::class,
                MoonShineUserRoleResource::class,
                SportPropertyResource::class,
                SportResource::class,
                CityResource::class,
                GenderResource::class,
                AgeResource::class,
                ClubResource::class,
                CompetitionResource::class,
                ArenaResource::class,
                ArticleResource::class,
                EventResource::class,
                ArenaableResource::class,
                GalleryResource::class,
                ImageResource::class,
                VideoResource::class,
                ParamResource::class,
                PicParamResource::class,
                PageResource::class,
            ])
            ->pages([
                ...$config->getPages(),
                SportPropertyTreePage::class,
                SportFormPage::class,
                ClubFormPage::class,
                ArenaFormPage::class,
                CompetitionFormPage::class,
            ]);
    }
}
