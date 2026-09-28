<template>
  <div class="reviews-admin">
    <!-- Header -->
    <div class="reviews-admin__header">
      <div>
        <h1 class="page-title">Avis clients</h1>
        <p class="page-subtitle">Modérez les avis soumis par les clients.</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="reviews-admin__filters">
      <input
        v-model="filters.search"
        class="input input--sm"
        placeholder="Chercher client ou produit…"
        @input="debouncedLoad"
      />

      <select v-model="filters.status" class="input input--sm" @change="loadReviews(1)">
        <option value="">Tous les statuts</option>
        <option value="pending">En attente</option>
        <option value="approved">Approuvés</option>
        <option value="rejected">Rejetés</option>
      </select>

      <select v-model="filters.rating" class="input input--sm" @change="loadReviews(1)">
        <option value="">Toutes les notes</option>
        <option v-for="n in [5,4,3,2,1]" :key="n" :value="n">{{ n }} ★</option>
      </select>
    </div>

    <!-- Stats chips -->
    <div class="reviews-admin__chips">
      <span class="chip chip--pending">
        ⏳ {{ pendingCount }} en attente
      </span>
    </div>

    <!-- Table -->
    <div class="reviews-admin__table-wrap">
      <div v-if="loading" class="table-loading">Chargement…</div>
      <div v-else-if="reviews.length === 0" class="table-empty">
        Aucun avis pour ce filtre.
      </div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>Produit</th>
            <th>Client</th>
            <th>Note</th>
            <th>Avis</th>
            <th>Date</th>
            <th>Statut</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="review in reviews" :key="review.id" class="data-table__row">
            <td class="data-table__cell--product">
              <span class="data-table__product-name">{{ review.product?.name ?? '—' }}</span>
            </td>
            <td>
              <div class="data-table__user">
                <span class="data-table__name">{{ review.user?.name ?? '—' }}</span>
              </div>
            </td>
            <td>
              <div class="review-stars">
                <span v-for="i in 5" :key="i"
                  class="review-star"
                  :class="{ 'review-star--filled': i <= review.rating }">★</span>
              </div>
            </td>
            <td class="data-table__cell--comment">
              <p v-if="review.title" class="review-comment__title">{{ review.title }}</p>
              <p class="review-comment__body">{{ review.comment || '(pas de commentaire)' }}</p>
            </td>
            <td class="data-table__cell--date">{{ review.created_at }}</td>
            <td>
              <span class="badge" :class="statusBadgeClass(review.status)">
                {{ statusLabel(review.status) }}
              </span>
            </td>
            <td>
              <div class="review-actions">
                <!-- Approve -->
                <button
                  v-if="review.status !== 'approved'"
                  class="btn-icon btn-icon--approve"
                  title="Approuver"
                  :disabled="actionLoading[review.id]"
                  @click="setStatus(review, 'approved')"
                >✓</button>

                <!-- Reject -->
                <button
                  v-if="review.status !== 'rejected'"
                  class="btn-icon btn-icon--reject"
                  title="Rejeter"
                  :disabled="actionLoading[review.id]"
                  @click="setStatus(review, 'rejected')"
                >✕</button>

                <!-- Delete -->
                <button
                  class="btn-icon btn-icon--delete"
                  title="Supprimer"
                  :disabled="actionLoading[review.id]"
                  @click="deleteReview(review)"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                </button>
              </div>
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
      item-singular="avis"
      item-plural="avis"
      @update:page="loadReviews"
    />

    <!-- Note modal -->
    <Teleport to="body">
      <div v-if="noteModal.open" class="modal-overlay" @click.self="noteModal.open = false">
        <div class="modal-card">
          <h2 class="modal-card__title">Note administrateur</h2>
          <textarea
            v-model="noteModal.note"
            class="input"
            rows="4"
            placeholder="Note interne (visible des admins uniquement)…"
          ></textarea>
          <div class="modal-card__actions">
            <button class="btn btn-outline" @click="noteModal.open = false">Annuler</button>
            <button class="btn btn-primary" @click="saveNote">Enregistrer</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { usePersistedFilters } from '@/admin/utils/persistedFilters'
import { reviewApi } from '@/features/reviews/review.api'
import AdminPagination from '@/admin/components/AdminPagination.vue'

const reviews      = ref([])
const meta         = ref(null)
const loading      = ref(false)
const page         = ref(1)
const actionLoading = reactive({})

// Conservés d'une visite à l'autre, comme sur les autres listes.
const filters = usePersistedFilters('reviews', {
  search: '',
  status: 'pending', // default to pending for moderation workflow
  rating: '',
})

const pendingCount = computed(() =>
  meta.value?.total ?? reviews.value.filter(r => r.status === 'pending').length
)

let searchTimer = null
function debouncedLoad() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadReviews(1), 400)
}

async function loadReviews(p = 1) {
  page.value = p
  loading.value = true
  try {
    const params = { page: p }
    if (filters.status) params.status = filters.status
    if (filters.rating) params.rating = filters.rating
    if (filters.search) params.search = filters.search
    const { data } = await reviewApi.adminList(params)
    reviews.value = data.data ?? data
    meta.value    = data.meta ?? null
  } finally {
    loading.value = false
  }
}

async function setStatus(review, status) {
  actionLoading[review.id] = true
  try {
    const { data } = await reviewApi.adminUpdate(review.id, { status })
    const idx = reviews.value.findIndex(r => r.id === review.id)
    if (idx !== -1) reviews.value[idx] = data.data ?? data
  } finally {
    delete actionLoading[review.id]
  }
}

