<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coupon nominatif : réservé à une cliente précise.
 *
 * Null = code ouvert à tous, comportement historique inchangé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->nullable()
                ->after('code')
                // Le coupon disparaît avec la cliente : le conserver
                // laisserait un code utilisable par personne.
                ->constrained('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
