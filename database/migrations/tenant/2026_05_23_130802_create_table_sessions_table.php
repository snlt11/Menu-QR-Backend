<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('table_id')->constrained('tables')->cascadeOnDelete();

            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->string('token')->unique();

            $table->string('status')->default('active');

            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();

            $table->timestamps();

            $table->index(['table_id', 'status']);
            $table->index(['token', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_sessions');
    }
};
