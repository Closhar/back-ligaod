<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title')->default('Контакты');
            $table->text('description')->nullable();
            $table->boolean('notify_email_enabled')->default(false);
            $table->string('notify_email_to')->nullable();
            $table->boolean('notify_telegram_enabled')->default(false);
            $table->string('notify_telegram_bot_token')->nullable();
            $table->string('notify_telegram_chat_id')->nullable();
            $table->timestamps();
        });

        Schema::create('contact_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->text('address');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_main')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(500)->index();
            $table->timestamps();
        });

        Schema::create('contact_phones', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('phone');
            $table->unsignedInteger('sort_order')->default(500)->index();
            $table->timestamps();
        });

        Schema::create('contact_emails', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('email');
            $table->unsignedInteger('sort_order')->default(500)->index();
            $table->timestamps();
        });

        Schema::create('contact_socials', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('icon')->default('mdi:link-variant');
            $table->string('url');
            $table->unsignedInteger('sort_order')->default(500)->index();
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('subject');
            $table->text('message');
            $table->boolean('is_processed')->default(false)->index();
            $table->timestamp('processed_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        DB::table('contact_pages')->insert([
            'title' => 'Контакты',
            'description' => 'Свяжитесь с нами удобным способом.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $systemSectionId = DB::table('menu_sections')->where('name', 'Система')->value('id');
        if ($systemSectionId) {
            DB::table('admin_pages')->updateOrInsert(
                ['slug' => 'contacts'],
                [
                    'title' => 'Контакты',
                    'icon' => 'mdi:card-account-phone-outline',
                    'description' => 'Контакты сайта и обращения пользователей',
                    'menu' => true,
                    'menu_section_id' => $systemSectionId,
                    'sort_order' => 100,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('admin_pages')->where('slug', 'contacts')->delete();
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('contact_socials');
        Schema::dropIfExists('contact_emails');
        Schema::dropIfExists('contact_phones');
        Schema::dropIfExists('contact_addresses');
        Schema::dropIfExists('contact_pages');
    }
};
