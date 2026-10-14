<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_is_registered_for_the_current_team(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('customers.store'), [
            'name' => 'Maria Souza',
            'phone' => '(11) 99999-9999',
            'address' => [
                'postal_code' => '01001-000',
                'street' => 'Praça da Sé',
                'number' => '100',
                'complement' => 'Apto 12',
                'neighborhood' => 'Sé',
                'city' => 'São Paulo',
                'state' => 'SP',
            ],
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Maria Souza')
            ->assertJsonPath('data.address.city', 'São Paulo');

        $this->assertDatabaseHas('customers', [
            'team_id' => $user->current_team_id,
            'name' => 'Maria Souza',
            'phone' => '(11) 99999-9999',
        ]);
    }

    public function test_phone_must_be_unique_inside_the_same_team(): void
    {
        $user = User::factory()->create();
        Customer::factory()->create([
            'team_id' => $user->current_team_id,
            'phone' => '(11) 99999-9999',
        ]);

        $this->actingAs($user)->postJson(route('customers.store'), [
            'name' => 'Outro cliente',
            'phone' => '11999999999',
            'address' => $this->address(),
        ])->assertUnprocessable()->assertJsonValidationErrors('phone');

        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)->postJson(route('customers.store'), [
            'name' => 'Cliente de outra loja',
            'phone' => '(11) 99999-9999',
            'address' => $this->address(),
        ])->assertCreated();
    }

    public function test_customer_list_is_team_scoped_and_shows_the_popular_product(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Ana Costa',
        ]);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Finalized,
            'finalized_at' => now(),
        ]);
        $order->items()->create([
            'product_name' => 'X-Bacon',
            'unit_price' => '22.00',
            'quantity' => 3,
        ]);
        Customer::factory()->create();

        $this->actingAs($user)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('customers/Index')
                ->where('customers.total', 1)
                ->where('customers.data.0.id', $customer->id)
                ->where('customers.data.0.ordersCount', 1)
                ->where('customers.data.0.popularProduct.name', 'X-Bacon')
                ->where('customers.data.0.popularProduct.quantity', 3));
    }

    public function test_customer_details_show_purchase_history_totals_and_popular_product(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['team_id' => $user->current_team_id]);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'status' => OrderStatus::Finalized,
            'delivery_fee' => '5.00',
            'finalized_at' => now(),
        ]);
        $order->items()->create([
            'product_name' => 'X-Salada',
            'unit_price' => '20.00',
            'quantity' => 2,
        ]);

        $this->actingAs($user)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('customers/Show')
                ->where('customer.id', $customer->id)
                ->where('summary.ordersCount', 1)
                ->where('summary.finalizedOrdersCount', 1)
                ->where('summary.totalSpent', '45.00')
                ->where('summary.popularProduct.name', 'X-Salada')
                ->where('summary.popularProduct.quantity', 2)
                ->has('orders.data', 1)
                ->where('orders.data.0.id', $order->id));
    }

    public function test_a_customer_from_another_team_cannot_be_viewed(): void
    {
        $user = User::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $this->actingAs($user)
            ->get(route('customers.show', $otherCustomer))
            ->assertNotFound();
    }

    public function test_an_order_can_be_linked_only_to_a_customer_from_the_current_team(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['team_id' => $user->current_team_id]);
        $otherCustomer = Customer::factory()->create();
        $product = Product::factory()->create();
        $payload = [
            'modality' => 'retirada',
            'payment_method' => 'pix',
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ];

        $this->actingAs($user)->postJson(route('orders.store'), [
            ...$payload,
            'customer_id' => $customer->id,
        ])->assertCreated()->assertJsonPath('data.customerId', $customer->id);

        $this->assertDatabaseHas('orders', ['customer_id' => $customer->id]);

        $this->actingAs($user)->postJson(route('orders.store'), [
            ...$payload,
            'customer_id' => $otherCustomer->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('customer_id');
    }

    public function test_an_order_with_a_phone_automatically_creates_or_reuses_a_customer(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $basePayload = [
            'payment_method' => 'pix',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ];

        $firstOrderId = $this->actingAs($user)->postJson(route('orders.store'), [
            ...$basePayload,
            'modality' => 'retirada',
            'customer_name' => 'Maria Souza',
            'customer_phone' => '(11) 99999-9999',
        ])->assertCreated()->json('data.id');

        $customer = Customer::query()->sole();
        $this->assertSame('11999999999', $customer->phone_normalized);
        $this->assertDatabaseHas('orders', [
            'id' => $firstOrderId,
            'customer_id' => $customer->id,
        ]);

        $secondOrderId = $this->postJson(route('orders.store'), [
            ...$basePayload,
            'modality' => 'entrega',
            'customer_name' => 'Maria da Silva',
            'customer_phone' => '11 99999-9999',
            'delivery_address' => $this->address(),
            'delivery_fee' => '5.00',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Maria da Silva',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $secondOrderId,
            'customer_id' => $customer->id,
        ]);
        $this->assertSame('Praça da Sé', $customer->refresh()->address['street']);
    }

    public function test_an_order_without_a_phone_does_not_create_a_customer(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->postJson(route('orders.store'), [
            'modality' => 'local',
            'payment_method' => 'pix',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->assertJsonPath('data.customerId', null);

        $this->assertDatabaseCount('customers', 0);
    }

    /** @return array<string, string|null> */
    private function address(): array
    {
        return [
            'postal_code' => '01001-000',
            'street' => 'Praça da Sé',
            'number' => '100',
            'complement' => null,
            'neighborhood' => 'Sé',
            'city' => 'São Paulo',
            'state' => 'SP',
        ];
    }
}
