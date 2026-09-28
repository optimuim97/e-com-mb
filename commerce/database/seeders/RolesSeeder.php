<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        // `livreur` : porté par les comptes qui font les tournées. Créé dès
        // maintenant pour que l'affectation d'une tournée ait une liste où
        // piocher ; le tableau de bord livreur viendra ensuite s'y adosser.
        foreach (['admin', 'staff', 'customer', 'livreur'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // Pas d'admin par défaut : un compte au mot de passe connu serait une
        // porte ouverte sur chaque boutique livrée. Le compte du propriétaire
        // est créé par OwnerAccountSeeder, à partir du .env de l'instance.
        $this->command?->info('✓ Rôles créés');
    }
}
