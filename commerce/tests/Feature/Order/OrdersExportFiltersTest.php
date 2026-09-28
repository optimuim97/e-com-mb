<?php

namespace Tests\Feature\Order;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Exports\OrdersExport;
use Modules\Order\Models\Order;
use Tests\TestCase;

/**
 * Filtres d'extraction des commandes.
 *
 * Deux corrections y sont verrouillées : l'extraction porte sur les ventes —
 * une commande annulée ou remboursée n'en est pas une et faussait les totaux
 * de la feuille — et les bornes de la plage tiennent compte de l'heure, ce que
 * `whereDate` interdisait.
 */
class OrdersExportFiltersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Abidjan et payée : les deux conditions pour que la commande survive au
     * filtre métier existant (hors Abidjan, seul le payé s'expédie).
     */
    private function commande(string $status, ?string $createdAt = null): Order
    {
        $order = Order::create([
            'number'           => Order::generateNumber(),
            'user_id'          => User::factory()->create()->id,
            'status'           => $status,
            'subtotal'         => 5000,
            'shipping_cost'    => 0,
            'discount_amount'  => 0,
            'total'            => 5000,
            'currency'         => 'XOF',
            'paid_at'          => now(),
            'shipping_address' => [
                'first_name' => 'Fatou', 'last_name' => 'Konaté',
                'city' => 'Abidjan', 'commune' => 'Cocody',
                'country' => 'CI', 'phone' => '0102030405',
            ],
            'billing_address'  => ['city' => 'Abidjan', 'country' => 'CI'],
        ]);

        if ($createdAt) {
            // `created_at` est renseigné par Eloquent : il faut le forcer après coup.
            $order->forceFill(['created_at' => $createdAt])->saveQuietly();
        }

        return $order->refresh();
    }

    private function numeros(array $filters): array
    {
        return OrdersExport::resolve($filters)->pluck('number')->all();
    }

    // ── Statuts ─────────────────────────────────────────────────────────────

    public function test_les_annulees_et_remboursees_sortent_de_lextraction(): void
    {
        $vendue    = $this->commande('delivered');
        $annulee   = $this->commande('cancelled');
        $remboursee = $this->commande('refunded');

        $numeros = $this->numeros([]);

        $this->assertContains($vendue->number, $numeros);
        $this->assertNotContains($annulee->number, $numeros);
        $this->assertNotContains($remboursee->number, $numeros);
    }

    public function test_on_peut_les_reintegrer_explicitement(): void
    {
        $annulee = $this->commande('cancelled');

        $this->assertContains($annulee->number, $this->numeros(['include_cancelled' => true]));
    }

    public function test_demander_le_statut_annulee_lemporte(): void
    {
        $vendue  = $this->commande('delivered');
        $annulee = $this->commande('cancelled');

        $numeros = $this->numeros(['status' => 'cancelled']);

        $this->assertSame([$annulee->number], $numeros);
        $this->assertNotContains($vendue->number, $numeros);
    }

    // ── Bornes horaires ─────────────────────────────────────────────────────

    public function test_une_date_nue_couvre_la_journee_entiere(): void
    {
        $matin = $this->commande('delivered', '2026-09-09 08:15:00');
        $soir  = $this->commande('delivered', '2026-09-09 22:40:00');
        $veille = $this->commande('delivered', '2026-09-08 23:59:00');

        $numeros = $this->numeros(['date_from' => '2026-09-09', 'date_to' => '2026-09-09']);

        // Comparer le timestamp à une date nue vaudrait minuit : sans la
        // normalisation, cette plage serait vide de bout en bout.
        $this->assertContains($matin->number, $numeros);
        $this->assertContains($soir->number, $numeros);
        $this->assertNotContains($veille->number, $numeros);
    }

    public function test_une_heure_saisie_borne_la_plage(): void
    {
        $matin      = $this->commande('delivered', '2026-09-09 08:15:00');
        $apresMidi  = $this->commande('delivered', '2026-09-09 15:30:00');

        $numeros = $this->numeros([
            'date_from' => '2026-09-09T07:00',
            'date_to'   => '2026-09-09T12:00',
        ]);

        $this->assertSame([$matin->number], $numeros);
        $this->assertNotContains($apresMidi->number, $numeros);
    }

    // ── Nom de fichier ──────────────────────────────────────────────────────

    public function test_le_nom_du_fichier_porte_lheure(): void
    {
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}_\d{2}h\d{2}$/',
            \App\Support\ExportStamp::now()
        );
    }
}
