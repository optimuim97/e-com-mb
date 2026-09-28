<template>
  <div class="abandoned-carts">

    <!-- Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Paniers abandonnés</h1>
        <p class="page-subtitle">Suivi des relances email J+1 &amp; J+3 via Brevo.</p>
      </div>
    </div>

    <!-- Stats cards -->
    <div class="stats-grid" v-if="stats">
      <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--rose">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 001.99 1.61h9.72a2 2 0 001.99-1.61L23 6H6"/></svg>
        </div>
        <div class="stat-card__body">
          <p class="stat-card__value">{{ stats.total_abandoned }}</p>
          <p class="stat-card__label">Paniers abandonnés</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--amber">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-card__body">
          <p class="stat-card__value">{{ stats.pending_j1 }}</p>
          <p class="stat-card__label">En attente J+1</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--blue">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        </div>
        <div class="stat-card__body">
          <p class="stat-card__value">{{ stats.reminder_1_sent }}</p>
          <p class="stat-card__label">Relances J+1 envoyées</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--purple">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg>
        </div>
        <div class="stat-card__body">
          <p class="stat-card__value">{{ stats.reminder_3_sent }}</p>
          <p class="stat-card__label">Relances J+3 envoyées</p>
        </div>
      </div>
      <div class="stat-card stat-card--wide">
        <div class="stat-card__icon stat-card__icon--green">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
        </div>
        <div class="stat-card__body">
          <p class="stat-card__value">{{ formatPrice(stats.revenue_at_risk) }}</p>
          <p class="stat-card__label">Chiffre d'affaires à risque</p>
        </div>
      </div>
    </div>

    <!-- Filter -->
    <div class="filters-bar">
      <select v-model="filter" class="input input--sm" @change="load(1)">
        <option value="">Tous</option>
        <option value="no_reminder">Sans relance</option>
        <option value="j1_sent">J+1 envoyé</option>
        <option value="j3_sent">J+3 envoyé</option>
      </select>
    </div>

    <!-- Brevo config notice -->
    <div class="brevo-notice">
      <span class="brevo-notice__icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      </span>
      <div>
        <strong>Configuration Brevo requise</strong> — Les relances s'envoient automatiquement toutes les heures via
        <code>php artisan cart:send-abandonment-reminders</code>.
        Configurez <code>MAIL_HOST=smtp-relay.brevo.com</code>, <code>MAIL_USERNAME</code> et <code>MAIL_PASSWORD</code>
        dans votre <code>.env</code>.
      </div>
    </div>

    <!-- Table -->
    <div class="table-card">
      <div v-if="loading" class="table-empty">Chargement…</div>
      <div v-else-if="carts.length === 0" class="table-empty">Aucun panier abandonné pour ce filtre.</div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>Client</th>
            <th>Email</th>
            <th>Articles</th>
            <th>Valeur</th>
            <th>Abandonné le</th>
            <th>J+1</th>
            <th>J+3</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="cart in carts" :key="cart.id" class="data-table__row">
            <td class="font-medium">{{ cart.user_name }}</td>
            <td class="text-muted">{{ cart.user_email }}</td>
            <td>
              <span class="badge badge-items">{{ cart.items_count }} article{{ cart.items_count > 1 ? 's' : '' }}</span>
            </td>
            <td class="font-semibold text-rose">{{ formatPrice(cart.subtotal) }}</td>
            <td class="text-muted text-sm">{{ cart.abandoned_at }}</td>
            <td>
              <span v-if="cart.reminder_1_sent_at" class="badge badge--success" :title="cart.reminder_1_sent_at">
                ✓ {{ cart.reminder_1_sent_at }}
              </span>
              <span v-else class="badge badge--warning">En attente</span>
            </td>
            <td>
              <span v-if="cart.reminder_3_sent_at" class="badge badge--success" :title="cart.reminder_3_sent_at">
                ✓ {{ cart.reminder_3_sent_at }}
              </span>
              <span v-else-if="cart.reminder_1_sent_at" class="badge badge--warning">Planifié</span>
              <span v-else class="text-muted text-sm">—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <AdminPagination
      :current-page="page"
      :last-page="meta?.last_page"
      :total="meta?.total"
      :show-per-page="false"
      item-singular="panier abandonné"
      item-plural="paniers abandonnés"
      @update:page="load"
    />

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api'
import AdminPagination from '@/admin/components/AdminPagination.vue'

const carts   = ref([])
const stats   = ref(null)
const meta    = ref(null)
const loading = ref(false)
const page    = ref(1)
const filter  = ref('')

