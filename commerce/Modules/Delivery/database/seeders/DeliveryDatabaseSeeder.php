<?php

namespace Modules\Delivery\Database\Seeders;

use Illuminate\Database\Seeder;

class DeliveryDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            CourierSeeder::class,
            DeliveryZoneSeeder::class,
            DeliveryTestSeeder::class,
        ]);
    }
}
