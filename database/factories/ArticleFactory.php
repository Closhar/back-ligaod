<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Article>
 */
class ArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence,
            'data' => $this->faker->dateTimeThisYear,
            'slug' => $this->faker->slug,
            'description' => $this->faker->text(),
            'content' => $this->faker->paragraph,
            'published' => $this->faker->boolean,
            'image' => $this->faker->imageUrl(),
        ];
    }
}
