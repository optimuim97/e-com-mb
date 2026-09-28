<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promotions appliquées automatiquement, sans code à saisir.
 *
 * Distinct des coupons : un coupon est saisi par la cliente au panier,
 * une promotion s'applique d'office et se voit dès le catalogue (prix barré).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();

            // Portée : sur quoi la promotion s'applique.
            //   global  : tout le catalogue
            //   product : uniquement les produits rattachés (table pivot)
            $table->enum('scope', ['global', 'product'])->default('global');

            // Nature de l'opération. Indépendant de la portée : un écoulement
            // peut viser quelques références comme tout un catalogue.
            //   standard  : opération commerciale ordinaire
            //   clearance : écoulement de stock (badge et suivi distincts)
            $table->enum('type', ['standard', 'clearance'])->default('standard');

            $table->enum('discount_type', ['percent', 'fixed']);
            $table->decimal('value', 12, 2);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);

            // Départage deux promotions applicables au même produit : la plus
            // haute gagne. À égalité, c'est la plus avantageuse qui l'emporte.
            $table->integer('priority')->default(0);

            $table->timestamps();

            // Requête chaude : « quelles promotions sont actives maintenant »
            $table->index(['is_active', 'starts_at', 'ends_at']);
            $table->index('scope');
            $table->index('type');
        });

        Schema::create('promotion_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unique(['promotion_id', 'product_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_product');
        Schema::dropIfExists('promotions');
    }
};
