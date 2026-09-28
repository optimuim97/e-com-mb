<?php

namespace Modules\Catalog\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Cache de la charge utile de la page d'accueil.
 *
 * Neuf requêtes lourdes à chaque chargement : le cache est nécessaire. Mais
 * il n'était vidé nulle part, si bien qu'une promotion créée en administration
 * n'apparaissait qu'au bout de cinq minutes — ou après un `cache:clear` lancé
 * à la main sur le serveur. Les clés vivent donc ici, avec ce qui les périme.
 *
 * Le magasin est `database` : pas de tags, d'où l'énumération explicite. Une
 * clé oubliée dans `KEYS` se traduirait par une page d'accueil qui ne bouge
 * plus après modification.
 */
class HomeStatsCache
{
    /**
     * Le contenu est segmenté sur `finance.view` : ProductResource n'expose le
     * prix d'achat qu'aux ayants droit, et une clé unique l'aurait servi à
     * toutes les visiteuses.
     */
    private const KEY_PUBLIC  = 'home:stats';
    private const KEY_FINANCE = 'home:stats:finance';

    private const KEYS = [self::KEY_PUBLIC, self::KEY_FINANCE];

    private const TTL_MINUTES = 5;

    public static function key(bool $seesFinance): string
    {
        return $seesFinance ? self::KEY_FINANCE : self::KEY_PUBLIC;
    }

    public static function remember(bool $seesFinance, Closure $compute): array
    {
        return Cache::remember(
            self::key($seesFinance),
            now()->addMinutes(self::TTL_MINUTES),
            $compute
        );
    }

    /** À appeler après toute modification visible depuis l'accueil. */
    public static function forget(): void
    {
        foreach (self::KEYS as $key) {
            Cache::forget($key);
        }
    }
}
