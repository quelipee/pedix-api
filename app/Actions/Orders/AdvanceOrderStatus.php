<?php

namespace App\Actions\Orders;

use App\Enums\OrderModality;
use App\Enums\OrderStatus;
use App\Models\CashRegister;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdvanceOrderStatus
{
    public function handle(Order $order, int $teamId, string $status, ?CashRegister $cashRegister = null): Order
    {
        return DB::transaction(function () use ($order, $teamId, $status, $cashRegister): Order {
            $lockedOrder = Order::query()
                ->where('team_id', $teamId)
                ->lockForUpdate()
                ->findOrFail($order->id);
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

            $attributes = ['status' => $requestedStatus];

            if ($requestedStatus === OrderStatus::Finalized && $cashRegister) {
                $attributes['cash_register_id'] = $cashRegister->id;
            }

            if ($requestedStatus === OrderStatus::Finalized) {
                $attributes['finalized_at'] = now();
            }

            $lockedOrder->update($attributes);

            return $lockedOrder;
        });
    }
}
