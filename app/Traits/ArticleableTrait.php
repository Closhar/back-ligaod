<?php

namespace App\Traits;

use App\MoonShine\Resources\ArticleResource;
use MoonShine\Laravel\Fields\Relationships\MorphToMany;

trait ArticleableTrait
{
    public function ArticleableTrait($title = ''): MorphToMany
    {
        return MorphToMany::make($title, 'articles', resource: ArticleResource::class)
            //->selectMode()
            //            ->onlyCount()
            ->relatedLink();
    }
}
