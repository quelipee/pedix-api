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

class KitchenPanelController extends Controller
{
    private const ORDERS_PER_COLUMN = 50;

    public function __invoke(Request $request): Response
    {
        $statuses = [
            OrderStatus::New,
            OrderStatus::InPreparation,
            OrderStatus::Ready,
        ];

        $counts = Order::query()
            ->whereIn('status', $statuses)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $columns = collect($statuses)->mapWithKeys(function (OrderStatus $status) use ($request): array {
            $orders = Order::query()
                ->with(['items', 'courier'])
                ->where('status', $status)
                ->oldest('created_at')
                ->oldest('id')
                ->limit(self::ORDERS_PER_COLUMN)
                ->get();

            return [
                $status->value => OrderResource::collection($orders)
                    ->response($request)
                    ->getData(true)['data'],
            ];
        });

        return Inertia::render('orders/Index', [
            'columns' => $columns,
            'counts' => collect($statuses)->mapWithKeys(fn (OrderStatus $status): array => [
                $status->value => (int) $counts->get($status->value, 0),
            ]),
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
            'refreshedAt' => now()->toISOString(),
        ]);
    }
}
