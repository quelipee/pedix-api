<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Orders\AdvanceOrderStatus;
use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\DispatchOrder;
use App\Enums\OrderModality;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\AdvanceOrderStatusRequest;
use App\Http\Requests\Orders\CancelOrderRequest;
use App\Http\Requests\Orders\DispatchOrderRequest;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductResource;
use App\Models\CashRegister;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $orders = Order::query()->with(['items', 'courier', 'cancelledBy'])->latest('id')->paginate(20);

        return OrderResource::collection($orders)->additional([
            'products' => ProductResource::collection(Product::query()->with('optionGroups.options')->where('active', true)->orderBy('name')->get())->resolve(),
            'modalities' => collect(OrderModality::cases())->map(fn (OrderModality $modality): array => ['value' => $modality->value, 'label' => $modality->label()]),
            'paymentMethods' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()]),
            'statuses' => collect(OrderStatus::cases())->map(fn (OrderStatus $status): array => ['value' => $status->value, 'label' => $status->label()]),
        ]);
    }

    public function store(StoreOrderRequest $request, CreateOrder $createOrder): JsonResponse
    {
        $teamId = $request->user()->current_team_id;
        abort_if($teamId === null, 403);

        $order = $createOrder->handle(
            $teamId,
            $request->integer('customer_id') ?: null,
            $request->validated('modality'),
            $request->validated('customer_name'),
            $request->validated('customer_phone'),
            $request->validated('payment_method'),
            $request->boolean('needs_change'),
            $request->validated('change_for'),
            $request->validated('delivery_address'),
            $request->validated('delivery_fee'),
            $request->validated('items'),
        );

        return OrderResource::make($order->load('items'))->response()->setStatusCode(201);
    }

    public function advance(AdvanceOrderStatusRequest $request, Order $order, AdvanceOrderStatus $advanceOrderStatus): JsonResponse
    {
        $cashRegister = CashRegister::query()
            ->where('team_id', $request->user()->current_team_id)
            ->whereNull('closed_at')
            ->latest('opened_at')
            ->first();

        $advanceOrderStatus->handle($order, $request->validated('status'), $cashRegister);

        return OrderResource::make($order->refresh()->load('items'))->response();
    }

    public function dispatch(DispatchOrderRequest $request, Order $order, DispatchOrder $dispatchOrder): JsonResponse
    {
        $courier = Courier::query()->findOrFail($request->integer('courier_id'));
        $teamId = $request->user()->current_team_id;

        abort_if($teamId === null, 403);

        $dispatchOrder->handle($order, $courier, $teamId);

        return OrderResource::make($order->refresh()->load(['items', 'courier']))->response();
    }

    public function cancel(CancelOrderRequest $request, Order $order, CancelOrder $cancelOrder): JsonResponse
    {
        $userId = $request->user()->id;
        $cancelOrder->handle($order, $userId, $request->validated('reason'));

        return OrderResource::make(
            $order->refresh()->load(['items', 'courier', 'cancelledBy']),
        )->response();
    }
}
