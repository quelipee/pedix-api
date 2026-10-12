<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Product::query()
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
            if (! Product::withTrashed()->where('name', $product['name'])->exists()) {
                Product::query()->create([...$product, 'active' => true]);
            }
        }

        Product::query()
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
