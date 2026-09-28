<template>
  <div class="admin-page">
    <header class="page-header">
      <div>
        <span class="eyebrow">Promotions</span>
        <h1 class="page-header__title">Opérations commerciales</h1>
      </div>
      <button @click="openModal()" class="btn btn-primary">Nouvelle promotion</button>
    </header>

    <p class="page-lead">
      Une promotion s'applique d'office, sans code à saisir : la cliente voit le
      prix barré dès le catalogue. Pour un code à saisir au panier, utilisez les
      <RouterLink :to="{ name: 'admin.coupons' }">coupons</RouterLink>.
    </p>

    <!-- Filtres -->
    <div class="card filters">
      <input v-model="filters.search" type="search" class="input"
             placeholder="Rechercher par nom…" @input="debouncedFetch" />
      <select v-model="filters.type" class="input" @change="fetchPromotions">
        <option value="">Tous les types</option>
        <option v-for="t in TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
      </select>
      <label class="filters__toggle">
        <input type="checkbox" v-model="filters.activeOnly" @change="fetchPromotions" />
        En cours seulement
      </label>
    </div>

    <div v-if="loading" class="loader-wrap"><div class="loader"></div></div>

    <div v-else-if="!promotions.length" class="card empty-state">
      <div class="empty-state__icon">🌸</div>
      <p>Aucune promotion. Créez-en une pour lancer une opération.</p>
    </div>

    <div v-else class="card">
      <div class="table-scroll">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Nom</th>
              <th>Type</th>
              <th>Portée</th>
              <th>Remise</th>
              <th>Période</th>
              <th>État</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="promo in promotions" :key="promo.id">
              <td>
                <strong>{{ promo.name }}</strong>
                <span v-if="promo.priority > 0" class="prio" title="Priorité : passe devant les autres">
                  priorité {{ promo.priority }}
                </span>
              </td>
              <td><span class="badge-type">{{ typeLabel(promo.type) }}</span></td>
              <td>
                <template v-if="promo.scope === 'global'">Tout le catalogue</template>
                <template v-else>{{ promo.products_count }} article(s)</template>
              </td>
              <td>
                {{ discountLabel(promo) }}
                <small v-if="promo.max_discount_amount" class="cap">
                  plafond {{ fmt(promo.max_discount_amount) }}
                </small>
              </td>
              <td class="period">{{ periodLabel(promo) }}</td>
              <td><span :class="stateClass(promo)">{{ stateLabel(promo) }}</span></td>
              <td class="admin-table__action">
                <button class="btn btn-xs btn-outline" @click="openModal(promo)">Modifier</button>
                <button class="btn btn-xs btn-danger" @click="remove(promo)">Supprimer</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <PromotionFormModal
      v-if="modalOpen"
      :promotion="editing"
      @close="modalOpen = false"
      @saved="onSaved"
    />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { usePersistedFilters } from '@/admin/utils/persistedFilters'
import { RouterLink } from 'vue-router'
import api from '@/api'
import PromotionFormModal from './PromotionFormModal.vue'
import { TYPES, typeLabel } from './promotion.constants'

const promotions = ref([])
const loading    = ref(true)
const modalOpen  = ref(false)
const editing    = ref(null)

// Conservés d'une visite à l'autre, comme sur les autres listes.
const filters = usePersistedFilters('promotions', { search: '', type: '', activeOnly: false })

async function fetchPromotions() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/promotions', {
      params: {
        search:      filters.search || undefined,
        type:        filters.type || undefined,
        active_only: filters.activeOnly || undefined,
      },
    })
    promotions.value = data.data ?? data
  } finally {
    loading.value = false
  }
}

// La recherche se déclenche à la frappe : sans délai, chaque lettre
// enverrait une requête.
let searchTimer
function debouncedFetch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(fetchPromotions, 350)
}

function openModal(promo = null) {
  editing.value = promo
  modalOpen.value = true
}

function onSaved() {
  modalOpen.value = false
  fetchPromotions()
}

async function remove(promo) {
  if (!confirm(`Supprimer la promotion « ${promo.name} » ?`)) return
  await api.delete(`/admin/promotions/${promo.id}`)
  fetchPromotions()
}

function fmt(value) {
  return new Intl.NumberFormat('fr-FR').format(Math.round(Number(value) || 0)) + ' F'
}

function discountLabel(promo) {
  return promo.discount_type === 'percent'
    ? `−${Number(promo.value)} %`
    : `−${fmt(promo.value)}`
}

function periodLabel(promo) {
  const d = (v) => v ? new Date(v).toLocaleDateString('fr-FR') : null
  const from = d(promo.starts_at)
  const to   = d(promo.ends_at)
  if (from && to) return `${from} → ${to}`
  if (from)       return `dès le ${from}`
  if (to)         return `jusqu'au ${to}`
  return 'sans limite'
}

/**
 * Trois états distincts : une promotion peut être active mais programmée
 * pour plus tard, ce qu'un simple « active/inactive » masquerait.
 */
function isRunning(promo) {
  if (!promo.is_active) return false
  const now = Date.now()
  if (promo.starts_at && new Date(promo.starts_at).getTime() > now) return false
  if (promo.ends_at   && new Date(promo.ends_at).getTime()   < now) return false
  return true
}

function stateLabel(promo) {
  if (!promo.is_active) return 'Désactivée'
  if (isRunning(promo)) return 'En cours'
  if (promo.starts_at && new Date(promo.starts_at) > new Date()) return 'Programmée'
  return 'Terminée'
}

function stateClass(promo) {
  if (isRunning(promo)) return 'badge badge-success'
  if (!promo.is_active) return 'badge badge-muted'
  return 'badge badge-warning'
}

onMounted(fetchPromotions)
</script>

<style scoped>
.page-lead { color: var(--gray-500); font-size: 0.875rem; margin-bottom: var(--space-4); }
.filters {
  display: flex; gap: var(--space-3); align-items: center;
  flex-wrap: wrap; padding: var(--space-3); margin-bottom: var(--space-4);
}
.filters .input { max-width: 260px; }
.filters__toggle { display: flex; align-items: center; gap: 6px; font-size: 0.875rem; }
.badge-type {
  font-size: 0.75rem; padding: 2px 8px; border-radius: 999px;
  background: var(--gray-100); color: var(--gray-700);
}
.prio {
  display: inline-block; margin-left: 8px; font-size: 0.6875rem;
  color: var(--rose-600); background: var(--rose-50);
  padding: 1px 6px; border-radius: 999px;
}
.cap { display: block; font-size: 0.6875rem; color: var(--gray-400); }
.period { white-space: nowrap; font-size: 0.8125rem; }
.badge-muted { background: var(--gray-100); color: var(--gray-500); }
</style>
