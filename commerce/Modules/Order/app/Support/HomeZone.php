<?php

namespace Modules\Order\Support;

use App\Support\Shop;
use Modules\Delivery\Models\DeliveryZone;

/**
 * La zone locale de la boutique : la ville où elle livre avec ses propres
 * livreurs, et où le paiement à la livraison est accepté.
 *
 * Marque blanche : la ville se règle dans l'administration
 * (`delivery_home_city`). Défaut « Abidjan », la boutique d'origine du
 * produit, pour qu'une instance existante garde exactement son comportement.
 *
 * L'identifiant interne de cette destination reste `abidjan`
 * (PaymentAvailability::ABIDJAN, colonne `orders.destination`) : il est
 * stocké en base, le renommer casserait l'historique. Il désigne la zone
 * locale quelle que soit la ville réglée ; seuls les libellés la nomment.
 */
final class HomeZone
{
    public const SETTING = 'delivery_home_city';

    public const DEFAULT_CITY = 'Abidjan';

    /** Clé du conteneur portant la ville mémorisée. */
    private const CACHE = 'homezone.city';

    /**
     * Ville de la zone locale, telle que saisie (« Abidjan », « Bouaké »).
     *
     * Mémorisée : isAbidjan() est appelée une fois par commande affichée, une
     * liste de commandes ferait sinon une requête par ligne. Le cache vit dans
     * le conteneur en `scoped` — vidé à chaque requête et à chaque job de file
     * d'attente — et tombe dès que le réglage est enregistré (voir
     * OrderServiceProvider).
     */
    public static function city(): string
    {
        $app = app();

        if (! $app->bound(self::CACHE)) {
            // Le réglage est lu dans la closure, à chaque résolution : après
            // un vidage du scope, la valeur est relue et non reprise figée.
            $app->scoped(self::CACHE, function () {
                $city = trim((string) Shop::setting(self::SETTING, ''));

                return $city !== '' ? $city : self::DEFAULT_CITY;
            });
        }

        return $app->make(self::CACHE);
    }

    /** Oublie la ville mémorisée (réglage modifié). */
    public static function forget(): void
    {
        app()->forgetInstance(self::CACHE);
    }

    /** Même ville, normalisée comme les adresses et les zones. */
    public static function normalized(): string
    {
        return DeliveryZone::normalize(self::city());
    }

    /** La zone locale est-elle Abidjan (district aux communes connues) ? */
    public static function isAbidjan(): bool
    {
        return self::normalized() === 'abidjan';
    }

    /** « Abidjan » — libellé de la zone locale. */
    public static function label(): string
    {
        return self::city();
    }

    /** « Hors Abidjan » — libellé du reste du pays. */
    public static function outsideLabel(): string
    {
        return 'Hors ' . self::city();
    }
}
