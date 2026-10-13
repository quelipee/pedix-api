<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('finalized_at')->nullable()->after('status');
            $table->index(['status', 'finalized_at', 'id']);
        });

        DB::table('orders')
            ->where('status', 'finalizado')
            ->update(['finalized_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'finalized_at', 'id']);
            $table->dropColumn('finalized_at');
        });
    }
};
