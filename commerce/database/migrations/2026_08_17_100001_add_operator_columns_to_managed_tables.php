<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qui a créé et qui a modifié en dernier chaque élément administré.
 *
 * Le journal d'activité conserve le détail de chaque modification ; ces deux
 * colonnes répondent à la question courante — « qui a touché à ça ? » — sans
 * obliger à parcourir l'historique.
 *
 * Limité aux entités qu'un agent manipule depuis le tableau de bord : les
 * pivots, les lignes de commande et les tables techniques n'ont pas d'auteur.
 */
return new class extends Migration
{
    /** Entités administrées depuis le tableau de bord. */
    private const TABLES = [
        'products', 'product_variants', 'product_lines', 'product_attributes',
        'categories', 'promotions', 'coupons', 'orders',
        'posts', 'post_categories', 'gallery_photos',
        'delivery_zones', 'couriers', 'newsletter_campaigns',
        'program_tiers', 'program_benefits',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'created_by')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                // nullOnDelete : supprimer un compte ne doit pas effacer les
                // éléments qu'il a créés, ni faire échouer la suppression.
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'created_by')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('created_by');
                $t->dropConstrainedForeignId('updated_by');
            });
        }
    }
};
