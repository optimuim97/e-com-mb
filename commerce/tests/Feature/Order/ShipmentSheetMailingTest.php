<?php

namespace Tests\Feature\Order;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Admin\Models\Setting;
use Modules\Order\Console\SendShipmentSheetCommand;
use Modules\Order\Mail\ShipmentSheetMail;
use Modules\Order\Models\DeliveryRound;
use Modules\Order\Models\Order;
use Modules\Order\Support\ShipmentSheetScope;
use Tests\TestCase;

/**
 * Envoi automatique de la feuille de livraison, à 21 h.
 *
 * La fenêtre part de la dernière extraction de la zone, jamais d'un « moins
 * vingt-quatre heures » figé : c'est ce qui fait tenir le week-end, le
 * regroupement du lundi et la cadence mardi/vendredi sans cas particulier.
 *
 * Le courriel reste le seul canal fiable : l'API WhatsApp Cloud n'autorise
 * l'envoi libre que dans les 24 h suivant un message du destinataire, et un
 * envoi programmé tombe presque toujours en dehors.
 */
class ShipmentSheetMailingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set(SendShipmentSheetCommand::RECIPIENTS_SETTING, 'nathalie@rosabeautystore.com');
    }

    private function commande(string $zone, array $attrs = []): Order
    {
        return Order::factory()->create(array_merge([
            'status'        => 'processing',
            'destination'   => $zone,
            'paid_at'       => now(),
            'user_id'       => null,
            'shipping_cost' => 2500,
            'shipping_address' => [
                'first_name' => 'Awa', 'last_name' => 'Koné',
                'address_line1' => 'Rue du marché',
                'city' => $zone === 'abidjan' ? 'Abidjan' : 'Bouaké',
                'commune' => $zone === 'abidjan' ? 'Cocody' : 'Bouaké',
                'country' => $zone === 'international' ? 'FR' : 'CI',
                'phone' => '0102030405',
            ],
        ], $attrs));
    }

    private function lancer(string $zone): void
    {
        $this->artisan('shop:send-shipment-sheet', ['--zone' => $zone])->assertSuccessful();
    }

    /** Les feuilles envoyées, hors courriels de retardataires. */
    private function feuilles(): \Illuminate\Support\Collection
    {
        return Mail::sent(ShipmentSheetMail::class)->map(fn ($e) => $e->mailable ?? $e)
            ->filter(fn (ShipmentSheetMail $m) => ! $m->pending)->values();
    }

    private function retards(): \Illuminate\Support\Collection
    {
        return Mail::sent(ShipmentSheetMail::class)->map(fn ($e) => $e->mailable ?? $e)
            ->filter(fn (ShipmentSheetMail $m) => $m->pending)->values();
    }

    // ── Fenêtre glissante ──────────────────────────────────────────────────

    public function test_seules_les_commandes_de_la_fenetre_partent(): void
    {
        Mail::fake();

        // Dernière extraction hier à 21 h.
        ShipmentSheetScope::rememberRun('abidjan', now()->subDay()->setTime(21, 0));

        $ancienne = $this->commande('abidjan', ['created_at' => now()->subDays(3)]);
        $recente  = $this->commande('abidjan', ['created_at' => now()->subHours(2)]);

        $this->lancer('abidjan');

        $feuille = $this->feuilles()->first();
        $this->assertNotNull($feuille);
        $this->assertStringContainsString($recente->number, $feuille->sheet);
        $this->assertStringNotContainsString($ancienne->number, $feuille->sheet);
    }

    public function test_la_fenetre_avance_apres_un_envoi(): void
    {
        Mail::fake();
        ShipmentSheetScope::rememberRun('abidjan', now()->subDay());
        $this->commande('abidjan', ['created_at' => now()->subHour()]);

        $this->lancer('abidjan');

        // Le passage suivant ne doit pas réexpédier la même commande.
        $this->assertTrue(ShipmentSheetScope::windowStart('abidjan')->greaterThan(now()->subMinute()));
    }

    public function test_le_lundi_reprend_le_week_end(): void
    {
        Mail::fake();

        // Dernière extraction vendredi soir ; on est lundi.
        ShipmentSheetScope::rememberRun('abidjan', now()->subDays(3));

        $samedi   = $this->commande('abidjan', ['created_at' => now()->subDays(2)]);
        $dimanche = $this->commande('abidjan', ['created_at' => now()->subDay()]);

        $this->lancer('abidjan');

        $feuille = $this->feuilles()->first();
        $this->assertStringContainsString($samedi->number, $feuille->sheet);
        $this->assertStringContainsString($dimanche->number, $feuille->sheet);
        $this->assertSame(2, $feuille->ordersCount);
    }

    // ── Règles par zone ────────────────────────────────────────────────────

    public function test_hors_abidjan_seules_les_payees_partent(): void
    {
        Mail::fake();
        ShipmentSheetScope::rememberRun('interior', now()->subDay());

        $payee   = $this->commande('interior', ['created_at' => now()->subHour()]);
        $impayee = $this->commande('interior', ['created_at' => now()->subHour(), 'paid_at' => null]);

        $this->lancer('interior');

        $feuille = $this->feuilles()->first();
        $this->assertStringContainsString($payee->number, $feuille->sheet);
        $this->assertStringNotContainsString($impayee->number, $feuille->sheet);
    }

    public function test_abidjan_garde_les_impayees(): void
    {
        Mail::fake();
        ShipmentSheetScope::rememberRun('abidjan', now()->subDay());

        // Le paiement à la livraison est la norme à Abidjan.
        $impayee = $this->commande('abidjan', ['created_at' => now()->subHour(), 'paid_at' => null]);

        $this->lancer('abidjan');

        $this->assertStringContainsString($impayee->number, $this->feuilles()->first()->sheet);
    }

    public function test_international_attend_que_les_frais_soient_arretes(): void
    {
        Mail::fake();
        ShipmentSheetScope::rememberRun('international', now()->subDay());

        $arretee = $this->commande('international', ['created_at' => now()->subHour()]);
        $sansFrais = $this->commande('international', [
            'created_at' => now()->subHour(),
            'shipping_cost' => 0,
            'metadata' => ['shipping_unknown' => true],
        ]);

        $this->lancer('international');

        $feuille = $this->feuilles()->first();
        $this->assertStringContainsString($arretee->number, $feuille->sheet);
        $this->assertStringNotContainsString($sansFrais->number, $feuille->sheet);
    }

    // ── Second courriel : les retardataires ────────────────────────────────

    public function test_les_retardataires_font_lobjet_dun_second_courriel(): void
    {
        Mail::fake();
        ShipmentSheetScope::rememberRun('interior', now()->subDay());

        $oubliee = $this->commande('interior', [
            'created_at' => now()->subDays(5),
            'paid_at'    => null,
        ]);

        $this->lancer('interior');

        // Sans ce rappel, la fenêtre glissante la ferait disparaître pour de bon.
        $retard = $this->retards()->first();
        $this->assertNotNull($retard, 'Aucun courriel de retardataires.');
        $this->assertStringContainsString($oubliee->number, $retard->sheet);
        $this->assertStringContainsString('en attente', $retard->envelope()->subject);
    }

    public function test_une_commande_reglee_a_temps_nest_pas_signalee(): void
    {
        Mail::fake();
        ShipmentSheetScope::rememberRun('interior', now()->subDay());
        $this->commande('interior', ['created_at' => now()->subHour()]);

        $this->lancer('interior');

        $this->assertCount(0, $this->retards());
    }

    public function test_les_annulees_ne_figurent_nulle_part(): void
    {
        Mail::fake();
        ShipmentSheetScope::rememberRun('abidjan', now()->subDay());

        $annulee = $this->commande('abidjan', ['created_at' => now()->subHour(), 'status' => 'cancelled']);
        $this->commande('abidjan', ['created_at' => now()->subHour()]);

        $this->lancer('abidjan');

        foreach (Mail::sent(ShipmentSheetMail::class) as $envoi) {
            $mail = $envoi->mailable ?? $envoi;
            $this->assertStringNotContainsString($annulee->number, $mail->sheet);
        }
    }

    // ── La tournée du lendemain ────────────────────────────────────────────

    public function test_lextraction_ouvre_une_tournee_en_brouillon(): void
    {
        Mail::fake();
        ShipmentSheetScope::rememberRun('abidjan', now()->subDay());
        $order = $this->commande('abidjan', ['created_at' => now()->subHour()]);

        $this->lancer('abidjan');

        $round = DeliveryRound::first();
        $this->assertNotNull($round, 'Aucune tournée préparée.');
        $this->assertSame(DeliveryRound::STATUS_DRAFT, $round->status);
        $this->assertNull($round->dispatched_at);

        // Rien ne part le soir : c'est la confirmation du matin qui engage.
        $this->assertSame('processing', $order->fresh()->status);

        // Et le code figure dans le courriel : c'est le lien entre la feuille
        // de 21 h et la sortie du lendemain.
        $feuille = $this->feuilles()->first();
        $this->assertSame($round->code, $feuille->round['code']);
        $this->assertStringContainsString($round->code, $feuille->envelope()->subject);

        // Le courriel rendu dit aussi ce qu'il faudra emporter.
        $this->assertStringContainsString('à emporter', $feuille->render());
    }

    public function test_loption_no_round_laisse_lextraction_seule(): void
    {
        Mail::fake();
        ShipmentSheetScope::rememberRun('abidjan', now()->subDay());
        $this->commande('abidjan', ['created_at' => now()->subHour()]);

        $this->artisan('shop:send-shipment-sheet', ['--zone' => 'abidjan', '--no-round' => true])
            ->assertSuccessful();

        $this->assertDatabaseCount('delivery_rounds', 0);
        $this->assertNull($this->feuilles()->first()->round);
    }

    public function test_une_tournee_impossible_nempeche_pas_la_feuille(): void
    {
        Mail::fake();
        ShipmentSheetScope::rememberRun('abidjan', now()->subDay());

        // Frais non arrêtés : la commande figure sur la feuille d'Abidjan mais
        // ne peut pas être expédiée, donc aucune tournée ne s'ouvre.
        $bloquee = $this->commande('abidjan', [
            'created_at'    => now()->subHour(),
            'paid_at'       => null,
            'payment_method' => 'wave',
        ]);

        $this->lancer('abidjan');

        $this->assertDatabaseCount('delivery_rounds', 0);

        // La feuille reste la pièce qui compte : c'est elle qui dit à la
        // boutique ce qu'il lui reste à régler.
        $this->assertStringContainsString($bloquee->number, $this->feuilles()->first()->sheet);
    }

    // ── Destinataires et cas limites ───────────────────────────────────────

    public function test_loption_email_court_circuite_le_reglage(): void
    {
        Mail::fake();
        ShipmentSheetScope::rememberRun('abidjan', now()->subDay());
        $this->commande('abidjan', ['created_at' => now()->subHour()]);

        $this->artisan('shop:send-shipment-sheet', [
            '--zone' => 'abidjan', '--email' => 'essai@rosabeautystore.com',
        ])->assertSuccessful();

        Mail::assertSent(ShipmentSheetMail::class, function (ShipmentSheetMail $mail) {
            return collect($mail->to)->pluck('address')->all() === ['essai@rosabeautystore.com'];
        });
    }

    public function test_aucune_commande_aucun_courriel(): void
    {
        Mail::fake();

        $this->lancer('abidjan');

        // Un courriel vide chaque soir finit par ne plus être ouvert.
        Mail::assertNothingSent();
    }

    public function test_la_simulation_nenvoie_rien_et_ne_deplace_pas_la_fenetre(): void
    {
        Mail::fake();
        $repere = now()->subDay();
        ShipmentSheetScope::rememberRun('abidjan', $repere);
        $this->commande('abidjan', ['created_at' => now()->subHour()]);

        $this->artisan('shop:send-shipment-sheet', ['--zone' => 'abidjan', '--dry-run' => true])
            ->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame(
            $repere->toIso8601String(),
            ShipmentSheetScope::windowStart('abidjan')->toIso8601String(),
        );
    }

    public function test_une_zone_inconnue_est_refusee(): void
    {
        Mail::fake();

        $this->artisan('shop:send-shipment-sheet', ['--zone' => 'lune'])->assertFailed();

        Mail::assertNothingSent();
    }
}
