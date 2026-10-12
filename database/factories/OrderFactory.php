<?php

namespace Database\Factories;

use App\Enums\OrderModality;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'modality' => OrderModality::Takeaway,
            'status' => OrderStatus::New,
            'payment_method' => null,
        ];
    }
}
