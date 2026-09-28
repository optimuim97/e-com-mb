<template>
  <div class="admin-page">

    <!-- Header -->
    <header class="page-header">
      <div>
        <span class="eyebrow">Logistique</span>
        <h1 class="page-header__title">Livraisons</h1>
      </div>
      <button class="btn btn-primary btn-sm" @click="showCreate = true">
        + Nouvelle livraison
      </button>
    </header>

    <!-- Stats rapides -->
    <div class="delivery-stats">
      <div v-for="s in statCards" :key="s.label" class="delivery-stat" :class="`delivery-stat--${s.color}`">
        <span class="delivery-stat__value">{{ stats[s.key] ?? '—' }}</span>
        <span class="delivery-stat__label">{{ s.label }}</span>
      </div>
    </div>

    <!-- Filtres -->
    <div class="card filters-bar">
      <div class="status-tabs">
        <button
          v-for="tab in statusTabs"
          :key="tab.value"
          @click="setStatus(tab.value)"
          class="status-tab"
          :class="{ 'status-tab--active': filters.status === tab.value }"
        >
          {{ tab.label }}
        </button>
      </div>
      <input
        v-model="filters.search"
        @input="debouncedLoad"
        type="text"
        class="input filters-bar__search"
        placeholder="Rechercher par N° commande…"
      />
      <select v-model="filters.courier_id" @change="load()" class="input filters-bar__select">
        <option value="">Tous les livreurs</option>
        <option v-for="c in couriers" :key="c.id" :value="c.id">{{ c.full_name }}</option>
      </select>
    </div>

    <!-- Tableau -->
    <div class="card">
      <div v-if="loading" class="loading-rows">
        <div v-for="i in 8" :key="i" class="loading-row"></div>
      </div>

      <div v-else-if="!deliveries.length" class="empty-state">
        <p>Aucune livraison trouvée.</p>
      </div>

      <div v-else class="table-scroll">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Commande</th>
            <th>Client</th>
            <th>Livreur</th>
            <th>Statut</th>
            <th>Paiement</th>
            <th>Montant</th>
            <th>Assigné le</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="d in deliveries" :key="d.id">
            <td class="font-mono text-sm">{{ d.order_number }}</td>
            <td>
              <div class="cell-name">{{ d.customer?.name ?? '—' }}</div>
              <div class="cell-sub">{{ d.customer?.phone }}</div>
            </td>
            <td>
              <span v-if="d.courier" class="cell-courier">
                {{ d.courier.full_name }}
              </span>
              <span v-else class="badge badge-warning badge-sm">Non assigné</span>
            </td>
            <td>
              <span :class="statusBadge(d.status)">{{ statusLabel(d.status) }}</span>
            </td>
            <td>
              <span class="badge badge-gray badge-sm">{{ d.payment_flow ?? '—' }}</span>
            </td>
            <td class="text-right font-medium">{{ fmt(d.total_amount) }}</td>
            <td class="text-sm text-gray-400">{{ fmtDate(d.assigned_at) }}</td>
            <td>
              <div class="row-actions">
                <!-- Assigner livreur -->
                <button
                  class="icon-btn icon-btn--primary"
                  title="Assigner un livreur"
                  @click="openAssign(d)"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
                  </svg>
                </button>
                <!-- Changer statut -->
                <button
                  class="icon-btn"
                  title="Changer le statut"
                  @click="openStatus(d)"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M9 12l2 2 4-4M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      </div>

      <AdminPagination
        :current-page="pagination.current_page"
        :last-page="pagination.last_page"
        :total="pagination.total"
        :show-per-page="false"
        item-singular="livraison"
        item-plural="livraisons"
        @update:page="p => { filters.page = p; load(); }"
      />
    </div>

    <!-- ── Modal Assigner livreur ── -->
    <div v-if="assignModal.open" class="modal-backdrop" @click.self="assignModal.open = false">
      <div class="modal-box">
        <h3 class="modal-title">Assigner un livreur</h3>
        <p class="modal-sub">Commande <strong>{{ assignModal.delivery?.order_number }}</strong></p>
        <div class="modal-field">
          <label class="label">Livreur *</label>
          <select v-model="assignModal.courierId" class="input">
            <option value="" disabled>Choisir…</option>
            <option v-for="c in couriers" :key="c.id" :value="c.id">
              {{ c.full_name }} — {{ c.phone }}
            </option>
          </select>
        </div>
        <p v-if="assignModal.error" class="modal-error">{{ assignModal.error }}</p>
        <div class="modal-actions">
          <button class="btn btn-outline btn-sm" @click="assignModal.open = false">Annuler</button>
          <button class="btn btn-primary btn-sm" :disabled="!assignModal.courierId || assignModal.loading" @click="confirmAssign">
            <span v-if="assignModal.loading">…</span>
            <span v-else>Assigner</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ── Modal Changer statut ── -->
    <div v-if="statusModal.open" class="modal-backdrop" @click.self="statusModal.open = false">
      <div class="modal-box">
        <h3 class="modal-title">Mettre à jour le statut</h3>
        <p class="modal-sub">Commande <strong>{{ statusModal.delivery?.order_number }}</strong></p>
        <div class="modal-field">
          <label class="label">Nouveau statut</label>
          <select v-model="statusModal.status" class="input">
            <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </div>
        <div v-if="statusModal.status === 'FAILED'" class="modal-field">
          <label class="label">Raison d'échec</label>
          <input v-model="statusModal.reason" type="text" class="input" placeholder="Ex. Client absent" />
        </div>
        <p v-if="statusModal.error" class="modal-error">{{ statusModal.error }}</p>
        <div class="modal-actions">
          <button class="btn btn-outline btn-sm" @click="statusModal.open = false">Annuler</button>
          <button class="btn btn-primary btn-sm" :disabled="statusModal.loading" @click="confirmStatus">
            <span v-if="statusModal.loading">…</span>
            <span v-else>Mettre à jour</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ── Modal Créer livraison ── -->
    <div v-if="showCreate" class="modal-backdrop" @click.self="showCreate = false">
      <div class="modal-box modal-box--lg">
        <h3 class="modal-title">Nouvelle livraison</h3>
        <div class="modal-grid">
          <div class="modal-field">
            <label class="label">N° de commande *</label>
            <input v-model="createForm.order_number" type="text" class="input" placeholder="ORD-2025-XXXX" />
          </div>
          <div class="modal-field">
            <label class="label">Flux paiement *</label>
            <select v-model="createForm.payment_flow" class="input">
              <option value="COD">COD (paiement à la livraison)</option>
              <option value="PREPAID">Prépayé</option>
              <option value="MIXED">Mixte</option>
            </select>
          </div>
          <div class="modal-field">
            <label class="label">Montant produit (XOF) *</label>
            <input v-model.number="createForm.product_amount" type="number" class="input" />
          </div>
          <div class="modal-field">
            <label class="label">Frais livraison (XOF)</label>
            <input v-model.number="createForm.delivery_fee" type="number" class="input" />
          </div>
          <div class="modal-field modal-field--full">
            <label class="label">Livreur (optionnel)</label>
            <select v-model="createForm.courier_id" class="input">
              <option value="">À assigner plus tard</option>
              <option v-for="c in couriers" :key="c.id" :value="c.id">{{ c.full_name }}</option>
            </select>
          </div>
          <div class="modal-field modal-field--full">
            <label class="label">Notes</label>
            <input v-model="createForm.notes" type="text" class="input" placeholder="Instructions particulières…" />
          </div>
        </div>
        <p v-if="createError" class="modal-error">{{ createError }}</p>
        <div class="modal-actions">
          <button class="btn btn-outline btn-sm" @click="showCreate = false">Annuler</button>
          <button class="btn btn-primary btn-sm" :disabled="createLoading" @click="createDelivery">
            <span v-if="createLoading">Création…</span>
            <span v-else>Créer la livraison</span>
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { usePersistedFilters } from '@/admin/utils/persistedFilters'
import api from '@/api'
import AdminPagination from '@/admin/components/AdminPagination.vue'

