<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $teamId = Team::query()->value('id') ?? User::factory()->create()->current_team_id;

        $categories = collect(['Lanches', 'Bebidas', 'Batatas', 'Outros'])
            ->mapWithKeys(function (string $name) use ($teamId): array {
                $category = ProductCategory::query()->firstOrCreate([
                    'team_id' => $teamId,
                    'name' => $name,
                ]);

                return [$name => $category->id];
            });

        Product::query()
            ->where('team_id', $teamId)
            ->where('name', 'Refrigerante lata')
            ->where('category', 'Bebidas')
            ->where('price', '6.00')
            ->update(['name' => 'Coca-Cola Original lata 350 ml', 'price' => '7.00']);

        foreach ([
            ['name' => 'X-Burger', 'category' => 'Lanches', 'price' => '18.00'],
            ['name' => 'X-Salada', 'category' => 'Lanches', 'price' => '22.00'],
            ['name' => 'Batata frita', 'category' => 'Batatas', 'price' => '12.00'],
            ['name' => 'Coca-Cola Original lata 350 ml', 'category' => 'Bebidas', 'price' => '7.00'],
            ['name' => 'Coca-Cola Zero lata 350 ml', 'category' => 'Bebidas', 'price' => '7.00'],
            ['name' => 'Sprite lata 350 ml', 'category' => 'Bebidas', 'price' => '7.00'],
            ['name' => 'Suco natural', 'category' => 'Bebidas', 'price' => '9.00'],
        ] as $product) {
            if (! Product::withTrashed()->where('team_id', $teamId)->where('name', $product['name'])->exists()) {
                Product::query()->create([
                    ...$product,
                    'team_id' => $teamId,
                    'category_id' => $categories[$product['category']],
                    'active' => true,
                ]);
            }
        }

        Product::query()
            ->where('team_id', $teamId)
            ->whereNull('category_id')
            ->get()
            ->each(function (Product $product) use ($categories): void {
                $product->update(['category_id' => $categories[$product->category] ?? $categories['Outros']]);
            });

        Product::query()
            ->where('team_id', $teamId)
            ->where('category', 'Lanches')
            ->where('active', true)
            ->each(function (Product $product): void {
                $group = $product->optionGroups()->firstOrCreate(
                    ['name' => 'Adicionais'],
                    ['min_selections' => 0, 'max_selections' => 3, 'sort_order' => 0],
                );

                foreach ([
                    ['name' => 'Bacon extra', 'price' => '4.00'],
                    ['name' => 'Queijo extra', 'price' => '2.50'],
                    ['name' => 'Salada extra', 'price' => '2.00'],
                ] as $sortOrder => $option) {
                    $group->options()->firstOrCreate(
                        ['name' => $option['name']],
                        [...$option, 'active' => true, 'sort_order' => $sortOrder],
                    );
                }
            });
    }
}
