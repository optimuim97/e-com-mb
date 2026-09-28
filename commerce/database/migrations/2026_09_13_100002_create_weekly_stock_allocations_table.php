<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock alloué à la vente en ligne, semaine par semaine.
 *
 * La boutique ne met pas tout son stock en ligne : elle décide chaque lundi ce
 * qu'elle accepte de vendre sur le site, et garde le reste au comptoir. Cette
 * décision n'existait nulle part — le site vendait tout ce qui était en stock.
 *
 * Une seule quantité est stockée : **ce qui est alloué**. Le vendu n'est pas un
 * compteur, il se calcule depuis les lignes de commande de la semaine. Deux
 * compteurs sur la même marchandise finissent toujours par diverger — au
 * premier pic de ventes, à la première annulation — et c'est alors la boutique
 * qui survend ou se bloque sans qu'on sache lequel des deux a raison.
 *
 * Absence de ligne = absence de plafond. Un lundi férié ou oublié ne doit pas
 * fermer la boutique : sans allocation, c'est le stock physique qui borne la
 * vente, exactement comme aujourd'hui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_stock_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Nul aujourd'hui — aucun article n'a de déclinaison — mais la
            // colonne évite une reprise de table le jour où il y en aura.
            $table->foreignId('variant_id')->nullable()
                  ->constrained('product_variants')->nullOnDelete();

            // Lundi de la semaine concernée.
            $table->date('week_start');

            $table->unsignedInteger('quantity')->default(0);

            // Ce que la gestionnaire a constaté : casse, écart, retour douteux.
            // Écrit à côté de la décision, pas dans un cahier à part.
            $table->string('note', 255)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Une seule allocation par article et par semaine : deux lignes
            // concurrentes rendraient le plafond indéterminé.
            $table->unique(['product_id', 'variant_id', 'week_start']);
            $table->index('week_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_stock_allocations');
    }
};
