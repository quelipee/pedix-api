<?php

namespace Database\Factories;

use App\Models\Courier;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Courier> */
class CourierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('(##) 9####-####'),
            'active' => true,
        ];
    }
}
