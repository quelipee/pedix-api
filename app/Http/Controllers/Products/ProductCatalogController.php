<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductCatalogController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $products = Product::query()
            ->with('optionGroups.options')
            ->where('team_id', $request->user()->current_team_id)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return Inertia::render('products/Index', [
            'products' => ProductResource::collection($products)->response($request)->getData(true)['data'],
            'categories' => ProductCategory::query()
                ->where('team_id', $request->user()->current_team_id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
