<?php

declare(strict_types=1);

namespace MoonShine\Laravel\Pages;

use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Core\Attributes\Layout;
use MoonShine\Laravel\Forms\LoginForm;
use MoonShine\Laravel\Layouts\LoginLayout;
use MoonShine\MenuManager\Attributes\SkipMenu;

#[SkipMenu]
#[Layout(LoginLayout::class)]
/**
 * @extends Page<null>
 */
class LoginPage extends Page
{
    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        return [
            moonshineConfig()->getForm('login', LoginForm::class),
        ];
    }
}
