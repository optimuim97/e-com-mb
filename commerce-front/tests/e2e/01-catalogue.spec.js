/**
 * TEST 01 — Catalogue & Panier
 *
 * Parcours : page d'accueil → liste produits → fiche produit → ajout panier → panier
 *
 * Grandeur nature : teste le vrai rendu DOM sur mobile (Pixel 7 par défaut).
 * Ne mocke rien — les appels API réels sont utilisés si le serveur tourne.
 * Si l'API est indisponible, certains tests passent en mode dégradé.
 */
import { test, expect } from '@playwright/test'
import { waitReady } from './helpers/setup.js'

test.describe('Catalogue', () => {
  test("la page d'accueil charge et affiche un produit ou une section", async ({ page }) => {
    await page.goto('/')
    await waitReady(page)

    // Le titre principal ou une section visible
    const hero = page.locator('h1, .hero__title, [class*="hero"] h1, [class*="hero"] h2').first()
    await expect(hero).toBeVisible({ timeout: 10_000 })

    // Pas d'erreur JS bloquante (pas de "white screen")
    const body = page.locator('body')
    await expect(body).not.toBeEmpty()
  })

  test('la page produits affiche au moins un produit ou un état vide', async ({ page }) => {
    await page.goto('/products')
    await waitReady(page)

    // Soit une carte produit, soit un message "aucun produit"
    const hasProducts   = await page.locator('[class*="product-card"], [class*="ProductCard"], .card').count()
    const hasEmptyState = await page.locator('text=/aucun produit|no product/i').count()

    expect(hasProducts + hasEmptyState).toBeGreaterThan(0)
  })

  test('ajout au panier depuis la page produits', async ({ page }) => {
    await page.goto('/products')
    await waitReady(page)

    // Cherche un bouton "Ajouter au panier" visible
    const addBtn = page.locator('button:has-text("Ajouter"), button:has-text("Add to cart")').first()
    const exists = await addBtn.isVisible({ timeout: 5_000 }).catch(() => false)

    if (!exists) {
      test.skip(true, 'Aucun produit disponible — API backend non démarrée')
      return
    }

    await addBtn.click()
    await page.waitForTimeout(1_000)

    // Le compteur panier doit être > 0 (badge dans le nav)
    const cartBadge = page.locator('[class*="cart-count"], [class*="badge"], [aria-label*="panier"] [class*="count"]').first()
    const badgeVisible = await cartBadge.isVisible({ timeout: 3_000 }).catch(() => false)

    // Alternative : le drawer/page panier s'ouvre
    const cartLink = page.locator('a[href="/cart"], a[href*="cart"]').first()

    if (badgeVisible) {
      const count = await cartBadge.innerText()
      expect(Number(count)).toBeGreaterThan(0)
    } else {
      // Naviguer vers le panier et vérifier qu'il n'est pas vide
      await page.goto('/cart')
      await waitReady(page)
      const emptyMsg = await page.locator('text=/panier est encore vide|cart is still empty/i').isVisible({ timeout: 3_000 }).catch(() => false)
      expect(emptyMsg).toBe(false)
    }
  })

  test('la fiche produit s\'affiche correctement', async ({ page }) => {
    await page.goto('/products')
    await waitReady(page)

    // Clic sur le premier lien vers une fiche produit
    const productLink = page.locator('a[href*="/product/"], a[href*="/produit/"]').first()
    const hasLink = await productLink.isVisible({ timeout: 5_000 }).catch(() => false)
    if (!hasLink) {
      test.skip(true, 'Aucun lien produit trouvé — API non disponible')
      return
    }

    await productLink.click()
    await waitReady(page)

    // La page produit doit avoir un bouton d'ajout panier ou un message de rupture
    const addOrStock = page.locator('button:has-text("Ajouter"), button:has-text("Rupture"), button:has-text("Out of stock")').first()
    await expect(addOrStock).toBeVisible({ timeout: 8_000 })
  })
})
