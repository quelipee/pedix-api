<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;

/** @implements Arrayable<string, mixed> */
readonly class OrderCommandData implements Arrayable
{
    /**
     * @param  array<string, string|null>|null  $deliveryAddress
     * @param  array<int, array{productName: string, quantity: int, unitPrice: string, subtotal: string, notes: string|null, selectedOptions: array<int, array{id: int, group: string, name: string, price: string}>}>  $items
     */
    public function __construct(
        public int $number,
        public string $teamName,
        public string $createdAt,
        public string $modality,
        public ?string $customerName,
        public ?string $customerPhone,
        public ?array $deliveryAddress,
        public array $items,
        public ?string $deliveryFee,
        public string $total,
        public ?string $paymentMethod,
        public ?string $changeFor,
        public ?string $changeAmount,
        public ?string $courierName,
    ) {
        //
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
