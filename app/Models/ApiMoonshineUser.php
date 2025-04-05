<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use MoonShine\Laravel\Models\MoonshineUser as BaseMoonshineUser;

class ApiMoonshineUser extends BaseMoonshineUser
{
    protected $table = 'moonshine_users';
    use HasApiTokens;

    // Дополнительные методы, если нужно
}
