<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EditProductPageController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, string $currentTeam, Product $product): Response
    {
        abort_unless($product->team_id === $request->user()->current_team_id, 404);

        $categories = Product::query()
            ->where('team_id', $request->user()->current_team_id)
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
