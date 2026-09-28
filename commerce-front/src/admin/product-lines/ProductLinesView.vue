<template>
  <div class="admin-page">
    <!-- Header -->
    <header class="page-header">
      <div>
        <span class="eyebrow">Catalogue</span>
        <h1 class="page-header__title">Gammes de produits</h1>
      </div>
      <RouterLink to="/admin/product-lines/create" class="btn btn-primary btn-sm">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
        </svg>
        Nouvelle gamme
      </RouterLink>
    </header>

    <!-- Loading -->
    <div v-if="loading" class="loader-wrap"><div class="loader"></div></div>

    <!-- Empty -->
    <div v-else-if="lines.length === 0" class="card empty-state">
      <div class="empty-state__icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.35"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
      </div>
      <p>Aucune gamme créée.</p>
      <RouterLink to="/admin/product-lines/create" class="btn btn-primary btn-sm">Créer la première gamme</RouterLink>
    </div>

    <!-- Grid -->
    <div v-else class="lines-grid">
      <div
        v-for="line in lines"
        :key="line.id"
        class="line-card card"
      >
        <!-- Cover -->
        <div class="line-card__cover" :style="{ background: line.color_hex + '22', borderColor: line.color_hex + '44' }">
          <img v-if="line.cover_url" :src="line.cover_url" :alt="line.name" class="line-card__img" />
          <div v-else class="line-card__placeholder" :style="{ color: line.color_hex }">
            <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
            </svg>
          </div>
          <!-- Active badge -->
          <span class="line-card__status" :class="line.is_active ? 'badge badge-success' : 'badge badge-gray'">
            {{ line.is_active ? 'Active' : 'Inactive' }}
          </span>
        </div>

        <!-- Info -->
        <div class="line-card__body">
          <div class="line-card__color-dot" :style="{ background: line.color_hex }"></div>
          <h3 class="line-card__name">{{ line.name }}</h3>
          <p v-if="line.tagline" class="line-card__tagline">{{ line.tagline }}</p>
          <p class="line-card__count">{{ line.products_count ?? 0 }} produit(s)</p>

          <div class="line-card__actions">
            <RouterLink :to="`/admin/product-lines/${line.id}/edit`" class="btn btn-outline btn-xs">
              <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
              Modifier
            </RouterLink>
            <button @click="confirmDelete(line)" class="btn btn-danger btn-xs">
              <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline stroke-linecap="round" points="3 6 5 6 21 6"/><path stroke-linecap="round" d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m5 0V4a1 1 0 011-1h2a1 1 0 011 1v2"/></svg>
              Supprimer
            </button>
          </div>
        </div>
      </div>
    </div>

    <AdminPagination
      :current-page="pagination.current_page"
      :last-page="pagination.last_page"
      :total="pagination.total"
      :show-per-page="false"
      item-singular="gamme"
      item-plural="gammes"
      @update:page="changePage"
    />

    <!-- Confirm delete modal -->
    <Teleport to="body">
      <div v-if="toDelete" class="modal-overlay" @click.self="toDelete = null">
        <div class="modal-box">
          <h3 class="modal-box__title">Supprimer la gamme ?</h3>
          <p class="modal-box__desc">
            « <strong>{{ toDelete.name }}</strong> » sera supprimée. Les produits associés ne seront plus rattachés à cette gamme.
          </p>
          <div class="modal-box__actions">
            <button @click="toDelete = null" class="btn btn-outline btn-sm">Annuler</button>
            <button @click="deleteLine" :disabled="deleting" class="btn btn-danger btn-sm">
              {{ deleting ? 'Suppression…' : 'Supprimer' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import AdminPagination from '@/admin/components/AdminPagination.vue'
import { readPagination } from '@/admin/utils/pagination'
import api from '@/api'

const lines      = ref([])
const loading    = ref(true)
const pagination = ref({})
const page       = ref(1)
const toDelete   = ref(null)
const deleting   = ref(false)

async function fetchLines() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/product-lines', { params: { page: page.value } })
    lines.value = data.data
    pagination.value = readPagination(data)
  } finally {
    loading.value = false
  }
}

function changePage(p) { page.value = p; fetchLines() }
function confirmDelete(line) { toDelete.value = line }

async function deleteLine() {
  if (!toDelete.value) return
  deleting.value = true
  try {
    await api.delete(`/admin/product-lines/${toDelete.value.id}`)
    lines.value = lines.value.filter(l => l.id !== toDelete.value.id)
    toDelete.value = null
  } finally {
    deleting.value = false
  }
}

onMounted(fetchLines)
</script>

<style scoped>
.lines-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: var(--space-5);
}

.line-card { overflow: hidden; display: flex; flex-direction: column; }

.line-card__cover {
  position: relative;
  height: 160px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-bottom: 1.5px solid;
}
.line-card__img { width: 100%; height: 100%; object-fit: cover; }
.line-card__placeholder { display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; }
.line-card__status {
  position: absolute;
  top: var(--space-3);
  right: var(--space-3);
}

.line-card__body {
  padding: var(--space-4);
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  flex: 1;
}
.line-card__color-dot {
  width: 10px; height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
  align-self: flex-start;
}
.line-card__name {
  font-weight: 600;
  font-size: 1rem;
  color: var(--gray-800);
  line-height: 1.3;
}
.line-card__tagline { font-size: 0.8125rem; color: var(--gray-500); }
.line-card__count { font-size: 0.75rem; color: var(--gray-400); }

.line-card__actions {
  display: flex;
  gap: var(--space-2);
  margin-top: auto;
  padding-top: var(--space-3);
  border-top: 1px solid var(--cream-200);
}

.btn-xs {
  padding: 4px 10px;
  font-size: 0.75rem;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border-radius: var(--radius-full);
}

.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-3) var(--space-4);
}
.pagination p { font-size: 0.8125rem; color: var(--gray-400); }
.pagination__actions { display: flex; gap: var(--space-2); }

/* Modal */
.modal-overlay {
  position: fixed; inset: 0; z-index: 200;
  background: rgba(0,0,0,0.45);
  display: flex; align-items: center; justify-content: center;
  padding: var(--space-4);
}
.modal-box {
  background: #fff;
  border-radius: var(--radius-xl);
  padding: var(--space-6);
  max-width: 420px; width: 100%;
  box-shadow: var(--shadow-xl);
}
.modal-box__title { font-size: 1.0625rem; font-weight: 600; color: var(--gray-800); margin-bottom: var(--space-2); }
.modal-box__desc { font-size: 0.875rem; color: var(--gray-500); margin-bottom: var(--space-5); line-height: 1.5; }
.modal-box__actions { display: flex; gap: var(--space-3); justify-content: flex-end; }
</style>
