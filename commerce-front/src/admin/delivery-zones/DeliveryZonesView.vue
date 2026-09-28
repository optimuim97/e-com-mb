<template>
  <div class="admin-page">
    <header class="page-header">
      <div>
        <span class="eyebrow">Logistique</span>
        <h1 class="page-header__title">Zones de livraison</h1>
        <p class="page-header__sub">Tarifs par ville / commune — utilisés lors du checkout.</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">+ Nouvelle zone</button>
    </header>

    <!-- Tabs par groupe -->
    <div class="zones-tabs">
      <button
        v-for="t in tabs"
        :key="t.key"
        class="zones-tab"
        :class="{ 'zones-tab--active': activeTab === t.key }"
        @click="setTab(t.key)"
      >
        {{ t.label }}
        <span class="zones-tab__count">{{ t.count }}</span>
      </button>
    </div>

    <!-- Search -->
    <div class="zones-toolbar">
      <input v-model="search" type="text" class="input zones-search" placeholder="Rechercher (zone, alias, pays)…" />
    </div>

    <div v-if="loading" class="loader-wrap"><div class="loader"></div></div>

    <div v-else class="card zones-table-card">
      <table class="admin-table admin-table--compact">
        <thead>
          <tr>
            <th class="th-sortable" @click="sortBy('name')">
              Zone
              <span class="sort-arrow" :class="{ 'sort-arrow--active': sort.key === 'name' }">{{ sort.key === 'name' ? (sort.dir === 'asc' ? '↑' : '↓') : '↕' }}</span>
            </th>
            <th>Alias</th>
            <th>Pays</th>
            <th class="th-sortable" @click="sortBy('price')">
              Tarif
              <span class="sort-arrow" :class="{ 'sort-arrow--active': sort.key === 'price' }">{{ sort.key === 'price' ? (sort.dir === 'asc' ? '↑' : '↓') : '↕' }}</span>
            </th>
            <th>Franco</th>
            <th>Actif</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="z in pageRows" :key="z.id">
            <td>
              <strong>{{ z.name }}</strong>
              <div class="cell-group">{{ z.group }}</div>
            </td>
            <td class="cell-aliases">{{ (z.cities || []).join(', ') || '—' }}</td>
            <td>{{ z.country }}</td>
            <td>
              {{ fmt(z.price) }}
              <span v-if="z.price_unit === 'per_kg'" class="badge badge-gold">/ kg</span>
            </td>
            <td>{{ z.free_threshold ? fmt(z.free_threshold) : '—' }}</td>
            <td>
              <span :class="['pill', z.active ? 'pill--ok' : 'pill--off']">
                {{ z.active ? 'Oui' : 'Non' }}
              </span>
            </td>
            <td class="cell-actions">
              <button class="btn btn-xs btn-outline" @click="openEdit(z)">Modifier</button>
              <button class="btn btn-xs btn-danger" @click="destroy(z)">Suppr.</button>
            </td>
          </tr>
          <tr v-if="!pageRows.length">
            <td colspan="7" class="cell-empty">Aucune zone trouvée.</td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination unifiée -->
      <AdminPagination
        :current-page="page"
        :last-page="pageCount"
        :total="filteredRows.length"
        :per-page="perPage"
        item-singular="zone"
        item-plural="zones"
        @update:page="p => page = p"
        @update:per-page="p => { perPage = p; page = 1; }"
      />
    </div>

    <!-- Modal partagée avec l'écran des expéditions -->
    <DeliveryZoneFormModal
      v-if="modal"
      :zone="modalZone"
      @saved="apresEnregistrement"
      @close="modal = null"
    />

  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import api from '@/api';
import { usePersistedRef } from '@/admin/utils/persistedFilters';
import AdminPagination from '@/admin/components/AdminPagination.vue';
import DeliveryZoneFormModal from './DeliveryZoneFormModal.vue';

const zones      = ref([]);
const loading    = ref(true);
const modal      = ref(null);
/* Zone confiée à la modale : null pour une création vierge. */
const modalZone  = ref(null);

const activeTab  = ref('all');
// Conservée d'une visite à l'autre, comme sur les autres listes.
const search     = usePersistedRef('delivery-zones.search', '');
const sort       = ref({ key: 'name', dir: 'asc' });
const page       = ref(1);
const perPage    = ref(10);

const tabs = computed(() => {
  const groups = {};
  for (const z of zones.value) {
    groups[z.group] = (groups[z.group] || 0) + 1;
  }
  return [
    { key: 'all', label: 'Tout', count: zones.value.length },
    ...Object.entries(groups).map(([key, count]) => ({ key, label: key, count })),
  ];
});

const filteredRows = computed(() => {
  let rows = zones.value;
  if (activeTab.value !== 'all') {
    rows = rows.filter(z => z.group === activeTab.value);
  }
  const q = search.value.trim().toLowerCase();
  if (q) {
    rows = rows.filter(z =>
      z.name.toLowerCase().includes(q)
      || (z.country || '').toLowerCase().includes(q)
      || (z.cities || []).some(c => c.toLowerCase().includes(q))
    );
  }
  rows = [...rows].sort((a, b) => {
    const k = sort.value.key;
    const dir = sort.value.dir === 'asc' ? 1 : -1;
    const av = a[k] ?? '';
    const bv = b[k] ?? '';
    if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * dir;
    return String(av).localeCompare(String(bv), 'fr') * dir;
  });
  return rows;
});

