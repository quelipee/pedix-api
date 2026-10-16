<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderCreatePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_open_the_order_creation_view_with_active_products(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'X-Bacon',
            'category' => 'Lanches',
            'description' => 'Hambúrguer com bacon crocante.',
            'ingredients' => ['Pão', 'Hambúrguer', 'Bacon'],
        ]);
        Product::factory()->inactive()->create();
        $customer = Customer::factory()->create(['team_id' => $user->current_team_id]);

        $response = $this->actingAs($user)->get(route('orders.create'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('orders/Create')
            ->has('products', 1)
            ->where('products.0.id', $product->id)
            ->where('products.0.name', 'X-Bacon')
            ->where('products.0.category', 'Lanches')
            ->where('products.0.description', 'Hambúrguer com bacon crocante.')
            ->where('products.0.ingredients.2', 'Bacon')
            ->has('modalities', 3)
            ->has('paymentMethods', 5)
            ->has('customers', 1)
            ->where('customers.0.id', $customer->id));
    }

    public function test_order_creation_page_labels_dine_in_as_consumo(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('orders.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('modalities.0.label', 'Consumo'),
            );
    }

    public function test_guests_are_redirected_to_login_from_the_order_creation_view(): void
    {
        $response = $this->get(route('orders.create', ['current_team' => 'lanchonete']));

        $response->assertRedirect(route('login'));
    }
}
