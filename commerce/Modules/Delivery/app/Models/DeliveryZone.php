<?php

namespace Modules\Delivery\Models;

use App\Support\Auditing\TracksOperator;
use Illuminate\Database\Eloquent\Model;
use Modules\Admin\Models\Setting;
// Le district d'Abidjan n'est décrit qu'à un seul endroit : le repli
// tarifaire ci-dessous doit couvrir exactement les mêmes communes que la
// règle de paiement, sinon les deux se contrediraient sur une même adresse.
use Modules\Order\Support\AbidjanZone;
use Modules\Order\Support\HomeZone;

class DeliveryZone extends Model
{
    use TracksOperator;

    /**
     * Villes où le tarif dépend de la commune : la zone locale de la boutique
     * (HomeZone, défaut Abidjan). Pour elle, on n'autorise PAS le match au
     * niveau ville même si une zone du même nom existe — la commune doit
     * toujours être choisie pour avoir un prix exact.
     *
     * @return string[] noms normalisés
     */
    public static function multiCommuneCities(): array
    {
        return [HomeZone::normalized()];
    }

    /** Réglage admin du tarif de base d'Abidjan, et sa valeur par défaut. */
    public const ABIDJAN_BASE_SETTING = 'shipping_abidjan_base_cost';
    public const ABIDJAN_BASE_DEFAULT = 2500;

    protected $fillable = [
        'group', 'name', 'country', 'cities', 'price',
        'price_unit', 'free_threshold', 'sort_order', 'active',
    ];

    protected $casts = [
        'cities'         => 'array',
        'price'          => 'integer',
        'free_threshold' => 'integer',
        'sort_order'     => 'integer',
        'active'         => 'boolean',
    ];

    /**
     * Résout le tarif livraison.
     * Ordre de recherche :
     *   1. commune (le plus précis : "Cocody Centre", "Marcory")
     *   2. ville — SAUF si c'est une ville multi-communes (Abidjan) sans commune,
     *      auquel cas on retourne null pour forcer le client à choisir une commune.
     *   3. fallback international (pays ≠ CI) → zone country='XX' (per_kg)
     */
    public static function quote(
        string $cityRaw,
        string $country = 'CI',
        ?int $cartSubtotal = null,
        ?string $communeRaw = null
    ): ?array {
        // 1) International : tout pays autre que CI tombe sur la zone XX (per_kg)
        if ($country !== 'CI') {
            $intl = self::where('active', true)->where('country', 'XX')->first();
            return $intl ? self::format($intl, $cartSubtotal) : null;
        }

        $cityNorm    = self::normalize($cityRaw);
        $communeNorm = self::normalize($communeRaw ?? '');

        // 2) Ville multi-communes sans commune renseignée → forcer la sélection.
        //    Empêche une zone "Abidjan" (fallback ville) de gagner sur "Abobo" (commune).
        if (in_array($cityNorm, self::multiCommuneCities(), true) && $communeNorm === '') {
            return null; // déclenche needs_commune côté controller
        }

        // 3) Recherche : commune en priorité, puis ville (la commune gagne toujours
        //    si elle matche, peu importe qu'une zone ville existe aussi).
        $zones = self::where('active', true)->where('country', 'CI')->orderBy('sort_order')->get();
        $candidates = array_filter([$communeNorm, $cityNorm], fn($v) => $v !== '');

        foreach ($candidates as $needle) {
            foreach ($zones as $zone) {
                // Pour la 2e passe (ville) : skip si c'est une ville multi-communes
                // dont on n'a pas la commune — protection ceinture+bretelles.
                if (
                    $needle === $cityNorm
                    && in_array($cityNorm, self::multiCommuneCities(), true)
                ) {
                    continue 2;
                }

                $names = array_merge([$zone->name], $zone->cities ?? []);
                foreach ($names as $n) {
                    if (self::normalize($n) === $needle) {
                        return self::format($zone, $cartSubtotal);
                    }
                }
            }
        }
        return null;
    }

    /**
     * Devis livraison, avec le tarif de base d'Abidjan en dernier recours.
     *
     * La gérante ajoute les communes et quartiers au fil de l'eau : une adresse
     * abidjanaise encore absente des zones ne doit pas partir en « frais à
     * confirmer » — c'est un blocage à l'expédition pour une livraison qu'on
     * sait faire. On la facture au tarif de base, modifiable en admin.
     *
     * Reste sans repli : Abidjan sans commune (on la demande, elle change le
     * prix), et tout ce qui n'est pas Abidjan.
     */
    public static function quoteWithAbidjanBase(
        string $cityRaw,
        string $country = 'CI',
        ?int $cartSubtotal = null,
        ?string $communeRaw = null
    ): ?array {
        $quote = self::quote($cityRaw, $country, $cartSubtotal, $communeRaw);
        if ($quote) {
            return $quote;
        }

        if ($country !== 'CI') {
            return null;
        }

        // Abidjan sans commune : le prix dépend de la commune, on la réclame
        // plutôt que d'appliquer un tarif qui serait faux une fois sur deux.
        if (self::isMultiCommuneCity($cityRaw) && trim((string) $communeRaw) === '') {
            return null;
        }

        if (! AbidjanZone::isAbidjan(['city' => $cityRaw, 'commune' => $communeRaw])) {
            return null;
        }

        return [
            'zone_id'   => null,
            'zone_name' => HomeZone::label() . ' (tarif de base)',
            'group'     => 'Grand ' . HomeZone::label(),
            'price'     => self::abidjanBaseCost(),
            'unit'      => 'fixed',
            'fallback'  => true,
        ];
    }