async function loadStats() {
  try {
    const { data } = await api.get('/admin/abandoned-carts/stats')
    stats.value = data
  } catch { /* ignore */ }
}

async function load(p = 1) {
  page.value  = p
  loading.value = true
  try {
    const params = { page: p }
    if (filter.value) params.status = filter.value
    const { data } = await api.get('/admin/abandoned-carts', { params })
    carts.value = data.data
    meta.value  = data.meta
  } finally {
    loading.value = false
  }
}

function formatPrice(val) {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency', currency: 'XOF', maximumFractionDigits: 0
  }).format(val ?? 0)
}

onMounted(() => { loadStats(); load(1) })
</script>

<style scoped>
.abandoned-carts {
  display: flex;
  flex-direction: column;
  gap: var(--space-6);
}

.page-header { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: var(--space-4); }
.page-title  { font-family: var(--font-display); font-size: 1.75rem; font-weight: 700; color: var(--gray-800); }
.page-subtitle { color: var(--gray-400); font-size: 0.875rem; margin-top: 2px; }

/* Stats */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--space-4);
}

.stat-card {
  background: var(--color-surface);
  border: 1px solid var(--cream-200);
  border-radius: var(--radius-xl);
  padding: var(--space-5);
  display: flex;
  align-items: center;
  gap: var(--space-4);
}

.stat-card--wide {
  grid-column: span 4;
}

.stat-card__icon {
  width: 48px; height: 48px;
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  flex-shrink: 0;
}

.stat-card__icon--rose   { background: #fce8f0; }
.stat-card__icon--amber  { background: #fef3c7; }
.stat-card__icon--blue   { background: #dbeafe; }
.stat-card__icon--purple { background: #ede9fe; }
.stat-card__icon--green  { background: #dcfce7; }

.stat-card__value { font-size: 1.75rem; font-weight: 800; color: var(--gray-800); line-height: 1.1; }
.stat-card__label { font-size: 0.8125rem; color: var(--gray-400); margin-top: 2px; }

/* Filters */
.filters-bar { display: flex; gap: var(--space-3); align-items: center; flex-wrap: wrap; }
.filters-bar .input { max-width: 200px; }

/* Brevo notice */
.brevo-notice {
  display: flex;
  gap: var(--space-3);
  align-items: flex-start;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: var(--radius-lg);
  padding: var(--space-4) var(--space-5);
  font-size: 0.8125rem;
  color: #1e40af;
  line-height: 1.6;
}

.brevo-notice__icon { font-size: 1.1rem; flex-shrink: 0; margin-top: 2px; }
.brevo-notice code {
  background: rgba(30,64,175,0.1);
  padding: 1px 6px;
  border-radius: 4px;
  font-family: monospace;
  font-size: 0.8rem;
}

/* Table */
.table-card {
  background: var(--color-surface);
  border: 1px solid var(--cream-200);
  border-radius: var(--radius-xl);
  overflow: hidden;
}

.table-empty {
  padding: var(--space-10);
  text-align: center;
  color: var(--gray-400);
}

.data-table { width: 100%; border-collapse: collapse; }

.data-table th {
  padding: var(--space-3) var(--space-4);
  text-align: left;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--gray-400);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  background: var(--cream-50);
  border-bottom: 1px solid var(--cream-200);
}

.data-table__row {
  border-bottom: 1px solid var(--cream-100);
  transition: background var(--transition-fast);
}
.data-table__row:hover { background: var(--rose-50); }
.data-table__row:last-child { border-bottom: none; }

.data-table td {
  padding: var(--space-3) var(--space-4);
  font-size: 0.875rem;
  color: var(--gray-700);
  vertical-align: middle;
}

.font-medium  { font-weight: 500; }
.font-semibold{ font-weight: 600; }
.text-rose    { color: var(--rose-600); }
.text-muted   { color: var(--gray-400); }
.text-sm      { font-size: 0.8125rem; }

.badge { display: inline-block; padding: 2px 8px; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 500; }
.badge-items  { background: var(--cream-100); color: var(--gray-600); }
.badge--success { background: #dcfce7; color: #15803d; }
.badge--warning { background: #fef3c7; color: #92400e; }

/* Pagination */
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-4);
}
.pagination__info { font-size: 0.875rem; color: var(--gray-500); }

@media (max-width: 900px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .stat-card--wide { grid-column: span 2; }
}

@media (max-width: 540px) {
  .stats-grid { grid-template-columns: 1fr; }
  .stat-card--wide { grid-column: span 1; }
}
</style>
