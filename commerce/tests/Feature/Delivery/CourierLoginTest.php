<?php

namespace Tests\Feature\Delivery;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * L'accès livreur est réservé aux comptes qui portent le rôle `courier`.
 *
 * La condition de rôle avait été écrite à côté du groupement de parenthèses :
 * SQL comprenait « email = ? OR (phone = ? AND rôle courier) », et la branche
 * e-mail passait sans contrôle. N'importe quel compte du site obtenait alors,
 * avec son propre mot de passe, un jeton portant l'habilitation `courier` —
 * et l'accès aux livraisons, aux montants à encaisser et aux confirmations.
 */
class CourierLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'courier',  'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    }

    public function test_un_livreur_se_connecte_par_email(): void
    {
        $livreur = User::factory()->create([
            'email'    => 'yao@livraison.test',
            'password' => bcrypt('motdepasse'),
        ]);
        $livreur->assignRole('courier');

        $this->postJson('/api/v1/auth/courier/login', [
            'identifier' => 'yao@livraison.test',
            'password'   => 'motdepasse',
        ])->assertOk();
    }

    public function test_un_compte_sans_role_livreur_est_refuse_meme_avec_le_bon_mot_de_passe(): void
    {
        $cliente = User::factory()->create([
            'email'    => 'cliente@example.test',
            'password' => bcrypt('motdepasse'),
        ]);
        $cliente->assignRole('customer');

        $this->postJson('/api/v1/auth/courier/login', [
            'identifier' => 'cliente@example.test',
            'password'   => 'motdepasse',
        ])->assertStatus(422);
    }
}
