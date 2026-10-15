<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $fallbackTeamId = DB::table('teams')->orderBy('id')->value('id');
        $hasExistingData = DB::table('products')->exists() || DB::table('orders')->exists();

        if ($hasExistingData && $fallbackTeamId === null) {
            throw new RuntimeException('Não foi possível vincular produtos e pedidos existentes porque nenhuma equipe foi encontrada.');
        }

        if ($fallbackTeamId !== null) {
            DB::table('products')->whereNull('team_id')->update(['team_id' => $fallbackTeamId]);

            DB::table('orders')
                ->whereNull('team_id')
                ->select(['id', 'customer_id', 'cash_register_id', 'courier_id'])
                ->eachById(function (object $order) use ($fallbackTeamId): void {
                    $teamId = null;

                    if ($order->customer_id !== null) {
                        $teamId = DB::table('customers')->where('id', $order->customer_id)->value('team_id');
                    }

                    if ($teamId === null && $order->cash_register_id !== null) {
                        $teamId = DB::table('cash_registers')->where('id', $order->cash_register_id)->value('team_id');
                    }

                    if ($teamId === null && $order->courier_id !== null) {
                        $teamId = DB::table('couriers')->where('id', $order->courier_id)->value('team_id');
                    }

                    if ($teamId === null) {
                        $teamId = DB::table('order_items')
                            ->join('products', 'products.id', '=', 'order_items.product_id')
                            ->where('order_items.order_id', $order->id)
                            ->value('products.team_id');
                    }

                    DB::table('orders')->where('id', $order->id)->update([
                        'team_id' => $teamId ?? $fallbackTeamId,
                    ]);
                });
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable(false)->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
