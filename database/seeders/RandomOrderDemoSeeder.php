<?php

namespace Database\Seeders;

use App\Enums\OrderModality;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RandomOrderDemoSeeder extends Seeder
{
    private const ORDER_COUNT = 50;

    public function run(): void
    {
        $teamId = Team::query()->value('id') ?? User::factory()->create()->current_team_id;

        $existingDemoOrders = Order::query()
            ->where('team_id', $teamId)
            ->where('customer_name', 'like', 'Demo #%')
            ->count();

        if ($existingDemoOrders >= self::ORDER_COUNT) {
            return;
        }

        $products = Product::query()->where('team_id', $teamId)->where('active', true)->get();

        if ($products->isEmpty()) {
            $this->call(ProductDemoSeeder::class);
            $products = Product::query()->where('team_id', $teamId)->where('active', true)->get();
        }

        $courierIds = Courier::query()->where('team_id', $teamId)->where('active', true)->pluck('id');
        $remaining = self::ORDER_COUNT - $existingDemoOrders;
        $modalities = $this->shuffledCases(OrderModality::cases(), $remaining);
        $paymentMethods = $this->shuffledCases(PaymentMethod::cases(), $remaining);
        $statuses = $this->shuffledCases(OrderStatus::cases(), $remaining);

        DB::transaction(function () use ($products, $courierIds, $remaining, $existingDemoOrders, $modalities, $paymentMethods, $statuses, $teamId): void {
            for ($index = 0; $index < $remaining; $index++) {
                $modality = $modalities[$index];
                $status = $statuses[$index];
                $createdAt = now()->subMinutes(random_int(0, 480));
                $courierId = $modality === OrderModality::Delivery
                    && in_array($status, [OrderStatus::Ready, OrderStatus::Finalized], true)
                    && $courierIds->isNotEmpty()
                    ? $courierIds->random()
                    : null;

                if ($modality === OrderModality::Delivery && $courierId === null && in_array($status, [OrderStatus::Ready, OrderStatus::Finalized], true)) {
                    $status = OrderStatus::InPreparation;
                }

                $order = Order::factory()->create([
                    'team_id' => $teamId,
                    'modality' => $modality,
                    'status' => $status,
                    'finalized_at' => $status === OrderStatus::Finalized ? $createdAt : null,
                    'cancelled_at' => $status === OrderStatus::Cancelled ? $createdAt : null,
                    'cancellation_reason' => $status === OrderStatus::Cancelled
                        ? 'Cancelamento de demonstração'
                        : null,
                    'payment_method' => $paymentMethods[$index],
                    'customer_name' => sprintf('Demo #%03d - %s', $existingDemoOrders + $index + 1, fake('pt_BR')->name()),
                    'customer_phone' => fake()->numerify('(11) 9####-####'),
                    'delivery_address' => $modality === OrderModality::Delivery ? [
                        'postal_code' => '01001-000',
                        'street' => fake('pt_BR')->streetName(),
                        'number' => (string) random_int(1, 999),
                        'neighborhood' => 'Centro',
                        'city' => 'São Paulo',
                        'state' => 'SP',
                    ] : null,
                    'delivery_fee' => $modality === OrderModality::Delivery ? random_int(4, 12).'.00' : null,
                    'courier_id' => $courierId,
                    'dispatched_at' => $modality === OrderModality::Delivery && $status === OrderStatus::Finalized ? $createdAt->copy()->addMinutes(20) : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $chosenProducts = $products->random(min(random_int(1, 3), $products->count()));

                foreach ($chosenProducts as $product) {
                    $order->items()->create([
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'unit_price' => $product->price,
                        'quantity' => random_int(1, 3),
                    ]);
                }
            }
        });
    }

    /**
     * @template T of \BackedEnum
     *
     * @param  list<T>  $cases
     * @return list<T>
     */
    private function shuffledCases(array $cases, int $count): array
    {
        $values = [];

        for ($index = 0; $index < $count; $index++) {
            $values[] = $cases[$index % count($cases)];
        }

        shuffle($values);

        return $values;
    }
}
