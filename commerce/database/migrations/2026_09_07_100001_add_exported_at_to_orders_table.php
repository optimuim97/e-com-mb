<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trace la dernière extraction d'une commande.
 *
 * Une feuille de livraison ou un tableur exporté ne laissait aucune trace :
 * rien ne distinguait, dans la liste, ce qui était déjà parti à l'impression de
 * ce qui restait à sortir. Sur deux exports du même jour, des commandes se
 * retrouvaient sur les deux feuilles — ou sur aucune.
 *
 * Volontairement une date et non un booléen : une commande peut être extraite
 * plusieurs fois, et savoir *quand* la dernière l'a été est ce qui permet de
 * trancher. Le marquage reste facultatif à l'export : un aperçu jeté pour
 * vérification ne doit rien engager.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('exported_at')->nullable()->after('delivered_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['exported_at']);
            $table->dropColumn('exported_at');
        });
    }
};
