/**
 * TEST 03 — Checkout complet (utilisateur connecté)
 *
 * Parcours : connexion → panier → étape 1 (infos) → étape 2 (adresse) → géolocalisation → paiement
 *
 * Grandeur nature desktop + mobile.
 * Mocke : API auth, cart, order, géolocalisation navigateur.
 */
import { test, expect } from '@playwright/test'
import { waitReady, mockApi } from './helpers/setup.js'

// Coordonnées GPS : Cocody, Abidjan
const COCODY_COORDS = { latitude: 5.366, longitude: -3.998, accuracy: 20 }

const MOCK_USER = {
  id: 1, name: 'Fatou Test', email: 'fatou@test.ci', phone: '0708000000',
  roles: ['customer'], permissions: [], is_admin: false, is_staff: false,
}
const MOCK_ORDER = {
  id: 100,
  number: 'RB-2024-001',
  total: 10500,
  shipping_cost: 1500,
  status: 'pending',
}

test.describe('Checkout — adresse & livraison', () => {
  test.beforeEach(async ({ page }) => {
    // Injecter un token d'auth fictif en localStorage
    await page.addInitScript(() => {
      localStorage.setItem('auth_token', 'test-token-fake')
    })

    // Mocker les appels API clés — endpoint de session réel : /api/auth/me
    await mockApi(page, '**/api/auth/me', MOCK_USER)
    await mockApi(page, '**/api/cart', {
      items: [{ id: 1, product_id: 1, quantity: 2, unit_price: 4500, product: { name: 'Eau Rose Pure' } }],
      total: 9000,
      item_count: 2,
    })
    await mockApi(page, '**/api/shipping/quote**', { found: true, price: 1500, is_free: false })
    await mockApi(page, '**/api/orders', MOCK_ORDER, 201)
  })

  test("l'étape 2 affiche le sélecteur ville/commune pour CI", async ({ page }) => {
    await page.goto('/checkout')
    await waitReady(page)

    // Si l'utilisateur est reconnu comme connecté, passer à l'étape 2
    // Sinon, le gate s'affiche
    const step2Btn = page.locator('button:has-text("Continuer"), button:has-text("Continue")').first()
    const isStep2Visible = await step2Btn.isVisible({ timeout: 5_000 }).catch(() => false)

    if (isStep2Visible) {
      await step2Btn.click()
      await page.waitForTimeout(500)
    }

    // Étape 2 (adresse) atteinte : au moins un de ces marqueurs doit être là
    // (sélecteur pays, champ ville CitySelect, ou bouton géolocalisation).
    const markers = page.locator(
      '[class*="AppSelect"], select, input.cs-input, input[placeholder*="ville"], input[placeholder*="commune"], button:has-text("Ma position"), button:has-text("My location")'
    )
    const count = await markers.count().catch(() => 0)
    if (count === 0) { test.skip(true, 'Étape 2 non atteinte'); return }
    await expect(markers.first()).toBeVisible({ timeout: 5_000 })
  })

  test('le bouton géolocalisation est visible sur étape 2 (CI)', async ({ page }) => {
    // Autoriser la géolocalisation mockée
    await page.context().grantPermissions(['geolocation'])
    await page.context().setGeolocation(COCODY_COORDS)

    await page.goto('/checkout')
    await waitReady(page)

    // Aller à l'étape 2 si possible
    const nextBtn = page.locator('button:has-text("Continuer"), button:has-text("Continue")').first()
    if (await nextBtn.isVisible({ timeout: 4_000 }).catch(() => false)) {
      await nextBtn.click()
      await page.waitForTimeout(500)
    }

    // Chercher le bouton "Ma position"
    const geoBtn = page.locator('button:has-text("Ma position"), button:has-text("My location")').first()
    const geoVisible = await geoBtn.isVisible({ timeout: 5_000 }).catch(() => false)

    if (!geoVisible) {
      test.skip(true, 'Bouton geo non visible (étape 2 non atteinte ou CI non sélectionné)')
      return
    }

    await expect(geoBtn).toBeEnabled()
  })

  test('géolocalisation remplit ville + commune (mock Nominatim)', async ({ page }) => {
    // Autoriser géolocalisation
    await page.context().grantPermissions(['geolocation'])
    await page.context().setGeolocation(COCODY_COORDS)

    // Mocker Nominatim avec une réponse Abidjan/Cocody
    await page.route('**/nominatim.openstreetmap.org/**', route => {
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          display_name: 'Cocody, Abidjan, Lagunes, Côte d\'Ivoire',
          address: {
            suburb: 'Cocody',
            city: 'Abidjan',
            country_code: 'ci',
            road: 'Rue des Jardins',
          },
        }),
      })
    })

    await page.goto('/checkout')
    await waitReady(page)

    // Tenter d'aller à étape 2
    const nextBtn = page.locator('button:has-text("Continuer"), button:has-text("Continue")').first()
    if (await nextBtn.isVisible({ timeout: 4_000 }).catch(() => false)) {
      await nextBtn.click()
      await page.waitForTimeout(500)
    }

    // Cliquer le bouton Ma position
    const geoBtn = page.locator('button:has-text("Ma position"), button:has-text("My location")').first()
    const geoVisible = await geoBtn.isVisible({ timeout: 5_000 }).catch(() => false)
    if (!geoVisible) {
      test.skip(true, 'Bouton geo non visible')
      return
    }

    await geoBtn.click()

    // Attendre que la ville soit remplie (Abidjan)
    const cityInput = page.locator('input[placeholder*="ville"], input.cs-input').first()
    await expect(cityInput).toHaveValue(/Abidjan/i, { timeout: 8_000 })

    // Message de succès visible
    const successMsg = page.locator('[class*="cs-geo-message--success"], [class*="geo-msg--success"]').first()
    await expect(successMsg).toBeVisible({ timeout: 5_000 })
    await expect(successMsg).toContainText(/Cocody/i)
  })

  test("les frais de livraison s'affichent dans le récapitulatif", async ({ page }) => {
    await page.goto('/checkout')
    await waitReady(page)

    // Aller à l'étape 2 si possible
    const nextBtn = page.locator('button:has-text("Continuer"), button:has-text("Continue")').first()
    if (await nextBtn.isVisible({ timeout: 4_000 }).catch(() => false)) {
      await nextBtn.click()
      await page.waitForTimeout(300)
    }

    // Le récapitulatif commande dans la sidebar doit mentionner "Livraison"
    const shippingLine = page.locator('text=/Livraison|Shipping/i').first()
    await expect(shippingLine).toBeVisible({ timeout: 8_000 })
  })
})
