<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_from_the_orders_page(): void
    {
        $response = $this->get(route('orders.index'));

        $response->assertUnauthorized();
    }

    public function test_authenticated_users_can_view_orders_and_active_products(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'X-Salada']);
        Product::factory()->inactive()->create(['name' => 'Produto desativado']);

        $response = $this->actingAs($user)->getJson(route('orders.index'));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.id', $product->id)
            ->assertJsonPath('products.0.name', 'X-Salada')
            ->assertJsonCount(4, 'paymentMethods')
            ->assertJsonCount(0, 'data');
    }

    public function test_an_order_can_be_created_with_multiple_products(): void
    {
        $user = User::factory()->create();
        $burger = Product::factory()->create([
            'name' => 'X-Salada',
            'price' => '18.50',
        ]);
        $juice = Product::factory()->create([
            'name' => 'Suco natural',
            'price' => '8.00',
        ]);

        $response = $this->actingAs($user)->postJson(route('orders.store'), [
            'modality' => 'retirada',
            'customer_name' => 'Mariana Costa',
            'customer_phone' => '(11) 99999-0000',
            'payment_method' => 'pix',
            'delivery_fee' => '9.00',
            'items' => [
                ['product_id' => $burger->id, 'quantity' => 2],
                ['product_id' => $juice->id, 'quantity' => 1, 'notes' => 'Adicionar bacon'],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.id', 1)
            ->assertJsonPath('data.modality', 'retirada')
            ->assertJsonPath('data.customerName', 'Mariana Costa')
            ->assertJsonPath('data.customerPhone', '(11) 99999-0000')
            ->assertJsonPath('data.paymentMethod', 'pix')
            ->assertJsonPath('data.paymentMethodLabel', 'Pix')
            ->assertJsonPath('data.deliveryFee', null)
            ->assertJsonPath('data.total', '45.00')
            ->assertJsonPath('data.status', 'novo');

        $this->assertDatabaseHas('orders', [
            'id' => 1,
            'modality' => 'retirada',
            'customer_name' => 'Mariana Costa',
            'customer_phone' => '(11) 99999-0000',
            'payment_method' => 'pix',
            'delivery_fee' => null,
            'status' => 'novo',
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => 1,
            'product_id' => $burger->id,
            'product_name' => 'X-Salada',
            'unit_price' => '18.50',
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => 1,
            'product_id' => $juice->id,
            'product_name' => 'Suco natural',
            'unit_price' => '8.00',
            'quantity' => 1,
            'notes' => 'Adicionar bacon',
        ]);
    }

    public function test_cash_order_records_the_amount_paid_for_and_calculates_change(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => '18.50']);

        $response = $this->actingAs($user)->postJson(route('orders.store'), [
            'modality' => 'local',
            'payment_method' => 'dinheiro',
            'needs_change' => true,
            'change_for' => '50.00',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.changeFor', '50.00')
            ->assertJsonPath('data.changeAmount', '13.00');

        $this->assertDatabaseHas('orders', [
            'payment_method' => 'dinheiro',
            'change_for' => '50.00',
        ]);
    }

    public function test_cash_order_accepts_whole_reais_entered_without_decimal_places(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Cheesebúrguer', 'price' => '18.00']);

        $this->actingAs($user)->postJson(route('orders.store'), [
            'modality' => 'local',
            'payment_method' => 'dinheiro',
            'needs_change' => true,
            'change_for' => '50',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.total', '18.00')
            ->assertJsonPath('data.changeFor', '50.00')
            ->assertJsonPath('data.changeAmount', '32.00');

        $this->assertDatabaseHas('orders', ['change_for' => '50.00']);
    }

    public function test_cash_order_rejects_change_amount_below_the_order_total(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => '18.50']);

        $response = $this->actingAs($user)->postJson(route('orders.store'), [
            'modality' => 'local',
            'payment_method' => 'dinheiro',
            'needs_change' => true,
            'change_for' => '18.00',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('change_for');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_delivery_orders_require_a_phone_and_complete_address(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($user)->postJson(route('orders.store'), [
            'modality' => 'entrega',
            'payment_method' => 'dinheiro',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'customer_phone',
            'delivery_address',
            'delivery_address.postal_code',
            'delivery_address.street',
            'delivery_address.number',
            'delivery_address.neighborhood',
            'delivery_address.city',
            'delivery_address.state',
            'delivery_fee',
        ]);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_delivery_order_saves_phone_address_and_payment_method(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => '18.00']);

        $this->actingAs($user)->postJson(route('orders.store'), [
            'modality' => 'entrega',
            'customer_name' => 'Mariana Costa',
            'customer_phone' => '(11) 99999-0000',
            'payment_method' => 'credito',
            'delivery_fee' => '5.50',
            'delivery_address' => [
                'postal_code' => '01001-000',
                'street' => 'Praça da Sé',
                'number' => '100',
                'complement' => 'Apto 2',
                'neighborhood' => 'Sé',
                'city' => 'São Paulo',
                'state' => 'SP',
            ],
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.paymentMethodLabel', 'Crédito')
            ->assertJsonPath('data.deliveryAddress.postalCode', '01001-000')
            ->assertJsonPath('data.deliveryAddress.street', 'Praça da Sé')
            ->assertJsonPath('data.deliveryAddress.number', '100')
            ->assertJsonPath('data.deliveryFee', '5.50')
            ->assertJsonPath('data.total', '23.50');

        $this->assertDatabaseHas('orders', [
            'customer_name' => 'Mariana Costa',
            'customer_phone' => '(11) 99999-0000',
            'payment_method' => 'credito',
            'change_for' => null,
            'delivery_fee' => '5.50',
        ]);
    }

    public function test_delivery_fee_is_included_in_cash_change_and_must_be_non_negative(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => '18.00']);
        $address = [
            'postal_code' => '01001-000',
            'street' => 'Praça da Sé',
            'number' => '100',
            'neighborhood' => 'Sé',
            'city' => 'São Paulo',
            'state' => 'SP',
        ];
        $order = [
            'modality' => 'entrega',
            'customer_phone' => '(11) 99999-0000',
            'payment_method' => 'dinheiro',
            'needs_change' => true,
            'change_for' => '50',
            'delivery_address' => $address,
            'delivery_fee' => '7',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ];

        $this->actingAs($user)->postJson(route('orders.store'), $order)
            ->assertCreated()
            ->assertJsonPath('data.deliveryFee', '7.00')
            ->assertJsonPath('data.total', '25.00')
            ->assertJsonPath('data.changeAmount', '25.00');

        $this->actingAs($user)->postJson(route('orders.store'), [...$order, 'delivery_fee' => '0'])
            ->assertCreated()
            ->assertJsonPath('data.deliveryFee', '0.00')
            ->assertJsonPath('data.total', '18.00')
            ->assertJsonPath('data.changeAmount', '32.00');

        $this->actingAs($user)->postJson(route('orders.store'), [...$order, 'change_for' => '24.00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('change_for');

        $this->actingAs($user)->postJson(route('orders.store'), [...$order, 'delivery_fee' => '-1.00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('delivery_fee');
    }

    public function test_order_creation_rejects_inactive_products(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inactive()->create();

        $response = $this->actingAs($user)->postJson(route('orders.store'), [
            'modality' => 'local',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_creation_rejects_soft_deleted_products(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $product->delete();

        $this->actingAs($user)->postJson(route('orders.store'), [
            'modality' => 'local',
            'payment_method' => 'pix',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_creation_requires_at_least_one_item(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('orders.store'), [
            'modality' => 'local',
            'items' => [],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_orders_follow_the_status_flow_without_recording_payment_yet(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['status' => 'novo']);

        $this->actingAs($user)
            ->patchJson(route('orders.advance', $order), ['status' => 'em_preparo'])
            ->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'em_preparo',
            'payment_method' => null,
        ]);

        $this->patchJson(route('orders.advance', $order), ['status' => 'pronto'])
            ->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pronto',
            'payment_method' => null,
        ]);

        $this->patchJson(route('orders.advance', $order), [
            'status' => 'finalizado',
        ])->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'finalizado',
            'payment_method' => null,
        ]);
        $this->assertNotNull($order->refresh()->finalized_at);
    }

    public function test_an_order_cannot_skip_a_status(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['status' => 'novo']);

        $response = $this->actingAs($user)->patchJson(route('orders.advance', $order), [
            'status' => 'pronto',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'novo',
            'payment_method' => null,
        ]);
    }

    public function test_a_finalized_order_cannot_be_advanced_again(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'status' => 'finalizado',
            'payment_method' => 'dinheiro',
        ]);

        $response = $this->actingAs($user)->patchJson(route('orders.advance', $order), [
            'status' => 'finalizado',
            'payment_method' => 'pix',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'finalizado',
            'payment_method' => 'dinheiro',
        ]);
    }

    public function test_order_items_keep_the_original_product_name_and_price(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'X-Salada',
            'price' => '18.50',
        ]);
        $order = Order::factory()->create();
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'X-Salada',
            'unit_price' => '18.50',
        ]);

        $product->update([
            'name' => 'X-Salada especial',
            'price' => '22.00',
        ]);

        $response = $this->actingAs($user)->getJson(route('orders.index'));

        $response
            ->assertOk()
            ->assertJsonPath('data.0.items.0.productName', 'X-Salada')
            ->assertJsonPath('data.0.items.0.unitPrice', '18.50');

        $this->assertModelExists($item);
    }

    public function test_order_applies_selected_options_and_preserves_their_snapshot(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'X-Bacon', 'price' => '20.00']);
        $group = $product->optionGroups()->create([
            'name' => 'Adicionais',
            'min_selections' => 1,
            'max_selections' => 2,
        ]);
        $bacon = $group->options()->create(['name' => 'Bacon extra', 'price' => '4.00', 'active' => true]);

        $response = $this->actingAs($user)->postJson(route('orders.store'), [
            'modality' => 'local',
            'payment_method' => 'pix',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'option_ids' => [$bacon->id],
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.total', '48.00')
            ->assertJsonPath('data.items.0.unitPrice', '24.00')
            ->assertJsonPath('data.items.0.selectedOptions.0.name', 'Bacon extra')
            ->assertJsonPath('data.items.0.selectedOptions.0.price', '4.00');

        $this->assertDatabaseHas('order_items', ['unit_price' => '24.00', 'quantity' => 2]);
    }

    public function test_order_rejects_missing_required_options_and_options_from_another_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $group = $product->optionGroups()->create(['name' => 'Escolha', 'min_selections' => 1, 'max_selections' => 1]);
        $group->options()->create(['name' => 'Única', 'price' => '0.00', 'active' => true]);
        $otherProduct = Product::factory()->create();
        $otherGroup = $otherProduct->optionGroups()->create(['name' => 'Outro', 'min_selections' => 0, 'max_selections' => 1]);
        $otherOption = $otherGroup->options()->create(['name' => 'Inválida', 'price' => '1.00', 'active' => true]);
        $payload = [
            'modality' => 'local',
            'payment_method' => 'pix',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'option_ids' => []]],
        ];

        $this->actingAs($user)->postJson(route('orders.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.option_ids');

        $payload['items'][0]['option_ids'] = [$otherOption->id];
        $this->actingAs($user)->postJson(route('orders.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.option_ids');
    }
}
