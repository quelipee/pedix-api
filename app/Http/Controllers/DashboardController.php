<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Resources\ProductResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TeamInvitation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $startOfToday = now()->startOfDay();
        $startOfTomorrow = $startOfToday->copy()->addDay();

        $todayOrderCounts = Order::query()
            ->where('created_at', '>=', $startOfToday)
            ->where('created_at', '<', $startOfTomorrow)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn (int|string $total): int => (int) $total);

        $orderCountsByStatus = collect(OrderStatus::cases())
            ->mapWithKeys(fn (OrderStatus $status): array => [
                $status->value => $todayOrderCounts->get($status->value, 0),
            ]);

        $recentOrders = Order::query()
            ->with(['items', 'courier', 'cancelledBy'])
            ->latest('created_at')
            ->limit(5)
            ->get(['id', 'customer_name', 'customer_phone', 'payment_method', 'change_for', 'delivery_address', 'delivery_fee', 'courier_id', 'dispatched_at', 'modality', 'status', 'created_at'])
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'modality' => $order->modality->value,
                'customerName' => $order->customer_name,
                'customerPhone' => $order->customer_phone,
                'paymentMethodLabel' => $order->payment_method?->label(),
                'changeFor' => $order->change_for,
                'deliveryFee' => $order->delivery_fee,
                'changeAmount' => $order->changeAmount(),
                'deliveryAddress' => $order->delivery_address ? [
                    'postalCode' => $order->delivery_address['postal_code'],
                    'street' => $order->delivery_address['street'],
                    'number' => $order->delivery_address['number'],
                    'complement' => $order->delivery_address['complement'] ?? null,
                    'neighborhood' => $order->delivery_address['neighborhood'],
                    'city' => $order->delivery_address['city'],
                    'state' => $order->delivery_address['state'],
                ] : null,
                'courier' => $order->courier ? [
                    'id' => $order->courier->id,
                    'name' => $order->courier->name,
                    'phone' => $order->courier->phone,
                ] : null,
                'dispatchedAt' => $order->dispatched_at?->toISOString(),
                'modalityLabel' => $order->modality->label(),
                'status' => $order->status->value,
                'statusLabel' => $order->status->label(),
                'createdAt' => $order->created_at?->format('d/m H:i'),
                'total' => $order->total(),
                'items' => $order->items->map(fn (OrderItem $item): array => [
                    'productName' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unitPrice' => $item->unit_price,
                    'notes' => $item->notes,
                    'selectedOptions' => $item->selected_options ?? [],
                ])->all(),
            ]);

        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        return Inertia::render('Dashboard', [
            'stats' => [
                'todayOrders' => $orderCountsByStatus->sum(),
                'newOrders' => $orderCountsByStatus->get(OrderStatus::New->value),
                'inPreparation' => $orderCountsByStatus->get(OrderStatus::InPreparation->value),
                'ready' => $orderCountsByStatus->get(OrderStatus::Ready->value),
                'finalized' => $orderCountsByStatus->get(OrderStatus::Finalized->value),
            ],
            'recentOrders' => $recentOrders,
            'products' => ProductResource::collection(
                Product::query()->with('optionGroups.options')->where('active', true)->orderBy('name')->get(),
            )->resolve(),
            'pendingInvitations' => $pendingInvitations,
        ]);
    }
}