async function deleteReview(review) {
  if (!confirm(`Supprimer l'avis de ${review.user?.name ?? 'cet utilisateur'} ?`)) return
  actionLoading[review.id] = true
  try {
    await reviewApi.adminDelete(review.id)
    reviews.value = reviews.value.filter(r => r.id !== review.id)
  } finally {
    delete actionLoading[review.id]
  }
}

// Note modal
const noteModal = reactive({ open: false, review: null, note: '' })

function openNote(review) {
  noteModal.review = review
  noteModal.note   = review.admin_note ?? ''
  noteModal.open   = true
}

async function saveNote() {
  if (!noteModal.review) return
  await reviewApi.adminUpdate(noteModal.review.id, { admin_note: noteModal.note })
  const idx = reviews.value.findIndex(r => r.id === noteModal.review.id)
  if (idx !== -1) reviews.value[idx].admin_note = noteModal.note
  noteModal.open = false
}

function statusLabel(status) {
  return { pending: 'En attente', approved: 'Approuvé', rejected: 'Rejeté' }[status] ?? status
}

function statusBadgeClass(status) {
  return {
    pending:  'badge--warning',
    approved: 'badge--success',
    rejected: 'badge--danger',
  }[status] ?? ''
}

onMounted(() => loadReviews(1))
</script>

<style scoped>
.reviews-admin {
  display: flex;
  flex-direction: column;
  gap: var(--space-6);
}

.reviews-admin__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: var(--space-4);
}

.page-title {
  font-family: var(--font-display);
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--gray-800);
}

.page-subtitle {
  color: var(--gray-400);
  font-size: 0.875rem;
  margin-top: 2px;
}

.reviews-admin__filters {
  display: flex;
  gap: var(--space-3);
  flex-wrap: wrap;
}

.reviews-admin__filters .input {
  max-width: 240px;
  flex: 1 1 160px;
}

.reviews-admin__chips {
  display: flex;
  gap: var(--space-3);
}

.chip {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  padding: 4px 12px;
  border-radius: var(--radius-full);
  font-size: 0.8125rem;
  font-weight: 500;
}

.chip--pending {
  background: #fef3c7;
  color: #92400e;
}

/* Table */
.reviews-admin__table-wrap {
  background: var(--color-surface);
  border: 1px solid var(--cream-200);
  border-radius: var(--radius-xl);
  overflow: hidden;
}

.table-loading,
.table-empty {
  padding: var(--space-10);
  text-align: center;
  color: var(--gray-400);
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

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
  vertical-align: top;
}

.data-table__cell--product { max-width: 140px; }
.data-table__product-name {
  font-weight: 500;
  color: var(--gray-800);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.data-table__name { font-weight: 500; }

.data-table__cell--comment { max-width: 260px; }
.review-comment__title {
  font-weight: 600;
  font-size: 0.8125rem;
  color: var(--gray-800);
  margin-bottom: 2px;
}
.review-comment__body {
  color: var(--gray-500);
  font-size: 0.8125rem;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.data-table__cell--date { white-space: nowrap; color: var(--gray-400); font-size: 0.75rem; }

/* Stars */
.review-stars { display: flex; gap: 2px; }
.review-star { color: #d1d5db; font-size: 0.875rem; }
.review-star--filled { color: #f59e0b; }

/* Badge */
.badge { display: inline-block; padding: 2px 10px; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 600; }
.badge--warning  { background: #fef3c7; color: #92400e; }
.badge--success  { background: #dcfce7; color: #15803d; }
.badge--danger   { background: #fee2e2; color: #b91c1c; }

/* Action buttons */
.review-actions {
  display: flex;
  gap: var(--space-1);
}

.btn-icon {
  width: 30px; height: 30px;
  border-radius: var(--radius-md);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8125rem;
  border: 1.5px solid transparent;
  transition: all var(--transition-fast);
  cursor: pointer;
}

.btn-icon:disabled { opacity: 0.4; cursor: not-allowed; }

.btn-icon--approve {
  background: #dcfce7; color: #15803d; border-color: #bbf7d0;
}
.btn-icon--approve:hover:not(:disabled) { background: #16a34a; color: #fff; }

.btn-icon--reject {
  background: #fee2e2; color: #b91c1c; border-color: #fecaca;
}
.btn-icon--reject:hover:not(:disabled) { background: #dc2626; color: #fff; }

.btn-icon--delete {
  background: var(--cream-100); color: var(--gray-500); border-color: var(--cream-200);
}
.btn-icon--delete:hover:not(:disabled) { background: #fecaca; border-color: #fca5a5; }

/* Pagination */
.reviews-admin__pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-4);
}

.reviews-admin__page-info {
  font-size: 0.875rem;
  color: var(--gray-500);
}

/* Modal */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.45);
  display: flex; align-items: center; justify-content: center;
  z-index: 100;
  padding: var(--space-4);
}

.modal-card {
  background: var(--color-surface);
  border-radius: var(--radius-2xl);
  padding: var(--space-8);
  width: 100%; max-width: 480px;
  display: flex; flex-direction: column; gap: var(--space-4);
  box-shadow: var(--shadow-xl);
}

.modal-card__title {
  font-family: var(--font-display);
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--gray-800);
}

.modal-card__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-3);
}
</style>
