<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remise propre à un article au sein d'une promotion.
 *
 * Une opération annonce souvent un taux général mais traite certaines
 * références à part — une nouveauté qu'on remise moins, un fond de stock
 * qu'on pousse davantage. Sans cela il fallait créer une promotion par taux,
 * ce qui multipliait les opérations pour une seule campagne.
 *
 * Colonnes nulles = l'article suit la remise de la promotion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotion_product', function (Blueprint $table) {
            $table->string('discount_type', 10)->nullable()->after('product_id');
            $table->decimal('value', 12, 2)->nullable()->after('discount_type');
        });
    }

    public function down(): void
    {
        Schema::table('promotion_product', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'value']);
        });
    }
};
