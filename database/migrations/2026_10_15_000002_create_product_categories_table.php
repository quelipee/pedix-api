<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->timestamps();

            $table->unique(['team_id', 'name']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('team_id')
                ->constrained('product_categories')
                ->nullOnDelete();
        });

        DB::table('products')
            ->select(['team_id', 'category'])
            ->whereNotNull('team_id')
            ->distinct()
            ->orderBy('team_id')
            ->each(function (object $product): void {
                DB::table('product_categories')->insertOrIgnore([
                    'team_id' => $product->team_id,
                    'name' => $product->category,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        DB::table('products')
            ->select(['id', 'team_id', 'category'])
            ->whereNotNull('team_id')
            ->orderBy('id')
            ->each(function (object $product): void {
                $categoryId = DB::table('product_categories')
                    ->where('team_id', $product->team_id)
                    ->where('name', $product->category)
                    ->value('id');

                DB::table('products')->where('id', $product->id)->update([
                    'category_id' => $categoryId,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('product_categories');
    }
};
