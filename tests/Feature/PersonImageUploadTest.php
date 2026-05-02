<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonImage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PersonImageUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->date('birth_date')->nullable();
            $table->timestamps();
        });

        Schema::create('person_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->onDelete('cascade');
            $table->string('image_path');
            $table->string('title')->nullable();
            $table->integer('position')->default(0);
            $table->boolean('is_main')->default(false);
            $table->string('alt_text')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function test_person_image_upload_route_stores_file_and_record(): void
    {
        Storage::fake('public');

        $person = Person::create([
            'first_name' => 'Ivan',
            'last_name' => 'Petrov',
        ]);

        $response = $this->post("/api/people/{$person->id}/images", [
            'image' => UploadedFile::fake()->image('person.jpg', 500, 750),
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.person_id', $person->id)
            ->assertJsonPath('data.is_main', true);

        $image = PersonImage::firstOrFail();

        Storage::disk('public')->assertExists($image->image_path);
        $this->assertStringStartsWith("people/{$person->id}/", $image->image_path);
    }

    public function test_person_image_url_uses_public_disk_url(): void
    {
        config([
            'filesystems.disks.public' => [
                'driver' => 's3',
                'key' => 'testing',
                'secret' => 'testing',
                'region' => 'us-east-1',
                'bucket' => 'ligaod-test',
                'url' => 'https://cdn.ligaod.test',
                'endpoint' => null,
                'use_path_style_endpoint' => false,
                'throw' => false,
                'report' => false,
            ],
        ]);
        Storage::forgetDisk('public');

        $image = new PersonImage([
            'image_path' => 'people/1/photo.png',
        ]);

        $this->assertSame('https://cdn.ligaod.test/people/1/photo.png', $image->image_url);
    }
}
