/**
 * Single source of truth for the price a customer sees.
 *
 * The API already resolves promotions, flash sales and their caps into
 * `effective_price`; the shop must display that, not the base `price`.
 * Each component used to reinvent the rule, so a promotion could be charged
 * at checkout while the catalogue still showed the full price — the worst
 * possible outcome: the discount is real but invisible.
 */

/** Price actually charged. Falls back to `price` on partial payloads. */
export function sellingPrice(product) {
  if (!product) return 0
  const effective = Number(product.effective_price)
  return Number.isFinite(effective) && effective > 0
    ? effective
    : Number(product.price) || 0
}

/**
 * Price to strike through, or null when there is nothing to compare.
 *
 * Two sources may justify it: an automatic discount (`effective_price` below
 * `price`) or a manual "was" price (`compare_price`). We keep the higher of
 * the two so the saving shown is never overstated.
 */
export function referencePrice(product) {
  if (!product) return null

  const selling = sellingPrice(product)
  const base    = Number(product.price) || 0
  const compare = Number(product.compare_price) || 0

  const reference = Math.max(base, compare)

  return reference > selling ? reference : null
}

/** Whether the product is currently sold below its reference price. */
export function isDiscounted(product) {
  return referencePrice(product) !== null
}

/** Rounded saving in percent, or null when there is no discount. */
export function discountPercent(product) {
  const reference = referencePrice(product)
  if (!reference) return null

  const percent = Math.round((1 - sellingPrice(product) / reference) * 100)
  return percent > 0 ? percent : null
}

/**
 * Badge text for the discount.
 *
 * A promotion may carry its own label ("DÉSTOCKAGE"); otherwise we fall back
 * to the computed percentage.
 */
export function discountBadge(product) {
  if (product?.promotion?.banner_label) return product.promotion.banner_label

  const percent = discountPercent(product)
  return percent ? `−${percent} %` : null
}
