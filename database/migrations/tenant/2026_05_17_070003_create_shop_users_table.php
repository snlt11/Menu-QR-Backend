<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('central_user_id');
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('role');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique('central_user_id');
            $table->index(['role', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_users');
    }
};
