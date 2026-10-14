<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KitchenPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_open_the_active_order_board(): void
    {
        $this->get(route('orders.manage', ['current_team' => 'lanchonete']))
            ->assertRedirect(route('login'));
    }

    public function test_the_kitchen_panel_groups_active_orders_and_excludes_finalized_orders(): void
    {
        $user = User::factory()->create();
        $newOrder = Order::factory()->create(['status' => OrderStatus::New]);
        $newOrder->items()->create([
            'product_name' => 'X-Salada',
            'unit_price' => '22.00',
            'quantity' => 2,
            'notes' => 'Sem cebola',
        ]);
        Order::factory()->create(['status' => OrderStatus::InPreparation]);
        Order::factory()->create(['status' => OrderStatus::Ready]);
        Order::factory()->create([
            'status' => OrderStatus::Finalized,
            'finalized_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('orders.manage'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('orders/Index')
                ->where('counts.novo', 1)
                ->where('counts.em_preparo', 1)
                ->where('counts.pronto', 1)
                ->has('columns.novo', 1)
                ->where('columns.novo.0.id', $newOrder->id)
                ->where('columns.novo.0.items.0.productName', 'X-Salada')
                ->where('columns.novo.0.items.0.notes', 'Sem cebola')
                ->has('columns.em_preparo', 1)
                ->has('columns.pronto', 1),
            );
    }

    public function test_each_kitchen_column_is_limited_to_the_50_oldest_orders(): void
    {
        $user = User::factory()->create();
        $orders = Order::factory()->count(51)->create([
            'status' => OrderStatus::New,
        ]);

        $this->actingAs($user)
            ->get(route('orders.manage'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('counts.novo', 51)
                ->has('columns.novo', 50)
                ->where('columns.novo.0.id', $orders->first()->id)
                ->where('columns.novo.49.id', $orders->get(49)->id),
            );
    }
}
