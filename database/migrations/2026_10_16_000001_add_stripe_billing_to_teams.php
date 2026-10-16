<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->string('stripe_id')->nullable()->unique();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('billing_checkout_id')->nullable();
            $table->uuid('billing_checkout_token')->nullable();
            $table->string('billing_checkout_plan')->nullable();
            $table->timestamp('billing_checkout_expires_at')->nullable();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('stripe_id')->unique();
            $table->string('stripe_status');
            $table->string('stripe_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['team_id', 'stripe_status']);
        });

        Schema::create('subscription_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_id')->unique();
            $table->string('stripe_product');
            $table->string('stripe_price');
            $table->string('meter_id')->nullable();
            $table->string('meter_event_name')->nullable();
            $table->integer('quantity')->nullable();
            $table->timestamps();
            $table->index(['subscription_id', 'stripe_price']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');

        Schema::table('teams', function (Blueprint $table): void {
            $table->dropUnique(['stripe_id']);
            $table->dropColumn([
                'stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at',
                'billing_checkout_id', 'billing_checkout_token',
                'billing_checkout_plan', 'billing_checkout_expires_at',
            ]);
        });
    }
};
