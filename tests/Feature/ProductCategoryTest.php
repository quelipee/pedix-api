<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_category_for_the_current_team(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('product-categories.store'), ['name' => '  Sobremesas  '])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Sobremesas');

        $this->assertDatabaseHas('product_categories', [
            'team_id' => $user->current_team_id,
            'name' => 'Sobremesas',
        ]);
    }

    public function test_category_name_must_be_unique_inside_the_team_ignoring_case(): void
    {
        $user = User::factory()->create();
        ProductCategory::factory()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Bebidas',
        ]);

        $this->actingAs($user)
            ->postJson(route('product-categories.store'), ['name' => 'bebidas'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_product_cannot_use_a_category_from_another_team(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherCategory = ProductCategory::factory()->create([
            'team_id' => $otherUser->current_team_id,
        ]);

        $this->actingAs($user)->postJson(route('products.store'), [
            'name' => 'Produto indevido',
            'category_id' => $otherCategory->id,
            'price' => '10.00',
            'active' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('category_id');

        $this->assertDatabaseMissing('products', ['name' => 'Produto indevido']);
    }

    public function test_user_can_rename_a_category_and_its_product_snapshots(): void
    {
        $user = User::factory()->create();
        $category = ProductCategory::factory()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Bebida',
        ]);
        $product = $category->products()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Suco',
            'category' => 'Bebida',
            'price' => '8.00',
            'active' => true,
        ]);

        $this->actingAs($user)
            ->patchJson(route('product-categories.update', $category), ['name' => 'Bebidas'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Bebidas');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'category_id' => $category->id,
            'category' => 'Bebidas',
        ]);
    }

    public function test_empty_category_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $category = ProductCategory::factory()->create(['team_id' => $user->current_team_id]);

        $this->actingAs($user)
            ->deleteJson(route('product-categories.destroy', $category))
            ->assertNoContent();

        $this->assertDatabaseMissing('product_categories', ['id' => $category->id]);
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $category = ProductCategory::factory()->create(['team_id' => $user->current_team_id]);
        $category->products()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Produto',
            'category' => $category->name,
            'price' => '10.00',
            'active' => true,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('product-categories.destroy', $category))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');

        $this->assertDatabaseHas('product_categories', ['id' => $category->id]);
    }
}
