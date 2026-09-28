<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vide la boutique de ses traces de recette, avant la mise en service.
 *
 * La règle tient en une phrase : **les transactions partent, la configuration
 * reste.** Commandes de test, paniers, mouvements de stock, tournées,
 * messages WhatsApp et journal d'activité disparaissent ; produits,
 * catégories, zones de livraison, coupons, réglages et comptes de l'équipe
 * ne bougent pas.
 *
 * Pourquoi cela mérite une commande plutôt qu'une poignée de `DELETE` : les
 * tables se tiennent par des clés étrangères, et l'ordre de suppression n'est
 * pas devinable. Une purge faite à la main laisse des lignes orphelines, ou
 * s'arrête au milieu sur une contrainte — et la boutique ouvre avec une
 * moitié de données de test.
 *
 * Usage :
 *   php artisan shop:reset-launch --dry-run
 *   php artisan shop:reset-launch --user=gerant@maboutique.com
 *   php artisan shop:reset-launch --clients        # purge aussi les comptes de test
 */
class ResetLaunchCommand extends Command
{
    protected $signature = 'shop:reset-launch
        {--dry-run : Montrer ce qui serait effacé, sans rien supprimer}
        {--clients : Supprimer aussi les comptes de test (@boutique.test et comptes de commande rapide)}
        {--keep-stock : Ne pas toucher au registre des stocks}
        {--zero-stock : Remettre les compteurs à 0 (avant un comptage physique complet)}
        {--user= : Email de l\'opérateur à qui attribuer le stock d\'ouverture}
        {--force : Ne pas demander confirmation}';

    protected $description = 'Efface les données de recette (commandes, paniers, tournées…) pour ouvrir sur une boutique propre';

    /**
     * Tables à vider, dans l'ordre où les contraintes le permettent : les
     * enfants avant les parents.
     *
     * @var array<string, string>  table => ce qu'elle contient, en français
     */
    private const TABLES = [
        'delivery_round_orders' => 'Lignes de tournée',
        'delivery_rounds'       => 'Tournées',
        'deliveries'            => 'Livraisons (ancien module)',
        'payments'              => 'Paiements',
        'order_items'           => 'Lignes de commande',
        'orders'                => 'Commandes',
        'cart_items'            => 'Lignes de panier',
        'carts'                 => 'Paniers',
        'whatsapp_messages'     => 'Messages WhatsApp',
        'purchase_order_items'  => "Lignes de bon d'achat",
        'purchase_orders'       => "Bons d'achat",
        'product_reviews'       => 'Avis produits',
        'wishlists'             => 'Listes d\'envies',
        'contact_inquiries'     => 'Messages de contact',
        'auth_otps'             => 'Codes de connexion',
        'activity_log'          => 'Journal des actions',
    ];

    /**
     * Domaines des comptes qui n'existent que pour la recette.
     *
     * `.test` est réservé par l'IANA et `.local` n'est pas routable : aucun
     * message ne peut partir vers une vraie personne, et c'est justement à ça
     * qu'on les reconnaît.
     */
    private const DOMAINES_DE_TEST = ['@boutique.test', '@clients.local'];

    public function handle(): int
    {
        $simulation = (bool) $this->option('dry-run');

        $aEffacer = $this->recenser();
        $comptes  = $this->option('clients') ? $this->comptesDeTest() : collect();

        $this->afficher($aEffacer, $comptes);

        if ($simulation) {
            $this->newLine();
            $this->info("Simulation : rien n'a été supprimé.");
            $this->line('  Relancez sans --dry-run pour appliquer.');

            return self::SUCCESS;
        }

        $total = array_sum($aEffacer) + $comptes->count();

        if ($total === 0) {
            $this->newLine();
            $this->info('Rien à effacer : la boutique est déjà propre.');

            return self::SUCCESS;
        }

        // La confirmation porte le décompte réel. Un « êtes-vous sûr ? » sans
        // chiffre se valide sans lire, et c'est exactement ce geste-là qu'on
        // ne veut pas voir passer sur une base de production.
        if (! $this->option('force') && ! $this->confirm(
            "Effacer définitivement {$total} enregistrement(s) ? Le catalogue et les réglages sont conservés.",
            false,
        )) {
            $this->line('Annulé.');

            return self::SUCCESS;
        }

        $this->purger($aEffacer, $comptes);

        // Le registre des stocks se réinitialise à part : il ne se vide pas,
        // il se rouvre sur une ligne d'ouverture par article.
        if (! $this->option('keep-stock')) {
            $this->newLine();
            $this->line('── Registre des stocks ──');

            $this->call('shop:reset-stock', array_filter([
                '--force' => true,
                '--zero'  => (bool) $this->option('zero-stock'),
                '--user'  => $this->option('user'),
            ]));
        }

        $this->newLine();
        $this->info('✓ Boutique prête pour la mise en service.');
        $this->line('  Vérifiez ensuite : Commandes (vide), Mouvements de stock (une ligne par article),');
        $this->line('  et le compteur de numéro de commande, qui repart à ORD-' . now()->year . '-00001.');

        return self::SUCCESS;
    }

    /**
     * Ce que chaque table contient aujourd'hui.
     *
     * @return array<string, int>
     */
    private function recenser(): array
    {
        $compte = [];

        foreach (self::TABLES as $table => $libelle) {
            if (Schema::hasTable($table)) {
                $compte[$table] = DB::table($table)->count();
            }
        }

        return $compte;
    }

    /**
     * Les comptes qui n'existent que pour la recette.
     *
     * Un compte de l'équipe ou une vraie cliente importée ne doit jamais
     * tomber dans cette liste : on ne s'appuie que sur le domaine de
     * l'adresse, qui est le seul marqueur voulu comme tel.
     */
    private function comptesDeTest()
    {
        $requete = User::query();

        foreach (self::DOMAINES_DE_TEST as $domaine) {
            $requete->orWhere('email', 'like', '%' . $domaine);
        }

        return $requete->get(['id', 'name', 'email']);
    }

    /** @param array<string, int> $aEffacer */
    private function afficher(array $aEffacer, $comptes): void
    {
        $lignes = [];

        foreach ($aEffacer as $table => $nombre) {
            if ($nombre > 0) {
                $lignes[] = [self::TABLES[$table], $nombre];
            }
        }

        if ($comptes->isNotEmpty()) {
            $lignes[] = ['Comptes de test', $comptes->count()];
        }

        $this->newLine();

        if ($lignes === []) {
            $this->line('  Rien à effacer.');

            return;
        }

        $this->line('  À EFFACER');
        $this->table(['Données', 'Lignes'], $lignes);

        $this->line('  CONSERVÉS : produits, catégories, gammes, zones de livraison, coupons,');
        $this->line('              promotions, réglages, comptes de l\'équipe et clientes réelles.');

        if (! $this->option('clients')) {
            $this->newLine();
            $this->comment('  Les comptes de test sont conservés (ajoutez --clients pour les supprimer).');
        }
    }

    /** @param array<string, int> $aEffacer */
    private function purger(array $aEffacer, $comptes): void
    {
        DB::transaction(function () use ($aEffacer, $comptes) {
            foreach (array_keys($aEffacer) as $table) {
                // `delete` et non `truncate` : truncate ne se laisse pas
                // annuler par la transaction, et ignore les clés étrangères
                // au lieu de signaler qu'on efface dans le mauvais ordre.
                DB::table($table)->delete();
            }

            if ($comptes->isNotEmpty()) {
                User::whereIn('id', $comptes->pluck('id'))->forceDelete();
            }
        });

        $this->newLine();

        foreach ($aEffacer as $table => $nombre) {
            if ($nombre > 0) {
                $this->line('  ✓ ' . self::TABLES[$table] . ' — ' . $nombre . ' effacée(s)');
            }
        }

        if ($comptes->isNotEmpty()) {
            $this->line('  ✓ Comptes de test — ' . $comptes->count() . ' supprimé(s)');
        }
    }
}
