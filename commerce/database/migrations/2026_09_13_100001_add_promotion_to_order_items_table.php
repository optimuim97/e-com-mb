<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Garde la trace de l'opération promotionnelle appliquée à chaque ligne.
 *
 * La ligne de commande ne retenait que le prix payé. Savoir « combien les soldes
 * de l'été ont-elles vendu » était donc impossible : l'information n'avait jamais
 * été écrite, et aucun calcul a posteriori ne peut la retrouver — le prix
 * effectif dépend de promotions qui ont pu changer, expirer ou disparaître
 * depuis.
 *
 * Deux colonnes, parce qu'elles répondent à deux questions différentes :
 * `promotion_id` dit laquelle, `discount_amount` dit combien elle a coûté. La
 * seconde est figée à la commande plutôt que recalculée : c'est la remise
 * réellement consentie, même si le barème a bougé depuis.
 *
 * `nullOnDelete` : supprimer une opération ne doit pas effacer l'historique des
 * ventes. La ligne perd son rattachement, elle garde son montant.
 *
 * L'historique antérieur reste sans promotion. Les statistiques ne peuvent donc
 * rien affirmer avant cette date — l'écran doit le dire plutôt que d'afficher
 * un zéro qu'on prendrait pour une absence de ventes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('promotion_id')->nullable()->after('variant_id')
                  ->constrained('promotions')->nullOnDelete();

            // Remise consentie sur la ligne, pour la quantité entière.
            $table->decimal('discount_amount', 12, 2)->default(0)->after('unit_price');

            // L'accès normal : « ce qu'une opération a vendu sur une période ».
            $table->index(['promotion_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['promotion_id', 'created_at']);
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn('discount_amount');
        });
    }
};
