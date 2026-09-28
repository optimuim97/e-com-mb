<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La tournée : l'ensemble des livraisons qu'un livreur emporte en une sortie.
 *
 * Jusqu'ici, préparer une tournée revenait à télécharger la feuille de route
 * en texte brut et à passer les commandes en « expédiée » une par une. Le
 * téléchargement ne laissait aucune trace : au retour du livreur, plus rien ne
 * disait quelles commandes étaient parties ensemble, ni combien il devait
 * rapporter. La feuille de papier était la seule mémoire du système.
 *
 * Deux tables plutôt qu'une colonne sur `orders` : après un échec, la commande
 * repart dans une seconde tournée, et c'est la comparaison des deux passages
 * qui permet la vérification. Une colonne unique écraserait le premier essai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_rounds', function (Blueprint $table) {
            $table->id();

            // Dicté au téléphone entre la boutique et le livreur : court, sans
            // caractères qu'on confond à l'oral (0/O, 1/I). Voir
            // DeliveryRound::generateCode().
            $table->string('code', 20)->unique();

            $table->string('label')->nullable();          // « Cocody — matin »

            // Zone au moment du départ. Recopiée plutôt que liée : une zone
            // peut être renommée ou désactivée, la tournée passée garde le nom
            // sous lequel elle a été faite.
            $table->string('zone_group')->nullable();
            $table->string('zone_name')->nullable();

            // Le livreur est un utilisateur (rôle `livreur`). Nullable : une
            // tournée peut partir avant qu'on sache qui la prend.
            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();

            // draft : préparée, pas encore partie · dispatched : partie, en
            // cours · closed : pointée au retour
            $table->string('status', 20)->default('dispatched')->index();

            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'dispatched_at']);
        });

        Schema::create('delivery_round_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('delivery_round_id')->constrained('delivery_rounds')->cascadeOnDelete();
            // Restrict : effacer une commande engagée dans une tournée en
            // effacerait la trace comptable. Les commandes se soft-deletent de
            // toute façon.
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();

            $table->unsignedSmallInteger('position')->default(0);

            // pending : à livrer · delivered · failed · postponed
            $table->string('status', 20)->default('pending')->index();

            // Montant à encaisser, figé au départ. La commande peut être
            // corrigée après coup (frais revus, remise) ; ce que le livreur
            // devait rapporter, lui, ne change plus.
            $table->decimal('amount_expected', 12, 2)->default(0);
            $table->decimal('amount_collected', 12, 2)->nullable();
            $table->string('payment_method_collected', 30)->nullable();

            $table->timestamp('delivered_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->text('notes')->nullable();

            // Qui a pointé la ligne au retour.
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Une commande ne figure qu'une fois par tournée. Elle peut en
            // revanche réapparaître dans une tournée ultérieure après un échec.
            $table->unique(['delivery_round_id', 'order_id']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_round_orders');
        Schema::dropIfExists('delivery_rounds');
    }
};
