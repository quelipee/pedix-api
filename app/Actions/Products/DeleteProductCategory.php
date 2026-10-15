<?php

namespace App\Actions\Products;

use App\Models\ProductCategory;
use Illuminate\Validation\ValidationException;

class DeleteProductCategory
{
    public function handle(ProductCategory $category): void
    {
        if ($category->products()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Mova ou exclua os itens desta categoria antes de removê-la.',
            ]);
        }

        $category->delete();
    }
}
