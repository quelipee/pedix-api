<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderManagementPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_open_the_order_management_page(): void
    {
        $this->get(route('orders.history', ['current_team' => 'lanchonete']))
            ->assertRedirect(route('login'));
    }

    public function test_management_page_lists_orders_with_status_counts_and_details(): void
    {
        $user = User::factory()->create();
        Order::factory()->create(['status' => OrderStatus::New]);
        $order = Order::factory()->create([
            'status' => OrderStatus::InPreparation,
            'customer_name' => 'Ana Costa',
            'customer_phone' => '(11) 99999-0000',
            'payment_method' => 'dinheiro',
            'change_for' => '50.00',
        ]);
        $order->items()->create([
            'product_name' => 'Cheesebúrguer',
            'unit_price' => '18.00',
            'quantity' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('orders.history'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('orders/History')
                ->where('filters.status', 'todos')
                ->where('statusCounts.todos', 2)
                ->where('statusCounts.novo', 1)
                ->where('statusCounts.em_preparo', 1)
                ->has('orders.data', 2)
                ->where('orders.data.0.id', $order->id)
                ->where('orders.data.0.customerName', 'Ana Costa')
                ->where('orders.data.0.nextStatus', 'pronto')
                ->where('orders.data.0.total', '18.00')
                ->where('orders.data.0.changeAmount', '32.00')
                ->where('orders.data.0.items.0.productName', 'Cheesebúrguer'),
            );
    }

    public function test_management_page_filters_by_status_and_searches_customer_or_number(): void
    {
        $user = User::factory()->create();
        Order::factory()->create([
            'status' => OrderStatus::New,
            'customer_name' => 'Bruno Lima',
        ]);
        $readyOrder = Order::factory()->create([
            'status' => OrderStatus::Ready,
            'customer_name' => 'Ana Costa',
            'customer_phone' => '(11) 99999-0000',
        ]);

        $this->actingAs($user)
            ->get(route('orders.history', ['status' => 'pronto', 'q' => 'Ana']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'pronto')
                ->where('filters.q', 'Ana')
                ->has('orders.data', 1)
                ->where('orders.data.0.id', $readyOrder->id),
            );

        $this->get(route('orders.history', ['q' => (string) $readyOrder->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('orders.data', 1)
                ->where('orders.data.0.id', $readyOrder->id),
            );

        $this->get(route('orders.history', ['q' => '99999-0000']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('orders.data', 1)
                ->where('orders.data.0.id', $readyOrder->id),
            );
    }

    public function test_management_page_paginates_orders(): void
    {
        $user = User::factory()->create();
        Order::factory()->count(13)->create();

        $this->actingAs($user)
            ->get(route('orders.history', ['page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('orders.data', 1)
                ->where('orders.currentPage', 2)
                ->where('orders.lastPage', 2)
                ->where('orders.total', 13),
            );
    }

    public function test_finalized_orders_are_filtered_by_completion_date_and_remain_paginated(): void
    {
        $user = User::factory()->create();
        Order::factory()->count(15)->create([
            'status' => OrderStatus::Finalized,
            'finalized_at' => now(),
        ]);
        Order::factory()->count(2)->create([
            'status' => OrderStatus::Finalized,
            'finalized_at' => now()->subDays(3),
        ]);
        Order::factory()->create([
            'status' => OrderStatus::Finalized,
            'finalized_at' => now()->subDays(45),
        ]);

        $this->actingAs($user)
            ->get(route('orders.history', ['status' => 'finalizado']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.period', 'today')
                ->where('orders.total', 15)
                ->where('orders.lastPage', 2)
                ->has('orders.data', 12)
                ->where('statusCounts.finalizado', 18),
            );

        $this->get(route('orders.history', ['status' => 'finalizado', 'page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('orders.currentPage', 2)
                ->has('orders.data', 3),
            );

        $this->get(route('orders.history', ['status' => 'finalizado', 'period' => '7d']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('orders.total', 17),
            );

        $this->get(route('orders.history', [
            'status' => 'finalizado',
            'period' => 'custom',
            'date_from' => now()->subDays(3)->toDateString(),
            'date_to' => now()->subDays(3)->toDateString(),
        ]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('orders.total', 2)
                ->has('orders.data', 2),
            );

        $this->get(route('orders.history', ['status' => 'finalizado', 'period' => 'all']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('orders.total', 18),
            );
    }
}
