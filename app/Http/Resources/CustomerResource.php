<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Customer */
class CustomerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address ? [
                'postalCode' => $this->address['postal_code'],
                'street' => $this->address['street'],
                'number' => $this->address['number'],
                'complement' => $this->address['complement'] ?? null,
                'neighborhood' => $this->address['neighborhood'],
                'city' => $this->address['city'],
                'state' => $this->address['state'],
            ] : null,
            'ordersCount' => $this->whenCounted('orders'),
            'lastOrderAt' => $this->getAttribute('last_order_at'),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
