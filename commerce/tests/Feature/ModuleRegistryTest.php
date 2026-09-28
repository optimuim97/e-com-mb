<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ModuleRegistry;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Admin\Models\Setting;
use Tests\TestCase;

/**
 * Les interrupteurs de modules.
 *
 * Ce qui est vérifié ici tient en une phrase : masquer n'est pas fermer. Le
 * menu du back-office retire les entrées d'un module éteint, mais une URL
 * retenue, un onglet resté ouvert ou un appel direct doivent recevoir la même
 * réponse — sinon l'interrupteur ne fait qu'un rangement de façade.
 */
class ModuleRegistryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->syncRoles(['admin']);
        Sanctum::actingAs($this->admin);
    }

    private function eteindre(string $module): void
    {
        Setting::updateOrCreate(
            ['key' => ModuleRegistry::setting($module)],
            ['value' => 'false'],
        );
    }

    private function allumer(string $module): void
    {
        Setting::updateOrCreate(
            ['key' => ModuleRegistry::setting($module)],
            ['value' => 'true'],
        );
    }

    /**
     * Un réglage absent vaut le défaut du registre, jamais « éteint ». Une
     * boutique qui n'a jamais touché à ces interrupteurs doit garder ce
     * qu'elle utilisait la veille.
     */
    public function test_un_reglage_absent_vaut_le_defaut_du_registre(): void
    {
        $this->assertTrue(ModuleRegistry::enabled('blog'));
        // Les Achats font exception : prix fournisseurs et marges n'ont pas à
        // apparaître dans une boutique qui n'en a pas besoin.
        $this->assertFalse(ModuleRegistry::enabled('purchases'));
    }

    public function test_un_module_inconnu_est_considere_actif(): void
    {
        // Mieux vaut laisser passer un nom inconnu que fermer une route par
        // une faute de frappe dans un middleware.
        $this->assertTrue(ModuleRegistry::enabled('module_qui_nexiste_pas'));
    }

    public function test_eteindre_un_module_ferme_ses_routes(): void
    {
        $this->getJson('/api/admin/blog/posts')->assertOk();

        $this->eteindre('blog');

        $this->getJson('/api/admin/blog/posts')
            ->assertForbidden()
            ->assertJsonPath('code', 'module_disabled')
            ->assertJsonPath('module', 'blog');
    }

    /** L'interrupteur vaut aussi pour l'administrateur qui l'a actionné. */
    public function test_ladmin_nechappe_pas_a_linterrupteur(): void
    {
        $this->eteindre('coupons');

        $this->getJson('/api/admin/coupons')->assertForbidden();
    }

    /**
     * Éteindre les Tournées ferme aussi l'espace livreur : c'est le même
     * module, vu de l'autre côté.
     */
    public function test_eteindre_les_tournees_ferme_aussi_lespace_livreur(): void
    {
        $livreur = User::factory()->create();
        $livreur->syncRoles(['livreur']);

        Sanctum::actingAs($livreur);
        $this->getJson('/api/livreur/rounds')->assertOk();

        $this->eteindre('rounds');

        $this->getJson('/api/livreur/rounds')->assertForbidden();
    }

    public function test_rallumer_remet_les_routes_en_service(): void
    {
        $this->eteindre('blog');
        $this->getJson('/api/admin/blog/posts')->assertForbidden();

        $this->allumer('blog');
        $this->getJson('/api/admin/blog/posts')->assertOk();
    }

    /**
     * Le front retire du menu les entrées d'un module éteint. Une entrée qui
     * ne mène qu'à un 403 est pire que pas d'entrée du tout.
     */
    public function test_lauthentification_annonce_les_modules_eteints(): void
    {
        $this->eteindre('blog');

        $prefixes = $this->getJson('/api/auth/me')->assertOk()->json('disabled_modules');

        $this->assertContains('blog.', $prefixes);
        $this->assertNotContains('orders.', $prefixes);
    }

    public function test_lecran_des_reglages_liste_les_modules_et_leur_etat(): void
    {
        $this->eteindre('newsletter');

        $modules = collect($this->getJson('/api/admin/modules')->assertOk()->json('data'))
            ->keyBy('key');

        $this->assertFalse($modules['newsletter']['enabled']);
        $this->assertTrue($modules['blog']['enabled']);
        $this->assertNotEmpty($modules['blog']['label']);
        $this->assertNotEmpty($modules['blog']['description']);
    }

    /** Les Achats gardent leur propre porte d'entrée, branchée sur le registre. */
    public function test_le_module_achats_suit_le_meme_interrupteur(): void
    {
        $this->allumer('purchases');
        $this->assertTrue(\Modules\Purchase\Support\PurchaseModule::enabled());

        $this->eteindre('purchases');
        $this->assertFalse(\Modules\Purchase\Support\PurchaseModule::enabled());

        $this->getJson('/api/admin/purchase-orders')->assertForbidden();
    }
}