/* ── State ── */
const deliveries = ref([])
const couriers   = ref([])
const stats      = ref({})
const loading    = ref(true)
const pagination = ref({ last_page: 1, current_page: 1, total: 0 })

// Conservés d'une visite à l'autre, comme sur les autres listes — sauf la
// page : revenir en page 4 d'une liste qui a bougé depuis n'aide personne.
const filters = usePersistedFilters('deliveries', {
  status:     '',
  courier_id: '',
  search:     '',
  page:       1,
})
filters.page = 1

const showCreate  = ref(false)
const createForm  = ref({ order_number: '', payment_flow: 'COD', product_amount: 0, delivery_fee: 0, courier_id: '', notes: '' })
const createLoading = ref(false)
const createError   = ref('')

const assignModal = reactive({ open: false, delivery: null, courierId: '', loading: false, error: '' })
const statusModal = reactive({ open: false, delivery: null, status: '', reason: '', loading: false, error: '' })

/* ── Config ── */
const statusTabs = [
  { value: '', label: 'Toutes' },
  { value: 'PENDING',     label: 'En attente' },
  { value: 'IN_PROGRESS', label: 'En cours' },
  { value: 'DELIVERED',   label: 'Livrées' },
  { value: 'FAILED',      label: 'Échouées' },
  { value: 'CANCELLED',   label: 'Annulées' },
]

