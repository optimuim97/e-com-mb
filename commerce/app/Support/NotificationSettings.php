<?php

namespace App\Support;

use Modules\Admin\Models\Setting;

/**
 * Interrupteurs des canaux de notification, lus depuis les réglages boutique.
 *
 * Les réglages sont stockés en chaînes brutes : l'écran d'administration écrit
 * « true » ou « false », un import ou une commande peuvent écrire « 1 », « 0 »
 * ou un booléen. Centraliser la lecture évite que deux canaux interprètent
 * différemment la même case cochée.
 *
 * Un réglage jamais renseigné vaut ACTIF. Le contraire éteindrait les
 * notifications d'une boutique qui n'a simplement jamais ouvert l'écran, et
 * personne ne comprendrait pourquoi plus rien ne part.
 */
final class NotificationSettings
{
    /** Le canal correspondant à ce réglage est-il autorisé à émettre ? */
    public static function enabled(string $key): bool
    {
        $valeur = Setting::get($key);

        if ($valeur === null || $valeur === '') {
            return true;
        }

        if (is_bool($valeur)) {
            return $valeur;
        }

        return ! in_array(strtolower(trim((string) $valeur)), ['0', 'false', 'off', 'non'], true);
    }
}
