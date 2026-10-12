<?php

namespace Database\Factories;

use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductOption>
 */
class ProductOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_option_group_id' => ProductOptionGroup::factory(),
            'name' => fake()->words(2, true),
            'price' => fake()->randomFloat(2, 0, 10),
            'active' => true,
            'sort_order' => 0,
        ];
    }
}
