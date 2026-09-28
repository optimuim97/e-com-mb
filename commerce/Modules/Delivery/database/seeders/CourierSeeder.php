<?php
// Modules/Delivery/Database/Seeders/CourierSeeder.php

namespace Modules\Delivery\Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

class CourierSeeder extends Seeder
{
    public function run(): void
    {
        // Créer le rôle courier s'il n'existe pas
        $role = Role::firstOrCreate(['name' => 'courier']);

        // Créer un livreur test
        $courier = User::create([
            'first_name' => 'Jean',
            'last_name' => 'Kouadio',
            'name' => 'Jean Kouadio',
            'email' => 'courier1@example.com',
            'phone' => '+2250700000001',
            'password' => bcrypt('password123'),
            'status' => 'ACTIVE',
            'email_verified_at' => now(),
        ]);

        $courier->assignRole('courier');

        // Livreur 2
        $courier2 = User::create([
            'first_name' => 'Marie',
            'last_name' => 'Koné',
            'name' => 'Marie Koné',
            'email' => 'courier2@example.com',
            'phone' => '+2250700000002',
            'password' => bcrypt('password123'),
            'status' => 'ACTIVE',
            'email_verified_at' => now(),
        ]);

        $courier2->assignRole('courier');

        $this->command->info('Couriers created successfully!');
        $this->command->info('Email: courier1@example.com / Password: password123');
    }
}
