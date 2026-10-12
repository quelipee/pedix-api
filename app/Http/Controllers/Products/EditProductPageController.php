<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class EditProductPageController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(string $currentTeam, Product $product): Response
    {
        $categories = Product::query()
            ->distinct()
            ->pluck('category')
            ->merge(['Lanches', 'Bebidas', 'Batatas', 'Outros'])
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return Inertia::render('products/Form', [
            'categories' => $categories,
            'product' => ProductResource::make($product->load('optionGroups.options'))->resolve(),
        ]);
    }
}
