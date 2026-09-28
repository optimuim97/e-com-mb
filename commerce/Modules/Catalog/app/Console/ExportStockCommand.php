<?php

namespace Modules\Catalog\Console;

use Illuminate\Console\Command;
use Modules\Catalog\Models\Product;

/**
 * Génère la feuille d'inventaire à faire remplir en boutique.
 *
 * Première moitié du cycle « stock réel » : on sort ce que le système croit
 * détenir, la gérante compte les rayons et note ce qu'elle trouve, puis
 * `shop:import-stock` réaligne la base sur le comptage.
 *
 * Le fichier est un CSV point-virgule avec BOM UTF-8 : c'est ce qu'Excel en
 * configuration française ouvre correctement d'un double-clic, sans passer
 * par l'assistant d'importation. Un CSV virgule sans BOM afficherait tout
 * dans une seule colonne et les accents en charabia.
 *
 * Usage :
 *   php artisan shop:export-stock
 *   php artisan shop:export-stock --out=inventaire-aout.csv --inactifs
 */
class ExportStockCommand extends Command
{
    protected $signature = 'shop:export-stock
        {--out= : Chemin du fichier CSV à écrire (défaut : storage/app/inventaire-<date>.csv)}
        {--inactifs : Inclure aussi les produits désactivés}';

    protected $description = "Exporte la feuille d'inventaire (produits + variantes) à faire remplir en boutique";

    /**
     * En-têtes en français : la feuille est remplie par la boutique, pas par un
     * développeur.
     *
     * Les trois dernières colonnes ne servent pas à l'import : elles portent le
     * contrôle humain — qui a compté, qui a contre-vérifié, ce qui a été
     * constaté. L'import associe les colonnes par leur nom et ignore celles
     * qu'il ne connaît pas, elles voyagent donc dans le même fichier sans
     * risque. Les garder sur la ligne du produit, plutôt que sur une feuille
     * à part, évite qu'une observation se retrouve séparée de ce qu'elle
     * explique.
     */
    private const HEADERS = [
        'id', 'reference', 'produit', 'variante',
        'stock_systeme', 'stock_compte',
        'prix_vente', 'cout_achat', 'seuil_alerte',
        'compte_par', 'verifie_par', 'observation',
    ];

    public function handle(): int
    {
        $path = $this->resolvePath();

        $query = Product::query()->with(['variants.attributeValues.attribute'])->orderBy('name');
        if (! $this->option('inactifs')) {
            $query->where('is_active', true);
        }

        $handle = @fopen($path, 'w');
        if ($handle === false) {
            $this->error("Impossible d'écrire dans {$path}");

            return self::FAILURE;
        }

        // BOM UTF-8 — sans lui Excel FR affiche « Bougé » au lieu de « Bougé ».
        fwrite($handle, "\xEF\xBB\xBF");
        $this->putRow($handle, self::HEADERS);

        $lignes = 0;

        $query->chunk(200, function ($products) use ($handle, &$lignes) {
            foreach ($products as $product) {
                // Un produit à variantes ne porte pas de stock propre : compter
                // « Sérum » globalement n'aurait pas de sens si seul le 30 ml
                // manque. On ne sort donc que ses variantes.
                if ($product->has_variants && $product->variants->isNotEmpty()) {
                    foreach ($product->variants as $variant) {
                        $this->putRow($handle, [
                            'V' . $variant->id,
                            $variant->sku ?? '',
                            $product->name,
                            $variant->label ?: ('Variante #' . $variant->id),
                            (int) $variant->stock,
                            '',                                    // stock_compte : à remplir
                            $this->money($variant->price ?? $product->price),
                            $this->money($product->cost_price),
                            (int) $product->low_stock_threshold,
                            '', '', '',            // compte_par, verifie_par, observation
                        ]);
                        $lignes++;
                    }

                    continue;
                }

                $this->putRow($handle, [
                    'P' . $product->id,
                    $product->sku ?? '',
                    $product->name,
                    '',
                    (int) $product->stock,
                    '',
                    $this->money($product->price),
                    $this->money($product->cost_price),
                    (int) $product->low_stock_threshold,
                    '', '', '',                    // compte_par, verifie_par, observation
                ]);
                $lignes++;
            }
        });

        fclose($handle);

        $this->info("✓ Feuille d'inventaire écrite : {$path}");
        $this->line("   {$lignes} ligne(s) — produits sans variante + variantes.");
        $this->newLine();
        $this->line('   À remplir : la colonne <options=bold>stock_compte</> uniquement.');
        $this->line('   Une case laissée vide = produit non compté → stock inchangé à l\'import.');
        $this->line('   Colonnes de contrôle libres : compte_par, verifie_par, observation — jamais lues à l\'import.');
        $this->line('   Réimport : php artisan shop:import-stock ' . $path . ' --dry-run');

        return self::SUCCESS;
    }

    private function resolvePath(): string
    {
        $out = $this->option('out');

        if (! $out) {
            return storage_path('app/inventaire-' . now()->format('Y-m-d') . '.csv');
        }

        // Un chemin relatif est résolu depuis le répertoire courant, là où
        // l'utilisateur s'attend à retrouver son fichier.
        return str_starts_with($out, '/') || preg_match('/^[A-Za-z]:/', $out)
            ? $out
            : getcwd() . DIRECTORY_SEPARATOR . $out;
    }

    /** Décimale française : la virgule, sinon Excel FR lit du texte. */
    private function money(mixed $value): string
    {
        return $value === null ? '' : str_replace('.', ',', (string) (float) $value);
    }

    /** @param array<int, string|int> $row */
    private function putRow($handle, array $row): void
    {
        // `escape` explicitement vide : le comportement par défaut est déprécié
        // depuis PHP 8.4 et produit des échappements non standard.
        fputcsv($handle, $row, ';', '"', '');
    }
}
