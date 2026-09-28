<?php

namespace Modules\Catalog\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Catalog\Models\StockMovement;

/**
 * Remet le registre des stocks à son point de départ, pour un lancement.
 *
 * Avant la mise en service, la base porte les traces de la recette : ventes
 * de test, corrections d'essai, imports successifs. Ce bruit n'a rien à faire
 * dans l'écran « Mouvements de stock » le jour où la gérante s'en sert pour
 * expliquer un écart de comptage — elle y chercherait l'origine d'un manquant
 * et tomberait sur des commandes qui n'ont jamais existé.
 *
 * La commande fait deux choses, et refuse de les faire à moitié :
 *
 *   1. elle vide l'historique des mouvements ;
 *   2. elle réécrit une première ligne par article — le stock d'ouverture —
 *      de sorte que le registre commence par une phrase qui dit d'où l'on
 *      part, et non par du vide.
 *
 * Le point 2 est ce qui distingue cette commande d'un `TRUNCATE`. Un registre
 * qui commence à rien ne permet pas de vérifier le premier inventaire : on ne
 * saurait pas si les 18 pots en rayon sont ceux qu'on avait comptés ou le
 * résultat d'une saisie oubliée.
 *
 * Usage :
 *   php artisan shop:reset-stock --dry-run
 *   php artisan shop:reset-stock --user=gerant@maboutique.com
 *   php artisan shop:reset-stock --zero        # avant un comptage complet
 */
class ResetStockCommand extends Command
{
    protected $signature = 'shop:reset-stock
        {--dry-run : Montrer ce qui serait fait, sans rien modifier}
        {--zero : Remettre aussi tous les compteurs à 0 (avant un comptage physique complet)}
        {--user= : Email de l\'opérateur à qui attribuer le stock d\'ouverture dans le journal}
        {--force : Ne pas demander confirmation (scripts, CI)}';

    protected $description = "Repart d'un registre de stock propre pour le lancement : purge l'historique et pose le stock d'ouverture";

    public function handle(): int
    {
        $simulation = (bool) $this->option('dry-run');
        $aZero      = (bool) $this->option('zero');

        if (! $this->attribuerOperateur()) {
            return self::FAILURE;
        }

        $mouvements = StockMovement::count();
        $articles   = $this->articles();

        $this->afficherEtat($mouvements, $articles, $aZero);

        if ($simulation) {
            $this->newLine();
            $this->info('Simulation : rien n\'a été modifié.');

            return self::SUCCESS;
        }

        // Geste irréversible : l'historique effacé ne se reconstitue pas. La
        // confirmation porte le décompte réel, pas un « êtes-vous sûr ? » que
        // l'on valide sans lire.
        if (! $this->option('force') && ! $this->confirm(
            "Effacer {$mouvements} mouvement(s) et poser le stock d'ouverture ? C'est irréversible.",
            false,
        )) {
            $this->line('Annulé.');

            return self::SUCCESS;
        }

        $poses = DB::transaction(function () use ($articles, $aZero) {
            // Purge d'abord : les lignes d'ouverture qu'on va écrire ne doivent
            // pas être emportées avec le reste.
            StockMovement::query()->delete();

            return $this->poserOuverture($articles, $aZero);
        });

        $this->newLine();
        $this->info("✓ Registre réinitialisé — {$poses} ligne(s) d'ouverture posée(s).");

        if ($aZero) {
            $this->warn('Les compteurs sont à 0 : le catalogue est en rupture tant que le comptage n\'est pas importé.');
            $this->line('  Suite : shop:export-stock → comptage en boutique → shop:import-stock --dry-run');
        }

        return self::SUCCESS;
    }

    /**
     * Les porteurs de compteur : une variante quand le produit en a, le
     * produit lui-même sinon. Les produits sans suivi de stock n'ont pas de
     * compteur à ouvrir.
     *
     * @return \Illuminate\Support\Collection<int, array{product: Product, variant: ?ProductVariant, stock: int, libelle: string}>
     */
    private function articles()
    {
        $lignes = collect();

        Product::with('variants')->where('track_stock', true)->chunk(200, function ($produits) use ($lignes) {
            foreach ($produits as $produit) {
                if ($produit->variants->isEmpty()) {
                    $lignes->push([
                        'product' => $produit,
                        'variant' => null,
                        'stock'   => (int) $produit->stock,
                        'libelle' => $produit->name,
                    ]);
                    continue;
                }

                foreach ($produit->variants as $variante) {
                    $lignes->push([
                        'product' => $produit,
                        'variant' => $variante,
                        'stock'   => (int) $variante->stock,
                        'libelle' => $produit->name . ' · ' . ($variante->label ?? $variante->sku ?? '—'),
                    ]);
                }
            }
        });

        return $lignes;
    }

    /**
     * Écrit la ligne d'ouverture de chaque article.
     *
     * Insertion directe et non passage par StockLedger : le registre est vide
     * à cet instant, et il n'y a pas de déplacement à appliquer — on décrit
     * l'état de départ, on ne le modifie pas. Passer par le service ferait
     * doubler le compteur.
     */
    private function poserOuverture($articles, bool $aZero): int
    {
        $maintenant = now();
        $operateur  = Auth::id();
        $motif      = $aZero
            ? "Stock d'ouverture (remis à zéro avant comptage)"
            : "Stock d'ouverture au lancement";

        $poses = 0;

        foreach ($articles as $ligne) {
            $depart = $aZero ? 0 : $ligne['stock'];

            if ($aZero && $ligne['stock'] !== 0) {
                $porteur = $ligne['variant'] ?? $ligne['product'];
                $porteur->forceFill(['stock' => 0])->save();
            }

            StockMovement::create([
                'product_id'   => $ligne['product']->id,
                'variant_id'   => $ligne['variant']?->id,
                'type'         => StockMovement::TYPE_IMPORT,
                // Quantité égale au stock : la ligne se lit comme l'entrée
                // initiale qui amène le compteur là où il est.
                'quantity'     => $depart,
                'stock_before' => 0,
                'stock_after'  => $depart,
                'reason'       => $motif,
                'user_id'      => $operateur,
                'created_at'   => $maintenant,
                'updated_at'   => $maintenant,
            ]);

            $poses++;
        }

        return $poses;
    }

    private function afficherEtat(int $mouvements, $articles, bool $aZero): void
    {
        $total = $articles->sum('stock');

        $this->newLine();
        $this->line('  Registre actuel   : ' . $mouvements . ' mouvement(s) — seront effacés');
        $this->line('  Articles suivis   : ' . $articles->count());
        $this->line('  Stock total       : ' . $total . ' unité(s)');
        $this->line('  Après la commande : ' . ($aZero ? '0 (compteurs remis à zéro)' : $total . ' (compteurs inchangés)'));

        // Un aperçu vaut mieux qu'un décompte : la gérante reconnaît ses
        // produits et repère du premier coup d'œil un compteur aberrant.
        $apercu = $articles->sortByDesc('stock')->take(5);

        if ($apercu->isNotEmpty()) {
            $this->newLine();
            $this->table(
                ['Article', 'Stock'],
                $apercu->map(fn ($l) => [$l['libelle'], $l['stock']])->all(),
            );

            if ($articles->count() > 5) {
                $this->line('  … et ' . ($articles->count() - 5) . ' autre(s).');
            }
        }
    }

    /** Même règle que shop:import-stock : un inventaire non attribué ne se conteste pas. */
    private function attribuerOperateur(): bool
    {
        $email = $this->option('user');

        if (! $email) {
            $this->warn("Aucun opérateur (--user=) : le stock d'ouverture sera journalisé sans auteur.");

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
}
