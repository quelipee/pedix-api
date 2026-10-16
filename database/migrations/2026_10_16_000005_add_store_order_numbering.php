<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->unsignedBigInteger('order_cycle')->default(1);
            $table->unsignedBigInteger('next_order_number')->default(1);
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('number')->nullable();
            $table->unsignedBigInteger('numbering_cycle')->default(1);
        });

        DB::transaction(function (): void {
            DB::table('teams')->select('id')->orderBy('id')->eachById(function (object $team): void {
                $number = 0;
                DB::table('orders')->where('team_id', $team->id)->select('id')->orderBy('id')
                    ->eachById(function (object $order) use (&$number): void {
                        DB::table('orders')->where('id', $order->id)->update(['number' => ++$number]);
                    });
                DB::table('teams')->where('id', $team->id)->update(['next_order_number' => $number + 1]);
            });
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('number')->nullable(false)->change();
            $table->unique(['team_id', 'numbering_cycle', 'number'], 'orders_store_cycle_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_store_cycle_number_unique');
            $table->dropColumn(['number', 'numbering_cycle']);
        });
        Schema::table('teams', function (Blueprint $table): void {
            $table->dropColumn(['order_cycle', 'next_order_number']);
        });
    }
};
