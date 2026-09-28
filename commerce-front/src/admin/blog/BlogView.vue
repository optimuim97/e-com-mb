<template>
  <div class="space-y-6">

    <!-- Header actions -->
    <div class="flex items-center justify-between flex-wrap gap-4">
      <div class="flex items-center gap-3 flex-wrap">
        <!-- Search -->
        <div class="blog-search">
          <svg class="blog-search__icon" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35" stroke-linecap="round"/>
          </svg>
          <input v-model="search" type="text" placeholder="Rechercher un article…" class="blog-search__input" @input="debouncedFetch" />
        </div>
        <!-- Status filter -->
        <div style="width:180px">
          <AppSelect v-model="filterStatus" :options="statusFilterOptions" placeholder="Tous les statuts" @update:modelValue="fetchPosts" />
        </div>
      </div>
      <RouterLink :to="{ name: 'admin.blog.create' }" class="btn btn-primary btn-sm">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 4v16m8-8H4"/></svg>
        Nouvel article
      </RouterLink>
    </div>

    <!-- Table -->
    <div class="card">
      <!-- Loading -->
      <div v-if="loading" class="flex justify-center py-16">
        <div class="w-8 h-8 border-2 border-rose-400 border-t-transparent rounded-full animate-spin"></div>
      </div>

      <!-- Empty -->
      <div v-else-if="!posts.length" class="blog-empty">
        <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1" class="text-gray-300 mb-3">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l4 4v10a2 2 0 01-2 2z"/>
          <path stroke-linecap="round" d="M17 8H7M17 12H7M13 16H7"/>
        </svg>
        <p class="text-gray-400 text-sm">Aucun article trouvé</p>
        <RouterLink :to="{ name: 'admin.blog.create' }" class="btn btn-primary btn-sm mt-4">Créer le premier article</RouterLink>
      </div>

      <!-- Rows -->
      <table v-else class="w-full text-sm">
        <thead class="border-b border-gray-100">
          <tr class="text-left">
            <th class="px-5 py-3 font-medium text-gray-400 text-xs uppercase tracking-wide">Article</th>
            <th class="px-3 py-3 font-medium text-gray-400 text-xs uppercase tracking-wide">Catégorie</th>
            <th class="px-3 py-3 font-medium text-gray-400 text-xs uppercase tracking-wide">Statut</th>
            <th class="px-3 py-3 font-medium text-gray-400 text-xs uppercase tracking-wide">Lecture</th>
            <th class="px-3 py-3 font-medium text-gray-400 text-xs uppercase tracking-wide">Date</th>
            <th class="px-3 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="post in posts" :key="post.id" class="hover:bg-gray-50 transition-colors">
            <!-- Cover + title -->
            <td class="px-5 py-4">
              <div class="flex items-center gap-3">
                <div class="blog-cover-thumb">
                  <img v-if="post.cover_image" :src="post.cover_image" :alt="post.title" />
                  <svg v-else width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1" class="text-gray-300">
                    <path stroke-linecap="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M14 8h.01"/>
                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                  </svg>
                </div>
                <div class="min-w-0">
                  <p class="font-medium text-gray-800 truncate max-w-xs">{{ post.title }}</p>
                  <p class="text-xs text-gray-400 truncate max-w-xs">{{ post.slug }}</p>
                </div>
              </div>
            </td>
            <td class="px-3 py-4 text-gray-500">{{ post.category?.name ?? '—' }}</td>
            <td class="px-3 py-4">
              <span :class="post.status === 'published' ? 'badge-published' : 'badge-draft'" class="blog-badge">
                {{ post.status === 'published' ? 'Publié' : 'Brouillon' }}
              </span>
            </td>
            <td class="px-3 py-4 text-gray-400 text-xs">{{ post.reading_time ? `${post.reading_time} min` : '—' }}</td>
            <td class="px-3 py-4 text-gray-400 text-xs whitespace-nowrap">{{ formatDate(post.published_at || post.created_at) }}</td>
            <td class="px-3 py-4">
              <div class="flex items-center gap-2 justify-end">
                <RouterLink :to="{ name: 'admin.blog.edit', params: { id: post.id } }"
                  class="btn-icon-sm" title="Modifier">
                  <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                  </svg>
                </RouterLink>
                <button @click="confirmDelete(post)" class="btn-icon-sm btn-icon-danger" title="Supprimer">
                  <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <AdminPagination
        :current-page="pagination?.current_page"
        :last-page="pagination?.last_page"
        :total="pagination?.total"
        :show-per-page="false"
        item-singular="article"
        item-plural="articles"
        @update:page="goPage"
      />
    </div>

    <!-- Delete confirm modal -->
    <Teleport to="body">
      <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget = null">
        <div class="modal-box">
          <h3 class="font-semibold text-gray-800 mb-2">Supprimer l'article ?</h3>
          <p class="text-sm text-gray-500 mb-5">« {{ deleteTarget.title }} » sera définitivement supprimé.</p>
          <div class="flex gap-3 justify-end">
            <button @click="deleteTarget = null" class="btn btn-ghost btn-sm">Annuler</button>
            <button @click="deletePost" :disabled="deleting" class="btn btn-sm" style="background:#ef4444;color:#fff;border-color:#ef4444">
              {{ deleting ? '…' : 'Supprimer' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { usePersistedRef } from '@/admin/utils/persistedFilters'
import { RouterLink } from 'vue-router'
import AdminPagination from '@/admin/components/AdminPagination.vue'
import api from '@/api'

const posts        = ref([])
const loading      = ref(true)
// Conservés d'une visite à l'autre, comme sur les autres listes.
const search       = usePersistedRef('blog.search', '')
const filterStatus = usePersistedRef('blog.status', '')

const statusFilterOptions = [
  { value: 'published', label: 'Publiés' },
  { value: 'draft',     label: 'Brouillons' },
]
const pagination   = ref(null)
const currentPage  = ref(1)
const deleteTarget = ref(null)
const deleting     = ref(false)

let debounceTimer = null
function debouncedFetch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(fetchPosts, 350)
}

async function fetchPosts(page = 1) {
  loading.value = true
  currentPage.value = page
  try {
    const { data } = await api.get('/admin/blog/posts', {
      params: { search: search.value || undefined, status: filterStatus.value || undefined, page },
    })
    posts.value      = data.data
    pagination.value = data.meta
  } finally {
    loading.value = false
  }
}

function goPage(p) { fetchPosts(p) }

function formatDate(val) {
  if (!val) return '—'
  return new Date(val).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
}

function confirmDelete(post) { deleteTarget.value = post }

async function deletePost() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await api.delete(`/admin/blog/posts/${deleteTarget.value.id}`)
    posts.value = posts.value.filter(p => p.id !== deleteTarget.value.id)
    deleteTarget.value = null
  } finally {
    deleting.value = false
  }
}

