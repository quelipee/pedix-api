<?php

namespace Tests\Feature;

use App\Enums\OrderModality;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Courier;
use App\Models\Order;
use Database\Seeders\RandomOrderDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RandomOrderDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_50_varied_orders_without_changing_existing_orders_or_duplicating_on_a_second_run(): void
    {
        $existingOrder = Order::factory()->create();
        Courier::factory()->create();

        $this->seed(RandomOrderDemoSeeder::class);

        $demoOrders = Order::query()->where('customer_name', 'like', 'Demo #%')->get();

        $this->assertCount(50, $demoOrders);
        $this->assertDatabaseCount('orders', 51);
        $this->assertModelExists($existingOrder);
        $this->assertGreaterThanOrEqual(50, $demoOrders->sum(fn (Order $order): int => $order->items()->count()));

        foreach (OrderModality::cases() as $modality) {
            $this->assertTrue($demoOrders->contains('modality', $modality));
        }

        foreach (PaymentMethod::cases() as $paymentMethod) {
            $this->assertTrue($demoOrders->contains('payment_method', $paymentMethod));
        }

        foreach (OrderStatus::cases() as $status) {
            $this->assertTrue($demoOrders->contains('status', $status));
        }

        $deliveryOrders = $demoOrders->where('modality', OrderModality::Delivery);
        $this->assertNotEmpty($deliveryOrders);

        foreach ($deliveryOrders as $order) {
            $this->assertNotNull($order->delivery_address);
            $this->assertNotNull($order->delivery_fee);

            if (in_array($order->status, [OrderStatus::Ready, OrderStatus::Finalized], true)) {
                $this->assertNotNull($order->courier_id);
            }
        }

        $this->seed(RandomOrderDemoSeeder::class);

        $this->assertDatabaseCount('orders', 51);
    }
}
