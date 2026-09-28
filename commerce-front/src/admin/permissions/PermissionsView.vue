<template>
  <div class="admin-page">
    <!-- Header -->
    <header class="page-header">
      <div>
        <span class="eyebrow">Accès</span>
        <h1 class="page-header__title">Permissions</h1>
      </div>
      <button @click="openCreate" class="btn btn-primary">
        <PlusIcon class="w-4 h-4" />
        Nouvelle permission
      </button>
    </header>

    <!-- Loading -->
    <div v-if="loading" class="loader-wrap"><div class="loader"></div></div>

    <!-- Content -->
    <template v-else>
      <div v-if="grouped.length === 0" class="card empty-state">
        <div class="empty-state__icon">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.35"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 11-7.778 7.778 5.5 5.5 0 017.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
        </div>
        <p>Aucune permission définie.</p>
      </div>

      <div v-for="group in grouped" :key="group.group" class="card perm-module">
        <div class="perm-module__header">
          <h3 class="perm-module__title">{{ group.group }}</h3>
          <span class="badge badge-gray">{{ group.items.length }}</span>
        </div>
        <div class="perm-module__list">
          <div v-for="p in group.items" :key="p" class="perm-row">
            <span class="perm-row__name">{{ p }}</span>
            <div class="perm-row__actions">
              <button @click="openEdit(p)" class="icon-btn icon-btn--edit" aria-label="Modifier">
                <PencilIcon class="w-4 h-4" />
              </button>
              <button @click="confirmDelete(p)" class="icon-btn icon-btn--delete" aria-label="Supprimer">
                <TrashIcon class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Create / Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
        <div class="modal modal--sm">
          <header class="modal__header">
            <h2>{{ editingName ? 'Modifier la permission' : 'Nouvelle permission' }}</h2>
            <button @click="closeModal" class="modal__close"><XMarkIcon class="w-5 h-5" /></button>
          </header>
          <form @submit.prevent="save" class="modal__body">
            <div>
              <label class="label">Nom *</label>
              <input
                v-model="form.name"
                type="text"
                class="input"
                required
                placeholder="ex: products.create"
              />
              <p class="input-hint">Convention : <code>module.action</code> (ex : orders.view, settings.edit)</p>
            </div>

            <p v-if="formError" class="form-error">{{ formError }}</p>

            <footer class="modal__footer">
              <button type="button" @click="closeModal" class="btn btn-outline">Annuler</button>
              <button type="submit" class="btn btn-primary" :disabled="saving">
                {{ saving ? 'Enregistrement…' : (editingName ? 'Enregistrer' : 'Créer') }}
              </button>
            </footer>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Delete confirm -->
    <Teleport to="body">
      <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget = null">
        <div class="modal modal--sm">
          <header class="modal__header">
            <h2>Supprimer la permission</h2>
            <button @click="deleteTarget = null" class="modal__close"><XMarkIcon class="w-5 h-5" /></button>
          </header>
          <div class="modal__body">
            <p class="delete-confirm__text">
              Supprimer <strong>{{ deleteTarget }}</strong> ?
              Elle sera retirée de tous les rôles et utilisateurs.
            </p>
            <footer class="modal__footer">
              <button @click="deleteTarget = null" class="btn btn-outline">Annuler</button>
              <button @click="doDelete" class="btn btn-danger" :disabled="saving">
                {{ saving ? 'Suppression…' : 'Supprimer' }}
              </button>
            </footer>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { PlusIcon, PencilIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import api from '@/api'

const grouped = ref([])
const allList = ref([])
const loading = ref(true)

const showModal   = ref(false)
const editingName = ref(null) // stores the original name when editing
const editingId   = ref(null)
const saving      = ref(false)
const formError   = ref('')
const deleteTarget = ref(null) // permission name

const form = reactive({ name: '' })

async function fetchPermissions() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/permissions')
    grouped.value = data.grouped
    allList.value = data.list
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingName.value = null; editingId.value = null
  form.name = ''; formError.value = ''
  showModal.value = true
}

function openEdit(name) {
  const found = allList.value.find(p => p.name === name)
  editingName.value = name
  editingId.value   = found?.id ?? null
  form.name = name; formError.value = ''
  showModal.value = true
}

function closeModal() { showModal.value = false }

async function save() {
  saving.value = true; formError.value = ''
  try {
    if (editingId.value) {
      await api.put(`/admin/permissions/${editingId.value}`, { name: form.name })
    } else {
      await api.post('/admin/permissions', { name: form.name })
    }
    closeModal()
    fetchPermissions()
  } catch (e) {
    formError.value = e.response?.data?.message
      || Object.values(e.response?.data?.errors || {})[0]?.[0]
      || 'Une erreur est survenue.'
  } finally {
    saving.value = false
  }
}

function confirmDelete(name) { deleteTarget.value = name }

async function doDelete() {
  const found = allList.value.find(p => p.name === deleteTarget.value)
  if (!found) return
  saving.value = true
  try {
    await api.delete(`/admin/permissions/${found.id}`)
    deleteTarget.value = null
    fetchPermissions()
  } catch (e) {
    alert(e.response?.data?.message || 'Erreur.')
  } finally {
    saving.value = false
  }
}

onMounted(fetchPermissions)
</script>

<style scoped>
.admin-page { display: flex; flex-direction: column; gap: var(--space-5); }

.perm-module { padding: var(--space-5); }
.perm-module__header {
  display: flex; align-items: center; gap: var(--space-3);
  margin-bottom: var(--space-4);
}
.perm-module__title {
  font-size: 0.9375rem; font-weight: 700;
  text-transform: capitalize; color: var(--gray-800);
}

.perm-module__list { display: flex; flex-direction: column; gap: 2px; }

.perm-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 8px 12px; border-radius: var(--radius-md);
  transition: background var(--transition-fast);
}
.perm-row:hover { background: var(--cream-50); }
.perm-row__name {
  font-family: var(--font-mono, monospace);
  font-size: 0.8125rem; color: var(--gray-700);
}
.perm-row__actions { display: flex; gap: var(--space-1); }

.input-hint { font-size: 0.75rem; color: var(--gray-400); margin-top: 4px; }
.input-hint code {
  font-family: var(--font-mono, monospace);
  background: var(--cream-100); padding: 1px 4px; border-radius: 3px;
}

.delete-confirm__text { font-size: 0.9375rem; color: var(--gray-700); line-height: 1.6; margin-bottom: var(--space-5); }
.btn-danger {
  background: #ef4444; color: #fff; border: none; padding: 10px 20px;
  border-radius: var(--radius-full); font-size: 0.875rem; font-weight: 600;
  cursor: pointer; transition: background var(--transition-fast);
}
.btn-danger:hover { background: #dc2626; }
.btn-danger:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
