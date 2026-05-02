<?php

namespace Tests\Feature;

use App\Models\Param;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ParamControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        Schema::create('params', function (Blueprint $table) {
            $table->id();
            $table->text('title');
            $table->text('name');
            $table->text('value');
            $table->string('type')->default('string');
        });
    }

    public function test_update_title_does_not_clear_value_when_value_is_missing(): void
    {
        $param = Param::create([
            'title' => 'Old title',
            'name' => 'site_logo',
            'value' => 'params/logo.png',
            'type' => 'string',
        ]);

        $response = $this->putJson("/api/params/{$param->id}", [
            'title' => 'New title',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('params', [
            'id' => $param->id,
            'title' => 'New title',
            'value' => 'params/logo.png',
        ]);
    }

    public function test_update_normalizes_explicit_null_value_to_empty_string(): void
    {
        $param = Param::create([
            'title' => 'Site title',
            'name' => 'site_title',
            'value' => 'Liga',
            'type' => 'string',
        ]);

        $response = $this->putJson("/api/params/{$param->id}", [
            'value' => null,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('params', [
            'id' => $param->id,
            'value' => '',
        ]);
    }
}
