/**
 * Lecture des compteurs de pagination d'une réponse Laravel.
 *
 * Deux formes coexistent selon l'endpoint : un paginateur nu porte
 * `current_page` à la racine, tandis qu'une `ResourceCollection` les range sous
 * `meta`. Les écrans d'administration lisaient la racine sur des endpoints qui
 * renvoient une collection de ressources : les compteurs valaient `undefined`,
 * et AdminPagination — qui se masque quand le total vaut zéro — disparaissait
 * purement et simplement. Une seule liste page 1 était donc accessible.
 */
export function readPagination(data) {
  const source = data?.meta ?? data ?? {}

  return {
    current_page: Number(source.current_page) || 1,
    last_page:    Number(source.last_page)    || 1,
    total:        Number(source.total)        || 0,
    per_page:     Number(source.per_page)     || undefined,
  }
}
