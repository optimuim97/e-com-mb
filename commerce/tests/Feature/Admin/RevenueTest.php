<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Admin\Services\DashboardService;
use Modules\Admin\Support\Revenue;
use Modules\Order\Models\Order;
use Tests\TestCase;

/**
 * Règle du chiffre d'affaires.
 *
 * Trois définitions coexistaient et donnaient trois chiffres différents : une
 * commande annulée gonflait la courbe des trente jours, une commande jamais
 * confirmée gonflait le CA du jour. Ces tests figent la règle unique — livrée
 * seulement, articles hors livraison — pour que les trois indicateurs ne
 * puissent plus diverger.
 */
class RevenueTest extends TestCase
{
    use RefreshDatabase;

    private function commande(string $status, float $articles = 10000, float $livraison = 1500, array $extra = []): Order
    {
        return Order::factory()->create(array_merge([
            'status'          => $status,
            'subtotal'        => $articles,
            'discount_amount' => 0,
            'shipping_cost'   => $livraison,
            'total'           => $articles + $livraison,
            'delivered_at'    => $status === 'delivered' ? now() : null,
        ], $extra));
    }

    // ── Ce qui compte, et ce qui ne compte pas ─────────────────────────────

    public function test_seule_une_commande_livree_fait_du_chiffre(): void
    {
        $this->commande('delivered');

        $this->assertSame(10000.0, Revenue::sum(Revenue::delivered()));
    }

    public function test_une_commande_annulee_ne_gonfle_pas_le_chiffre(): void
    {
        $this->commande('delivered');
        $this->commande('cancelled');

        // Le cœur de la question : une annulation ne doit rien ajouter.
        $this->assertSame(10000.0, Revenue::sum(Revenue::delivered()));
    }

    public function test_une_commande_remboursee_ne_compte_pas_davantage(): void
    {
        $this->commande('delivered');
        $this->commande('refunded');

        // Elle échappait au filtre « sauf annulées » et gonflait le CA du jour.
        $this->assertSame(10000.0, Revenue::sum(Revenue::delivered()));
    }

    public function test_une_commande_en_attente_nest_pas_du_chiffre_acquis(): void
    {
        $this->commande('pending');

        $this->assertSame(0.0, Revenue::sum(Revenue::delivered()));
        // Ni acquise, ni engagée : la boutique ne l'a pas encore confirmée.
        $this->assertSame(0.0, Revenue::sum(Revenue::inProgress()));
    }

    public function test_lengage_est_compte_a_part_du_realise(): void
    {
        $this->commande('delivered');
        $this->commande('shipped');
        $this->commande('processing');

        $this->assertSame(10000.0, Revenue::sum(Revenue::delivered()));
        $this->assertSame(20000.0, Revenue::sum(Revenue::inProgress()));
    }

    // ── Montant retenu ─────────────────────────────────────────────────────

    public function test_les_frais_de_livraison_sont_exclus(): void
    {
        $this->commande('delivered', articles: 10000, livraison: 2500);

        // 12 500 encaissés, mais 2 500 repartent chez le livreur.
        $this->assertSame(10000.0, Revenue::sum(Revenue::delivered()));
    }

    public function test_la_remise_est_deduite(): void
    {
        $this->commande('delivered', articles: 10000, extra: ['discount_amount' => 1500]);

        $this->assertSame(8500.0, Revenue::sum(Revenue::delivered()));
    }

    // ── Tableau de bord ────────────────────────────────────────────────────

    public function test_le_ca_du_jour_ignore_annulees_et_non_confirmees(): void
    {
        // getStats() compte les clientes par rôle : sans les rôles, spatie lève.
        $this->seed(RolesSeeder::class);

        $this->commande('delivered');
        $this->commande('cancelled');
        $this->commande('pending');

        $stats = app(DashboardService::class)->getStats();

        $this->assertSame(10000.0, $stats['today_revenue']);
        $this->assertSame(10000.0, $stats['week_revenue']);
    }

    public function test_la_courbe_ignore_les_annulees(): void
    {
        $this->commande('delivered');
        $this->commande('cancelled');

        $courbe = app(DashboardService::class)->getSalesChart('week');

        $this->assertSame(10000.0, (float) $courbe->sum('revenue'));
        $this->assertSame(1, (int) $courbe->sum('orders'));
    }

    public function test_la_courbe_regroupe_par_semaine_par_defaut(): void
    {
        // Deux livraisons la même semaine, une la semaine précédente.
        $this->commande('delivered', extra: ['delivered_at' => now()->startOfWeek()->addDay()]);
        $this->commande('delivered', extra: ['delivered_at' => now()->startOfWeek()->addDays(3)]);
        $this->commande('delivered', extra: ['delivered_at' => now()->startOfWeek()->subDays(2)]);

        $courbe = app(DashboardService::class)->getSalesChart('week');

        $this->assertCount(2, $courbe, 'Les trois livraisons doivent tenir sur deux semaines.');

        $semaine = $courbe->last();
        $this->assertSame(now()->startOfWeek()->toDateString(), $semaine->date);
        $this->assertSame(20000.0, $semaine->revenue);
        $this->assertSame(2, $semaine->orders);
    }

    public function test_le_jour_reste_disponible(): void
    {
        $this->commande('delivered', extra: ['delivered_at' => now()->startOfWeek()->addDay()]);
        $this->commande('delivered', extra: ['delivered_at' => now()->startOfWeek()->addDays(3)]);

        $courbe = app(DashboardService::class)->getSalesChart('day');

        $this->assertCount(2, $courbe, 'À la journée, les deux livraisons restent séparées.');
    }

    // ── Endpoint ───────────────────────────────────────────────────────────

    public function test_lendpoint_rend_la_semaine_par_defaut(): void
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);
        Sanctum::actingAs($admin);

        $this->commande('delivered');

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('period', 'week')
            ->assertJsonPath('stats.revenue', 10000)
            ->assertJsonPath('sales_chart.0.revenue', 10000);
    }

    public function test_une_periode_inconnue_retombe_sur_la_semaine(): void
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/dashboard?period=nimporte')
            ->assertOk()
            ->assertJsonPath('period', 'week');
    }

    public function test_un_agent_sans_finance_ne_voit_aucun_montant(): void
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);

        $agent = User::factory()->create();
        $agent->syncRoles(['staff']);
        Sanctum::actingAs($agent);

        $this->commande('delivered');

        $reponse = $this->getJson('/api/admin/dashboard')->assertOk();

        // La confidentialité de la finance vaut aussi pour les nouvelles clés.
        foreach (['revenue', 'revenue_in_progress', 'today_revenue', 'week_revenue'] as $cle) {
            $reponse->assertJsonMissingPath("stats.{$cle}");
        }
        $reponse->assertJsonPath('sales_chart', []);
    }
}