const statusOptions = [
  { value: 'PENDING',     label: 'En attente' },
  { value: 'IN_PROGRESS', label: 'En cours' },
  { value: 'DELIVERED',   label: 'Livré' },
  { value: 'FAILED',      label: 'Échoué' },
  { value: 'CANCELLED',   label: 'Annulé' },
]

const statCards = [
  { key: 'pending',     label: 'En attente',  color: 'warning' },
  { key: 'in_progress', label: 'En cours',    color: 'primary' },
  { key: 'delivered',   label: 'Livrées',     color: 'success' },
  { key: 'failed',      label: 'Échouées',    color: 'danger'  },
  { key: 'unassigned',  label: 'Non assignées', color: 'gray'  },
]

/* ── Load ── */
async function load() {
  loading.value = true
  const params = Object.fromEntries(
    Object.entries(filters).filter(([, v]) => v !== '' && v !== null)
  )
  try {
    const { data } = await api.get('/admin/deliveries', { params })
    deliveries.value = data.data
    pagination.value = data.meta ?? {}
  } finally {
    loading.value = false
  }
}

async function loadCouriers() {
  const { data } = await api.get('/admin/couriers')
  couriers.value = data.data
}

async function loadStats() {
  const { data } = await api.get('/admin/deliveries/stats')
  stats.value = data
}

let searchTimer = null
function debouncedLoad() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { filters.page = 1; load() }, 350)
}

function setStatus(val) {
  filters.status = val
  filters.page   = 1
  load()
}

/* ── Assign modal ── */
function openAssign(d) {
  assignModal.delivery  = d
  assignModal.courierId = d.courier?.id ?? ''
  assignModal.error     = ''
  assignModal.open      = true
}

async function confirmAssign() {
  assignModal.loading = true
  assignModal.error   = ''
  try {
    await api.patch(`/admin/deliveries/${assignModal.delivery.id}/assign`, {
      courier_id: assignModal.courierId,
    })
    assignModal.open = false
    load()
    loadStats()
  } catch (e) {
    assignModal.error = e.response?.data?.message ?? 'Erreur lors de l\'assignation.'
  } finally {
    assignModal.loading = false
  }
}

/* ── Status modal ── */
function openStatus(d) {
  statusModal.delivery = d
  statusModal.status   = d.status
  statusModal.reason   = ''
  statusModal.error    = ''
  statusModal.open     = true
}

