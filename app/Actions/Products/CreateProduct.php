<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class CreateProduct
{
    /** @param array<string, mixed> $attributes */
    public function handle(array $attributes): Product
    {
        return DB::transaction(function () use ($attributes): Product {
            $optionGroups = $attributes['option_groups'] ?? [];
            unset($attributes['option_groups']);

            $product = Product::query()->create($attributes);
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
