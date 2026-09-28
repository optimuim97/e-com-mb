<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rang de la boutique, fixé à la main par l'administration.
 *
 * Jusqu'ici, la vitrine classait par date de création : le dernier produit
 * saisi passait devant, sans qu'on puisse mettre en avant une gamme ou un
 * produit d'appel. La boutique range désormais ses articles elle-même, par
 * glisser-déposer.
 *
 * Les produits existants reçoivent leur rang dans l'ordre affiché jusqu'ici
 * — du plus récent au plus ancien — pour que la mise en service ne change
 * rien à l'écran tant que personne n'a déplacé quoi que ce soit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('is_featured');

            // La vitrine trie sur ce champ à chaque page de catalogue.
            $table->index('position');
        });

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
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['position']);
            $table->dropColumn('position');
        });
    }
};
