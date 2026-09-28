<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registre des entrées et sorties de stock.
 *
 * Jusqu'ici le stock n'était qu'un compteur : on voyait qu'il restait 3 pots
 * sans pouvoir dire ce qui avait consommé les autres, ni quand, ni sur quelle
 * commande. Un écart d'inventaire n'était donc pas explicable.
 *
 * Chaque ligne est immuable et décrit UN déplacement : la quantité signée
 * (positif = entrée, négatif = sortie), l'état du compteur avant et après, et
 * ce qui l'a provoqué. Conserver `stock_after` permet de relire l'historique
 * même si le compteur a été corrigé à la main entre-temps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()
                  ->constrained('product_variants')->nullOnDelete();

            // vente, annulation, achat, inventaire, retour, import…
            $table->string('type', 32);

            // Signée : le sens du mouvement se lit sans colonne supplémentaire,
            // et la somme d'une période donne directement la variation nette.
            $table->integer('quantity');
            $table->integer('stock_before');
            $table->integer('stock_after');

            // Coût unitaire d'entrée : c'est lui qui alimente le CMUP.
            $table->decimal('unit_cost', 12, 2)->nullable();

            $table->string('reason', 255)->nullable();

            // Pièce à l'origine du mouvement (commande, bon d'achat…).
            $table->nullableMorphs('reference');

            // Qui l'a provoqué. Nul pour une commande passée par une cliente.
            $table->foreignId('user_id')->nullable()
                  ->constrained()->nullOnDelete();

            $table->timestamps();

            // Historique d'un produit : le tri chronologique est l'accès normal.
            $table->index(['product_id', 'created_at']);
            $table->index(['variant_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
