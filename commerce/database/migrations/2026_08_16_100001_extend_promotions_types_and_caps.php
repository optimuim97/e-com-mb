<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Types d'opération usuels du commerce en ligne, et plafond de remise.
 *
 * Le type ne change pas le calcul : il distingue les opérations dans les
 * écrans et les bilans, et détermine le badge affiché en boutique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            // Chaîne plutôt qu'enum : ajouter un type ne doit pas demander une
            // migration de schéma, et l'enum se traduit en contrainte de
            // contrôle sur SQLite, pénible à faire évoluer. Les valeurs
            // admises sont tenues par Promotion::TYPES et la validation.
            $table->string('type', 20)->default('standard')->change();

            // Plafonne une remise en pourcentage. Sans cela, −30 % sur un
            // coffret à 80 000 F retire 24 000 F, ce qui est rarement
            // l'intention derrière une opération annoncée « jusqu'à −30 % ».
            $table->decimal('max_discount_amount', 12, 2)->nullable()->after('value');

            // Texte du badge en boutique. Vide, l'affichage se déduit de la
            // remise elle-même (« −20 % »).
            $table->string('banner_label', 40)->nullable()->after('max_discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['max_discount_amount', 'banner_label']);
        });
    }
};
