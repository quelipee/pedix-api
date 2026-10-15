<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_from_the_products_page(): void
    {
        $response = $this->get(route('products.index'));

        $response->assertUnauthorized();
    }

    public function test_authenticated_users_can_view_the_product_catalog(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'X-Salada', 'price' => '15.00']);

        $response = $this->actingAs($user)->getJson(route('products.index'));

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.name', 'X-Salada')
            ->assertJsonPath('data.0.price', '15.00')
            ->assertJsonPath('data.0.active', true);
    }

    public function test_authenticated_users_can_create_a_product(): void
    {
        $user = User::factory()->create();
        $category = ProductCategory::factory()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Bebidas',
        ]);

        $response = $this->actingAs($user)->postJson(route('products.store'), [
            'name' => 'Suco natural',
            'category_id' => $category->id,
            'price' => '8.50',
            'active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Suco natural')
            ->assertJsonPath('data.category', 'Bebidas');

        $this->assertDatabaseHas('products', [
            'team_id' => $user->current_team_id,
            'name' => 'Suco natural',
            'category' => 'Bebidas',
            'price' => '8.50',
            'active' => true,
        ]);
    }

    public function test_product_can_store_a_description_and_ingredients(): void
    {
        $user = User::factory()->create();
        $category = ProductCategory::factory()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Lanches',
        ]);

        $response = $this->actingAs($user)->postJson(route('products.store'), [
            'name' => 'X-Salada',
            'category_id' => $category->id,
            'description' => 'Hambúrguer com salada fresca.',
            'ingredients' => ['Pão', 'Hambúrguer', 'Alface', 'Tomate'],
            'price' => '22.00',
            'active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.description', 'Hambúrguer com salada fresca.')
            ->assertJsonPath('data.ingredients.2', 'Alface')
            ->assertJsonPath('data.price', '22.00');

        $product = Product::query()->where('name', 'X-Salada')->firstOrFail();
        $this->assertSame(['Pão', 'Hambúrguer', 'Alface', 'Tomate'], $product->ingredients);
    }

    public function test_product_creation_rejects_duplicate_ingredients(): void
    {
        $user = User::factory()->create();
        $category = ProductCategory::factory()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Lanches',
        ]);

        $this->actingAs($user)->postJson(route('products.store'), [
            'name' => 'X-Salada',
            'category_id' => $category->id,
            'ingredients' => ['Tomate', 'Tomate'],
            'price' => '22.00',
            'active' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('ingredients.1');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_product_creation_requires_a_registered_category(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('products.store'), [
            'name' => 'Água mineral',
            'price' => '4.00',
            'active' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');

        $this->assertDatabaseMissing('products', ['name' => 'Água mineral']);
    }

    public function test_product_creation_rejects_a_zero_price(): void
    {
        $user = User::factory()->create();
        $category = ProductCategory::factory()->create(['team_id' => $user->current_team_id]);

        $response = $this->actingAs($user)->postJson(route('products.store'), [
            'name' => 'Suco natural',
            'category_id' => $category->id,
            'price' => '0.00',
            'active' => true,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('price');

        $this->assertDatabaseMissing('products', [
            'name' => 'Suco natural',
        ]);
    }

    public function test_authenticated_users_can_update_and_deactivate_a_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'X-Salada',
            'category' => 'Lanches',
            'price' => '15.00',
            'active' => true,
        ]);
        $product->refresh();

        $response = $this->actingAs($user)->patchJson(route('products.update', $product), [
            'name' => 'X-Salada especial',
            'category_id' => $product->category_id,
            'price' => '18.00',
            'active' => false,
        ]);

        $response->assertOk()->assertJsonPath('data.active', false);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'X-Salada especial',
            'price' => '18.00',
            'active' => false,
        ]);
    }

    public function test_an_inactive_product_can_be_reactivated_and_used_in_orders(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inactive()->create([
            'name' => 'X-Bacon',
            'category' => 'Lanches',
            'price' => '24.00',
        ]);
        $product->refresh();

        $this->actingAs($user)->patchJson(route('products.update', $product), [
            'name' => $product->name,
            'category_id' => $product->category_id,
            'description' => $product->description,
            'ingredients' => $product->ingredients,
            'price' => $product->price,
            'active' => true,
        ])->assertOk()->assertJsonPath('data.active', true);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'active' => true]);
        $this->actingAs($user)->getJson(route('orders.index'))
            ->assertOk()
            ->assertJsonPath('products.0.id', $product->id);
    }

    public function test_soft_deleting_a_product_preserves_order_history_and_hides_it_from_the_catalog(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'X-Salada', 'price' => '22.00']);
        $order = Order::factory()->create();
        $orderItem = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => 'X-Salada',
            'unit_price' => '22.00',
            'quantity' => 1,
        ]);

        $this->actingAs($user)->deleteJson(route('products.destroy', $product))->assertNoContent();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertDatabaseHas('order_items', [
            'id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => 'X-Salada',
            'unit_price' => '22.00',
        ]);
        $this->actingAs($user)->getJson(route('products.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->actingAs($user)->getJson(route('orders.index'))
            ->assertOk()
            ->assertJsonCount(0, 'products')
            ->assertJsonPath('data.0.items.0.productName', 'X-Salada');
        $this->actingAs($user)->get(route('products.edit', ['product' => $product]))->assertNotFound();
        $this->actingAs($user)->patchJson(route('products.update', $product), [
            'name' => 'X-Salada',
            'price' => '22.00',
            'active' => true,
        ])->assertNotFound();
        $this->actingAs($user)->deleteJson(route('products.destroy', $product))->assertNotFound();
    }

    public function test_product_can_be_created_with_option_groups_and_additional_prices(): void
    {
        $user = User::factory()->create();
        $category = ProductCategory::factory()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Lanches',
        ]);

        $response = $this->actingAs($user)->postJson(route('products.store'), [
            'name' => 'X-Bacon',
            'category_id' => $category->id,
            'price' => '22.00',
            'active' => true,
            'option_groups' => [[
                'name' => 'Adicionais',
                'min_selections' => 0,
                'max_selections' => 2,
                'options' => [
                    ['name' => 'Bacon extra', 'price' => '4.00', 'active' => true],
                    ['name' => 'Queijo extra', 'price' => '2.50', 'active' => true],
                ],
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.optionGroups.0.name', 'Adicionais')
            ->assertJsonPath('data.optionGroups.0.options.0.name', 'Bacon extra')
            ->assertJsonPath('data.optionGroups.0.options.0.price', '4.00');

        $this->assertDatabaseHas('product_option_groups', ['name' => 'Adicionais', 'max_selections' => 2]);
        $this->assertDatabaseHas('product_options', ['name' => 'Bacon extra', 'price' => '4.00']);
    }

    public function test_updating_availability_without_option_payload_preserves_existing_options(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'X-Bacon']);
        $product->refresh();
        $group = $product->optionGroups()->create([
            'name' => 'Adicionais',
            'min_selections' => 0,
            'max_selections' => 2,
        ]);
        $option = $group->options()->create([
            'name' => 'Bacon extra',
            'price' => '4.00',
            'active' => true,
        ]);

        $this->actingAs($user)->patchJson(route('products.update', $product), [
            'name' => $product->name,
            'category_id' => $product->category_id,
            'description' => $product->description,
            'ingredients' => $product->ingredients,
            'price' => $product->price,
            'active' => false,
        ])->assertOk();

        $this->assertModelExists($group);
        $this->assertModelExists($option);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'active' => false]);
    }

    public function test_products_are_isolated_between_teams(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownProduct = Product::factory()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Produto da equipe',
        ]);
        $otherProduct = Product::factory()->create([
            'team_id' => $otherUser->current_team_id,
            'name' => 'Produto de outra equipe',
        ]);
        $ownProduct->refresh();

        $this->actingAs($user)->getJson(route('products.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownProduct->id);

        $payload = [
            'name' => 'Alteração indevida',
            'category_id' => $ownProduct->category_id,
            'price' => '10.00',
            'active' => true,
        ];

        $this->actingAs($user)->patchJson(route('products.update', $otherProduct), $payload)->assertNotFound();
        $this->actingAs($user)->deleteJson(route('products.destroy', $otherProduct))->assertNotFound();
        $this->actingAs($user)->get(route('products.edit', ['product' => $otherProduct]))->assertNotFound();

        $this->assertDatabaseHas('products', [
            'id' => $otherProduct->id,
            'team_id' => $otherUser->current_team_id,
            'name' => 'Produto de outra equipe',
            'deleted_at' => null,
        ]);
    }
}
