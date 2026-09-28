<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            // Timestamp when the cart was first flagged as abandoned
            $table->timestamp('abandoned_at')->nullable()->after('coupon_code');
            // Track which reminder emails have been sent
            $table->timestamp('reminder_1_sent_at')->nullable()->after('abandoned_at');
            $table->timestamp('reminder_3_sent_at')->nullable()->after('reminder_1_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn(['abandoned_at', 'reminder_1_sent_at', 'reminder_3_sent_at']);
        });
    }
};
