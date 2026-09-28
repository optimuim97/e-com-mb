<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Affichage du bouton « Support WhatsApp » de la vitrine.
 *
 * Le réglage est distinct du numéro WhatsApp : la boutique peut vouloir garder
 * le numéro — il sert aux liens de commande et à la mise en relation avec un
 * agent — sans afficher la pastille flottante sur chaque page.
 */
class WhatsAppButtonSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        return $admin;
    }

    public function test_le_reglage_parvient_a_la_vitrine(): void
    {
        Sanctum::actingAs($this->admin());

        $this->putJson('/api/admin/settings', [
            'settings' => ['whatsapp_button_hidden' => 'true'],
        ])->assertOk();

        // La vitrine lit les réglages publics : sans cette clé dans la liste
        // blanche, l'interrupteur de l'administration n'aurait aucun effet.
        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('whatsapp_button_hidden', 'true');
    }

    public function test_le_bouton_se_reaffiche(): void
    {
        Sanctum::actingAs($this->admin());

        $this->putJson('/api/admin/settings', [
            'settings' => ['whatsapp_button_hidden' => 'true'],
        ])->assertOk();

        $this->putJson('/api/admin/settings', [
            'settings' => ['whatsapp_button_hidden' => 'false'],
        ])->assertOk();

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('whatsapp_button_hidden', 'false');
    }

    public function test_le_numero_whatsapp_reste_disponible_bouton_masque(): void
    {
        Sanctum::actingAs($this->admin());

        $this->putJson('/api/admin/settings', [
            'settings' => [
                'whatsapp_button_hidden' => 'true',
                'whatsapp_admin_number'  => '+2250707221960',
            ],
        ])->assertOk();

        // Masquer la pastille ne doit pas priver la boutique de son numéro :
        // la mise en relation avec un agent en dépend.
        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('whatsapp_button_hidden', 'true')
            ->assertJsonPath('whatsapp_admin_number', '+2250707221960');
    }

    public function test_un_visiteur_ne_change_pas_le_reglage(): void
    {
        $this->putJson('/api/admin/settings', [
            'settings' => ['whatsapp_button_hidden' => 'true'],
        ])->assertStatus(401);
    }
}
