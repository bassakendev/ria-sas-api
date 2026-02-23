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
        Schema::table('users', function (Blueprint $table) {
            // Get the current enum values and add 'trialing'
            $table->enum('subscription_status', ['active', 'trialing', 'canceled', 'past_due', 'expired'])
                ->default('active')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('subscription_status', ['active', 'canceled', 'past_due'])
                ->default('active')
                ->change();
        });
    }
};
