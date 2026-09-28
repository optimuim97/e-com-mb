<?php

namespace Modules\Payment\Console;

use Illuminate\Console\Command;
use Modules\Order\Models\Order;
use Modules\Payment\Services\GeniusPayService;

/**
 * Teste l'intégration GeniusPay sans passer par l'UI.
 *
 *   php artisan geniuspay:test                      (commande la plus récente, lien checkout)
 *   php artisan geniuspay:test --order=ORD-2026-0001
 *   php artisan geniuspay:test --method=wave        (gateway direct au lieu du checkout)
 *   php artisan geniuspay:test --verify=MTX-XXXX    (vérifie une référence existante)
 */
class TestGeniusPayCommand extends Command
{
    protected $signature = 'geniuspay:test
                            {--order= : Numéro de commande (défaut: la plus récente)}
                            {--method= : wave|orange_money (sinon: lien checkout hébergé)}
                            {--verify= : Référence MTX-... à vérifier au lieu de créer}';

    protected $description = 'Teste la création / vérification d\'un paiement GeniusPay';

    public function handle(GeniusPayService $geniuspay): int
    {
        if (!$geniuspay->isConfigured()) {
            $this->error('GeniusPay non configuré. Vérifie GENIUSPAY_API_KEY / GENIUSPAY_SECRET_KEY dans .env');
            $this->warn('(Pense à `php artisan config:clear` après modification du .env)');
            return self::FAILURE;
        }

        // ── Mode vérification ────────────────────────────────────────────────
        if ($ref = $this->option('verify')) {
            $this->info("Vérification de la référence : {$ref}");
            $data = $geniuspay->verify($ref);
            if (empty($data)) {
                $this->error('Aucune donnée retournée (référence inconnue ou clés invalides).');
                return self::FAILURE;
            }
            $this->table(
                ['Champ', 'Valeur'],
                collect($data)->map(fn($v, $k) => [$k, is_scalar($v) ? $v : json_encode($v)])->values()->all()
            );
            return self::SUCCESS;
        }

        // ── Mode création ────────────────────────────────────────────────────
        $order = $this->option('order')
            ? Order::where('number', $this->option('order'))->first()
            : Order::latest()->first();

        if (!$order) {
            $this->error('Aucune commande trouvée (DB vide ou numéro inconnu).');
            return self::FAILURE;
        }

        $method = $this->option('method');
        $this->info("Commande : {$order->number} — total " . number_format($order->total, 0, ',', ' ') . ' FCFA');
        $this->info('Mode     : ' . ($method ? "gateway direct ({$method})" : 'lien checkout hébergé'));
        $this->line('');

        try {
            $result = $method
                ? $geniuspay->initiate($order, $method)
                : $geniuspay->createCheckoutLink($order);

            $this->info('✅ Paiement créé avec succès.');
            $this->table(
                ['Champ', 'Valeur'],
                [
                    ['reference',    $result['reference']    ?? '—'],
                    ['status',       $result['status']       ?? '—'],
                    ['amount',       $result['amount']       ?? '—'],
                    ['fees',         $result['fees']         ?? '—'],
                    ['payment_url',  $result['payment_url']  ?? '—'],
                    ['checkout_url', $result['checkout_url'] ?? '—'],
                ]
            );
            $this->newLine();
            $link = $result['checkout_url'] ?? $result['payment_url'] ?? null;
            if ($link) {
                $this->info('👉 Ouvre ce lien pour tester le paiement :');
                $this->line('   ' . $link);
            }
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Échec : ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
