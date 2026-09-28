<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionsSeeder extends Seeder
{
    /**
     * Catalogue complet des permissions de l'admin, groupées par module.
     * Convention : {module}.{action}. Le préfixe sert au regroupement
     * dans l'écran /admin/permissions et au filtrage des menus côté front.
     */
    public const PERMISSIONS = [
        // Tableau de bord
        'dashboard.view',
        // ⚠ Finance : données monétaires sensibles (CA, marges, totaux €).
        // Volontairement retirée du staff « agent » → valeurs privées.
        'finance.view',
        // Produits
        'products.view', 'products.create', 'products.edit', 'products.delete',
        // Gammes de soins
        'product_lines.view', 'product_lines.create', 'product_lines.edit', 'product_lines.delete',
        // Catégories
        'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
        // Registre des mouvements de stock. `adjust` corrige un compteur après
        // comptage : c'est un geste d'inventaire, pas une consultation.
        'stock.view', 'stock.adjust',
        // Module Achats : prix d'achat, fournisseurs, prix de revient, CMUP.
        // Réservé à l'administration — la gérante comprise : ces montants sont
        // la base de la marge, ils ne circulent pas dans l'équipe.
        'purchases.view', 'purchases.create', 'purchases.edit', 'purchases.delete',
        'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
        // Commandes
        'orders.view', 'orders.edit', 'orders.delete',
        // Caisse / POS
        'pos.use',
        // File des messages WhatsApp Business. `convert` couvre les gestes qui
        // engagent (rattacher à une commande, écarter) : consulter la file et
        // décider de son sort ne sont pas le même niveau de responsabilité.
        'whatsapp.view', 'whatsapp.convert',
        // Clients
        'customers.view', 'customers.edit', 'customers.delete',
        // Coupons
        'coupons.view', 'coupons.create', 'coupons.edit', 'coupons.delete',
        // Journal des actions du tableau de bord (consultation seule)
        'activity.view',
        // Promotions automatiques (remises sans code, écoulement de stock)
        'promotions.view', 'promotions.create', 'promotions.edit', 'promotions.delete',
        // Club fidélité / programme
        'program.view', 'program.edit',
        // Avis clients
        'reviews.view', 'reviews.edit', 'reviews.delete',
        // Newsletter
        'newsletter.view', 'newsletter.edit',
        // Paniers abandonnés
        'abandoned_carts.view',
        // Contact / messages
        'contact.view', 'contact.edit', 'contact.delete',
        // Livraisons
        'deliveries.view', 'deliveries.edit',
        // Tournées : préparer une sortie expédie les commandes en bloc, d'où
        // une permission d'écriture distincte de la simple consultation.
        'rounds.view', 'rounds.edit',
        // `rounds.point` est à part : c'est la seule permission du rôle
        // livreur. Elle autorise le pointage, pas la préparation ni la
        // clôture — et ce sont les requêtes, bornées à `courier_id`, qui
        // disent quelles tournées.
        'rounds.point',
        'delivery_zones.view', 'delivery_zones.create', 'delivery_zones.edit', 'delivery_zones.delete',
        // Villes et communes desservies : ce que la cliente peut choisir comme
        // adresse. Distinct des zones, qui en fixent le prix.
        'localities.view', 'localities.create', 'localities.edit', 'localities.delete',
        // Paramètres
        'settings.view', 'settings.edit',
        // Rôles & permissions
        'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
        'permissions.view', 'permissions.create', 'permissions.edit', 'permissions.delete',
        // Utilisateurs admin
        'users.view', 'users.create', 'users.edit', 'users.delete',
        // Blog
        'blog.view', 'blog.create', 'blog.edit', 'blog.delete',
        // Galerie photos (home)
        'gallery.view', 'gallery.create', 'gallery.edit', 'gallery.delete',
        // Diagnostics système
        'diagnostics.view',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // ── admin : tout ─────────────────────────────────────────────────────
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(self::PERMISSIONS);

        // ── manager (gérant) : tout SAUF gestion users/roles/permissions et
        //    settings.edit — mais garde la finance (voit le CA/marges). ────────
        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager->syncPermissions(array_values(array_filter(self::PERMISSIONS, fn ($p) =>
            ! str_starts_with($p, 'roles.')
            && ! str_starts_with($p, 'permissions.')
            && ! str_starts_with($p, 'users.')
            // Le module Achats reste hors du périmètre de la gérante : voir le
            // chiffre d'affaires et les marges est une chose, connaître les prix
            // fournisseurs en est une autre. À rouvrir ici si la décision change
            // — les routes exigent de toute façon le rôle admin.
            && ! str_starts_with($p, 'purchases.')
            && ! str_starts_with($p, 'suppliers.')
            && ! in_array($p, ['settings.edit'], true)
        )));

        // ── staff (agent) : opérations quotidiennes, SANS finance ni
        //    administration. C'est le rôle d'un agent : il ne voit pas
        //    les valeurs monétaires privées. ───────────────────────────────────
        $staff = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        $staff->syncPermissions(self::staffPermissions());

        // ── livreur : pointer ses propres tournées, rien d'autre. ────────────
        // Pas de tableau de bord, pas de commandes, pas de catalogue : il
        // n'entre pas dans le back-office. Une seule permission, et l'écran
        // qu'elle ouvre ne montre que les tournées à son nom.
        $livreur = Role::firstOrCreate(['name' => 'livreur', 'guard_name' => 'web']);
        $livreur->syncPermissions(['rounds.point']);

        // Purge le cache de permissions Spatie
        Artisan::call('permission:cache-reset');

        $this->command->info('✓ '.count(self::PERMISSIONS).' permissions synchronisées — rôles admin / manager / staff.');
    }

    /**
     * Permissions du rôle staff « agent » : il gère commandes, livraisons,
     * catalogue et service client, mais SANS accès à la finance, aux
     * paramètres, ni à la gestion des accès (users/roles/permissions).
     *
     * @return array<int, string>
     */
    public static function staffPermissions(): array
    {
        return array_values(array_filter(self::PERMISSIONS, fn ($p) =>
            // Accès administration exclus
            ! str_starts_with($p, 'roles.')
            && ! str_starts_with($p, 'permissions.')
            && ! str_starts_with($p, 'users.')
            && ! str_starts_with($p, 'settings.')
            && ! str_starts_with($p, 'diagnostics.')
            // Finance privée exclue — prix d'achat et fournisseurs compris
            && $p !== 'finance.view'
            && ! str_starts_with($p, 'purchases.')
            && ! str_starts_with($p, 'suppliers.')
            // Les promotions fixent les prix de vente : décision commerciale,
            // pas opération quotidienne. L'agent les consulte (une cliente peut
            // poser la question) mais ne les crée pas. `promotions.edit` lève
            // par ailleurs le plafond de remise au comptoir : l'accorder au
            // staff reviendrait à supprimer ce garde-fou.
            && ! in_array($p, ['promotions.create', 'promotions.edit', 'promotions.delete'], true)
            // Le journal expose qui a fait quoi : outil d'encadrement,
            // pas de consultation entre collegues.
            && $p !== 'activity.view'
            // Corriger un compteur de stock, c'est arbitrer un écart
            // d'inventaire : la décision revient à la gérante. L'agent compte
            // et consulte l'historique, il ne solde pas le manquant lui-même.
            && $p !== 'stock.adjust'
            // Actions destructrices sensibles exclues. Supprimer une ville
            // emporte toutes ses communes : l'agent ajoute et corrige une
            // localité manquante, il ne retire pas une branche entière.
            && ! in_array($p, ['customers.delete', 'orders.delete', 'products.delete', 'localities.delete'], true)
        ));
    }
}
