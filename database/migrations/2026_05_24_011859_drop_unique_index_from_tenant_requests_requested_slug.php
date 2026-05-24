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
        Schema::table('tenant_requests', function (Blueprint $table) {
            $table->dropUnique('tenant_requests_requested_slug_unique');

            $table->index('requested_slug');
            $table->index(['requested_slug', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('tenant_requests', function (Blueprint $table) {
            $table->dropIndex(['requested_slug']);
            $table->dropIndex(['requested_slug', 'status']);

            $table->unique('requested_slug');
        });
    }
};
