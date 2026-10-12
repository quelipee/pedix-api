<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdvanceOrderStatus
{
    public function handle(Order $order, string $status): Order
    {
        return DB::transaction(function () use ($order, $status): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $currentStatus = $lockedOrder->status;
            $nextStatus = $currentStatus->next();
            $requestedStatus = OrderStatus::from($status);

            if ($nextStatus !== $requestedStatus) {
                throw ValidationException::withMessages(['status' => 'O pedido só pode avançar para a próxima etapa.']);
            }

            $lockedOrder->update(['status' => $requestedStatus]);

            return $lockedOrder;
        });
    }
}
