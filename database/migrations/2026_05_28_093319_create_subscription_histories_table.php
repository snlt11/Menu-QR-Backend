<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->index();
            $table->foreignUuid('subscription_id')->nullable()->index();
            $table->foreignUuid('old_plan_id')->nullable();
            $table->foreignUuid('new_plan_id')->nullable();
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->string('action');
            $table->text('note')->nullable();
            $table->foreignUuid('changed_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('subscription_id')->references('id')->on('tenant_subscriptions')->cascadeOnDelete();
            $table->foreign('old_plan_id')->references('id')->on('plans')->nullOnDelete();
            $table->foreign('new_plan_id')->references('id')->on('plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_histories');
    }
};
