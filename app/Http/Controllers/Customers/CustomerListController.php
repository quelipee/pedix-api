<?php

namespace App\Http\Controllers\Customers;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerListController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $teamId = $request->user()->current_team_id;
        abort_if($teamId === null, 403);

        $search = is_string($request->query('q'))
            ? mb_substr(trim($request->query('q')), 0, 100)
            : '';

        $customers = Customer::query()
            ->where('team_id', $teamId)
            ->when($search !== '', fn (Builder $query): Builder => $query->where(
                fn (Builder $query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"),
            ))
            ->withCount('orders')
            ->withMax('orders as last_order_at', 'created_at')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $popularProducts = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.customer_id', $customers->getCollection()->modelKeys())
            ->where('orders.status', OrderStatus::Finalized)
            ->selectRaw('orders.customer_id, order_items.product_name, SUM(order_items.quantity) as quantity')
            ->groupBy('orders.customer_id', 'order_items.product_name')
            ->get()
            ->groupBy('customer_id')
            ->map(fn ($items): array => [
                'name' => $items->sortByDesc('quantity')->first()->product_name,
                'quantity' => (int) $items->max('quantity'),
            ]);

        $data = $customers->getCollection()->map(
            fn (Customer $customer): array => [
                ...CustomerResource::make($customer)->resolve($request),
                'popularProduct' => $popularProducts->get($customer->id),
            ],
        )->values();

        return Inertia::render('customers/Index', [
            'customers' => [
                'data' => $data,
                'currentPage' => $customers->currentPage(),
                'lastPage' => $customers->lastPage(),
                'total' => $customers->total(),
            ],
            'filters' => ['q' => $search],
        ]);
    }
}
