<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number')->unique();
            $table->foreignUuid('table_id')->nullable()->constrained('shop_tables')->nullOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_type');
            $table->string('checkout_type');
            $table->string('payment_timing');
            $table->string('status')->default('submitted');
            $table->string('payment_status')->default('unpaid');
            $table->decimal('subtotal_amount', 14, 2)->default(0);
            $table->decimal('service_charge_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('gross_total_amount', 14, 2)->default(0);
            $table->integer('redeemed_points')->default(0);
            $table->decimal('point_discount_amount', 14, 2)->default(0);
            $table->decimal('payable_amount', 14, 2)->default(0);
            $table->integer('earned_points')->default(0);
            $table->timestamps();

            $table->index(['status', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
