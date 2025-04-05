<?php

namespace Database\Factories;

use App\Models\Age;
use App\Models\City;
use App\Models\Gender;
use App\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Club>
 */
class ClubFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->company,
            'title_short' => $this->faker->word,
            'slug' => $this->faker->slug,
            'about' => $this->faker->text,
            'address' => $this->faker->address,
            'map' => $this->faker->text,
            'sport_id' => null,
            'city_id' => null,
            'gender_id' => null,
            'age_id' => null,
            'is_alien' => $this->faker->boolean,
            'image' => $this->faker->imageUrl(),
        ];
    }
}
