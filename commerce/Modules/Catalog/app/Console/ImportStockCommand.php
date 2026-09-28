<?php

namespace Modules\Catalog\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Catalog\Models\StockMovement;
use Modules\Catalog\Services\StockLedger;

/**
 * Réaligne le stock de la base sur le comptage fait en boutique.
 *
 * Seconde moitié du cycle « stock réel » (voir `shop:export-stock`). Trois
 * décisions structurent la commande :
 *
 *   · Une case `stock_compte` vide signifie « pas compté », jamais « zéro ».
 *     L'inverse serait la façon la plus rapide de mettre tout le catalogue en
 *     rupture parce qu'un rayon n'a pas été fini.
 *   · Les prix ne bougent pas sans `--prix`. Un inventaire répond à « combien
 *     en ai-je ? » ; laisser la même feuille redéfinir les tarifs en silence
 *     mélange deux décisions qui n'ont ni le même auteur ni le même risque.
 *   · L'écriture passe par le modèle et non par un UPDATE : le journal
 *     d'activité (TracksOperator) enregistre alors chaque écart, avant/après.
 *     Un inventaire non tracé ne se conteste pas, il se subit.
 *
 * Usage :
 *   php artisan shop:import-stock inventaire-2026-08-25.csv --dry-run
 *   php artisan shop:import-stock inventaire-2026-08-25.csv --user=gerant@maboutique.com
 */
class ImportStockCommand extends Command
{
    protected $signature = 'shop:import-stock
        {fichier : Chemin du CSV rempli en boutique}
        {--dry-run : Simuler et afficher les écarts sans rien modifier}
        {--prix : Appliquer aussi prix_vente, cout_achat et seuil_alerte}
        {--user= : Email de l\'opérateur à qui attribuer l\'inventaire dans le journal}';

    protected $description = 'Applique un comptage physique (CSV) au stock du catalogue';

    public function handle(): int
    {
        $path = $this->argument('fichier');
        if (! is_file($path)) {
            $this->error("Fichier introuvable : {$path}");

            return self::FAILURE;
        }

        if (! $this->attributeToOperator()) {
            return self::FAILURE;
        }

        $rows = $this->readCsv($path);
        if ($rows === null) {
            return self::FAILURE;
        }

        $dryRun   = (bool) $this->option('dry-run');
        $ecarts   = [];
        $inconnus = [];
        $ignores  = 0;

        foreach ($rows as $numero => $row) {
            $counted = $this->parseInt($row['stock_compte'] ?? '');

            // Non compté : on n'y touche pas. C'est le cas le plus fréquent
            // sur un inventaire partiel, ce n'est pas une anomalie.
            if ($counted === null) {
                $ignores++;
                continue;
            }

            $target = $this->resolveTarget($row);
            if ($target === null) {
                $inconnus[] = [$numero, $row['id'] ?? '', $row['reference'] ?? '', $row['produit'] ?? ''];
                continue;
            }

            [$model, $libelle, $coutUnitaire] = $target;
            $avant = (int) $model->stock;

            if ($avant === $counted && ! $this->option('prix')) {
                continue;
            }

            $ecarts[] = [
                'model'   => $model,
                'libelle' => $libelle,
                'avant'   => $avant,
                'apres'   => $counted,
                'delta'   => $counted - $avant,
                'valeur'  => ($counted - $avant) * $coutUnitaire,
                'row'     => $row,
            ];
        }

        $this->reportEcarts($ecarts, $inconnus, $ignores, count($rows));

        if (empty($ecarts)) {
            $this->info('✓ Aucun écart : le stock de la base correspond déjà au comptage.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('Simulation (--dry-run) : aucune écriture effectuée.');

            return self::SUCCESS;
        }

        if (! $this->confirm(sprintf('Appliquer ces %d correction(s) au stock ?', count($ecarts)), true)) {
            $this->line('Abandon — rien n\'a été modifié.');

            return self::SUCCESS;
        }

        $this->applyEcarts($ecarts);

        return self::SUCCESS;
    }

    // ── Lecture du fichier ────────────────────────────────────────────────────

    /**
     * @return array<int, array<string, string>>|null  Lignes indexées par numéro de ligne du fichier
     */
    private function readCsv(string $path): ?array
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            $this->error("Lecture impossible : {$path}");

            return null;
        }

        $separator = $this->detectSeparator($path);
        $headers   = null;
        $rows      = [];
        $numero    = 0;

        while (($cells = fgetcsv($handle, 0, $separator, '"', '')) !== false) {
            $numero++;

            if ($cells === [null] || $cells === [] || $cells === [false]) {
                continue;   // ligne vide
            }

            if ($headers === null) {
                // Le BOM UTF-8 écrit à l'export colle à la première en-tête et
                // ferait échouer la comparaison avec « id ».
                $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cells[0]);
                $headers  = array_map(fn ($h) => strtolower(trim((string) $h)), $cells);

                if (! in_array('stock_compte', $headers, true)) {
                    fclose($handle);
                    $this->error('Colonne « stock_compte » absente — le fichier n\'est pas une feuille d\'inventaire.');

                    return null;
                }

                continue;
            }

            $cells = array_pad($cells, count($headers), '');
            $rows[$numero] = array_combine($headers, array_slice($cells, 0, count($headers)));
        }

