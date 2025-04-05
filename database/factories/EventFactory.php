<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
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
            'competition_id' => null,
            'arena_id' => null,
            'date_from' => $this->faker->date(),
            'date_to' => $this->faker->date(),
            'club1_id' => null,
            'club2_id' => null,
            'result' => $this->faker->word,
            'image' => $this->faker->imageUrl(),
        ];
    }


}
