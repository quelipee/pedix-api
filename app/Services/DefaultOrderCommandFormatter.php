<?php

namespace App\Services;

use App\Contracts\OrderCommandFormatter;
use App\Data\OrderCommandData;
use App\Models\Order;
use App\Models\OrderItem;

class DefaultOrderCommandFormatter implements OrderCommandFormatter
{
    public function format(Order $order): OrderCommandData
    {
        $order->loadMissing(['items', 'team', 'courier']);

        return new OrderCommandData(
            number: $order->number,
            teamName: $order->team->name,
            createdAt: $order->created_at?->toISOString() ?? now()->toISOString(),
            modality: $order->modality->label(),
            customerName: $order->customer_name,
            customerPhone: $order->customer_phone,
            deliveryAddress: $order->delivery_address,
            items: $order->items->map(fn (OrderItem $item): array => [
                'productName' => $item->product_name,
                'quantity' => $item->quantity,
                'unitPrice' => $item->unit_price,
                'subtotal' => $this->multiply($item->unit_price, $item->quantity),
                'notes' => $item->notes,
                'selectedOptions' => $item->selected_options ?? [],
            ])->all(),
            deliveryFee: $order->delivery_fee,
            total: $order->total(),
            paymentMethod: $order->payment_method?->label(),
            changeFor: $order->change_for,
            changeAmount: $order->changeAmount(),
            courierName: $order->courier?->name,
        );
    }

    private function multiply(string $amount, int $quantity): string
    {
        $totalInCents = (int) str_replace('.', '', $amount) * $quantity;

        return intdiv($totalInCents, 100).'.'.str_pad((string) ($totalInCents % 100), 2, '0', STR_PAD_LEFT);
    }
}
