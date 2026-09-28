<?php

namespace App\Support;

use Modules\Admin\Models\Setting;

/**
 * Les pans du back-office que la gérante peut éteindre.
 *
 * Une boutique n'utilise pas tout. Laisser dans le menu une douzaine d'écrans
 * dont on ne se sert jamais, c'est allonger la recherche de ceux qu'on utilise
 * vingt fois par jour — et faire découvrir à un agent, un mardi chargé, un
 * écran dont personne ne lui a parlé.
 *
 * Le registre est la source unique : il dit ce qui s'éteint, quelles
 * permissions cela couvre, et donc quelles entrées de menu disparaissent. Sans
 * lui, l'interrupteur et le menu diborderaient l'un de l'autre, et on aurait
 * un module « éteint » qui laisse ses écrans en place jusqu'au 403.
 */
final class ModuleRegistry
{
    /**
     * Chaque module : ce qu'il fait, et les préfixes de permission qu'il
     * couvre. `default` dit s'il est allumé quand personne n'a rien choisi.
     *
     * Ne figurent ici que les pans qu'on peut retirer sans casser une vente.
     * Commandes, catalogue, caisse et clientes n'y sont pas : les éteindre
     * reviendrait à fermer la boutique, et un interrupteur qui ne doit jamais
     * servir n'a pas à exister.
     *
     * @var array<string, array{label: string, description: string, permissions: array<int, string>, default: bool}>
     */
    private const MODULES = [
        'purchases' => [
            'label'       => 'Achats',
            'description' => "Fournisseurs, bons d'achat, prix de revient et coût moyen pondéré. Réservé aux administrateurs : ces montants sont la base de la marge.",
            'permissions' => ['purchases.', 'suppliers.'],
            'default'     => false,
        ],
        'rounds' => [
            'label'       => 'Tournées',
            'description' => "Préparation des tournées, feuille de route et pointage au retour. Éteint, les commandes se marquent expédiées une par une.",
            'permissions' => ['rounds.'],
            'default'     => true,
        ],
        'blog' => [
            'label'       => 'Blog',
            'description' => 'Articles et catégories du journal de la boutique.',
            'permissions' => ['blog.'],
            'default'     => true,
        ],
        'newsletter' => [
            'label'       => 'Newsletter',
            'description' => 'Campagnes et abonnées.',
            'permissions' => ['newsletter.'],
            'default'     => true,
        ],
        'program' => [
            'label'       => 'Club fidélité',
            'description' => 'Paliers, avantages et points des clients.',
            'permissions' => ['program.'],
            'default'     => true,
        ],
        'reviews' => [
            'label'       => 'Avis clients',
            'description' => 'Modération des avis déposés sur les produits.',
            'permissions' => ['reviews.'],
            'default'     => true,
        ],
        'abandoned_carts' => [
            'label'       => 'Paniers abandonnés',
            'description' => 'Relances des paniers laissés en cours.',
            'permissions' => ['abandoned_carts.'],
            'default'     => true,
        ],
        'gallery' => [
            'label'       => 'Galerie',
            'description' => "Galerie de photos affichée sur la page d'accueil.",
            'permissions' => ['gallery.'],
            'default'     => true,
        ],
        'promotions' => [
            'label'       => 'Promotions',
            'description' => 'Remises automatiques, sans code.',
            'permissions' => ['promotions.'],
            'default'     => true,
        ],
        'coupons' => [
            'label'       => 'Coupons',
            'description' => 'Codes promotionnels saisis par le client.',
            'permissions' => ['coupons.'],
            'default'     => true,
        ],
        'wishlist' => [
            'label'       => 'Liste de souhaits',
            'description' => 'Les clients mettent des produits de côté depuis la boutique.',
            'permissions' => ['wishlist.'],
            'default'     => true,
        ],
        'whatsapp' => [
            'label'       => 'File WhatsApp',
            'description' => 'Messages entrants à rapprocher des commandes (double saisie).',
            'permissions' => ['whatsapp.'],
            'default'     => true,
        ],
    ];

    /** Réglage portant l'état d'un module. */
    public static function setting(string $module): string
    {
        return "module_{$module}_enabled";
    }

    public static function exists(string $module): bool
    {
        return isset(self::MODULES[$module]);
    }

    /**
     * Le module est-il allumé ?
     *
     * Un réglage absent vaut le défaut du registre, jamais « éteint » par
     * accident : une boutique qui n'a jamais touché à ces interrupteurs doit
     * garder tout ce qu'elle utilisait la veille.
     */
    public static function enabled(string $module): bool
    {
        if (! self::exists($module)) {
            return true;
        }

        try {
            $valeur = Setting::get(self::setting($module));
        } catch (\Throwable) {
            // Base absente ou non migrée (installation, tests unitaires) :
            // le défaut du registre répond, comme pour un réglage jamais posé.
            $valeur = null;
        }

        if ($valeur === null || $valeur === '') {
            return self::MODULES[$module]['default'];
        }

        if (is_bool($valeur)) {
            return $valeur;
        }

        return in_array(strtolower(trim((string) $valeur)), ['1', 'true', 'on', 'oui'], true);
    }

    /**
     * État de tous les modules, pour l'écran des réglages.
     *
     * @return array<int, array{key: string, label: string, description: string, enabled: bool, permissions: array<int, string>}>
     */
    public static function all(): array
    {
        $liste = [];

        foreach (self::MODULES as $cle => $module) {
            $liste[] = [
                'key'         => $cle,
                'label'       => $module['label'],
                'description' => $module['description'],
                'enabled'     => self::enabled($cle),
                'permissions' => $module['permissions'],
            ];
        }

        return $liste;
    }

    /**
     * Les préfixes de permission des modules éteints.
     *
     * Le front s'en sert pour retirer du menu les écrans devenus inutiles :
     * une entrée qui ne mène qu'à un 403 est pire que pas d'entrée du tout.
     *
     * @return array<int, string>
     */
    public static function disabledPermissionPrefixes(): array
    {
        $prefixes = [];

        foreach (self::MODULES as $cle => $module) {
            if (! self::enabled($cle)) {
                $prefixes = array_merge($prefixes, $module['permissions']);
            }
        }

        return array_values(array_unique($prefixes));
    }

    /**
     * État de chaque module sous la forme d'un réglage public
     * (`module_blog_enabled` => 'true'|'false').
     *
     * La boutique n'a pas accès à l'écran des modules, mais elle doit masquer
     * le lien « Blog » ou le bouton « Liste de souhaits » d'un module éteint.
     * Le défaut du registre est résolu ici, côté serveur : le front n'a pas à
     * connaître la liste des modules ni leurs défauts pour afficher juste.
     *
     * @return array<string, string>
     */
    public static function publicStates(): array
    {
        $states = [];

        foreach (array_keys(self::MODULES) as $key) {
            $states[self::setting($key)] = self::enabled($key) ? 'true' : 'false';
        }

        return $states;
    }
}
