<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('price')->nullable();
            $table->string('currency')->default('MMK');
            $table->string('billing_cycle');
            $table->unsignedInteger('trial_days')->nullable();
            $table->unsignedInteger('max_owners')->nullable();
            $table->unsignedInteger('max_staff')->nullable();
            $table->unsignedInteger('max_kitchen')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
