<?php
// Modules/Delivery/Database/Migrations/2026_05_01_add_courier_fields_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
//            $table->string('phone', 20)->nullable()->unique()->after('email');
            $table->string('fcm_token')->nullable()->after('remember_token');
            $table->boolean('biometric_enabled')->default(false)->after('fcm_token');
            $table->timestamp('last_login_at')->nullable()->after('biometric_enabled');

            $table->string('status')->default('ACTIVE')->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'fcm_token',
                'biometric_enabled',
                'last_login_at',
                'status',
            ]);
        });
    }
};
