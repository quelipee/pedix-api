<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductCategory> */
class ProductCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => fn () => Team::query()->value('id') ?? Team::factory(),
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
