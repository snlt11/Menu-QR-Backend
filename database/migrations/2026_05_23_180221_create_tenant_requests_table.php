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
        Schema::create('tenant_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('shop_name');
            $table->string('requested_slug')->unique();

            $table->string('owner_name');
            $table->string('owner_email');
            $table->string('owner_phone')->nullable();

            $table->string('password');

            $table->string('status')->default('pending');
            $table->foreignUuid('tenant_id')
                ->nullable()
                ->constrained('tenants')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_requests');
    }
};
