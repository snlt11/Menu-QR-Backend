<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('currency', 8)->default('MMK');
            $table->decimal('service_charge_rate', 5, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->string('opening_hours')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile');
    }
};
