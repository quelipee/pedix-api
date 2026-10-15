<?php

namespace Database\Factories;

use App\Enums\OrderModality;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Team;
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
            'team_id' => fn () => Team::query()->value('id') ?? Team::factory(),
            'modality' => OrderModality::Takeaway,
            'status' => OrderStatus::New,
            'payment_method' => null,
        ];
    }
}
