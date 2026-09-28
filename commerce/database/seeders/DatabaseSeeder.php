<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Delivery\Database\Seeders\DeliveryZoneSeeder;

/**
 * Installation d'une nouvelle boutique (marque blanche).
 *
 *   php artisan migrate --seed
 *
 * Pose le socle : rôles et permissions, compte du propriétaire (OWNER_* du
 * .env), zones de livraison par défaut. Aucun catalogue, aucun article de
 * blog, aucun compte de test : la boutique démarre vide et le commerçant la
 * remplit depuis l'administration.
 *
 * SEED_DEMO=true ajoute un catalogue de démonstration neutre, pour une
 * instance de démo commerciale.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            PermissionsSeeder::class,
            OwnerAccountSeeder::class,
            DeliveryZoneSeeder::class,
            // TestCustomersSeeder volontairement absent : comptes de recette,
            // à lancer à la main sur la recette uniquement.
        ]);

        if (filter_var(env('SEED_DEMO', false), FILTER_VALIDATE_BOOL)) {
            $this->call(DemoCatalogSeeder::class);
        }
    }
}
