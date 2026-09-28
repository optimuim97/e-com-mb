<?php

namespace Modules\Order\Support;

use Modules\Delivery\Models\DeliveryZone;

/**
 * Détection de la zone locale à partir d'une adresse de livraison.
 *
 * Marque blanche : la zone locale est la ville réglée par la boutique
 * (HomeZone, défaut Abidjan). Le nom de la classe est historique ; tout ce
 * qui suit parle d'Abidjan parce que c'est le cas par défaut et le mieux
 * connu, mais la règle vaut pour n'importe quelle ville.
 *
 * Règle métier : hors zone locale, on n'expédie que ce qui est déjà payé
 * (pas de paiement à la livraison). Une adresse est abidjanaise si la ville
 * contient « Abidjan », ou si la commune appartient au district.
 *
 * Le district livré est plus large que le découpage administratif : Abatta,
 * Faya, Riviera, Angré, Gonzagueville, N'Dotré ne sont pas des communes
 * officielles mais sont bien desservies et tarifées. Les décrire en dur ici
 * revenait à les traiter comme l'intérieur du pays — Wave ou Orange Money
 * imposés à une cliente que le livreur voit, et commande bloquée à
 * l'expédition tant qu'elle n'a pas payé d'avance.
 *
 * La liste vient donc des zones de livraison du groupe Abidjan : c'est
 * l'administration qui décide de ce qu'on livre, pas le code.
 */
class AbidjanZone
{
    /**
     * Socle : les treize communes du district autonome.
     *
     * Conservé pour que la règle de paiement reste juste même sans base
     * peuplée — première installation, tests, base en cours de migration.
     * Les zones s'y ajoutent, elles ne le remplacent pas.
     */
    private const COMMUNES_OFFICIELLES = [
        'abobo', 'adjame', 'attecoube', 'cocody', 'koumassi',
        'marcory', 'plateau', 'port-bouet', 'treichville', 'yopougon',
        'anyama', 'bingerville', 'songon',
    ];

    /**
     * @param  array|null  $address  shipping_address (city, commune…)
     */
    public static function isAbidjan(?array $address): bool
    {
        if (!$address) return false;

        $city    = DeliveryZone::normalize((string) ($address['city']    ?? ''));
        $commune = DeliveryZone::normalize((string) ($address['commune'] ?? ''));

        if ($city !== '' && str_contains($city, HomeZone::normalized())) return true;

        $communes = self::communes();

        if ($commune !== '' && in_array($commune, $communes, true)) return true;

        // Certains flux mettent la commune dans la ville (ex. « Cocody »)
        if ($city !== '' && in_array($city, $communes, true)) return true;

        return false;
    }

    /**
     * Le district tel qu'on le livre : communes officielles + zones du groupe
     * Abidjan, noms normalisés.
     *
     * Appelée une fois par commande affichée (OrderResource) : sans le cache
     * porté par DeliveryZone, une liste de commandes déclencherait une requête
     * par ligne.
     *
     * @return string[]
     */
    public static function communes(): array
    {
        // Le socle officiel ne vaut que pour Abidjan : pour une autre ville,
        // seules les zones saisies par la boutique décrivent le périmètre.
        return array_values(array_unique(array_merge(
            HomeZone::isAbidjan() ? self::COMMUNES_OFFICIELLES : [],
            DeliveryZone::abidjanZoneNames(),
        )));
    }
}
