<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archiving an order: hiding it, never deleting it.
 *
 * The working list fills up with orders that are settled — delivered, paid,
 * or cancelled long ago — and what still needs doing gets lost among them.
 * Archiving takes them out of the list while keeping them whole and findable:
 * accounting records are kept for years, and an order is a record.
 *
 * Deliberately not a soft delete: `deleted_at` would take the order out of
 * every query in the application, revenue figures included.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('exported_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
