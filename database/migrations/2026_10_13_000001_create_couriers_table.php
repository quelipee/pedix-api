<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 30);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['team_id', 'phone']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('courier_id')->nullable()->after('delivery_fee')->constrained()->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable()->after('courier_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('courier_id');
            $table->dropColumn('dispatched_at');
        });

        Schema::dropIfExists('couriers');
    }
};
