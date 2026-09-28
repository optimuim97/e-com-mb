<?php

namespace Tests\Feature\Catalog;

use App\Mail\Admin\AdminStockAlertMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Modules\Admin\Models\Setting;
use Modules\Catalog\Events\StockAlertRaised;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\StockMovement;
use Modules\Catalog\Services\StockLedger;
use Tests\TestCase;

/**
 * Alerte de réassort.
 *
 * L'écran du tableau de bord listait déjà les stocks bas, encore fallait-il
 * aller le regarder. La rupture se découvrait donc le jour où une cliente la
 * signalait — trop tard pour réapprovisionner.
 *
 * L'alerte part au franchissement du seuil, et à ce moment-là seulement :
 * une notification qui se répète à chaque vente cesse d'être lue.
 */
class StockAlertTest extends TestCase
{
    use RefreshDatabase;

    private function ledger(): StockLedger
    {
        return app(StockLedger::class);
    }

    // ── Déclenchement ──────────────────────────────────────────────────────

    public function test_le_dernier_article_parti_declenche_une_rupture(): void
    {
        Event::fake([StockAlertRaised::class]);

        $produit = Product::factory()->create(['stock' => 3, 'low_stock_threshold' => 0]);
        $this->ledger()->out($produit, null, 3, StockMovement::TYPE_SALE);

        Event::assertDispatched(
            StockAlertRaised::class,
            fn (StockAlertRaised $e) => $e->level === StockAlertRaised::LEVEL_OUT
                && $e->product->id === $produit->id
        );
    }

    public function test_passer_sous_le_seuil_declenche_une_alerte_de_stock_bas(): void
    {
        Event::fake([StockAlertRaised::class]);

        $produit = Product::factory()->create(['stock' => 10, 'low_stock_threshold' => 5]);
        $this->ledger()->out($produit, null, 6, StockMovement::TYPE_SALE);

        Event::assertDispatched(
            StockAlertRaised::class,
            fn (StockAlertRaised $e) => $e->level === StockAlertRaised::LEVEL_LOW
        );
    }

    public function test_la_rupture_prime_sur_le_stock_bas(): void
    {
        Event::fake([StockAlertRaised::class]);

        $produit = Product::factory()->create(['stock' => 10, 'low_stock_threshold' => 5]);
        $this->ledger()->out($produit, null, 10, StockMovement::TYPE_SALE);

        Event::assertDispatchedTimes(StockAlertRaised::class, 1);
        Event::assertDispatched(
            StockAlertRaised::class,
            fn (StockAlertRaised $e) => $e->level === StockAlertRaised::LEVEL_OUT
        );
    }

    // ── Non-déclenchement ──────────────────────────────────────────────────

    public function test_rester_sous_le_seuil_ne_realerte_pas(): void
    {
        $produit = Product::factory()->create(['stock' => 10, 'low_stock_threshold' => 5]);

        // Première vente : franchissement, l'alerte est légitime.
        $this->ledger()->out($produit, null, 6, StockMovement::TYPE_SALE);

        Event::fake([StockAlertRaised::class]);

        // Ventes suivantes : toujours sous le seuil, plus rien à annoncer.
        $this->ledger()->out($produit, null, 1, StockMovement::TYPE_SALE);
        $this->ledger()->out($produit, null, 1, StockMovement::TYPE_SALE);

        Event::assertNotDispatched(StockAlertRaised::class);
    }

    public function test_une_entree_de_stock_nalerte_jamais(): void
    {
        Event::fake([StockAlertRaised::class]);

        $produit = Product::factory()->create(['stock' => 1, 'low_stock_threshold' => 5]);
        $this->ledger()->in($produit, null, 20, StockMovement::TYPE_PURCHASE);

        Event::assertNotDispatched(StockAlertRaised::class);
    }

    public function test_un_reassort_rearme_lalerte(): void
    {
        $produit = Product::factory()->create(['stock' => 10, 'low_stock_threshold' => 5]);

        $this->ledger()->out($produit, null, 6, StockMovement::TYPE_SALE);   // 10 → 4, alerte
        $this->ledger()->in($produit,  null, 16, StockMovement::TYPE_PURCHASE); // 4 → 20

        Event::fake([StockAlertRaised::class]);

        $this->ledger()->out($produit, null, 16, StockMovement::TYPE_SALE);  // 20 → 4

        Event::assertDispatchedTimes(StockAlertRaised::class, 1);
    }

    public function test_un_produit_sans_seuil_nalerte_que_sur_rupture(): void
    {
        Event::fake([StockAlertRaised::class]);

        $produit = Product::factory()->create(['stock' => 10, 'low_stock_threshold' => 0]);
        $this->ledger()->out($produit, null, 9, StockMovement::TYPE_SALE);

        Event::assertNotDispatched(StockAlertRaised::class);
    }

    // ── Notification ───────────────────────────────────────────────────────

    public function test_la_rupture_envoie_un_courriel_a_la_boutique(): void
    {
        Mail::fake();
        Setting::set('shop_email', 'boutique@rosabeautystore.com');

        $produit = Product::factory()->create(['stock' => 2, 'low_stock_threshold' => 0]);
        $this->ledger()->out($produit, null, 2, StockMovement::TYPE_SALE);

        Mail::assertSent(
            AdminStockAlertMail::class,
            fn (AdminStockAlertMail $mail) => $mail->event === 'stock.out'
                && $mail->payload['product_name'] === $produit->name
                && $mail->payload['stock'] === 0
        );
    }

    public function test_linterrupteur_coupe_les_alertes_de_stock(): void
    {
        Mail::fake();
        Setting::set('shop_email', 'boutique@rosabeautystore.com');
        Setting::set('stock_alert_notify_admin', 'false');

        $produit = Product::factory()->create(['stock' => 2, 'low_stock_threshold' => 0]);
        $this->ledger()->out($produit, null, 2, StockMovement::TYPE_SALE);

        Mail::assertNothingSent();
    }

    public function test_couper_les_alertes_de_stock_nempeche_pas_les_ventes(): void
    {
        Setting::set('stock_alert_notify_admin', 'false');

        $produit = Product::factory()->create(['stock' => 2, 'low_stock_threshold' => 0]);
        $this->ledger()->out($produit, null, 2, StockMovement::TYPE_SALE);

        $this->assertSame(0, (int) $produit->fresh()->stock);
        $this->assertSame(1, StockMovement::count());
    }
}
