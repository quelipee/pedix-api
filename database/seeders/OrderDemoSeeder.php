<?php

namespace Database\Seeders;

use App\Enums\OrderModality;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderDemoSeeder extends Seeder
{
    /**
     * Seed sample orders for the dashboard without duplicating real orders.
     */
    public function run(): void
    {
        if (Order::query()->exists()) {
            return;
        }

        $products = Product::query()
            ->where('active', true)
            ->orderBy('id')
            ->limit(2)
            ->get()
            ->values();

        if ($products->isEmpty()) {
            return;
        }

        /** @var list<array{modality: OrderModality, status: OrderStatus, payment_method: PaymentMethod|null, minutes_ago: int, quantities: list<int>}> $orders */
        $orders = [
            ['modality' => OrderModality::Delivery, 'status' => OrderStatus::New, 'payment_method' => null, 'minutes_ago' => 7, 'quantities' => [1, 1]],
            ['modality' => OrderModality::Takeaway, 'status' => OrderStatus::New, 'payment_method' => null, 'minutes_ago' => 15, 'quantities' => [2, 1]],
            ['modality' => OrderModality::DineIn, 'status' => OrderStatus::InPreparation, 'payment_method' => null, 'minutes_ago' => 23, 'quantities' => [1, 2]],
            ['modality' => OrderModality::Takeaway, 'status' => OrderStatus::InPreparation, 'payment_method' => null, 'minutes_ago' => 38, 'quantities' => [2, 2]],
            ['modality' => OrderModality::Delivery, 'status' => OrderStatus::Ready, 'payment_method' => null, 'minutes_ago' => 47, 'quantities' => [1, 1]],
            ['modality' => OrderModality::DineIn, 'status' => OrderStatus::Ready, 'payment_method' => null, 'minutes_ago' => 65, 'quantities' => [1, 2]],
            ['modality' => OrderModality::Takeaway, 'status' => OrderStatus::Finalized, 'payment_method' => PaymentMethod::Pix, 'minutes_ago' => 90, 'quantities' => [2, 1]],
            ['modality' => OrderModality::Delivery, 'status' => OrderStatus::Finalized, 'payment_method' => PaymentMethod::Cash, 'minutes_ago' => 130, 'quantities' => [1, 2]],
        ];

        $seededAt = now();

        DB::transaction(function () use ($orders, $products, $seededAt): void {
            foreach ($orders as $orderData) {
                $createdAt = $seededAt->copy()->subMinutes($orderData['minutes_ago']);

                $order = Order::factory()->create([
                    'modality' => $orderData['modality'],
                    'status' => $orderData['status'],
                    'payment_method' => $orderData['payment_method'],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                foreach ($products as $index => $product) {
                    $order->items()->create([
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'unit_price' => $product->price,
                        'quantity' => $orderData['quantities'][$index] ?? 1,
                    ]);
                }
            }
        });
    }
}
