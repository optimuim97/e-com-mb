<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traces of the two payment reminders sent to a customer.
 *
 * Outside Abidjan nothing ships unpaid, so an unpaid order simply waits — and
 * waits silently. These two columns are what keeps the shop from reminding the
 * same customer twice a day, or from never reminding her at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('payment_reminder_1_sent_at')->nullable()->after('archived_at');
            $table->timestamp('payment_reminder_3_sent_at')->nullable()->after('payment_reminder_1_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_reminder_1_sent_at', 'payment_reminder_3_sent_at']);
        });
    }
};
