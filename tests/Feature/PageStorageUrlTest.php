<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PageStorageUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
    }

    public function test_page_image_accessor_uses_public_disk_url(): void
    {
        $page = new Page([
            'image' => 'pages/fNWIR96hOp9MV8KiZZcl4xg9nNVQqwz8svwhH0fO.png',
            'image_default' => 'pages/default.png',
        ]);

        $this->assertSame(
            'https://cdn.ligaod.test/pages/fNWIR96hOp9MV8KiZZcl4xg9nNVQqwz8svwhH0fO.png',
            $page->page_image
        );
        $this->assertSame(
            'https://cdn.ligaod.test/pages/default.png',
            $page->default_page_image
        );
    }
}
