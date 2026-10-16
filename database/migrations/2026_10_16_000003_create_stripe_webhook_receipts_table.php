<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_webhook_receipts', function (Blueprint $table): void {
            $table->string('event_id')->primary();
            $table->string('subscription_id')->index();
            $table->string('event_type');
            $table->timestamp('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_receipts');
    }
};
