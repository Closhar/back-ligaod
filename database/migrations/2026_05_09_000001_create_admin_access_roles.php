<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('admin_role_admin_page', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_role_id')->constrained('admin_roles')->cascadeOnDelete();
            $table->foreignId('admin_page_id')->constrained('admin_pages')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['admin_role_id', 'admin_page_id'], 'admin_role_page_unique');
        });

        Schema::create('admin_role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_role_id')->constrained('admin_roles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['admin_role_id', 'user_id'], 'admin_role_user_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_blocked')->default(false)->after('is_admin')->index();
            $table->timestamp('blocked_at')->nullable()->after('is_blocked');
        });

        $systemSectionId = DB::table('menu_sections')->where('name', 'Система')->value('id');

        if (! $systemSectionId) {
            $systemSectionId = DB::table('menu_sections')->insertGetId([
                'name' => 'Система',
                'icon' => 'fluent:window-dev-tools-20-filled',
                'description' => 'Системные настройки',
                'sort_order' => 0,
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ([
            [
                'title' => 'Пользователи',
                'slug' => 'admin-users',
                'icon' => 'heroicons:users',
                'description' => 'Управление пользователями и доступом',
                'sort_order' => 80,
            ],
            [
                'title' => 'Роли',
                'slug' => 'admin-access-roles',
                'icon' => 'heroicons:shield-check',
                'description' => 'Роли доступа к страницам админки',
                'sort_order' => 90,
            ],
        ] as $page) {
            $pageData = [
                ...$page,
                'menu' => true,
                'menu_section_id' => $systemSectionId,
                'updated_at' => now(),
            ];

            $existingPageId = DB::table('admin_pages')->where('slug', $page['slug'])->value('id');

            if ($existingPageId) {
                DB::table('admin_pages')->where('id', $existingPageId)->update($pageData);
            } else {
                DB::table('admin_pages')->insert([
                    ...$pageData,
                    'created_at' => now(),
                ]);
            }
        }

        $existingUserRoleId = DB::table('admin_roles')->where('slug', 'user')->value('id');

        if ($existingUserRoleId) {
            DB::table('admin_roles')->where('id', $existingUserRoleId)->update([
                'name' => 'Пользователь',
                'description' => 'Базовый статус без доступа к админке',
                'is_active' => true,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('admin_roles')->insert([
                'name' => 'Пользователь',
                'slug' => 'user',
                'description' => 'Базовый статус без доступа к админке',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('admin_pages')->whereIn('slug', ['admin-users', 'admin-access-roles'])->delete();
        DB::table('admin_roles')->where('slug', 'user')->delete();

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_blocked', 'blocked_at']);
        });

        Schema::dropIfExists('admin_role_user');
        Schema::dropIfExists('admin_role_admin_page');
        Schema::dropIfExists('admin_roles');
    }
};
