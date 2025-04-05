<?php

namespace Database\Factories;

use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Arena>
 */
class ArenaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->word(),
            'slug' => $this->faker->slug(),
            'city_id' => null, // Если есть связь с городом
            'about' => $this->faker->text(),
            'address' => $this->faker->address(),
            'map' => $this->faker->text(),
            'image' => $this->faker->imageUrl(),
        ];
    }
}