async function confirmStatus() {
  statusModal.loading = true
  statusModal.error   = ''
  try {
    await api.patch(`/admin/deliveries/${statusModal.delivery.id}/status`, {
      status:         statusModal.status,
      failure_reason: statusModal.reason || null,
    })
    statusModal.open = false
    load()
    loadStats()
  } catch (e) {
    statusModal.error = e.response?.data?.message ?? 'Erreur lors de la mise à jour.'
  } finally {
    statusModal.loading = false
  }
}

/* ── Create ── */
async function createDelivery() {
  createLoading.value = true
  createError.value   = ''
  try {
    await api.post('/admin/deliveries', {
      ...createForm.value,
      courier_id: createForm.value.courier_id || null,
    })
    showCreate.value = false
    createForm.value = { order_number: '', payment_flow: 'COD', product_amount: 0, delivery_fee: 0, courier_id: '', notes: '' }
    load()
    loadStats()
  } catch (e) {
    createError.value = e.response?.data?.message ?? 'Erreur lors de la création.'
  } finally {
    createLoading.value = false
  }
}

/* ── Helpers ── */
function statusLabel(s) {
  const map = { PENDING: 'En attente', IN_PROGRESS: 'En cours', DELIVERED: 'Livré', FAILED: 'Échoué', CANCELLED: 'Annulé' }
  return map[s] ?? s
}
function statusBadge(s) {
  const map = {
    PENDING:     'badge badge-warning',
    IN_PROGRESS: 'badge badge-primary',
    DELIVERED:   'badge badge-success',
    FAILED:      'badge badge-danger',
    CANCELLED:   'badge badge-gray',
  }
  return map[s] ?? 'badge badge-gray'
}
function fmt(val) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', maximumFractionDigits: 0 }).format(val ?? 0)
}
function fmtDate(val) {
  if (!val) return '—'
  return new Date(val).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}

onMounted(async () => {
  await Promise.all([load(), loadCouriers(), loadStats()])
})
</script>

