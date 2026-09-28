import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/api'
import { useAuthStore } from '@/features/auth/auth.store'

export const useWishlistStore = defineStore('wishlist', () => {
  // product_ids en wishlist (source de vérité rapide)
  const productIds = ref(new Set())
  const items      = ref([])   // objets complets (chargés depuis /wishlist)
  const loading    = ref(false)

  const count = computed(() => productIds.value.size)

  function isWishlisted(productId) {
    return productIds.value.has(Number(productId))
  }

  /** Charge la wishlist complète (appelé au login / montage WishlistView) */
  async function fetch() {
    const auth = useAuthStore()
    if (!auth.isLoggedIn) return
    loading.value = true
    try {
      const { data } = await api.get('/wishlist')
      items.value    = data.items ?? []
      productIds.value = new Set((data.product_ids ?? []).map(Number))
    } catch {
      // silencieux
    } finally {
      loading.value = false
    }
  }

  /** Toggle add/remove — ne requiert pas le chargement de la liste complète */
  async function toggle(productId) {
    const auth = useAuthStore()
    if (!auth.isLoggedIn) {
      // Redirige vers login géré au niveau du composant
      return { requiresLogin: true }
    }

    const id = Number(productId)
    // Optimistic update
    const wasIn = productIds.value.has(id)
    if (wasIn) {
      productIds.value.delete(id)
      items.value = items.value.filter(i => i.product_id !== id)
    } else {
      productIds.value.add(id)
    }

    try {
      const { data } = await api.post('/wishlist/toggle', { product_id: id })
      // Sync avec le serveur
      if (data.wishlisted) {
        productIds.value.add(id)
      } else {
        productIds.value.delete(id)
        items.value = items.value.filter(i => i.product_id !== id)
      }
      // Force réactivité sur Set
      productIds.value = new Set(productIds.value)
      return { wishlisted: data.wishlisted }
    } catch {
      // Rollback
      if (wasIn) {
        productIds.value.add(id)
      } else {
        productIds.value.delete(id)
      }
      productIds.value = new Set(productIds.value)
      return { error: true }
    }
  }

  /** Retire un article directement (depuis la page wishlist) */
  async function remove(productId) {
    const id = Number(productId)
    productIds.value.delete(id)
    items.value = items.value.filter(i => i.product_id !== id)
    productIds.value = new Set(productIds.value)
    try {
      await api.delete(`/wishlist/${id}`)
    } catch {
      // item déjà supprimé côté UI, pas grave
    }
  }

  /** Reset au logout */
  function clear() {
    productIds.value = new Set()
    items.value      = []
  }

  return { productIds, items, count, loading, isWishlisted, fetch, toggle, remove, clear }
})
