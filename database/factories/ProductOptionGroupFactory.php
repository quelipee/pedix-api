<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductOptionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductOptionGroup>
 */
class ProductOptionGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => 'Adicionais',
            'min_selections' => 0,
            'max_selections' => 2,
            'sort_order' => 0,
        ];
    }
}
