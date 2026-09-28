<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pose un rang de départ sur les catalogues restés à zéro.
 *
 * La migration qui a créé la colonne remplissait déjà les rangs, mais elle ne
 * pouvait rien faire là où elle s'est appliquée sur une table vide — base
 * fraîchement recréée, puis repeuplée par un seeder. Les produits arrivaient
 * alors tous à zéro, et le glisser-déposer n'avait aucun rang à redistribuer.
 *
 * Reprend l'ordre affiché jusqu'ici — du plus récent au plus ancien — pour que
 * rien ne bouge à l'écran tant que personne n'a déplacé quoi que ce soit.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Garde-fou : si un seul produit porte déjà un rang, c'est que la
        // boutique a rangé sa vitrine. On n'y touche pas.
        if (DB::table('products')->where('position', '>', 0)->exists()) {
            return;
        }

        $rang = 0;

        DB::table('products')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->each(function ($id) use (&$rang) {
                DB::table('products')->where('id', $id)->update(['position' => ++$rang]);
            });
    }

    public function down(): void
    {
        // Rien à défaire : la colonne disparaît avec la migration précédente.
    }
};
