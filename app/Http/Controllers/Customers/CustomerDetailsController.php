<?php

namespace App\Http\Controllers\Customers;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerDetailsController extends Controller
{
    public function __invoke(Request $request, Team $current_team, Customer $customer): Response|JsonResponse
    {
        abort_unless(
            $customer->team_id === $current_team->id &&
            $customer->team_id === $request->user()->current_team_id,
            404,
        );

        $orders = $customer->orders()
            ->with(['items', 'courier', 'cancelledBy'])
            ->latest('created_at')
            ->latest('id')
            ->paginate(10);

        $finalizedOrders = Order::query()
            ->where('team_id', $request->user()->current_team_id)
            ->where('customer_id', $customer->id)
            ->where('status', OrderStatus::Finalized);
        $itemsTotal = OrderItem::query()
            ->whereHas('order', fn ($query) => $query
                ->where('customer_id', $customer->id)
                ->where('status', OrderStatus::Finalized))
            ->selectRaw('COALESCE(SUM(unit_price * quantity), 0) as total')
            ->value('total');
        $deliveryTotal = (string) (clone $finalizedOrders)->sum('delivery_fee');
        $totalSpent = number_format((float) $itemsTotal + (float) $deliveryTotal, 2, '.', '');
        $popularProduct = OrderItem::query()
            ->whereHas('order', fn ($query) => $query
                ->where('customer_id', $customer->id)
                ->where('status', OrderStatus::Finalized))
            ->selectRaw('product_name, SUM(quantity) as quantity')
            ->groupBy('product_name')
            ->orderByDesc('quantity')
            ->orderBy('product_name')
            ->first();

        $details = [
            'customer' => CustomerResource::make($customer)->resolve($request),
            'summary' => [
                'ordersCount' => $customer->orders()->count(),
                'finalizedOrdersCount' => (clone $finalizedOrders)->count(),
                'totalSpent' => $totalSpent,
                'popularProduct' => $popularProduct ? [
                    'name' => $popularProduct->product_name,
                    'quantity' => (int) $popularProduct->quantity,
                ] : null,
            ],
            'orders' => [
                'data' => OrderResource::collection($orders->getCollection())->response($request)->getData(true)['data'],
                'currentPage' => $orders->currentPage(),
                'lastPage' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ];

        if ($request->expectsJson()) {
            return response()->json($details);
        }

        return Inertia::render('customers/Show', $details);
    }
}
