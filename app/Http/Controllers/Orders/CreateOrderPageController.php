<?php

namespace App\Http\Controllers\Orders;

use App\Enums\OrderModality;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class CreateOrderPageController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('orders/Create', [
            'products' => ProductResource::collection(
                Product::query()->with('optionGroups.options')->where('active', true)->orderBy('name')->get(),
            )->resolve(),
            'modalities' => collect(OrderModality::cases())
                ->map(fn (OrderModality $modality): array => [
                    'value' => $modality->value,
                    'label' => $modality->label(),
                ])
                ->values(),
            'paymentMethods' => collect(PaymentMethod::cases())
                ->map(fn (PaymentMethod $method): array => [
                    'value' => $method->value,
                    'label' => $method->label(),
                ])
                ->values(),
        ]);
    }
}
