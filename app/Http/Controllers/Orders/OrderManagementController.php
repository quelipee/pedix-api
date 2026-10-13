<?php

namespace App\Http\Controllers\Orders;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Courier;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderManagementController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $requestedStatus = $request->query('status');
        $status = is_string($requestedStatus) && OrderStatus::tryFrom($requestedStatus)
            ? $requestedStatus
            : 'todos';
        $requestedSearch = $request->query('q');
        $search = is_string($requestedSearch) ? mb_substr(trim($requestedSearch), 0, 100) : '';

        $statusCounts = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $orders = Order::query()
            ->with(['items', 'courier'])
            ->when($status !== 'todos', fn (Builder $query): Builder => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%");

                    if (ctype_digit($search)) {
                        $query->orWhereKey((int) $search);
                    }
                });
            })
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('orders/Index', [
            'orders' => [
                'data' => OrderResource::collection($orders->getCollection())->response($request)->getData(true)['data'],
                'currentPage' => $orders->currentPage(),
                'lastPage' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
            'filters' => ['status' => $status, 'q' => $search],
            'statusCounts' => collect(OrderStatus::cases())
                ->mapWithKeys(fn (OrderStatus $orderStatus): array => [
                    $orderStatus->value => (int) $statusCounts->get($orderStatus->value, 0),
                ])
                ->put('todos', (int) $statusCounts->sum()),
            'couriers' => Courier::query()
                ->where('team_id', $request->user()->current_team_id)
                ->withCount(['orders as active_deliveries_count' => fn (Builder $query): Builder => $query
                    ->whereNotNull('dispatched_at')
                    ->where('status', '!=', OrderStatus::Finalized)])
                ->orderBy('name')
                ->get()
                ->map(fn (Courier $courier): array => [
                    'id' => $courier->id,
                    'name' => $courier->name,
                    'phone' => $courier->phone,
                    'active' => $courier->active,
                    'available' => $courier->active,
                    'activeDeliveriesCount' => $courier->active_deliveries_count,
                ]),
        ]);
    }
}
