<?php

namespace Modules\Delivery\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Delivery\Models\DeliveryZone;
use Modules\Delivery\Models\Locality;

/**
 * Seeds the cities and communes of Côte d'Ivoire.
 *
 * The data file mirrors what used to ship in the front-end bundle: the 111
 * departments in force since 2011 and their sub-prefectures. It is a starting
 * point, not a fixed truth — the shop edits the list from the admin afterwards.
 *
 * Idempotent, and it never overwrites: a locality renamed or disabled by the
 * shop survives a re-seed. Only what is missing is added.
 */
class LocalitySeeder extends Seeder
{
    public function run(): void
    {
        $path = __DIR__ . '/../data/localities-ci.json';

        $cities = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        foreach ($cities as $city) {
            $parent = Locality::firstOrCreate(
                ['parent_id' => null, 'slug' => DeliveryZone::normalize($city['name']), 'country' => 'CI'],
                [
                    'name'       => $city['name'],
                    'region'     => $city['region'] ?? null,
                    'sort_order' => $city['sort_order'] ?? 0,
                    'active'     => true,
                ],
            );

            foreach ($city['communes'] ?? [] as $name) {
                Locality::firstOrCreate(
                    [
                        'parent_id' => $parent->id,
                        'slug'      => DeliveryZone::normalize($name),
                        'country'   => 'CI',
                    ],
                    ['name' => $name, 'active' => true],
                );
            }
        }
    }
}
