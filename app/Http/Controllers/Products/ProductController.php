<?php

namespace App\Http\Controllers\Products;

use App\Actions\Products\CreateProduct;
use App\Actions\Products\DeleteProduct;
use App\Actions\Products\UpdateProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Products\SaveProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return ProductResource::collection(Product::query()
            ->with('optionGroups.options')
            ->where('team_id', $request->user()->current_team_id)
            ->orderBy('name')
            ->get())->response();
    }

    public function store(SaveProductRequest $request, CreateProduct $createProduct): JsonResponse
    {
        $teamId = $request->user()->current_team_id;
        abort_if($teamId === null, 403);

        $product = $createProduct->handle($teamId, $request->validated());

        return ProductResource::make($product->load('optionGroups.options'))->response()->setStatusCode(201);
    }

    public function update(SaveProductRequest $request, Product $product, UpdateProduct $updateProduct): JsonResponse
    {
        abort_unless($product->team_id === $request->user()->current_team_id, 404);

        $updateProduct->handle($product, $request->validated());

        return ProductResource::make($product->refresh()->load('optionGroups.options'))->response();
    }

    public function destroy(Product $product, DeleteProduct $deleteProduct): Response
    {
        abort_unless($product->team_id === request()->user()->current_team_id, 404);

        $deleteProduct->handle($product);

        return response()->noContent();
    }
}
