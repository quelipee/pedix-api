<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('phone_normalized', 30)->nullable()->after('phone');
        });

        $seen = [];

        DB::table('customers')->orderBy('id')->get(['id', 'team_id', 'phone'])->each(
            function (object $customer) use (&$seen): void {
                $normalized = preg_replace('/\D+/', '', (string) $customer->phone) ?: null;
                $key = $customer->team_id.':'.$normalized;

                if ($normalized === null || isset($seen[$key])) {
                    return;
                }

                DB::table('customers')->where('id', $customer->id)->update([
                    'phone_normalized' => $normalized,
                ]);
                $seen[$key] = true;
            },
        );

        Schema::table('customers', function (Blueprint $table) {
            $table->unique(['team_id', 'phone_normalized']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'phone_normalized']);
            $table->dropColumn('phone_normalized');
        });
    }
};
