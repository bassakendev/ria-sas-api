<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('plan', ['free', 'pro'])->default('free');
            $table->enum('status', ['active', 'trialing', 'expired', 'canceled'])->default('active');
            $table->enum('billing_period', ['month', 'year'])->default('month');
            $table->date('start_date');
            $table->date('next_billing_date')->nullable();
            $table->date('canceled_at')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->decimal('price', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'stripe_subscription_id']);
            $table->index('user_id');
            $table->index('plan');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
