<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'isOnline' => $this->is_online,
            'customerId' => $this->customer_id,
            'modality' => $this->modality->value,
            'modalityLabel' => $this->modality->label(),
            'customerName' => $this->customer_name,
            'customerPhone' => $this->customer_phone,
            'paymentMethod' => $this->payment_method?->value,
            'paymentMethodLabel' => $this->payment_method?->label(),
            'changeFor' => $this->change_for,
            'deliveryFee' => $this->delivery_fee,
            'changeAmount' => $this->whenLoaded('items', fn (): ?string => $this->changeAmount()),
            'deliveryAddress' => $this->delivery_address ? [
                'postalCode' => $this->delivery_address['postal_code'],
                'street' => $this->delivery_address['street'],
                'number' => $this->delivery_address['number'],
                'complement' => $this->delivery_address['complement'] ?? null,
                'neighborhood' => $this->delivery_address['neighborhood'],
                'city' => $this->delivery_address['city'],
                'state' => $this->delivery_address['state'],
            ] : null,
            'courier' => $this->courier ? [
                'id' => $this->courier->id,
                'name' => $this->courier->name,
                'phone' => $this->courier->phone,
            ] : null,
            'dispatchedAt' => $this->dispatched_at?->toISOString(),
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'nextStatus' => $this->status->next()?->value,
            'total' => $this->whenLoaded('items', fn (): string => $this->total()),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'createdAt' => $this->created_at?->toISOString(),
            'finalizedAt' => $this->finalized_at?->toISOString(),
            'cancelledAt' => $this->cancelled_at?->toISOString(),
            'cancellationReason' => $this->cancellation_reason,
            'cancelledBy' => $this->whenLoaded('cancelledBy', fn (): ?array => $this->cancelledBy ? [
                'id' => $this->cancelledBy->id,
                'name' => $this->cancelledBy->name,
            ] : null),
        ];
    }
}
