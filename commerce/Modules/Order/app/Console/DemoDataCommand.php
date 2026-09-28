<?php

namespace Modules\Order\Console;

use Database\Seeders\DemoOrdersSeeder;
use Illuminate\Console\Command;

/**
 * Jeu de données de démonstration : commandes, tournées, livreurs.
 *
 *   php artisan shop:demo-data           crée (ou remplace) le jeu
 *   php artisan shop:demo-data --purge   le retire, sans toucher au reste
 *
 * Refusé en production. Sur la recette, à lancer en connaissance de cause :
 * les commandes de la semaine consomment les quotas du stock de la semaine, et
 * pèsent sur le best-seller de l'accueil et les chiffres du tableau de bord.
 */
class DemoDataCommand extends Command
{
    protected $signature = 'shop:demo-data
                            {--purge : Retire le jeu de démonstration au lieu de le créer}';

    protected $description = 'Crée ou retire un jeu de commandes et de tournées de démonstration';

    public function handle(): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('Refusé en production : ce jeu de données fausserait les ventes réelles.');

            return self::FAILURE;
        }

        if ($this->option('purge')) {
            $retire = DemoOrdersSeeder::purge();

            $this->info('Jeu de démonstration retiré.');
            $this->table(['Tournées', 'Lignes de tournée', 'Commandes', 'Comptes'], [[
                $retire['rounds'], $retire['lines'], $retire['orders'], $retire['users'],
            ]]);

            return self::SUCCESS;
        }

        $seeder = $this->laravel->make(DemoOrdersSeeder::class);
        $seeder->setContainer($this->laravel)->setCommand($this);
        $seeder->run();

        $b = $seeder->bilan();

        $this->newLine();
        $this->info('Jeu de démonstration créé' . (($b['purge'] ?? 0) > 0 ? ' (le précédent a été remplacé).' : '.'));
        $this->table(['Commandes', 'Tournées', 'Lignes de tournée', 'Livreurs', 'Clientes avec compte'], [[
            $b['orders'] ?? 0, $b['rounds'] ?? 0, $b['round_lines'] ?? 0, $b['couriers'] ?? 0, $b['customers'] ?? 0,
        ]]);

        $this->line('  Commandes : numéros ORD-DEMO-…, sur les 30 derniers jours.');
        $this->line('  Livreurs  : demo_kyao, demo_mdiallo, demo_saka — mot de passe « ' . DemoOrdersSeeder::COURIER_PASSWORD . ' ».');
        $this->line('  Clientes  : adresses en @' . DemoOrdersSeeder::EMAIL_DOMAIN . ' — mot de passe « ' . DemoOrdersSeeder::CUSTOMER_PASSWORD . ' ».');
        $this->newLine();
        $this->warn('Ces commandes pèsent sur le stock de la semaine, le best-seller de l\'accueil et le tableau de bord.');
        $this->line('  Pour les retirer : php artisan shop:demo-data --purge');

        return self::SUCCESS;
    }
}
