<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePublicOrder
{
    public function __construct(private readonly CreateOrder $createOrder) {}

    /** @param array<string, mixed> $data */
    public function handle(Team $store, array $data): Order
    {
        return DB::transaction(function () use ($store, $data): Order {
            Team::query()->whereKey($store->id)->update(['is_open' => DB::raw('is_open')]);
            $team = Team::query()->lockForUpdate()->findOrFail($store->id);
            $existing = Order::query()->where('team_id', $team->id)->where('public_order_token', $data['checkout_token'])->first();
            if ($existing) {
                return $existing;
            }
            if (! $team->canAcceptOnlineOrders()) {
                throw ValidationException::withMessages(['store' => 'Esta loja não está recebendo pedidos agora.']);
            }
            if ($data['modality'] === 'entrega' && $team->online_delivery_fee === null) {
                throw ValidationException::withMessages(['modality' => 'Esta loja está aceitando apenas retirada no momento.']);
            }
            $order = $this->createOrder->handle(
                teamId: $team->id,
                customerId: null,
                modality: $data['modality'],
                customerName: $data['customer_name'],
                customerPhone: $data['customer_phone'],
                paymentMethod: $data['payment_method'],
                needsChange: (bool) ($data['needs_change'] ?? false),
                changeFor: isset($data['change_for']) ? (string) $data['change_for'] : null,
                deliveryAddress: $data['delivery_address'] ?? null,
                deliveryFee: $data['modality'] === 'entrega' ? $team->online_delivery_fee : null,
                items: $data['items'],
                isOnline: true,
            );
            $order->forceFill(['public_order_token' => $data['checkout_token']])->save();

            return $order;
        }, 5);
    }
}
