<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (Product $product): void {
            if ($product->category_id !== null) {
                return;
            }

            $category = ProductCategory::query()->firstOrCreate([
                'team_id' => $product->team_id,
                'name' => $product->category,
            ]);

            $product->update(['category_id' => $category->id]);
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => fn () => Team::query()->value('id') ?? Team::factory(),
            'name' => fake()->words(2, true),
            'category' => 'Outros',
            'description' => null,
            'ingredients' => [],
            'price' => fake()->randomFloat(2, 1, 50),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['active' => false]);
    }
}
