<?php

declare(strict_types=1);

namespace App\Forms;

use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Fields\Email;
use MoonShine\UI\Fields\Password;
use MoonShine\UI\Fields\PasswordRepeat;
use MoonShine\UI\Fields\Text;

final class RegisterForm
{
    public static function make(): FormBuilder
    {
        return FormBuilder::make(
            route('register'),
        )->fields([
            Text::make('Name')->required(),
            Email::make('Email')->required(),
            Password::make('Password')->required(),
            PasswordRepeat::make('Password confirmation')->required(),
        ])->submit('Регистрация', ['class' => 'btn btn-primary w-full']);
    }
}
