<?php

namespace Modules\Order\Console;

use App\Support\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Admin\Models\Setting;
use Modules\Order\Exceptions\NoShippableOrdersException;
use Modules\Order\Mail\ShipmentSheetMail;
use Modules\Order\Services\DeliveryRoundService;
use Modules\Order\Services\InvoiceService;
use Modules\Order\Support\ShipmentSheetScope;

/**
 * Envoi du soir : la feuille de livraison, par courriel.
 *
 * Quatre zones, quatre rythmes, fixés par la boutique et tenus par
 * ShipmentSheetScope : Abidjan du dimanche au jeudi, l'intérieur et la
 * sous-région les mardis et jeudis, l'international (DHL) du dimanche au
 * mercredi. Chaque passage a lieu à 21 h la veille de la sortie — la boutique
 * est fermée le week-end, et le dimanche soir reprend ce qui s'est accumulé.
 *
 * Deux courriels par passage, et c'est voulu. Le premier porte la feuille à
 * expédier. Le second liste ce qui était attendu et n'est toujours pas prêt :
 * sans lui, la fenêtre glissante ferait disparaître pour de bon une commande
 * traitée trop tard.
 *
 * Le passage dépose aussi une tournée en brouillon, dont le code figure dans
 * le courriel. C'est le lien qui manquait entre l'extraction du soir et la
 * sortie du lendemain : la boutique confirme le départ sur ce code, et ce qui
 * part le matin est exactement ce qui a été extrait la veille.
 *
 * Le WhatsApp viendra plus tard : l'API Cloud n'autorise l'envoi libre que
 * dans les 24 h suivant un message du destinataire, et un envoi automatique à
 * 21 h tombe presque toujours en dehors. Il faudra un modèle approuvé par
 * Meta ; le courriel, lui, n'a pas de fenêtre.
 */
