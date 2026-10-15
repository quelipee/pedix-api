<?php

namespace App\Actions\Products;

use App\Models\ProductCategory;

class CreateProductCategory
{
    public function handle(int $teamId, string $name): ProductCategory
    {
        return ProductCategory::query()->create([
            'team_id' => $teamId,
            'name' => $name,
        ]);
    }
}
