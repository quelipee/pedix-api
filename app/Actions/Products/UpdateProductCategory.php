<?php

namespace App\Actions\Products;

use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;

class UpdateProductCategory
{
    public function handle(ProductCategory $category, string $name): ProductCategory
    {
        return DB::transaction(function () use ($category, $name): ProductCategory {
            $category->products()->update(['category' => $name]);
            $category->update(['name' => $name]);

            return $category->refresh();
        });
    }
}