    /** Tarif de base d'Abidjan, en francs CFA. */
    public static function abidjanBaseCost(): int
    {
        $valeur = Setting::get(self::ABIDJAN_BASE_SETTING);

        return is_numeric($valeur) && (int) $valeur > 0
            ? (int) $valeur
            : self::ABIDJAN_BASE_DEFAULT;
    }

    /**
     * Helper utilisé par le controller pour décider entre 404 "non couvert"
     * et 200 needs_commune.
     */
    public static function isMultiCommuneCity(?string $city): bool
    {
        return $city !== null
            && in_array(self::normalize($city), self::multiCommuneCities(), true);
    }

    /**
     * Cette zone appartient-elle au district d'Abidjan ?
     *
     * Le test porte sur le GROUPE, jamais sur le nom : « Abatta » ou « Faya »
     * ne ressemblent pas à Abidjan mais en font partie. La gérante saisit le
     * groupe à la main, d'où la comparaison souple — « Grand Abidjan »,
     * « grand abidjan », « Abidjan » désignent la même chose.
     */
    public static function isAbidjanGroup(?string $group): bool
    {
        return $group !== null
            && str_contains(self::normalize($group), HomeZone::normalized());
    }

    /**
     * Noms et alias des zones du district, normalisés.
     *
     * Mémorisé : isAbidjan() est appelée une fois par commande affichée, et
     * une liste de commandes ferait autrement une requête par ligne. Le cache
     * est vidé dès qu'une zone bouge (voir booted()).
     *
     * @return string[]
     */
    public static function abidjanZoneNames(): array
    {
        if (self::$abidjanNamesCache !== null) {
            return self::$abidjanNamesCache;
        }

        try {
            $noms = [];
            foreach (self::where('active', true)->where('country', 'CI')->get() as $zone) {
                if (! self::isAbidjanGroup($zone->group)) {
                    continue;
                }
                foreach (array_merge([$zone->name], $zone->cities ?? []) as $n) {
                    $n = self::normalize((string) $n);
                    if ($n !== '') {
                        $noms[] = $n;
                    }
                }
            }
            return self::$abidjanNamesCache = array_values(array_unique($noms));
        } catch (\Throwable) {
            // Base absente ou non migrée (installation, console) : le socle des
            // communes officielles porté par AbidjanZone suffit à répondre.
            // Volontairement non mémorisé, pour réessayer au prochain appel.
            return [];
        }
    }

    /** Cache des noms de zones abidjanaises — voir abidjanZoneNames(). */
    private static ?array $abidjanNamesCache = null;

    /** Vide le cache. Utile aux tests, et appelé à chaque écriture. */
    public static function forgetAbidjanZoneNames(): void
    {
        self::$abidjanNamesCache = null;
    }

    /**
     * Ajouter ou retirer une zone change le district : le cache doit tomber,
     * sinon la gérante ajoute « Abatta » et les commandes continuent d'être
     * classées hors Abidjan jusqu'au prochain redémarrage.
     */
    protected static function booted(): void
    {
        static::saved(fn () => self::forgetAbidjanZoneNames());
        static::deleted(fn () => self::forgetAbidjanZoneNames());
    }

    private static function format(self $zone, ?int $cartSubtotal): array
    {
        // Pas de livraison gratuite dans le système : le prix de la zone
        // s'applique toujours tel quel (free_threshold volontairement ignoré).
        return [
            'zone_id'   => $zone->id,
            'zone_name' => $zone->name,
            'group'     => $zone->group,
            'price'     => $zone->price,
            'unit'      => $zone->price_unit,
        ];
    }

    /**
     * Publique : AbidjanZone compare des adresses à des noms de zones. Les
     * deux côtés doivent normaliser à l'identique, sinon « Port-Bouët » et
     * « port-bouet » cessent d'être la même commune.
     */
    public static function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = preg_replace('/\s+/', ' ', $s);
        $map = ['à','â','ä','á','ã','ç','é','è','ê','ë','î','ï','í','ô','ö','ó','ù','û','ü','ú','ÿ','ñ','œ'];
        $rep = ['a','a','a','a','a','c','e','e','e','e','i','i','i','o','o','o','u','u','u','u','y','n','oe'];
        return str_replace($map, $rep, $s);
    }
}
