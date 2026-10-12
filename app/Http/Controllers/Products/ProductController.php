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
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        return ProductResource::collection(Product::query()->with('optionGroups.options')->orderBy('name')->get())->response();
    }

    public function store(SaveProductRequest $request, CreateProduct $createProduct): JsonResponse
    {
        $product = $createProduct->handle($request->validated());

        return ProductResource::make($product->load('optionGroups.options'))->response()->setStatusCode(201);
    }

    public function update(SaveProductRequest $request, Product $product, UpdateProduct $updateProduct): JsonResponse
    {
        $updateProduct->handle($product, $request->validated());

        return ProductResource::make($product->refresh()->load('optionGroups.options'))->response();
    }

    public function destroy(Product $product, DeleteProduct $deleteProduct): Response
    {
        $deleteProduct->handle($product);

        return response()->noContent();
    }
}
