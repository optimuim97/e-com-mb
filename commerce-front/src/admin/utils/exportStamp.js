/**
 * Horodatage des noms de fichiers d'extraction : `2026-09-09_14h32`.
 *
 * La date seule faisait arriver deux extractions du même jour sous le même
 * nom, le navigateur suffixant « (1) » sans dire laquelle est la plus récente.
 *
 * Heure locale, et non l'UTC de `toISOString` : c'est l'heure de la boutique
 * que la gérante reconnaîtra. Le « h » remplace le « : », interdit dans un nom
 * de fichier Windows. Même format que `App\Support\ExportStamp` côté serveur,
 * pour que les fichiers se rangent ensemble quelle que soit leur provenance.
 */
export function exportStamp(date = new Date()) {
  const pad = (n) => String(n).padStart(2, '0')

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
    + `_${pad(date.getHours())}h${pad(date.getMinutes())}`
}
