<?php

namespace Tests\Feature;

use App\Models\Arena;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Gallery;
use App\Models\Image;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class StorageEntityUrlTest extends TestCase
{
    public function test_s3_public_urls_are_used_for_main_image_entities(): void
    {
        Config::set('filesystems.disks.public', [
            'driver' => 's3',
            'key' => 'test',
            'secret' => 'test',
            'region' => 'ru-1',
            'bucket' => 'ligaod',
            'url' => 'https://cdn.example.test',
            'endpoint' => 'https://s3.example.test',
            'use_path_style_endpoint' => false,
            'throw' => false,
        ]);

        $this->assertSame('https://cdn.example.test/arenas/arena.jpg', (new Arena([
            'image' => 'arenas/arena.jpg',
        ]))->arena_image_path);

        $club = new Club([
            'image' => 'clubs/logo.png',
            'image_bg' => 'clubs/bg.png',
        ]);
        $this->assertSame('https://cdn.example.test/clubs/logo.png', $club->club_image_path);
        $this->assertSame('https://cdn.example.test/clubs/bg.png', $club->bg_club_image_path);

        $competition = new Competition([
            'image' => 'competitions/logo.png',
            'bg_image' => 'competitions/bg.png',
        ]);
        $this->assertSame('https://cdn.example.test/competitions/logo.png', $competition->competition_image_path);
        $this->assertSame('https://cdn.example.test/competitions/bg.png', $competition->competition_bg_image_path);

        $this->assertSame('https://cdn.example.test/galleries/5/cover.jpg', (new Gallery([
            'image' => 'galleries/5/cover.jpg',
        ]))->gallery_image_path);

        $image = new Image([
            'image' => 'galleries/5/photo.jpg',
        ]);
        $this->assertSame('https://cdn.example.test/galleries/5/photo.jpg', $image->gallery_image_path);
        $this->assertSame('https://cdn.example.test/galleries/5/thmb_photo.jpg', $image->thumbnail);
    }
}
