<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class CreateProductPageController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): Response
    {
        $categories = Product::query()
            ->distinct()
            ->pluck('category')
            ->merge(['Lanches', 'Bebidas', 'Batatas', 'Outros'])
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return Inertia::render('products/Form', ['categories' => $categories]);
    }
}
