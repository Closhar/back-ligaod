<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use App\MoonShine\Resources\ArenaResource;
use App\MoonShine\Resources\SportPropertyResource;
use App\MoonShine\Resources\SportResource;
use MoonShine\ColorManager\ColorManager;
use MoonShine\Contracts\ColorManager\ColorManagerContract;
use MoonShine\Laravel\Layouts\CompactLayout;
use MoonShine\MenuManager\MenuGroup;
use MoonShine\MenuManager\MenuItem;
use MoonShine\UI\Components\{Layout\Layout};
use App\MoonShine\Resources\CityResource;
use App\MoonShine\Resources\GenderResource;
use App\MoonShine\Resources\AgeResource;
use App\MoonShine\Resources\ClubResource;
use App\MoonShine\Resources\CompetitionResource;
use App\MoonShine\Resources\ArticleResource;
use App\MoonShine\Resources\EventResource;
use App\MoonShine\Resources\ArenaableResource;
use App\MoonShine\Resources\GalleryResource;
use App\MoonShine\Resources\ImageResource;
use App\MoonShine\Resources\VideoResource;
use App\MoonShine\Resources\ParamResource;
use App\MoonShine\Resources\PicParamResource;
use App\MoonShine\Resources\PageResource;

final class MoonShineLayout extends CompactLayout
{
    protected function assets(): array
    {
        return [
            ...parent::assets(),
        ];
    }

    protected function menu(): array
    {
        return [
            //...parent::menu(),
            MenuGroup::make(static fn() => __('admin.menu.system'), [
                MenuItem::make(__('admin.table.params'), ParamResource::class),
                MenuItem::make(__('admin.table.pic_params'), PicParamResource::class),
                MenuItem::make(__('admin.table.pages'), PageResource::class),
            ]),
            MenuItem::make(__('admin.table.genders'), GenderResource::class)->icon('icons8:gender'),
            MenuItem::make(__('admin.table.ages'), AgeResource::class)->icon('fluent:agents-20-regular'),
            MenuItem::make(__('admin.table.sport_properties'), SportPropertyResource::class)->icon('codicon:symbol-property'),
            MenuItem::make(__('admin.table.sports'), SportResource::class)->icon('ic:baseline-sports-kabaddi'),
            MenuItem::make(__('admin.table.cities'), CityResource::class)->icon('icon-park-solid:city'),
            MenuItem::make(__('admin.table.clubs'), ClubResource::class)->icon('entypo:sports-club'),
            MenuItem::make(__('admin.table.arenas'), ArenaResource::class)->icon('mdi:arena-outline'),
            MenuItem::make(__('admin.table.competitions'), CompetitionResource::class)->icon('solar:cup-bold'),
            MenuItem::make(__('admin.table.events'), EventResource::class)->icon('material-symbols:event-rounded'),
            MenuItem::make(__('admin.table.articles'), ArticleResource::class)->icon('material-symbols:article-rounded'),
            MenuItem::make(__('admin.table.galleries'), GalleryResource::class)->icon('solar:gallery-wide-linear'),
            MenuItem::make(__('admin.table.videos'), VideoResource::class)->icon('fluent:video-28-regular'),
        ];
    }

    /**
     * @param ColorManager $colorManager
     */
    protected function colors(ColorManagerContract $colorManager): void
    {
        parent::colors($colorManager);

        // $colorManager->primary('#00000');
    }

    public function build(): Layout
    {
        return parent::build();
    }
}
