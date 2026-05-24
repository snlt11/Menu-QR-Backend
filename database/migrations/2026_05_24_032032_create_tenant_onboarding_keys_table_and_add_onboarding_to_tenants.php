<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_onboarding_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->string('key_hash')->unique();

            $table->string('status')->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_used_at')->nullable();

            $table->unsignedInteger('attempts')->default(0);

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('onboarding_status')->default('pending')->after('status');
            $table->timestamp('onboarded_at')->nullable()->after('onboarding_status');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['onboarding_status', 'onboarded_at']);
        });

        Schema::dropIfExists('tenant_onboarding_keys');
    }
};