const pageCount = computed(() => Math.max(1, Math.ceil(filteredRows.value.length / perPage.value)));
const pageRows  = computed(() => {
  const start = (page.value - 1) * perPage.value;
  return filteredRows.value.slice(start, start + perPage.value);
});

// Reset à la page 1 quand un filtre change
watch([activeTab, search, perPage], () => { page.value = 1; });

function setTab(key) {
  activeTab.value = key;
}

function sortBy(key) {
  if (sort.value.key === key) {
    sort.value.dir = sort.value.dir === 'asc' ? 'desc' : 'asc';
  } else {
    sort.value = { key, dir: 'asc' };
  }
}

async function load() {
  loading.value = true;
  try {
    const { data } = await api.get('/admin/delivery-zones');
    zones.value = data.data ?? [];
  } finally {
    loading.value = false;
  }
}

function openCreate() {
  modalZone.value = null;
  modal.value = 'create';
}

function openEdit(z) {
  modalZone.value = { ...z };
  modal.value = 'edit';
}

async function apresEnregistrement() {
  modal.value = null;
  await load();
}

async function destroy(z) {
  if (!confirm(`Supprimer la zone "${z.name}" ?`)) return;
  await api.delete(`/admin/delivery-zones/${z.id}`);
  await load();
}

function fmt(v) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', minimumFractionDigits: 0 }).format(Number(v ?? 0));
}

onMounted(load);
</script>

<style scoped>
.page-header__sub {
  font-size: 0.875rem;
  color: var(--gray-500);
  margin-top: 4px;
}

/* ── Tabs ── */
.zones-tabs {
  display: flex;
  gap: 4px;
  margin-bottom: var(--space-3);
  padding-bottom: var(--space-2);
  border-bottom: 1px solid var(--cream-200);
  overflow-x: auto;
}
.zones-tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: var(--radius-md);
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--gray-500);
  background: transparent;
  border: none;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s;
}
.zones-tab:hover { background: var(--cream-100); }
.zones-tab--active {
  background: var(--rose-500);
  color: #fff;
}
.zones-tab__count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 18px;
  padding: 0 6px;
  border-radius: 9999px;
  background: var(--cream-200);
  color: var(--gray-600);
  font-size: 0.6875rem;
  font-weight: 700;
}
.zones-tab--active .zones-tab__count {
  background: rgba(255,255,255,0.25);
  color: #fff;
}

/* ── Toolbar ── */
.zones-toolbar {
  display: flex;
  gap: var(--space-2);
  margin-bottom: var(--space-3);
}
.zones-search { flex: 1; max-width: 360px; }
.zones-perpage { width: 110px; }

/* ── Table ── */
.zones-table-card { overflow: hidden; }
.admin-table--compact th,
.admin-table--compact td {
  padding: 8px 12px !important;
  font-size: 0.8125rem;
}
.admin-table--compact thead th {
  font-size: 0.6875rem;
  letter-spacing: 0.06em;
}
.th-sortable { cursor: pointer; user-select: none; white-space: nowrap; }
.th-sortable:hover { color: var(--rose-600); }
.sort-arrow {
  display: inline-block;
  margin-left: 4px;
  font-size: 0.75rem;
  color: var(--gray-300);
}
.sort-arrow--active { color: var(--rose-500); font-weight: 700; }

.cell-group {
  font-size: 0.6875rem;
  color: var(--gray-400);
  margin-top: 2px;
}
.cell-aliases {
  color: var(--gray-500);
  font-size: 0.75rem;
  max-width: 280px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.cell-actions { display: flex; gap: 4px; white-space: nowrap; }
.cell-empty { text-align: center; color: var(--gray-400); padding: var(--space-4) !important; }

.btn-xs {
  padding: 4px 8px !important;
  font-size: 0.6875rem !important;
  height: auto !important;
}

.pill {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 9999px;
  font-size: 0.6875rem;
  font-weight: 600;
}
.pill--ok  { background: #dcfce7; color: #15803d; }
.pill--off { background: var(--cream-200); color: var(--gray-500); }

/* ── Pagination ── */
.zones-pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-3) var(--space-4);
  border-top: 1px solid var(--cream-200);
  background: var(--cream-50);
}
.zones-pagination__info {
  font-size: 0.75rem;
  color: var(--gray-500);
}

/* ── Modal ── */
.modal-overlay {
  position: fixed; inset: 0; z-index: 60;
  background: rgba(0,0,0,0.4);
  display: flex; align-items: center; justify-content: center;
  padding: var(--space-4);
}
.modal {
  background: #fff;
  border-radius: var(--radius-lg);
  padding: var(--space-5);
  width: 100%;
  max-width: 560px;
  max-height: 90vh;
  overflow-y: auto;
}
.modal h3 { font-family: var(--font-display); font-size: 1.25rem; margin-bottom: var(--space-4); }
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-3);
}
.form-grid label { display: flex; flex-direction: column; gap: 4px; font-size: 0.8125rem; color: var(--gray-600); }
.form-grid input, .form-grid select {
  border: 1px solid var(--cream-300);
  border-radius: var(--radius-sm);
  padding: 8px 10px;
  font-size: 0.875rem;
}
.span-2 { grid-column: span 2; }
.checkbox { flex-direction: row !important; align-items: center; }
.modal-actions {
  display: flex; gap: var(--space-2);
  justify-content: flex-end;
  margin-top: var(--space-4);
}
.form-error { color: #ef4444; font-size: 0.8125rem; margin-top: var(--space-2); }
</style>
