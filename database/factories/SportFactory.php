<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sport>
 */
class SportFactory extends Factory
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
            'title_short' => $this->faker->word,
            'annotation' => $this->faker->sentence,
            'icon' => $this->faker->imageUrl(),
            'slug' => $this->faker->slug,
            'order' => $this->faker->word,
        ];
    }
}
