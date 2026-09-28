<?php

namespace Modules\Delivery\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Delivery\Models\DeliveryZone;

class DeliveryZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            // Grand Abidjan — 1 500 FCFA
            ['Grand Abidjan', 'Cocody Centre',  1500, ['cocody', 'cocody centre']],
            ['Grand Abidjan', 'Deux Plateaux',  1500, ['ii plateaux', 'ii-plateaux', '2 plateaux']],
            ['Grand Abidjan', 'Abobo',          1500, ['abobo']],
            ['Grand Abidjan', 'Treichville',    1500, ['treichville']],
            ['Grand Abidjan', 'Marcory',        1500, ['marcory']],
            ['Grand Abidjan', 'Koumassi',       1500, ['koumassi']],
            ['Grand Abidjan', 'Plateau',        1500, ['plateau']],
            ['Grand Abidjan', 'Abatta',         1500, ['abatta']],
            ['Grand Abidjan', 'Faya',           1500, ['faya']],
            ['Grand Abidjan', 'Riviera',        1500, ['riviera', 'riviera 2', 'riviera 3', 'riviera palmeraie']],

            // Grand Abidjan — 2 000 FCFA
            ['Grand Abidjan', 'Port-Bouët',     2000, ['port bouet', 'port-bouet']],
            ['Grand Abidjan', 'Gonzagueville',  2000, ['gonzagueville']],
            ['Grand Abidjan', 'Bingerville',    2000, ['bingerville']],
            ['Grand Abidjan', "N'Dotré",        2000, ['ndotre', "n'dotre"]],
            ['Grand Abidjan', 'Anyama',         2000, ['anyama']],
            ['Grand Abidjan', 'Angré',          2000, ['angre', 'angré 8e tranche', 'angre château']],
            ['Grand Abidjan', 'Yopougon',       2000, ['yopougon', 'yop']],

            // Grand Abidjan — 3 000 FCFA
            ['Grand Abidjan', 'Grand-Bassam',   3000, ['grand bassam', 'bassam']],
            ['Grand Abidjan', 'Songon',         3000, ['songon']],
            ['Grand Abidjan', 'Modeste',        3000, ['modeste']],

            // Intérieur Côte d'Ivoire — 2 500 FCFA
            ['Intérieur CI', 'Bouaké',          2500, ['bouake']],
            ['Intérieur CI', 'Yamoussoukro',    2500, ['yakro', 'yamoussoukro']],
            ['Intérieur CI', 'Korhogo',         2500, ['korhogo']],
            ['Intérieur CI', 'Daloa',           2500, ['daloa']],
            ['Intérieur CI', 'Gagnoa',          2500, ['gagnoa']],
            ['Intérieur CI', 'Man',             2500, ['man']],
            ['Intérieur CI', 'San Pedro',       2500, ['san pedro', 'san-pedro']],
            ['Intérieur CI', 'Touba',           2500, ['touba']],
            ['Intérieur CI', 'Tanda',           2500, ['tanda']],

            // Intérieur Côte d'Ivoire — 3 000 FCFA
            ['Intérieur CI', 'Sassandra',       3000, ['sassandra']],
            ['Intérieur CI', 'Ferkessédougou',  3000, ['ferke', 'ferkessedougou']],
            ['Intérieur CI', 'Grand Lahou',     3000, ['lahou', 'grand-lahou']],
        ];

        $order = 0;
        foreach ($zones as [$group, $name, $price, $aliases]) {
            DeliveryZone::updateOrCreate(
                ['country' => 'CI', 'name' => $name],
                [
                    'group'      => $group,
                    'cities'     => $aliases,
                    'price'      => $price,
                    'price_unit' => 'flat',
                    'sort_order' => $order++,
                    'active'     => true,
                ]
            );
        }

        // International — tarif "à partir de 6 500 FCFA / kg"
        DeliveryZone::updateOrCreate(
            ['country' => 'XX', 'name' => 'International'],
            [
                'group'      => 'International',
                'cities'     => [],
                'price'      => 6500,
                'price_unit' => 'per_kg',
                'sort_order' => 999,
                'active'     => true,
            ]
        );
    }
}