class SendShipmentSheetCommand extends Command
{
    protected $signature = 'shop:send-shipment-sheet
                            {--zone=abidjan : abidjan | interior | sous_region | international}
                            {--email= : Destinataire unique, à la place de ceux réglés en administration}
                            {--no-round : N\'ouvre pas de tournée en brouillon}
                            {--dry-run : Affiche la feuille sans envoyer ni déplacer la fenêtre}';

    protected $description = "Envoie la feuille de livraison du soir et la liste des commandes en attente";

    /** Réglage portant les destinataires, séparés par des virgules. */
    public const RECIPIENTS_SETTING = 'shipment_sheet_recipients';

    public function handle(InvoiceService $invoices, DeliveryRoundService $rounds): int
    {
        $zone = (string) $this->option('zone');

        if (! ShipmentSheetScope::isKnown($zone)) {
            $this->error("Zone inconnue : « {$zone} ». Attendu : "
                . implode(' | ', array_keys(ShipmentSheetScope::ZONES)) . '.');

            return self::INVALID;
        }

        $label = ShipmentSheetScope::label($zone);
        $debut = ShipmentSheetScope::windowStart($zone);
        $fin   = now();

        $aExpedier = ShipmentSheetScope::due($zone, $debut, $fin);
        $enAttente = ShipmentSheetScope::overdue($zone, $debut);

        $this->line(sprintf(
            '%s — fenêtre du %s au %s : %d à expédier, %d en attente.',
            $label, $debut->format('d/m H:i'), $fin->format('d/m H:i'),
            $aExpedier->count(), $enAttente->count(),
        ));

        if ($this->option('dry-run')) {
            if ($aExpedier->isNotEmpty()) {
                $this->line($this->sheetFor($invoices, $aExpedier->pluck('id')->all(), $label));
            }
            $this->info('[SIMULATION] Aucun envoi, la fenêtre n\'a pas bougé.');

            return self::SUCCESS;
        }

        $recipients = $this->recipients();

        if ($recipients === []) {
            $this->error('Aucun destinataire. Renseignez « ' . self::RECIPIENTS_SETTING
                . ' » dans les Paramètres, ou passez --email=.');

            return self::FAILURE;
        }

        $envois         = 0;
        $feuilleEnvoyee = false;

        // ── La feuille ──────────────────────────────────────────────────────
        if ($aExpedier->isNotEmpty()) {
            $tournee = $this->ouvrirBrouillon($rounds, $aExpedier, $zone, $label);

            $feuilleEnvoyee = $this->send($recipients, new ShipmentSheetMail(
                zoneLabel:   $label,
                sheet:       $this->sheetFor($invoices, $aExpedier->pluck('id')->all(), $label),
                ordersCount: $aExpedier->count(),
                filename:    'livraison-' . $zone . '-' . $fin->format('Y-m-d') . '.txt',
                window:      [$debut, $fin],
                round:       $tournee,
            ), $zone);

            $envois += (int) $feuilleEnvoyee;
        } else {
            // Un courriel vide chaque soir finit par ne plus être ouvert, et
            // c'est celui qui compte qu'on rate ensuite.
            $this->line('  Rien à expédier : pas de feuille envoyée.');
        }

        // ── Les retardataires ───────────────────────────────────────────────
        if ($enAttente->isNotEmpty()) {
            $envois += (int) $this->send($recipients, new ShipmentSheetMail(
                zoneLabel:   $label,
                sheet:       $this->sheetFor($invoices, $enAttente->pluck('id')->all(), $label . ' — en attente'),
                ordersCount: $enAttente->count(),
                filename:    'en-attente-' . $zone . '-' . $fin->format('Y-m-d') . '.txt',
                window:      [$debut, $fin],
                pending:     true,
            ), $zone);
        }

        // Ce qui est parti sur la feuille est marqué extrait — le même marqueur
        // que l'extraction manuelle de l'administration. Sans lui, une commande
        // envoyée ce soir reparaîtrait demain parmi les oubliées, et l'écran ne
        // pourrait pas distinguer ce qui est en préparation de ce qui dort.
        // Une commande à la fois, par le modèle : le journal retient l'auteur.
        if ($feuilleEnvoyee) {
            $aExpedier->each(fn ($order) => $order->update(['exported_at' => $fin]));
        }

        // La fenêtre n'avance que si le passage a abouti : un échec d'envoi ne
        // doit pas escamoter les commandes de la période.
        if ($envois > 0 || ($aExpedier->isEmpty() && $enAttente->isEmpty())) {
            ShipmentSheetScope::rememberRun($zone, $fin);
        }

        $this->info("{$label} — {$envois} courriel(s) envoyé(s) à " . implode(', ', $recipients));

        return self::SUCCESS;
    }

    /**
     * Ouvre la tournée du lendemain, en brouillon.
     *
     * Rien n'est expédié : les colis restent en boutique jusqu'à ce que
     * quelqu'un confirme le départ et désigne le livreur. Le brouillon fige en
     * revanche la sélection, et réserve ses commandes — celles qu'il retient ne
     * peuvent plus tomber dans une tournée composée à la main le lendemain
     * matin sans qu'on le voie.
     *
     * Un échec ici ne fait pas échouer l'envoi : la feuille reste la pièce qui
     * compte, et une tournée manquante se recrée en deux clics depuis l'écran
     * des expéditions.
     *
     * @param  Collection<int, \Modules\Order\Models\Order>  $orders
     * @return array{code: string, count: int, items: int, skipped: int}|null
     */
    private function ouvrirBrouillon(
        DeliveryRoundService $rounds,
        Collection $orders,
        string $zone,
        string $label,
    ): ?array {
        if ($this->option('no-round') || ! ModuleRegistry::enabled('rounds')) {
            return null;
        }

        try {
            $resultat = $rounds->create(
                orderIds: $orders->pluck('id')->all(),
                label:    $label . ' — ' . now()->format('d/m'),
                draft:    true,
            );
        } catch (NoShippableOrdersException $e) {
            // Toutes bloquées : la feuille part quand même, c'est elle qui dit
            // à la boutique ce qu'il reste à régler.
            $this->warn('  Aucune tournée ouverte : ' . $e->getMessage());

            return null;
        } catch (\Throwable $e) {
            Log::error('[FeuilleLivraison] Brouillon de tournée impossible', [
                'zone' => $zone, 'error' => $e->getMessage(),
            ]);
            $this->warn('  Aucune tournée ouverte : ' . $e->getMessage());

            return null;
        }

        $round   = $resultat['round'];
        $ecartes = count($resultat['rejected']);

        $this->line("  Tournée {$round->code} préparée : {$round->lines->count()} commande(s)"
            . ($ecartes > 0 ? ", {$ecartes} écartée(s)." : '.'));

        return [
            'code'    => $round->code,
            'count'   => $round->lines->count(),
            'items'   => (int) $round->lines->sum(fn ($l) => $l->order?->items->sum('quantity') ?? 0),
            'skipped' => $ecartes,
        ];
    }

    /** @param array<int,int> $ids */
    private function sheetFor(InvoiceService $invoices, array $ids, string $label): string
    {
        return $invoices->generateByZoneTxt([
            'order_ids'           => $ids,
            'label'               => $label,
            'show_product_totals' => false,
            'show_item_counts'    => false,
            // La sélection est déjà filtrée en amont ; la règle « hors Abidjan,
            // rien d'impayé » y viderait la liste des commandes en attente,
            // qui sont impayées par définition.
            'include_unpaid'      => true,
        ]);
    }

    /** @param array<int,string> $recipients */
    private function send(array $recipients, ShipmentSheetMail $mail, string $zone): bool
    {
        try {
            Mail::to($recipients)->send($mail);

            return true;
        } catch (\Throwable $e) {
            // Journalisé plutôt que remonté : la tâche planifiée tourne sans
            // personne pour lire une exception, et un échec d'envoi ne doit
            // pas faire échouer les autres tâches du soir.
            Log::error('[FeuilleLivraison] Envoi échoué', ['zone' => $zone, 'error' => $e->getMessage()]);
            $this->error("Envoi échoué : {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Destinataires, réglables en administration.
     *
     * Une liste en dur obligerait à passer par un déploiement pour ajouter
     * quelqu'un. `--email` court-circuite le réglage, pour un essai ponctuel.
     *
     * @return array<int, string>
     */
    private function recipients(): array
    {
        $brut = (string) ($this->option('email')
            ?: Setting::get(self::RECIPIENTS_SETTING)
            ?: Setting::get('shop_email', ''));

        return collect(preg_split('/[,;\s]+/', $brut) ?: [])
            ->map(fn (string $adresse) => trim($adresse))
            ->filter(fn (string $adresse) => filter_var($adresse, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }
}
