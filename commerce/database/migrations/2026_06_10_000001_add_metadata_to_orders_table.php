<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Stocke les références opérateur de paiement en ligne :
            //   - cinetpay_transaction_id, cinetpay_token
            //   - geniuspay_reference
            //   - tout autre payload spécifique provider
            $table->json('metadata')->nullable()->after('admin_note');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
