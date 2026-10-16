<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->decimal('online_delivery_fee', 10, 2)->nullable();
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->boolean('is_online')->default(false);
            $table->uuid('public_order_token')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['public_order_token']);
            $table->dropColumn(['is_online', 'public_order_token']);
        });
        Schema::table('teams', function (Blueprint $table): void {
            $table->dropColumn('online_delivery_fee');
        });
    }
};
