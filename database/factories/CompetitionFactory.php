<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Competition>
 */
class CompetitionFactory extends Factory
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
            'slug' => $this->faker->slug,
            'sport_id' => null,  // Создаем спортивную категорию
            'date_from' => $this->faker->date(),
            'date_to' => $this->faker->date(),
            'about' => $this->faker->text,
            'image' => $this->faker->imageUrl(),
        ];
    }
}
