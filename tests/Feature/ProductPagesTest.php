<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_open_the_catalog_or_product_creation_page(): void
    {
        $this->get(route('products.catalog', ['current_team' => 'lanchonete']))
            ->assertRedirect(route('login'));
        $this->get(route('products.create', ['current_team' => 'lanchonete']))
            ->assertRedirect(route('login'));
        $this->get(route('products.edit', ['current_team' => 'lanchonete', 'product' => 1]))
            ->assertRedirect(route('login'));
    }

    public function test_catalog_lists_products_with_their_ingredients_and_availability(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'X-Salada',
            'category' => 'Lanches',
            'description' => 'Com salada fresca.',
            'ingredients' => ['Pão', 'Hambúrguer', 'Alface', 'Tomate'],
            'price' => '22.00',
        ]);
        Product::factory()->inactive()->create(['name' => 'Produto indisponível']);

        $this->actingAs($user)->get(route('products.catalog'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('products/Index')
                ->has('products', 2)
                ->where('products.0.id', $product->id)
                ->where('products.0.description', 'Com salada fresca.')
                ->where('products.0.ingredients.3', 'Tomate')
                ->where('products.0.active', true),
            );
    }

    public function test_product_creation_page_suggests_existing_categories(): void
    {
        $user = User::factory()->create();
        Product::factory()->create(['category' => 'Sobremesas']);

        $this->actingAs($user)->get(route('products.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('products/Form')
                ->has('categories', 5)
                ->where('categories.4', 'Sobremesas'),
            );
    }

    public function test_soft_deleted_products_are_hidden_from_the_catalog(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $product->delete();

        $this->actingAs($user)->get(route('products.catalog'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('products/Index')
                ->has('products', 0),
            );
    }

    public function test_edit_page_shows_the_existing_product_and_categories(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'X-Salada',
            'category' => 'Lanches',
            'description' => 'Hambúrguer com salada.',
            'ingredients' => ['Pão', 'Hambúrguer', 'Alface'],
            'price' => '22.00',
        ]);

        $this->actingAs($user)->get(route('products.edit', ['product' => $product]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('products/Form')
                ->where('product.id', $product->id)
                ->where('product.name', 'X-Salada')
                ->where('product.description', 'Hambúrguer com salada.')
                ->where('product.ingredients.2', 'Alface')
                ->where('product.price', '22.00')
                ->has('categories'),
            );
    }
}
