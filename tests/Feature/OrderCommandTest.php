<?php

namespace Tests\Feature;

use App\Enums\OrderModality;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_open_an_order_command(): void
    {
        $order = Order::factory()->create();

        $this->get(route('orders.command', [
            'current_team' => $order->team,
            'order' => $order,
        ]))->assertRedirect(route('login'));
    }

    public function test_team_member_can_view_a_formatted_order_command(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $order = Order::factory()->create([
            'team_id' => $team->id,
            'modality' => OrderModality::Takeaway,
            'customer_name' => 'Felipe Matheus',
            'customer_phone' => '(11) 99999-9999',
            'payment_method' => PaymentMethod::Cash,
            'change_for' => '50.00',
        ]);
        $order->items()->create([
            'product_name' => 'X-Bacon',
            'unit_price' => '22.00',
            'quantity' => 2,
            'notes' => 'Sem cebola',
            'selected_options' => [[
                'id' => 1,
                'group' => 'Adicionais',
                'name' => 'Bacon',
                'price' => '4.00',
            ]],
        ]);

        $this->actingAs($user)
            ->get(route('orders.command', [
                'current_team' => $team,
                'order' => $order,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('orders/Command')
                ->where('command.number', $order->number)
                ->where('command.teamName', $team->name)
                ->where('command.customerName', 'Felipe Matheus')
                ->where('command.modality', 'Retirada')
                ->where('command.items.0.productName', 'X-Bacon')
                ->where('command.items.0.quantity', 2)
                ->where('command.items.0.subtotal', '44.00')
                ->where('command.items.0.notes', 'Sem cebola')
                ->where('command.items.0.selectedOptions.0.name', 'Bacon')
                ->where('command.total', '44.00')
                ->where('command.paymentMethod', 'Dinheiro')
                ->where('command.changeAmount', '6.00'));
    }

    public function test_order_command_from_another_team_is_not_exposed(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $order = Order::factory()->create(['team_id' => $otherUser->current_team_id]);

        $this->actingAs($user)
            ->get(route('orders.command', [
                'current_team' => $user->currentTeam,
                'order' => $order,
            ]))
            ->assertNotFound();
    }
}
