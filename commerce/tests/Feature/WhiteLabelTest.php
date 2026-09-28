<?php

namespace Tests\Feature;

use App\Support\ModuleRegistry;
use App\Support\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Models\Setting;
use Modules\Coupon\Models\Coupon;
use Tests\Concerns\ActingAsUser;
use Tests\TestCase;

/**
 * Marque blanche : une instance se configure depuis ses réglages, sans code.
 *
 * Deux promesses vérifiées ici :
 *   - un module éteint disparaît aussi de la boutique — pas seulement du menu
 *     d'administration — et ses routes publiques se ferment ;
 *   - l'identité affichée (nom, couleurs) vient des réglages de la boutique,
 *     jamais d'une autre marque écrite en dur.
 */
class WhiteLabelTest extends TestCase
{
    use RefreshDatabase, ActingAsUser;

    private function eteindre(string $module): void
    {
        Setting::set(ModuleRegistry::setting($module), 'false');
    }

    // ── Réglages publics ─────────────────────────────────────────────────────

    public function test_les_reglages_publics_annoncent_l_etat_de_chaque_module(): void
    {
        $this->eteindre('blog');

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('module_blog_enabled', 'false')
            // Jamais réglé : le défaut du registre, résolu par le serveur.
            ->assertJsonPath('module_reviews_enabled', 'true')
            ->assertJsonPath('module_wishlist_enabled', 'true')
            ->assertJsonPath('module_purchases_enabled', 'false');
    }

    public function test_les_couleurs_de_la_marque_sont_publiques(): void
    {
        Setting::set('brand_primary_color', '#1d4ed8');
        Setting::set('brand_accent_color', '#f59e0b');

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('brand_primary_color', '#1d4ed8')
            ->assertJsonPath('brand_accent_color', '#f59e0b');
    }

    // ── Routes publiques des modules éteints ─────────────────────────────────

    public function test_blog_eteint_ferme_les_articles_publics(): void
    {
        $this->getJson('/api/blog')->assertOk();

        $this->eteindre('blog');

        $this->getJson('/api/blog')
            ->assertForbidden()
            ->assertJsonPath('code', 'module_disabled');
    }

    public function test_liste_de_souhaits_eteinte_ferme_l_api(): void
    {
        $this->setUpUser();
        $this->eteindre('wishlist');

        $this->actingAs($this->user)
            ->getJson('/api/wishlist')
            ->assertForbidden()
            ->assertJsonPath('module', 'wishlist');
    }

    public function test_club_eteint_ferme_la_page_du_programme(): void
    {
        $this->eteindre('program');

        $this->getJson('/api/program')->assertForbidden();
    }

    public function test_galerie_eteinte_renvoie_une_liste_vide_sans_erreur(): void
    {
        $this->eteindre('gallery');

        // Liste vide plutôt qu'un 403 : la page d'accueil masque la section.
        $this->getJson('/api/gallery')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_le_desabonnement_newsletter_reste_ouvert_module_eteint(): void
    {
        $this->eteindre('newsletter');

        $this->postJson('/api/newsletter/subscribe', ['email' => 'a@b.test'])->assertForbidden();

        // Obligation légale : un abonné se désinscrit toujours.
        $this->get('/api/newsletter/unsubscribe/jeton-inconnu')->assertOk();
    }

    public function test_coupons_eteints_aucun_code_ne_s_applique(): void
    {
        $coupon = Coupon::factory()->create(['is_active' => true]);
        $this->assertTrue($coupon->isValid(100000));

        $this->eteindre('coupons');

        $this->assertFalse($coupon->fresh()->isValid(100000));
        $this->postJson('/api/coupons/validate', ['code' => $coupon->code])->assertForbidden();
    }

    // ── Identité de la boutique ──────────────────────────────────────────────

    public function test_le_nom_vient_des_reglages_sinon_de_app_name(): void
    {
        config(['app.name' => 'Boutique Test']);
        Setting::where('key', 'shop_name')->delete();
        $this->assertSame('Boutique Test', Shop::name());

        Setting::set('shop_name', 'Chez Awa');
        $this->assertSame('Chez Awa', Shop::name());
    }
}
