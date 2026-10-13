<?php

namespace App\Actions\Orders;

use App\Enums\OrderModality;
use App\Enums\OrderStatus;
use App\Models\Courier;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DispatchOrder
{
    public function handle(Order $order, Courier $courier, int $teamId): Order
    {
        return DB::transaction(function () use ($order, $courier, $teamId): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $lockedCourier = Courier::query()->lockForUpdate()->findOrFail($courier->id);

            if (
                $lockedOrder->modality !== OrderModality::Delivery
                || ! in_array($lockedOrder->status, [OrderStatus::InPreparation, OrderStatus::Ready], true)
            ) {
                throw ValidationException::withMessages([
                    'order' => 'Selecione o motoboy durante o preparo ou antes de finalizar a entrega.',
                ]);
            }

            if ($lockedCourier->team_id !== $teamId || ! $lockedCourier->active) {
                throw ValidationException::withMessages(['courier_id' => 'Selecione um motoboy disponível.']);
            }

            $lockedOrder->update([
                'courier_id' => $lockedCourier->id,
                'dispatched_at' => $lockedOrder->dispatched_at ?? now(),
            ]);

            return $lockedOrder;
        });
    }
}
