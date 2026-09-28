import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/api'

const TTL_MS = 5 * 60 * 1000 // 5 minutes

export const useHomeStore = defineStore('home', () => {
  // ── State ──────────────────────────────────────────────────────────────
  const categories  = ref([])
  const featured    = ref([])
  const homeStats   = ref({})

  const loadingCats     = ref(false)
  const loadingProducts = ref(false)
  const loadingStats    = ref(false)

  // Timestamps du dernier fetch
  const _fetchedAt = ref({ categories: 0, featured: 0, homeStats: 0 })

  // ── TTL check ──────────────────────────────────────────────────────────
  const isStale = (key) => Date.now() - _fetchedAt.value[key] > TTL_MS

  // ── Fetchers idempotents ───────────────────────────────────────────────
  async function fetchCategories(force = false) {
    if (!force && !isStale('categories') && categories.value.length) return
    loadingCats.value = true
    try {
      const res = await api.get('/categories')
      categories.value = res.data.data ?? res.data
      _fetchedAt.value.categories = Date.now()
    } catch {
      // garde les données existantes si erreur réseau
    } finally {
      loadingCats.value = false
    }
  }

  async function fetchFeatured(force = false) {
    if (!force && !isStale('featured') && featured.value.length) return
    loadingProducts.value = true
    try {
      const res = await api.get('/products', { params: { featured: true, per_page: 4 } })
      featured.value = res.data.data ?? []
      _fetchedAt.value.featured = Date.now()
    } catch {
      // garde les données existantes si erreur réseau
    } finally {
      loadingProducts.value = false
    }
  }

  async function fetchStats(force = false) {
    if (!force && !isStale('homeStats') && Object.keys(homeStats.value).length) return
    loadingStats.value = true
    try {
      const res = await api.get('/home/stats')
      homeStats.value = res.data
      _fetchedAt.value.homeStats = Date.now()
    } catch {
      // garde les données existantes si erreur réseau
    } finally {
      loadingStats.value = false
    }
  }

  // ── Chargement parallèle ───────────────────────────────────────────────
  async function fetchAll(force = false) {
    await Promise.allSettled([
      fetchCategories(force),
      fetchFeatured(force),
      fetchStats(force),
    ])
  }

  return {
    categories, featured, homeStats,
    loadingCats, loadingProducts, loadingStats,
    fetchAll, fetchCategories, fetchFeatured, fetchStats,
  }
})
