<?php

namespace App\Support;

use Modules\Admin\Models\Setting;

/**
 * Identité de la boutique, pour tout ce que le serveur écrit en son nom :
 * e-mails, messages WhatsApp, factures, SEO, pages légales.
 *
 * Marque blanche : le nom vient des réglages (`shop_name`), saisis par le
 * commerçant dans l'administration. Rien ne doit l'écrire en dur, sinon une
 * boutique reçoit des e-mails signés d'une autre.
 */
final class Shop
{
    /**
     * Domaine des adresses e-mail générées pour les clients sans e-mail
     * (commande rapide, caisse). Jamais routable : ces adresses ne reçoivent
     * aucun courrier, voir CustomerEmail.
     */
    public const CUSTOMER_EMAIL_DOMAIN = 'clients.local';

    /** Domaine des comptes internes (livreurs, agents sans e-mail). */
    public const INTERNAL_EMAIL_DOMAIN = 'boutique.local';

    /** Nom commercial : réglage `shop_name`, sinon APP_NAME. */
    public static function name(): string
    {
        $name = trim((string) self::setting('shop_name'));

        return $name !== '' ? $name : (string) config('app.name', 'Ma Boutique');
    }

    /** E-mail de contact affiché aux clients. */
    public static function email(): string
    {
        $email = trim((string) self::setting('shop_email'));

        return $email !== '' ? $email : (string) config('mail.from.address', '');
    }

    /** Téléphone de la boutique, tel que saisi (« +225 07 00 00 00 00 »). */
    public static function phone(): string
    {
        return trim((string) self::setting('shop_phone'));
    }

    /** Ville et pays de la boutique, pour les pieds d'e-mails (« Abidjan, Côte d'Ivoire »). */
    public static function location(): string
    {
        return collect([self::setting('shop_city'), self::setting('shop_country')])
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->implode(', ');
    }

    /**
     * Couleur principale de la marque (réglage `brand_primary_color`), pour
     * les e-mails : le thème CSS du site ne les atteint pas.
     */
    public static function primaryColor(): string
    {
        $hex = ltrim(trim((string) self::setting('brand_primary_color', '')), '#');

        return preg_match('/^[0-9a-fA-F]{6}$/', $hex) ? '#' . strtolower($hex) : '#e8336d';
    }

    /** Variante foncée de la couleur principale (titres, liens au survol). */
    public static function primaryColorDark(): string
    {
        [$r, $g, $b] = sscanf(self::primaryColor(), '#%02x%02x%02x');

        // Même palier que le --rose-700 généré côté front (mélange à 32 % de noir).
        return sprintf('#%02x%02x%02x', (int) round($r * 0.68), (int) round($g * 0.68), (int) round($b * 0.68));
    }

    /** URL publique de la vitrine. */
    public static function url(): string
    {
        return rtrim((string) config('app.frontend_url', config('app.url')), '/');
    }

    /**
     * Lecture tolérante d'un réglage : e-mails, coquille SEO et commandes
     * Artisan peuvent tourner avant la migration (installation, tests sans
     * base) ; le repli sur la valeur par défaut suffit alors.
     */
    public static function setting(string $key, mixed $default = null): mixed
    {
        try {
            return Setting::get($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}
