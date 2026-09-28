<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Order\Models\Order;
use Modules\Order\Support\PaymentAvailability;

/**
 * Fige la destination de livraison sur la commande.
 *
 * Elle se déduisait jusqu'ici de l'adresse à chaque lecture, par AbidjanZone :
 * normalisation des accents, puis comparaison à une liste de communes. Cette
 * logique n'est pas reproductible en SQL, et l'administration ne pouvait donc
 * pas filtrer les commandes à expédier autrement qu'en chargeant tout en
 * mémoire — ce qui aurait cassé la pagination.
 *
 * La valeur est calculée une fois, à la création, et sert d'index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('destination', 20)->nullable()->after('shipping_method')->index();
        });

        // Reprise de l'existant : sans elle, les commandes déjà passées
        // n'apparaîtraient dans aucun onglet de l'écran des expéditions.
        Order::query()
            ->select(['id', 'shipping_address'])
            ->chunkById(200, function ($commandes) {
                foreach ($commandes as $commande) {
                    $commande->newQuery()
                        ->whereKey($commande->id)
                        ->update(['destination' => PaymentAvailability::destination($commande->shipping_address)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['destination']);
            $table->dropColumn('destination');
        });
    }
};
