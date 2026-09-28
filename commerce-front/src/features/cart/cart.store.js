import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { cartApi } from './cart.api'
import api from '@/api'
import { useToast } from 'vue-toastification'
import i18n from '@/i18n'
import { sellingPrice } from '@/utils/pricing'
import { isOrderable, maxQuantity } from '@/utils/availability'

// ── Clé localStorage pour le panier invité ──────────────────────────────────
const GUEST_KEY = 'shop_guest_cart'

function getLocalItems() {
  try { return JSON.parse(localStorage.getItem(GUEST_KEY) || '[]') } catch { return [] }
}
function saveLocalItems(items) {
  localStorage.setItem(GUEST_KEY, JSON.stringify(items))
}
function isLoggedIn() {
  return !!localStorage.getItem('auth_token')
}

export const useCartStore = defineStore('cart', () => {
  const cart   = ref({ items: [], subtotal: 0, discount: 0, total: 0, item_count: 0, coupon: null })
  const isOpen = ref(false)

  const itemCount = computed(() => cart.value.item_count ?? 0)
  const items     = computed(() => cart.value.items ?? [])
  const subtotal  = computed(() => cart.value.subtotal ?? 0)
  const total     = computed(() => cart.value.total ?? 0)
  const coupon    = computed(() => cart.value.coupon ?? null)

  function open()  { isOpen.value = true }
  function close() { isOpen.value = false }
  function clear() {
    cart.value = { items: [], subtotal: 0, discount: 0, total: 0, item_count: 0, coupon: null }
    isOpen.value = false
  }

  // ── Panier local (invité) ─────────────────────────────────────────────────
  function buildFromLocal() {
    // Le prix vient du produit mémorisé, qui porte `effective_price` ; le
    // montant figé dans localStorage ne sert que de repli. Un panier ouvert
    // avant le début d'une opération affichait sinon l'ancien tarif jusqu'au
    // paiement — le serveur, lui, retarife toujours à la commande.
    const localItems = getLocalItems().map(item => ({
      ...item,
      unit_price: sellingPrice(item.product) || Number(item.unit_price) || 0,
    }))
    const subtotal = localItems.reduce((s, i) => s + i.unit_price * i.quantity, 0)
    cart.value = {
      items: localItems,
      subtotal,
      discount: 0,
      total: subtotal,
      item_count: localItems.reduce((s, i) => s + i.quantity, 0),
      coupon: null,
    }
  }

  // ── Fetch ─────────────────────────────────────────────────────────────────
  async function fetch() {
    if (!isLoggedIn()) {
      buildFromLocal()
      return
    }
    try {
      const { data } = await cartApi.get()
      cart.value = data
    } catch {}
  }

  // ── Ajouter ───────────────────────────────────────────────────────────────
  async function add(productId, quantity = 1, variantId = null, { silent = false, snapshot = null } = {}) {
    const toast = useToast()

    if (!isLoggedIn()) {
      // ── Mode invité : localStorage ────────────────────────────────────────
      const localItems = getLocalItems()
      const existing = localItems.find(
        i => i.product_id === productId && (i.variant_id ?? null) === (variantId ?? null)
      )

      // Même borne que le serveur applique au panier connecté : le panier
      // invité ne passe pas par l'API, et sans ce contrôle la cliente
      // découvrirait la limite au paiement. Toutes les lignes de l'article
      // comptent, déclinaisons comprises — l'allocation porte sur l'article.
      const reference = snapshot ?? existing?.product
      if (reference) {
        if (!isOrderable(reference)) {
          toast.error(i18n.global.t('cart.unavailable'))
          return
        }
        const max = maxQuantity(reference)
        const dejaAuPanier = localItems
          .filter(i => i.product_id === productId)
          .reduce((total, i) => total + i.quantity, 0)
        if (max !== null && dejaAuPanier + quantity > max) {
          toast.error(i18n.global.t('cart.onlineLimit', { count: max }))
          return
        }
      }

      if (existing) {
        existing.quantity += quantity
      } else {
        // Récupérer les infos produit pour l'affichage dans le drawer
        let product = snapshot
        if (!product) {
          try {
            const { data } = await api.get(`/products/${productId}`)
            product = data
          } catch {}
        }
        // Prix promotionnel, comme partout ailleurs dans la boutique : le
        // panier invité retenait `price` et affichait donc le tarif plein
        // d'un article en promotion.
        const unitPrice = sellingPrice(product)
        localItems.push({
          id:         `local-${productId}-${variantId ?? 0}-${Date.now()}`,
          product_id: productId,
          variant_id: variantId ?? null,
          quantity,
          product:    product ?? null,
          unit_price: unitPrice,
          price:      unitPrice,
        })
      }
      saveLocalItems(localItems)
      buildFromLocal()
      if (!silent) toast.success(i18n.global.t('cart.addedToast'), { timeout: 2000 })
      return
    }

    // ── Mode connecté : API ───────────────────────────────────────────────
    try {
      const { data } = await cartApi.addItem(productId, quantity, variantId)
      cart.value = data
      if (!silent) toast.success(i18n.global.t('cart.addedToast'), { timeout: 2000 })
    } catch (e) {
      const msg = e.response?.data?.message ?? i18n.global.t('cart.addError')
      toast.error(msg)
    }
  }

  // ── Modifier quantité ─────────────────────────────────────────────────────
  async function update(itemId, quantity) {
    if (!isLoggedIn()) {
      const localItems = getLocalItems()
      const idx = localItems.findIndex(i => i.id === itemId)
      if (idx !== -1) {
        if (quantity === 0) localItems.splice(idx, 1)
        else localItems[idx].quantity = quantity
      }
      saveLocalItems(localItems)
      buildFromLocal()
      return
    }
    const { data } = await cartApi.updateItem(itemId, quantity)
    cart.value = data
  }

  // ── Supprimer un article ──────────────────────────────────────────────────
  async function remove(itemId) {
    if (!isLoggedIn()) {
      const localItems = getLocalItems().filter(i => i.id !== itemId)
      saveLocalItems(localItems)
      buildFromLocal()
      return
    }
    const { data } = await cartApi.removeItem(itemId)
    cart.value = data
  }

  // ── Coupon ────────────────────────────────────────────────────────────────
  async function applyCoupon(code) {
    const { data } = await cartApi.applyCoupon(code)
    cart.value = data
  }
  async function removeCoupon() {
    const { data } = await cartApi.removeCoupon()
    cart.value = data
  }

  // ── Fusion panier invité → panier utilisateur ─────────────────────────────
  async function mergeLocalCart() {
    const localItems = getLocalItems()
    localStorage.removeItem(GUEST_KEY)  // nettoyer avant même l'appel API
    if (!localItems.length) return

    try {
      const { data } = await cartApi.merge(
        localItems.map(i => ({
          product_id: i.product_id,
          variant_id: i.variant_id ?? null,
          quantity:   i.quantity,
        }))
      )
      cart.value = data
    } catch {
      // En cas d'erreur API, on recharge juste le panier serveur
      await fetch()
    }
  }

  return {
    cart, isOpen,
    itemCount, items, subtotal, total, coupon,
    open, close, clear,
    fetch, add, update, remove,
    applyCoupon, removeCoupon,
    mergeLocalCart,
  }
})
