<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coupon ciblé : le code ne mord que sur les articles rattachés ici.
 *
 * Table vide pour un coupon = code applicable à tout le panier, comportement
 * historique inchangé. Un coupon ciblé, lui, ne remise que ses articles, ce qui
 * permet d'écouler une référence précise sans brader le reste de la commande.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_product', function (Blueprint $table) {
            $table->id();

            // Le rattachement disparaît avec le coupon : le garder laisserait
            // une cible orpheline qui n'a plus rien à remiser.
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();

            // Idem si l'article est supprimé — un coupon dont tous les articles
            // ont disparu n'accorde plus rien, ce que le calcul détecte seul.
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            // Deux fois le même article sur un coupon compterait sa ligne deux
            // fois dans l'assiette de la remise.
            $table->unique(['coupon_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_product');
    }
};
