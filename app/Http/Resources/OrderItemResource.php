<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderItem */
class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $priceInCents = (int) str_replace('.', '', (string) $this->unit_price);
        $subtotalInCents = $priceInCents * $this->quantity;

        return [
            'id' => $this->id,
            'productId' => $this->product_id,
            'productName' => $this->product_name,
            'unitPrice' => $this->unit_price,
            'quantity' => $this->quantity,
            'notes' => $this->notes,
            'selectedOptions' => $this->selected_options ?? [],
            'subtotal' => intdiv($subtotalInCents, 100).'.'.str_pad((string) ($subtotalInCents % 100), 2, '0', STR_PAD_LEFT),
        ];
    }
}
