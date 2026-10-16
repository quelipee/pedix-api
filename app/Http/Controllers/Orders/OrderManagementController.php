<?php

namespace App\Http\Controllers\Orders;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Courier;
use App\Models\Order;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class OrderManagementController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $teamId = $request->user()->current_team_id;
        abort_if($teamId === null, 403);

        $requestedStatus = $request->query('status');
        $status = is_string($requestedStatus) && OrderStatus::tryFrom($requestedStatus)
            ? $requestedStatus
            : 'todos';
        $requestedSearch = $request->query('q');
        $search = is_string($requestedSearch) ? mb_substr(trim($requestedSearch), 0, 100) : '';
        $requestedPeriod = $request->query('period');
        $period = is_string($requestedPeriod) && in_array($requestedPeriod, ['today', '7d', '30d', 'custom', 'all'], true)
            ? $requestedPeriod
            : 'today';
        $dateFrom = match ($period) {
            'today' => today(),
            '7d' => today()->subDays(6),
            '30d' => today()->subDays(29),
            'custom' => $this->dateFromQuery($request->query('date_from')),
            default => null,
        };
        $dateTo = match ($period) {
            'today', '7d', '30d' => today(),
            'custom' => $this->dateFromQuery($request->query('date_to')),
            default => null,
        };

        $statusCounts = Order::query()
            ->where('team_id', $teamId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $orders = Order::query()
            ->with(['items', 'courier', 'cancelledBy'])
            ->where('team_id', $teamId)
            ->when($status !== 'todos', fn (Builder $query): Builder => $query->where('status', $status))
            ->when($status === OrderStatus::Finalized->value && $dateFrom !== null, fn (Builder $query): Builder => $query->where('finalized_at', '>=', $dateFrom->copy()->startOfDay()))
            ->when($status === OrderStatus::Finalized->value && $dateTo !== null, fn (Builder $query): Builder => $query->where('finalized_at', '<', $dateTo->copy()->addDay()->startOfDay()))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%");

                    if (ctype_digit($search)) {
                        $query->orWhere('number', (int) $search);
                    }
                });
            })
            ->when(
                $status === OrderStatus::Finalized->value,
                fn (Builder $query): Builder => $query->orderByDesc('finalized_at')->orderByDesc('id'),
                fn (Builder $query): Builder => $query->latest('id'),
            )
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('orders/History', [
            'orders' => [
                'data' => OrderResource::collection($orders->getCollection())->response($request)->getData(true)['data'],
                'currentPage' => $orders->currentPage(),
                'lastPage' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
            'filters' => [
                'status' => $status,
                'q' => $search,
                'period' => $period,
                'dateFrom' => $dateFrom?->toDateString(),
                'dateTo' => $dateTo?->toDateString(),
            ],
            'statusCounts' => collect(OrderStatus::cases())
                ->mapWithKeys(fn (OrderStatus $orderStatus): array => [
                    $orderStatus->value => (int) $statusCounts->get($orderStatus->value, 0),
                ])
                ->put('todos', (int) $statusCounts->sum()),
            'couriers' => Courier::query()
                ->where('team_id', $teamId)
                ->withCount(['orders as active_deliveries_count' => fn (Builder $query): Builder => $query
                    ->whereNotNull('dispatched_at')
                    ->whereNotIn('status', [OrderStatus::Finalized->value, OrderStatus::Cancelled->value])])
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

    private function dateFromQuery(mixed $value): ?Carbon
    {
        if (! is_string($value)) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value
            ? Carbon::parse($value)->startOfDay()
            : null;
    }
}
