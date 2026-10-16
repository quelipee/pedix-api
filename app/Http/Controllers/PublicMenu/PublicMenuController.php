<?php

namespace App\Http\Controllers\PublicMenu;

use App\Actions\Orders\CreatePublicOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicMenu\StorePublicOrderRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PublicMenuController extends Controller
{
    public function show(Request $request, Team $store): Response
    {
        return Inertia::render('public-menu/Index', [
            'store' => ['name' => $store->name, 'slug' => $store->slug, 'acceptingOrders' => $store->canAcceptOnlineOrders(), 'deliveryFee' => $store->online_delivery_fee],
            'products' => ProductResource::collection(Product::query()->where('team_id', $store->id)->where('active', true)
                ->with(['optionGroups.options' => fn ($query) => $query->where('active', true)])
                ->orderBy('category')->orderBy('name')->get())->resolve($request),
            'checkoutToken' => $this->issueToken($request, $store),
        ]);
    }

    public function store(StorePublicOrderRequest $request, Team $store, CreatePublicOrder $action): JsonResponse
    {
        $order = $action->handle($store, $request->validated());

        return response()->json(['number' => $order->number, 'total' => $order->total(), 'checkoutToken' => $this->issueToken($request, $store)], 201);
    }

    private function issueToken(Request $request, Team $store): string
    {
        $token = (string) Str::uuid();
        $tokens = $request->session()->get('public_checkout_tokens', []);
        $tokens[$token] = $store->id;
        $request->session()->put('public_checkout_tokens', array_slice($tokens, -20, null, true));

        return $token;
    }
}
