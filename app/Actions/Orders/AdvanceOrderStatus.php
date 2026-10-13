<?php

namespace App\Actions\Orders;

use App\Enums\OrderModality;
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

            if (
                $currentStatus === OrderStatus::InPreparation
                && $requestedStatus === OrderStatus::Ready
                && $lockedOrder->modality === OrderModality::Delivery
                && $lockedOrder->courier_id === null
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Selecione um motoboy antes de marcar a entrega como pronta.',
                ]);
            }

            if (
                $currentStatus === OrderStatus::Ready
                && $requestedStatus === OrderStatus::Finalized
                && $lockedOrder->modality === OrderModality::Delivery
                && $lockedOrder->courier_id === null
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Selecione um motoboy antes de finalizar a entrega.',
                ]);
            }

            if ($nextStatus !== $requestedStatus) {
                throw ValidationException::withMessages(['status' => 'O pedido só pode avançar para a próxima etapa.']);
            }

            $lockedOrder->update(['status' => $requestedStatus]);

            return $lockedOrder;
        });
    }
}