<style scoped>
/* ── Stats ── */
.delivery-stats {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: var(--space-3);
  margin-bottom: var(--space-5);
}
.delivery-stat {
  background: #fff;
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  text-align: center;
  border: 1.5px solid var(--cream-200);
}
.delivery-stat__value {
  display: block;
  font-size: 1.75rem;
  font-weight: 700;
  font-family: var(--font-display);
  line-height: 1;
}
.delivery-stat__label {
  display: block;
  font-size: 0.75rem;
  color: var(--gray-400);
  margin-top: 4px;
}
.delivery-stat--warning .delivery-stat__value { color: #d97706; }
.delivery-stat--primary .delivery-stat__value { color: var(--rose-500); }
.delivery-stat--success .delivery-stat__value { color: #16a34a; }
.delivery-stat--danger  .delivery-stat__value { color: #dc2626; }
.delivery-stat--gray    .delivery-stat__value { color: var(--gray-500); }

/* ── Filtres ── */
.filters-bar {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  margin-bottom: var(--space-4);
  flex-wrap: wrap;
}
.filters-bar__search { max-width: 240px; }
.filters-bar__select { max-width: 200px; }

.status-tabs { display: flex; gap: var(--space-1); flex-wrap: wrap; }
.status-tab {
  padding: 6px 14px;
  border-radius: var(--radius-full);
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--gray-500);
  border: 1.5px solid transparent;
  transition: all var(--transition-fast);
}
.status-tab:hover { color: var(--rose-500); }
.status-tab--active {
  background: var(--rose-50);
  border-color: var(--rose-200);
  color: var(--rose-600);
}

/* ── Table ── */
.admin-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.admin-table th {
  text-align: left;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--gray-400);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: var(--space-3) var(--space-4);
  border-bottom: 1px solid var(--cream-200);
}
.admin-table td {
  padding: var(--space-3) var(--space-4);
  border-bottom: 1px solid var(--cream-100);
  vertical-align: middle;
}
.admin-table tr:last-child td { border-bottom: none; }
.admin-table tr:hover td { background: var(--cream-50); }

.font-mono   { font-family: monospace; }
.font-medium { font-weight: 500; }
.text-sm     { font-size: 0.8125rem; }
.text-right  { text-align: right; }
.text-gray-400 { color: var(--gray-400); }

.cell-name { font-weight: 500; color: var(--gray-800); }
.cell-sub  { font-size: 0.75rem; color: var(--gray-400); }
.cell-courier { font-weight: 500; color: var(--gray-700); }

.badge-sm { font-size: 0.7rem; padding: 2px 8px; }

.row-actions { display: flex; gap: var(--space-2); }
.icon-btn {
  width: 30px; height: 30px;
  border-radius: var(--radius-md);
  display: flex; align-items: center; justify-content: center;
  border: 1.5px solid var(--cream-300);
  color: var(--gray-400);
  transition: all var(--transition-fast);
}
.icon-btn:hover { border-color: var(--rose-300); color: var(--rose-500); }
.icon-btn--primary { border-color: var(--rose-200); color: var(--rose-500); }
.icon-btn--primary:hover { background: var(--rose-500); color: #fff; border-color: var(--rose-500); }

/* ── Pagination ── */
.table-pagination { display: flex; justify-content: center; gap: var(--space-2); padding: var(--space-4) var(--space-4) var(--space-2); }
.page-btn {
  width: 32px; height: 32px;
  border-radius: var(--radius-md);
  font-size: 0.8125rem;
  border: 1.5px solid var(--cream-300);
  background: #fff;
  color: var(--gray-500);
}
.page-btn:hover { border-color: var(--rose-300); color: var(--rose-500); }
.page-btn--active { background: var(--rose-500); border-color: var(--rose-500); color: #fff; }

/* ── Loading skeleton ── */
.loading-rows { padding: var(--space-4); display: flex; flex-direction: column; gap: var(--space-3); }
.loading-row {
  height: 44px; border-radius: var(--radius-md);
  background: linear-gradient(90deg, var(--cream-100) 25%, var(--cream-50) 50%, var(--cream-100) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s ease-in-out infinite;
}
@keyframes shimmer { from { background-position: 200% 0; } to { background-position: -200% 0; } }

/* ── Empty ── */
.empty-state { text-align: center; padding: var(--space-12); color: var(--gray-400); }

/* ── Modals ── */
.modal-backdrop {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.4);
  backdrop-filter: blur(3px);
  z-index: 2000;
  display: flex; align-items: center; justify-content: center;
  padding: var(--space-4);
}
.modal-box {
  background: #fff;
  border-radius: var(--radius-xl);
  padding: var(--space-6);
  width: 100%; max-width: 440px;
  box-shadow: 0 20px 60px rgba(0,0,0,.15);
}
.modal-box--lg { max-width: 580px; }
.modal-title { font-family: var(--font-display); font-size: 1.125rem; color: var(--gray-800); margin-bottom: var(--space-1); }
.modal-sub { font-size: 0.875rem; color: var(--gray-400); margin-bottom: var(--space-4); }
.modal-field { display: flex; flex-direction: column; gap: var(--space-1); margin-bottom: var(--space-4); }
.modal-field--full { grid-column: span 2; }
.modal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-1) var(--space-4); }
.modal-actions { display: flex; justify-content: flex-end; gap: var(--space-2); margin-top: var(--space-2); }
.modal-error { font-size: 0.875rem; color: #dc2626; background: #fef2f2; padding: var(--space-3); border-radius: var(--radius-md); margin-bottom: var(--space-3); }

/* ── Header ── */
.page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: var(--space-5); }
.page-header__title { font-family: var(--font-display); font-size: 1.5rem; color: var(--gray-800); }

@media (max-width: 768px) {
  .delivery-stats { grid-template-columns: repeat(2, 1fr); }
  .admin-table { font-size: 0.8125rem; }
}
</style>
