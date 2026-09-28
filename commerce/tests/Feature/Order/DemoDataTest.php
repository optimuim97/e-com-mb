<?php

namespace Tests\Feature\Order;

use App\Models\User;
use Database\Seeders\DemoOrdersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Models\DeliveryRound;
use Modules\Order\Models\DeliveryRoundOrder;
use Modules\Order\Models\Order;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Jeu de données de démonstration : `php artisan shop:demo-data`.
 *
 * Ce qui est vérifié tient à deux promesses. Les données sont crédibles — un
 * écran de démonstration qui affiche une commande livrée avant d'être passée,
 * ou une tournée partie sans livreur, apprend de mauvaises habitudes à qui
 * s'en sert. Et le jeu se retire sans rien emporter d'autre.
 */
class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    private function creer(): void
    {
        $this->artisan('shop:demo-data')->assertSuccessful();
    }

    /** @return \Illuminate\Support\Collection<int, Order> */
    private function commandes()
    {
        return Order::where('number', 'like', DemoOrdersSeeder::NUMBER_PREFIX . '%')->get();
    }

    // ── Volume et couverture ───────────────────────────────────────────────

    public function test_le_jeu_couvre_les_ecrans(): void
    {
        $this->creer();
        $commandes = $this->commandes();

        $this->assertGreaterThan(80, $commandes->count());

        // Chaque destination, et les statuts que les écrans distinguent.
        $this->assertEqualsCanonicalizing(
            ['abidjan', 'interior', 'international'],
            $commandes->pluck('destination')->unique()->values()->all(),
        );
        foreach (['pending', 'processing', 'shipped', 'delivered', 'cancelled'] as $statut) {
            $this->assertTrue($commandes->contains('status', $statut), "Aucune commande « {$statut} ».");
        }

        // Aujourd'hui et hier : les raccourcis de date doivent avoir de quoi montrer.
        $this->assertTrue($commandes->contains(fn ($o) => $o->created_at->isToday()));
        $this->assertTrue($commandes->contains(fn ($o) => $o->created_at->isYesterday()));

        // Des commandes à tarifer, extraites, vendues au comptoir.
        $this->assertTrue($commandes->contains(fn ($o) => $o->metadata['shipping_unknown'] ?? false));
        $this->assertTrue($commandes->contains(fn ($o) => $o->exported_at !== null));
        $this->assertTrue($commandes->contains(fn ($o) => ($o->metadata['channel'] ?? null) === 'pos'));
    }

    public function test_les_tournees_sont_dans_tous_leurs_etats(): void
    {
        $this->creer();

        $tournees = DeliveryRound::where('notes', DemoOrdersSeeder::ROUND_MARKER)->get();

        foreach ([DeliveryRound::STATUS_DRAFT, DeliveryRound::STATUS_DISPATCHED, DeliveryRound::STATUS_CLOSED] as $statut) {
            $this->assertTrue($tournees->contains('status', $statut), "Aucune tournée « {$statut} ».");
        }

        $lignes = DeliveryRoundOrder::whereIn('delivery_round_id', $tournees->pluck('id'))->get();

        // Un échec motivé, et un écart de caisse à expliquer au retour.
        $this->assertTrue($lignes->contains(fn ($l) => $l->status === DeliveryRoundOrder::STATUS_FAILED && $l->failure_reason));
        $this->assertTrue($lignes->contains(fn ($l) => $l->status === DeliveryRoundOrder::STATUS_DELIVERED
            && (float) $l->amount_collected < (float) $l->amount_expected));
    }

    // ── Cohérence ──────────────────────────────────────────────────────────

    public function test_les_montants_se_tiennent(): void
    {
        $this->creer();

        foreach ($this->commandes() as $o) {
            $this->assertEqualsWithDelta(
                (float) $o->subtotal - (float) $o->discount_amount + (float) $o->shipping_cost,
                (float) $o->total,
                0.01,
                "Total incohérent sur {$o->number}.",
            );
            $this->assertEqualsWithDelta((float) $o->subtotal, (float) $o->items()->sum('total_price'), 0.01, "Sous-total incohérent sur {$o->number}.");
        }
    }

    public function test_les_dates_se_suivent_et_ne_sont_jamais_dans_le_futur(): void
    {
        $this->creer();
        $maintenant = now()->addMinute();

        foreach ($this->commandes() as $o) {
            foreach (['created_at', 'paid_at', 'shipped_at', 'delivered_at', 'exported_at'] as $champ) {
                $this->assertFalse($o->{$champ}?->gt($maintenant) ?? false, "{$champ} dans le futur sur {$o->number}.");
            }
            if ($o->shipped_at) {
                $this->assertTrue($o->shipped_at->gte($o->created_at), "Expédiée avant d'être passée : {$o->number}.");
            }
            if ($o->delivered_at) {
                $this->assertNotNull($o->shipped_at, "Livrée sans expédition : {$o->number}.");
                $this->assertTrue($o->delivered_at->gte($o->shipped_at), "Livrée avant d'être expédiée : {$o->number}.");
            }
        }
    }

    /** Hors Abidjan, on n'expédie que ce qui est réglé : la démo ne doit pas montrer le contraire. */
    public function test_rien_ne_part_hors_abidjan_sans_etre_paye(): void
    {
        $this->creer();

        $fautives = $this->commandes()
            ->filter(fn ($o) => $o->destination !== 'abidjan'
                && in_array($o->status, ['processing', 'shipped', 'delivered'], true)
                && $o->paid_at === null)
            ->pluck('number');

        $this->assertSame([], $fautives->all());
    }

    public function test_une_commande_nest_engagee_que_dans_une_tournee_ouverte(): void
    {
        $this->creer();

        $doublons = DeliveryRoundOrder::query()
            ->join('delivery_rounds', 'delivery_rounds.id', '=', 'delivery_round_orders.delivery_round_id')
            ->whereIn('delivery_rounds.status', [DeliveryRound::STATUS_DRAFT, DeliveryRound::STATUS_DISPATCHED])
            ->where('delivery_round_orders.status', DeliveryRoundOrder::STATUS_PENDING)
            ->selectRaw('delivery_round_orders.order_id, COUNT(*) as n')
            ->groupBy('delivery_round_orders.order_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('n', 'order_id');

        $this->assertSame([], $doublons->all());

        // Une tournée clôturée n'a plus rien d'attendu ; un brouillon n'est pas parti.
        $this->assertSame(0, DeliveryRoundOrder::whereHas('round', fn ($q) => $q->where('status', DeliveryRound::STATUS_CLOSED))
            ->where('status', DeliveryRoundOrder::STATUS_PENDING)->count());
        $this->assertSame(0, DeliveryRound::where('status', DeliveryRound::STATUS_DRAFT)->whereNotNull('dispatched_at')->count());
        $this->assertSame(0, DeliveryRound::where('status', DeliveryRound::STATUS_DISPATCHED)->whereNull('courier_id')->count());
    }

    /** Le montant attendu est ce que le livreur rapporte : zéro pour une commande réglée en ligne. */
    public function test_le_montant_attendu_suit_le_mode_de_paiement(): void
    {
        $this->creer();

        $lignes = DeliveryRoundOrder::with('order')->get();
        $this->assertNotEmpty($lignes);

        foreach ($lignes as $l) {
            $aLaPorte = in_array($l->order->payment_method, ['cod', 'delivery'], true);
            $this->assertEqualsWithDelta($aLaPorte ? (float) $l->order->total : 0.0, (float) $l->amount_expected, 0.01);
        }
    }

    /**
     * Le jeu se construit autour de « maintenant ». Les heures limites — juste
     * après minuit, avant le départ de 9 h 30, après l'extraction de 21 h —
     * sont celles où une étape risque de tomber avant la précédente ou dans le
     * futur.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('heuresDeLancement')]
    public function test_coherent_quelle_que_soit_lheure_de_lancement(string $heure): void
    {
        $this->travelTo(now()->setTimeFromTimeString($heure));

        $this->creer();
        $maintenant = now()->addMinute();

        foreach ($this->commandes() as $o) {
            foreach (['created_at', 'paid_at', 'shipped_at', 'delivered_at', 'exported_at'] as $champ) {
                $this->assertFalse($o->{$champ}?->gt($maintenant) ?? false, "{$heure} : {$champ} dans le futur sur {$o->number}.");
            }
            if ($o->delivered_at) {
                $this->assertTrue($o->shipped_at && $o->delivered_at->gte($o->shipped_at), "{$heure} : livraison avant expédition sur {$o->number}.");
            }
            if ($o->status === 'delivered') {
                $this->assertNotNull($o->delivered_at, "{$heure} : livrée sans date sur {$o->number}.");
            }
        }

        foreach (DeliveryRoundOrder::with(['order', 'round'])->get() as $l) {
            $this->assertTrue($l->order->created_at->lte($l->round->created_at->max($l->round->dispatched_at ?? $l->round->created_at)),
                "{$heure} : ligne de tournée antérieure à sa commande ({$l->order->number}).");
            $this->assertFalse($l->delivered_at?->gt($maintenant) ?? false, "{$heure} : pointage dans le futur.");
            if ($l->status === DeliveryRoundOrder::STATUS_DELIVERED) {
                $this->assertSame('delivered', $l->order->status, "{$heure} : ligne livrée, commande {$l->order->status}.");
            }
        }

        $this->assertSame(0, DeliveryRound::where('dispatched_at', '>', $maintenant)->count(), "{$heure} : tournée partie dans le futur.");
    }

    public static function heuresDeLancement(): array
    {
        return [
            'après minuit'       => ['00:20'],
            'tôt le matin'       => ['07:45'],
            'juste après départ' => ['09:40'],
            'midi'               => ['12:30'],
            "après l'extraction" => ['21:30'],
            'avant minuit'       => ['23:55'],
        ];
    }

    public function test_le_journal_nest_pas_encombre(): void
    {
        $this->creer();

        // Des centaines d'entrées « Automatique » noieraient les vraies actions.
        $this->assertSame(0, Activity::where('subject_type', Order::class)->count());
    }

    // ── Relance et purge ───────────────────────────────────────────────────

    public function test_relancer_remplace_au_lieu_dempiler(): void
    {
        $this->creer();
        $premier = $this->commandes()->count();

        $this->creer();

        $this->assertSame($premier, $this->commandes()->count());
        $this->assertSame(3, User::role('livreur')->where('email', 'like', '%@' . DemoOrdersSeeder::EMAIL_DOMAIN)->count());
    }

    public function test_la_purge_ne_retire_que_la_demo(): void
    {
        $vraieCliente  = User::factory()->create();
        $vraieCommande = Order::factory()->create(['user_id' => $vraieCliente->id]);
        $vraieTournee  = DeliveryRound::create(['code' => 'TR-0101-A', 'status' => DeliveryRound::STATUS_CLOSED]);

        $this->creer();
        $this->artisan('shop:demo-data', ['--purge' => true])->assertSuccessful();

        $this->assertSame(0, $this->commandes()->count());
        $this->assertSame(0, DeliveryRound::where('notes', DemoOrdersSeeder::ROUND_MARKER)->count());
        $this->assertSame(0, User::where('email', 'like', '%@' . DemoOrdersSeeder::EMAIL_DOMAIN)->count());

        $this->assertModelExists($vraieCliente);
        $this->assertModelExists($vraieCommande);
        $this->assertModelExists($vraieTournee);
    }

    public function test_refuse_en_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('shop:demo-data')->assertFailed();

        $this->assertSame(0, $this->commandes()->count());
    }
}
