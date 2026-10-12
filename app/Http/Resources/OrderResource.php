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
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'nextStatus' => $this->status->next()?->value,
            'total' => $this->whenLoaded('items', fn (): string => $this->total()),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
