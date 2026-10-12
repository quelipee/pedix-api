<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class UpdateProduct
{
    /** @param array<string, mixed> $attributes */
    public function handle(Product $product, array $attributes): Product
    {
        return DB::transaction(function () use ($product, $attributes): Product {
            $shouldReplaceOptionGroups = array_key_exists('option_groups', $attributes);
            $optionGroups = $attributes['option_groups'] ?? [];
            unset($attributes['option_groups']);

            $product->update($attributes);

            if ($shouldReplaceOptionGroups) {
                $product->optionGroups()->delete();

                foreach ($optionGroups as $groupIndex => $groupAttributes) {
                    $options = $groupAttributes['options'];
                    unset($groupAttributes['options']);

                    $group = $product->optionGroups()->create([...$groupAttributes, 'sort_order' => $groupIndex]);
                    foreach ($options as $optionIndex => $optionAttributes) {
                        $group->options()->create([...$optionAttributes, 'sort_order' => $optionIndex]);
                    }
                }
            }

            return $product->load('optionGroups.options');
        });
    }
}
