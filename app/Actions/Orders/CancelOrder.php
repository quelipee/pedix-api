<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelOrder
{
    public function handle(Order $order, int $userId, ?string $reason): Order
    {
        return DB::transaction(function () use ($order, $userId, $reason): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->status === OrderStatus::Finalized) {
                throw ValidationException::withMessages([
                    'order' => 'Um pedido finalizado não pode ser cancelado.',
                ]);
            }

            if ($lockedOrder->status === OrderStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'order' => 'Este pedido já foi cancelado.',
                ]);
            }

            $lockedOrder->update([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $userId,
                'cancellation_reason' => filled($reason) ? trim($reason) : null,
            ]);

            return $lockedOrder;
        });
    }
}
