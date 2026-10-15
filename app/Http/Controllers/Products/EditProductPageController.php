<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductCategory;
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

        $categories = ProductCategory::query()
            ->where('team_id', $request->user()->current_team_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('products/Form', [
            'categories' => $categories,
            'product' => ProductResource::make($product->load('optionGroups.options'))->resolve(),
        ]);
    }
}
