<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreateProductPageController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $categories = ProductCategory::query()
            ->where('team_id', $request->user()->current_team_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('products/Form', ['categories' => $categories]);
    }
}
