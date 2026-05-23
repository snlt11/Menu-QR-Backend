<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('table_session_enabled')->default(true)->after('allow_pay_after_meal');
            $table->unsignedInteger('table_session_expiry_minutes')->default(120)->after('table_session_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['table_session_enabled', 'table_session_expiry_minutes']);
        });
    }
};
