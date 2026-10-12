<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('category', 80)->default('Outros')->after('name');
        });

        foreach ([
            'X-Burger' => 'Lanches',
            'X-Salada' => 'Lanches',
            'Batata frita' => 'Batatas',
            'Refrigerante lata' => 'Bebidas',
            'Suco natural' => 'Bebidas',
        ] as $name => $category) {
            DB::table('products')->where('name', $name)->update(['category' => $category]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
