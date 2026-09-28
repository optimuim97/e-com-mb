<?php

namespace App\Support;

/**
 * Horodatage des fichiers d'extraction.
 *
 * Les noms ne portaient que la date : deux extractions du même jour arrivaient
 * dans le dossier de téléchargement sous le même nom, le navigateur suffixant
 * « (1) » sans dire laquelle est la plus récente. L'heure les départage, et
 * elle dit aussi jusqu'où va l'extraction quand elle est faite en cours de
 * journée.
 *
 * Le format reste triable dans l'ordre alphabétique et ne contient aucun
 * caractère interdit dans un nom de fichier Windows — d'où « h » et non « : ».
 */
final class ExportStamp
{
    public static function now(): string
    {
        return now()->format('Y-m-d_H\hi');
    }
}
