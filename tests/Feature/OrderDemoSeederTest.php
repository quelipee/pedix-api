<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\OrderDemoSeeder;
use Database\Seeders\ProductDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_populates_dashboard_with_demo_orders_and_items(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.todayOrders', 8)
            ->where('stats.newOrders', 2)
            ->where('stats.inPreparation', 2)
            ->where('stats.ready', 2)
            ->where('stats.finalized', 2)
            ->has('recentOrders', 5)
            ->where('recentOrders.0.status', OrderStatus::New->value),
        );

        $this->assertDatabaseCount('orders', 8);
        $this->assertDatabaseCount('order_items', 16);
        $this->assertSame([
            'Coca-Cola Original lata 350 ml',
            'Coca-Cola Zero lata 350 ml',
            'Sprite lata 350 ml',
            'Suco natural',
        ], Product::query()->where('category', 'Bebidas')->orderBy('id')->pluck('name')->all());
    }

    public function test_seeding_again_does_not_duplicate_demo_orders_or_products(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('orders', 8);
        $this->assertDatabaseCount('order_items', 16);
        $this->assertDatabaseCount('products', 7);
        $this->assertDatabaseCount('product_option_groups', 2);
        $this->assertDatabaseCount('product_options', 6);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_product_seeder_updates_existing_demo_drinks_without_changing_other_products(): void
    {
        Product::factory()->create(['name' => 'Refrigerante lata', 'category' => 'Bebidas', 'price' => '6.00']);
        $customProduct = Product::factory()->create(['name' => 'X-Bacon', 'category' => 'Lanches']);

        $this->seed(ProductDemoSeeder::class);
        $this->seed(ProductDemoSeeder::class);

        $this->assertDatabaseCount('products', 8);
        $this->assertDatabaseMissing('products', ['name' => 'Refrigerante lata']);
        $this->assertDatabaseHas('products', ['name' => 'Coca-Cola Original lata 350 ml', 'price' => '7.00']);
        $this->assertDatabaseHas('products', ['name' => 'Coca-Cola Zero lata 350 ml']);
        $this->assertDatabaseHas('products', ['name' => 'Sprite lata 350 ml']);
        $this->assertModelExists($customProduct);
        $this->assertDatabaseHas('product_option_groups', ['product_id' => $customProduct->id, 'name' => 'Adicionais']);
        $this->assertDatabaseCount('product_options', 9);
    }

    public function test_product_seeder_does_not_recreate_soft_deleted_demo_products(): void
    {
        $this->seed(ProductDemoSeeder::class);
        Product::query()->delete();

        $this->seed(ProductDemoSeeder::class);

        $this->assertDatabaseCount('products', 7);
        $this->assertSame(0, Product::query()->count());
    }

    public function test_order_seeder_preserves_orders_that_already_exist(): void
    {
        $this->seed(ProductDemoSeeder::class);
        $existingOrder = Order::factory()->create();

        $this->seed(OrderDemoSeeder::class);

        $this->assertDatabaseCount('orders', 1);
        $this->assertModelExists($existingOrder);
        $this->assertDatabaseCount('order_items', 0);
    }
}
