<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;

class CreateProduct
{
    /** @param array<string, mixed> $attributes */
    public function handle(int $teamId, array $attributes): Product
    {
        return DB::transaction(function () use ($teamId, $attributes): Product {
            $optionGroups = $attributes['option_groups'] ?? [];
            unset($attributes['option_groups']);

            $category = ProductCategory::query()
                ->where('team_id', $teamId)
                ->whereKey($attributes['category_id'])
                ->firstOrFail();
            $attributes['category'] = $category->name;

            $product = Product::query()->create([...$attributes, 'team_id' => $teamId]);
            $this->createOptionGroups($product, $optionGroups);

            return $product->load('optionGroups.options');
        });
    }

    /** @param array<int, array<string, mixed>> $optionGroups */
    private function createOptionGroups(Product $product, array $optionGroups): void
    {
        foreach ($optionGroups as $groupIndex => $groupAttributes) {
            $options = $groupAttributes['options'];
            unset($groupAttributes['options']);

            $group = $product->optionGroups()->create([...$groupAttributes, 'sort_order' => $groupIndex]);
            foreach ($options as $optionIndex => $optionAttributes) {
                $group->options()->create([...$optionAttributes, 'sort_order' => $optionIndex]);
            }
        }
    }
}
