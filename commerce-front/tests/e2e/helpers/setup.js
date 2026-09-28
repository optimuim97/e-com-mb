/**
 * Helpers partagés pour les tests E2E de la boutique.
 * Chaque helper prend `page` (Playwright Page) en premier argument.
 */

/** Attend que le spinner disparaisse (loading global) */
export async function waitReady(page) {
  await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {})
}

/**
 * Ajoute le premier produit disponible au panier depuis la page /products.
 * Retourne le nom du produit ajouté.
 */
export async function addFirstProductToCart(page) {
  await page.goto('/products')
  await waitReady(page)

  // Clique sur le premier bouton "Ajouter au panier"
  const btn = page.locator('[data-testid="add-to-cart"], .btn-add-cart, button:has-text("Ajouter")').first()
  await btn.waitFor({ timeout: 10_000 })
  const productName = await page.locator('.product-card__name, .card__name, h3').first().innerText().catch(() => 'Produit')
  await btn.click()
  await page.waitForTimeout(800)
  return productName.trim()
}

/**
 * Remplit un champ de téléphone (PhoneInput peut être un input standard ou composant custom).
 */
export async function fillPhone(page, selector, number) {
  const input = page.locator(selector).first()
  await input.fill(number)
}

/**
 * Intercepte un appel API et retourne la réponse mockée.
 */
export async function mockApi(page, urlPattern, responseBody, status = 200) {
  await page.route(urlPattern, route => {
    route.fulfill({
      status,
      contentType: 'application/json',
      body: JSON.stringify(responseBody),
    })
  })
}