onMounted(() => fetchPosts())
</script>

<style scoped>
.blog-search {
  position: relative;
  display: flex;
  align-items: center;
}
.blog-search__icon {
  position: absolute;
  left: 10px;
  color: var(--gray-400);
  pointer-events: none;
}
.blog-search__input {
  padding: 8px 12px 8px 32px;
  border: 1.5px solid var(--cream-300);
  border-radius: var(--radius-md);
  font-size: 0.8125rem;
  background: #fff;
  color: var(--gray-800);
  width: 240px;
  transition: border-color var(--transition-fast);
}
.blog-search__input:focus { outline: none; border-color: var(--rose-400); }

.blog-cover-thumb {
  width: 44px;
  height: 44px;
  border-radius: var(--radius-md);
  background: var(--cream-100);
  overflow: hidden;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}
.blog-cover-thumb img { width: 100%; height: 100%; object-fit: cover; }

.blog-badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 10px;
  border-radius: var(--radius-full);
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.badge-published { background: #dcfce7; color: #15803d; }
.badge-draft     { background: var(--cream-200); color: var(--gray-500); }

.blog-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: var(--space-16);
}

.btn-icon-sm {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: var(--radius-md);
  background: var(--cream-100);
  color: var(--gray-500);
  transition: all var(--transition-fast);
}
.btn-icon-sm:hover { background: var(--rose-50); color: var(--rose-600); }
.btn-icon-danger:hover { background: #fee2e2; color: #ef4444; }

.blog-page-btn {
  width: 32px;
  height: 32px;
  border-radius: var(--radius-md);
  font-size: 0.8125rem;
  color: var(--gray-500);
  background: var(--cream-100);
  transition: all var(--transition-fast);
}
.blog-page-btn.active { background: var(--rose-500); color: #fff; }
.blog-page-btn:hover:not(.active) { background: var(--rose-50); color: var(--rose-500); }

/* Delete modal */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.45);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: var(--z-modal);
}
.modal-box {
  background: #fff;
  border-radius: var(--radius-lg);
  padding: var(--space-6);
  width: 100%;
  max-width: 400px;
  box-shadow: var(--shadow-lg);
}
</style>
