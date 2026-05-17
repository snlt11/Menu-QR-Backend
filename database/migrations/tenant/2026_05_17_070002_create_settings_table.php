<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('payment_timing')->default('pay_after_meal');
            $table->boolean('allow_guest_order')->default(true);
            $table->boolean('allow_member_self_checkout')->default(true);
            $table->boolean('allow_cashier_checkout')->default(true);
            $table->boolean('allow_pay_after_meal')->default(true);
            $table->boolean('points_enabled')->default(true);
            $table->integer('earn_rate_amount')->default(1000);
            $table->integer('earn_rate_points')->default(1);
            $table->integer('redeem_rate_points')->default(1);
            $table->integer('redeem_rate_amount')->default(100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
