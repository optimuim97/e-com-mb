<template>
  <div class="admin-page">
    <!-- Header -->
    <header class="page-header">
      <div>
        <span class="eyebrow">Boutique</span>
        <h1 class="page-header__title">Catégories</h1>
      </div>
      <button @click="openCreate" class="btn btn-primary">
        <PlusIcon class="w-4 h-4" />
        Nouvelle catégorie
      </button>
    </header>

    <!-- Form inline create/edit -->
    <div v-if="showForm" class="card form-inline">
      <h2 class="form-inline__title">
        {{ editingId ? 'Modifier la catégorie' : 'Nouvelle catégorie' }}
      </h2>
      <form @submit.prevent="saveCategory" class="form-inline__row">
        <input v-model="formName" type="text" class="input" required placeholder="Nom de la catégorie" />
        <input v-model="formSlug" type="text" class="input" placeholder="slug-auto (optionnel)" />
        <div class="form-inline__actions">
          <button type="submit" :disabled="saving" class="btn btn-primary">
            {{ saving ? '…' : (editingId ? 'Mettre à jour' : 'Créer') }}
          </button>
          <button type="button" @click="cancelForm" class="btn btn-ghost">Annuler</button>
        </div>
      </form>
      <p v-if="formError" class="form-error">{{ formError }}</p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loader-wrap">
      <div class="loader"></div>
    </div>

    <!-- Empty -->
    <div v-else-if="categories.length === 0" class="card empty-state">
      <div class="empty-state__icon">🌸</div>
      <p>Aucune catégorie. Commencez par en créer une.</p>
    </div>

    <!-- Table -->
    <div v-else class="card table-scroll">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Nom</th>
            <th>Slug</th>
            <th>Produits</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="cat in categories" :key="cat.id">
            <td class="admin-table__client">{{ cat.name }}</td>
            <td class="admin-table__mono">{{ cat.slug }}</td>
            <td>{{ cat.products_count ?? 0 }}</td>
            <td class="admin-table__action-cell">
              <button @click="openEdit(cat)" class="icon-btn icon-btn--edit" aria-label="Modifier">
                <PencilIcon class="w-4 h-4" />
              </button>
              <button @click="deleteCategory(cat)" class="icon-btn icon-btn--delete" aria-label="Supprimer">
                <TrashIcon class="w-4 h-4" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import api from '@/api'
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'

const categories = ref([])
const loading = ref(true)
const showForm = ref(false)
const editingId = ref(null)
const formName = ref('')
const formSlug = ref('')
const formError = ref('')
const saving = ref(false)

watch(formName, (val) => {
  if (!editingId.value) {
    formSlug.value = val.toLowerCase().trim().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '')
  }
})

function openCreate() {
  editingId.value = null
  formName.value = ''
  formSlug.value = ''
  formError.value = ''
  showForm.value = true
}

function openEdit(cat) {
  editingId.value = cat.id
  formName.value = cat.name
  formSlug.value = cat.slug
  formError.value = ''
  showForm.value = true
}

function cancelForm() {
  showForm.value = false
  editingId.value = null
}

async function fetchCategories() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/categories')
    categories.value = data.data ?? data
  } finally {
    loading.value = false
  }
}

async function saveCategory() {
  saving.value = true
  formError.value = ''
  try {
    const payload = { name: formName.value, slug: formSlug.value || undefined }
    if (editingId.value) {
      await api.patch(`/admin/categories/${editingId.value}`, payload)
    } else {
      await api.post('/admin/categories', payload)
    }
    showForm.value = false
    editingId.value = null
    await fetchCategories()
  } catch (e) {
    formError.value = e.response?.data?.message ?? 'Erreur.'
  } finally {
    saving.value = false
  }
}

async function deleteCategory(cat) {
  if (!confirm(`Supprimer la catégorie "${cat.name}" ?`)) return
  try {
    await api.delete(`/admin/categories/${cat.id}`)
    await fetchCategories()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Impossible de supprimer cette catégorie.')
  }
}

onMounted(fetchCategories)
</script>

<style scoped>
.admin-page { display: flex; flex-direction: column; gap: var(--space-5); }

/* ── Form inline create/edit ── */
.form-inline {
  padding: var(--space-5);
  border: 2px solid var(--rose-200);
  background: linear-gradient(160deg, var(--rose-50) 0%, #fff 100%);
}
.form-inline__title {
  font-family: var(--font-display);
  font-size: 1.125rem;
  font-weight: 500;
  color: var(--gray-800);
  margin-bottom: var(--space-4);
}
.form-inline__row {
  display: flex;
  gap: var(--space-3);
  flex-wrap: wrap;
}
.form-inline__row .input { flex: 1; min-width: 200px; }
.form-inline__actions { display: flex; gap: var(--space-2); }

/* ── Table modifiers ── */
.admin-table__client { font-weight: 500; color: var(--gray-800); }
.admin-table__action-cell { display: flex; gap: var(--space-2); justify-content: flex-end; }
</style>