        fclose($handle);

        if ($headers === null) {
            $this->error('Fichier vide.');

            return null;
        }

        return $rows;
    }

    /**
     * Point-virgule à l'export, mais un aller-retour par Google Sheets rend
     * souvent une virgule. Deviner évite un support inutile.
     */
    private function detectSeparator(string $path): string
    {
        $probe = fopen($path, 'r');
        $first = (string) fgets($probe);
        fclose($probe);

        return substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
    }

    // ── Résolution des lignes ─────────────────────────────────────────────────

    /**
     * Retrouve le produit ou la variante visé par une ligne.
     *
     * L'identifiant technique (P12 / V34) prime sur la référence : il est écrit
     * par l'export et ne bouge pas, alors qu'un SKU peut avoir été retapé à la
     * main dans le tableur.
     *
     * @param  array<string, string>  $row
     * @return array{0: Product|ProductVariant, 1: string, 2: float}|null
     */
    private function resolveTarget(array $row): ?array
    {
        $id = strtoupper(trim($row['id'] ?? ''));

        if (preg_match('/^V(\d+)$/', $id, $m)) {
            $variant = ProductVariant::with('product')->find((int) $m[1]);

            return $variant ? [$variant, $this->labelForVariant($variant), $this->cost($variant->product)] : null;
        }

        if (preg_match('/^P(\d+)$/', $id, $m)) {
            $product = Product::find((int) $m[1]);

            return $product ? [$product, $product->name, $this->cost($product)] : null;
        }

        $reference = trim($row['reference'] ?? '');
        if ($reference === '') {
            return null;
        }

        // Variante d'abord : sa référence est la plus précise des deux.
        if ($variant = ProductVariant::with('product')->where('sku', $reference)->first()) {
            return [$variant, $this->labelForVariant($variant), $this->cost($variant->product)];
        }

        if ($product = Product::where('sku', $reference)->first()) {
            return [$product, $product->name, $this->cost($product)];
        }

        return null;
    }

    private function labelForVariant(ProductVariant $variant): string
    {
        return trim(($variant->product?->name ?? 'Produit supprimé') . ' — ' . ($variant->sku ?? ('#' . $variant->id)));
    }

    private function cost(?Product $product): float
    {
        return (float) ($product?->cost_price ?? 0);
    }

    // ── Restitution ───────────────────────────────────────────────────────────

    /**
     * @param  array<int, array<string, mixed>>  $ecarts
     * @param  array<int, array<int, string>>    $inconnus
     */
    private function reportEcarts(array $ecarts, array $inconnus, int $ignores, int $total): void
    {
        $this->newLine();
        $this->info("📋 Inventaire — {$total} ligne(s) lue(s)");
        $this->line("   {$ignores} non comptée(s) → stock inchangé");
        $this->line('   ' . count($ecarts) . ' écart(s) détecté(s)');

        if ($inconnus) {
            $this->newLine();
            $this->warn('Références introuvables (ignorées) :');
            $this->table(['Ligne', 'id', 'Référence', 'Produit'], $inconnus);
        }

        if (! $ecarts) {
            return;
        }

        $this->newLine();
        $this->table(
            ['Produit', 'Base', 'Compté', 'Écart', 'Valeur (coût)'],
            array_map(fn ($e) => [
                mb_strimwidth($e['libelle'], 0, 44, '…'),
                $e['avant'],
                $e['apres'],
                ($e['delta'] > 0 ? '+' : '') . $e['delta'],
                $e['valeur'] == 0.0 ? '—' : number_format($e['valeur'], 0, ',', ' ') . ' F',
            ], $ecarts)
        );

        $manquants = array_sum(array_map(fn ($e) => $e['delta'] < 0 ? $e['delta'] : 0, $ecarts));
        $surplus   = array_sum(array_map(fn ($e) => $e['delta'] > 0 ? $e['delta'] : 0, $ecarts));
        $valeur    = array_sum(array_column($ecarts, 'valeur'));

        $this->line(sprintf(
            '   Manquants : %d article(s) · Surplus : +%d · Impact valorisé : %s F',
            $manquants,
            $surplus,
            number_format($valeur, 0, ',', ' ')
        ));

        if ($valeur < 0) {
            $this->warn('   Un écart négatif important mérite une vérification avant validation (vol, casse, ou vente non saisie).');
        }
    }

    // ── Écriture ──────────────────────────────────────────────────────────────

    /** @param array<int, array<string, mixed>> $ecarts */
    private function applyEcarts(array $ecarts): void
    {
        $appliquePrix = (bool) $this->option('prix');

        $ledger = app(StockLedger::class);
        $source = 'Import inventaire — ' . basename((string) $this->argument('fichier'));

        DB::transaction(function () use ($ecarts, $appliquePrix, $ledger, $source) {
            foreach ($ecarts as $e) {
                /** @var Product|ProductVariant $model */
                $model = $e['model'];

                // Les prix vivent sur le même enregistrement que le compteur :
                // on les écrit d'abord, le registre s'occupe du stock ensuite.
                if ($appliquePrix) {
                    $this->applyPrices($model, $e['row']);
                    $model->save();
                }

                [$produit, $declinaison] = $model instanceof ProductVariant
                    ? [$model->product, $model]
                    : [$model, null];

                // Un produit sans suivi de stock n'a pas de mouvement à
                // raconter ; on garde l'écriture directe pour ne pas changer
                // ce que l'import faisait jusqu'ici.
                if (! $produit->track_stock) {
                    $model->stock = $e['apres'];
                    $model->save();
                    continue;
                }

                // Le comptage physique fait foi : le registre enregistre l'écart
                // pour qu'une correction reste distinguable d'une vente.
                $ledger->adjustTo($produit, $declinaison, (int) $e['apres'], [
                    'type'   => StockMovement::TYPE_IMPORT,
                    'reason' => $source,
                ]);
            }
        });

        $this->newLine();
        $this->info('✓ ' . count($ecarts) . ' stock(s) mis à jour.');
        $this->line('   Détail consultable dans le journal des actions du back-office.');
    }

    /** @param array<string, string> $row */
    private function applyPrices(Product|ProductVariant $model, array $row): void
    {
        if (($prix = $this->parseMoney($row['prix_vente'] ?? '')) !== null) {
            $model->price = $prix;
        }

        // Coût d'achat et seuil d'alerte ne vivent que sur le produit.
        if ($model instanceof Product) {
            if (($cout = $this->parseMoney($row['cout_achat'] ?? '')) !== null) {
                $model->cost_price = $cout;
            }
            if (($seuil = $this->parseInt($row['seuil_alerte'] ?? '')) !== null) {
                $model->low_stock_threshold = $seuil;
            }
        }
    }

    // ── Attribution ───────────────────────────────────────────────────────────

    /**
     * Authentifie l'opérateur pour la durée de la commande : `updated_by` et
     * le causeur du journal d'activité se remplissent alors seuls, comme
     * depuis le back-office.
     */
    private function attributeToOperator(): bool
    {
        $email = $this->option('user');

        if (! $email) {
            $this->warn('Aucun opérateur (--user=) : l\'inventaire sera journalisé sans auteur.');

            return true;
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("Aucun compte pour {$email}.");

            return false;
        }

        Auth::login($user);
        $this->line("Opérateur : {$user->name} <{$user->email}>");

        return true;
    }

    // ── Conversions ───────────────────────────────────────────────────────────

    /** Entier, ou null si la case est vide (= non compté). */
    private function parseInt(string $value): ?int
    {
        $value = trim($value);

        return $value === '' || ! preg_match('/^-?\d+$/', $value) ? null : (int) $value;
    }

    /** Montant saisi avec virgule ou point, espaces de milliers tolérés. */
    private function parseMoney(string $value): ?float
    {
        $value = str_replace([' ', ' ', ','], ['', '', '.'], trim($value));

        return $value === '' || ! is_numeric($value) ? null : (float) $value;
    }
}
