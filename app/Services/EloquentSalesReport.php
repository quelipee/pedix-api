<?php

namespace App\Services;

use App\Contracts\SalesReport;
use App\Enums\OrderModality;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

class EloquentSalesReport implements SalesReport
{
    public function generate(int $teamId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $orders = Order::query()
            ->with('items')
            ->where('status', OrderStatus::Finalized)
            ->whereBetween('finalized_at', [$from, $to])
            ->orderBy('finalized_at')
            ->get();

        $revenueInCents = $orders->sum(fn (Order $order): int => $this->toCents($order->total()));
        $deliveryFeesInCents = $orders->sum(
            fn (Order $order): int => $this->toCents($order->delivery_fee ?? '0.00'),
        );
        $ordersCount = $orders->count();

        $daily = [];

        foreach (CarbonPeriod::create($from->startOfDay(), $to->startOfDay()) as $date) {
            $key = $date->format('Y-m-d');
            $daily[$key] = [
                'date' => $key,
                'label' => $date->format('d/m'),
                'orders' => 0,
                'revenue' => '0.00',
            ];
        }

        foreach ($orders as $order) {
            $date = $order->finalized_at?->format('Y-m-d');

            if ($date === null || ! isset($daily[$date])) {
                continue;
            }

            $current = $daily[$date];
            $current['orders']++;
            $current['revenue'] = $this->fromCents(
                $this->toCents($current['revenue']) + $this->toCents($order->total()),
            );
            $daily[$date] = $current;
        }

        $payments = collect(PaymentMethod::cases())->map(function (PaymentMethod $method) use ($orders): array {
            $methodOrders = $orders->where('payment_method', $method);

            return [
                'value' => $method->value,
                'label' => $method->label(),
                'orders' => $methodOrders->count(),
                'total' => $this->fromCents(
                    $methodOrders->sum(fn (Order $order): int => $this->toCents($order->total())),
                ),
            ];
        })->values();

        $modalities = collect(OrderModality::cases())->map(function (OrderModality $modality) use ($orders): array {
            $modalityOrders = $orders->where('modality', $modality);

            return [
                'value' => $modality->value,
                'label' => $modality->label(),
                'orders' => $modalityOrders->count(),
                'total' => $this->fromCents(
                    $modalityOrders->sum(fn (Order $order): int => $this->toCents($order->total())),
                ),
            ];
        })->values();

        $topProducts = $orders->flatMap(fn (Order $order) => $order->items)
            ->groupBy(fn (OrderItem $item): string => $item->product_name)
            ->map(function ($items, string $name): array {
                $quantity = $items->sum(fn (OrderItem $item): int => $item->quantity);
                $totalInCents = $items->sum(
                    fn (OrderItem $item): int => $this->toCents($item->unit_price) * $item->quantity,
                );

                return [
                    'name' => $name,
                    'quantity' => $quantity,
                    'total' => $this->fromCents($totalInCents),
                ];
            })
            ->sortByDesc('quantity')
            ->take(5)
            ->values();

        return [
            'summary' => [
                'revenue' => $this->fromCents($revenueInCents),
                'orders' => $ordersCount,
                'averageTicket' => $this->fromCents($ordersCount > 0 ? intdiv($revenueInCents, $ordersCount) : 0),
                'deliveryFees' => $this->fromCents($deliveryFeesInCents),
                'newCustomers' => Customer::query()
                    ->where('team_id', $teamId)
                    ->whereBetween('created_at', [$from, $to])
                    ->count(),
            ],
            'dailySales' => array_values($daily),
            'payments' => $payments,
            'modalities' => $modalities,
            'topProducts' => $topProducts,
        ];
    }

    private function toCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    private function fromCents(int $amount): string
    {
        return intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }
}
