/**
 * Disponibilité d'un article en boutique : une seule règle pour tous les écrans.
 *
 * Six endroits décidaient chacun « épuisé » sur `stock === 0`. C'était faux deux
 * fois : un article non suivi porte un stock à zéro et se vend pourtant, et un
 * article dont le quota de la semaine est atteint n'est pas épuisé — la
 * marchandise est en réserve, elle revient lundi. Le serveur tranche désormais
 * (`availability`), et les écrans lisent cette décision ici.
 */

export const AVAILABLE     = 'available'
export const QUOTA_REACHED = 'quota_reached'
export const OUT_OF_STOCK  = 'out_of_stock'

/**
 * État de l'article.
 *
 * Repli sur le stock quand le champ manque — instantané de panier invité
 * enregistré avant cette version, réponse d'un endpoint qui ne le porte pas
 * encore. Mieux vaut l'ancienne règle qu'un article bloqué par défaut.
 */
export function availabilityOf(product) {
  const etat = product?.availability ?? product?.product_availability
  if (etat) return etat

  const stock = product?.stock ?? product?.product_stock
  return stock === 0 ? OUT_OF_STOCK : AVAILABLE
}

export function isOrderable(product) {
  return availabilityOf(product) === AVAILABLE
}

/**
 * Clé de traduction du badge d'indisponibilité, ou null si l'article se vend.
 *
 * Le lundi, « de retour lundi » se lirait « aujourd'hui » alors que le quota du
 * jour vient d'être épuisé : on dit « lundi prochain ». La semaine suit celle
 * du serveur, qui tourne à l'heure d'Abidjan (GMT, sans heure d'été).
 *
 * @param {object} product
 * @param {string} scope  espace de clés i18n : `product` ou `common`
 */
export function unavailableLabelKey(product, scope = 'product') {
  switch (availabilityOf(product)) {
    case QUOTA_REACHED:
      return `${scope}.${new Date().getUTCDay() === 1 ? 'backNextMonday' : 'backMonday'}`
    case OUT_OF_STOCK:
      return `${scope}.soldOut`
    default:
      return null
  }
}

/**
 * Quantité maximale commandable, ou null sans limite.
 *
 * Borne le sélecteur de quantité : laisser choisir 10 quand 3 sont vendables,
 * c'est reporter le refus au paiement.
 */
export function maxQuantity(product) {
  const stock = product?.stock ?? product?.product_stock
  return typeof stock === 'number' ? stock : null
}

/**
 * Nombre à afficher dans « Plus que N », ou null s'il ne faut rien dire.
 *
 * Seulement quand la limite est réelle et basse : sans limite, il n'y a rien
 * de rare à annoncer, et « plus que 40 » n'incite personne.
 */
export function scarcityCount(product, threshold = 5) {
  if (!isOrderable(product)) return null
  const max = maxQuantity(product)
  return max !== null && max > 0 && max <= threshold ? max : null
}
