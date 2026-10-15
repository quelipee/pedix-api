<?php

namespace App\Http\Controllers\Products;

use App\Actions\Products\CreateProductCategory;
use App\Actions\Products\DeleteProductCategory;
use App\Actions\Products\UpdateProductCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Products\StoreProductCategoryRequest;
use App\Http\Requests\Products\UpdateProductCategoryRequest;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductCategoryController extends Controller
{
    public function store(StoreProductCategoryRequest $request, CreateProductCategory $createCategory): JsonResponse
    {
        $teamId = $request->user()->current_team_id;
        abort_if($teamId === null, 403);

        $category = $createCategory->handle($teamId, $request->validated('name'));

        return response()->json(['data' => $category], 201);
    }

    public function update(
        UpdateProductCategoryRequest $request,
        ProductCategory $productCategory,
        UpdateProductCategory $updateCategory,
    ): JsonResponse {
        abort_unless($productCategory->team_id === $request->user()->current_team_id, 404);

        $category = $updateCategory->handle($productCategory, $request->validated('name'));

        return response()->json(['data' => $category]);
    }

    public function destroy(ProductCategory $productCategory, DeleteProductCategory $deleteCategory): Response
    {
        abort_unless($productCategory->team_id === request()->user()->current_team_id, 404);

        $deleteCategory->handle($productCategory);

        return response()->noContent();
    }
}
