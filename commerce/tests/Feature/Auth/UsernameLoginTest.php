<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Connexion par identifiant court.
 *
 * Un livreur n'a pas toujours d'adresse électronique, et dicter
 * « livreur1@boutique.local » à quelqu'un qui tape sur un clavier de
 * téléphone, debout dans la rue, est une mauvaise façon de commencer une
 * tournée. « souattara » se retient et se saisit.
 */
class UsernameLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
    }

    /** « Sidik Ouattara » donne « souattara » : initiale du prénom, puis le nom. */
    public function test_lidentifiant_se_deduit_du_nom(): void
    {
        $this->assertSame('souattara', User::usernameBase('Sidik Ouattara'));
        $this->assertSame('nkouadio',  User::usernameBase('Nathalie Kouadio'));

        // Accents et apostrophes retirés : personne ne les reproduit à
        // l'identique sur un clavier de téléphone.
        $this->assertSame('atraore',    User::usernameBase('Awa Traoré'));
        $this->assertSame('ynguessan',  User::usernameBase("Yao N'Guessan"));

        // Un seul mot : il fait l'identifiant à lui seul.
        $this->assertSame('kone', User::usernameBase('Koné'));
    }

    public function test_un_homonyme_recoit_un_suffixe_plutot_quun_refus(): void
    {
        User::factory()->create(['username' => 'souattara']);

        $this->assertSame('souattara2', User::generateUsername('Sidik Ouattara'));
    }

    public function test_connexion_par_identifiant(): void
    {
        $livreur = User::factory()->create([
            'name'     => 'Sidik Ouattara',
            'username' => 'souattara',
            'password' => bcrypt('motdepasse'),
        ]);
        $livreur->syncRoles(['livreur']);

        $reponse = $this->postJson('/api/auth/login', [
            'email'    => 'souattara',
            'password' => 'motdepasse',
        ])->assertOk();

        $this->assertSame($livreur->id, $reponse->json('user.id'));
        $this->assertSame('souattara',  $reponse->json('user.username'));
    }

    /** Un clavier de téléphone met volontiers une majuscule au premier caractère. */
    public function test_lidentifiant_ignore_la_casse(): void
    {
        $livreur = User::factory()->create([
            'username' => 'souattara',
            'password' => bcrypt('motdepasse'),
        ]);
        $livreur->syncRoles(['livreur']);

        $this->postJson('/api/auth/login', [
            'email'    => 'Souattara',
            'password' => 'motdepasse',
        ])->assertOk();
    }

    /** L'e-mail continue de fonctionner : rien n'est retiré aux comptes existants. */
    public function test_la_connexion_par_email_fonctionne_toujours(): void
    {
        $cliente = User::factory()->create([
            'email'    => 'awa@example.test',
            'password' => bcrypt('motdepasse'),
        ]);
        $cliente->syncRoles(['customer']);

        $this->postJson('/api/auth/login', [
            'email'    => 'awa@example.test',
            'password' => 'motdepasse',
        ])->assertOk()->assertJsonPath('user.id', $cliente->id);
    }

    public function test_un_identifiant_inconnu_est_refuse(): void
    {
        $this->postJson('/api/auth/login', [
            'email'    => 'personne',
            'password' => 'motdepasse',
        ])->assertUnprocessable();
    }

    /**
     * Compte créé depuis l'administration sans adresse : l'identifiant est
     * déduit du nom, et l'adresse composée à partir de lui.
     */
    public function test_ladmin_cree_un_livreur_sans_adresse(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);
        Sanctum::actingAs($admin);

        $reponse = $this->postJson('/api/admin/users', [
            'name'     => 'Sidik Ouattara',
            'password' => 'motdepasse123',
            'role'     => 'livreur',
        ])->assertCreated();

        $this->assertSame('souattara', $reponse->json('username'));
        // L'adresse de service n'est pas affichée : il n'y a pas de boîte
        // aux lettres derrière, et la montrer laisserait croire le contraire.
        $this->assertNull($reponse->json('email'));

        $this->postJson('/api/auth/login', [
            'email'    => 'souattara',
            'password' => 'motdepasse123',
        ])->assertOk();
    }

    public function test_deux_identifiants_identiques_sont_refuses(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);
        Sanctum::actingAs($admin);

        User::factory()->create(['username' => 'souattara']);

        $this->postJson('/api/admin/users', [
            'name'     => 'Autre Personne',
            'username' => 'souattara',
            'password' => 'motdepasse123',
            'role'     => 'livreur',
        ])->assertUnprocessable()->assertJsonValidationErrors('username');
    }
}
