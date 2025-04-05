<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SportProperty>
 */
class SportPropertyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->word,
            'annotation' => $this->faker->sentence,
            'icon' => $this->faker->imageUrl(),
            'order' => $this->faker->word,
        ];
    }
}
