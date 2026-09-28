<template>
  <div class="admin-page">
    <!-- Header -->
    <header class="page-header">
      <div>
        <span class="eyebrow">Accès</span>
        <h1 class="page-header__title">Rôles</h1>
      </div>
      <button @click="openCreate" class="btn btn-primary">
        <PlusIcon class="w-4 h-4" />
        Nouveau rôle
      </button>
    </header>

    <!-- Loading -->
    <div v-if="loading" class="loader-wrap"><div class="loader"></div></div>

    <!-- Empty -->
    <div v-else-if="roles.length === 0" class="card empty-state">
      <div class="empty-state__icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.35"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
      </div>
      <p>Aucun rôle trouvé.</p>
    </div>

    <!-- Cards -->
    <div v-else class="roles-grid">
      <div v-for="role in roles" :key="role.id" class="role-card card">
        <div class="role-card__header">
          <div class="role-card__info">
            <span class="role-card__name">{{ role.name }}</span>
            <span class="badge badge-gray">{{ role.users_count }} utilisateur(s)</span>
          </div>
          <div class="role-card__actions">
            <button @click="openEdit(role)" class="icon-btn icon-btn--edit" aria-label="Modifier">
              <PencilIcon class="w-4 h-4" />
            </button>
            <button
              v-if="!protectedRoles.includes(role.name)"
              @click="confirmDelete(role)"
              class="icon-btn icon-btn--delete"
              aria-label="Supprimer"
            >
              <TrashIcon class="w-4 h-4" />
            </button>
            <span v-else class="role-card__protected" title="Rôle système — non supprimable">
              <LockClosedIcon class="w-4 h-4" />
            </span>
          </div>
        </div>

        <div class="role-card__permissions">
          <span v-if="role.permissions.length === 0" class="role-card__empty-perm">
            Aucune permission assignée
          </span>
          <template v-else>
            <span v-for="p in role.permissions.slice(0, 6)" :key="p" class="perm-tag">{{ p }}</span>
            <span v-if="role.permissions.length > 6" class="perm-tag perm-tag--more">
              +{{ role.permissions.length - 6 }} autres
            </span>
          </template>
        </div>
      </div>
    </div>

    <!-- Create / Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
        <div class="modal modal--lg">
          <header class="modal__header">
            <h2>{{ editingRole ? `Modifier "${editingRole.name}"` : 'Nouveau rôle' }}</h2>
            <button @click="closeModal" class="modal__close"><XMarkIcon class="w-5 h-5" /></button>
          </header>

          <form @submit.prevent="saveRole" class="modal__body">
            <div>
              <label class="label">Nom du rôle *</label>
              <input
                v-model="form.name"
                type="text"
                class="input"
                required
                placeholder="ex: moderateur"
                :disabled="editingRole && protectedRoles.includes(editingRole.name)"
              />
            </div>

            <div class="perm-section">
              <div class="perm-section__header">
                <label class="label">Permissions</label>
                <div class="perm-section__actions">
                  <button type="button" @click="selectAll" class="btn-link">Tout sélectionner</button>
                  <span class="perm-section__sep">·</span>
                  <button type="button" @click="clearAll" class="btn-link">Tout désélectionner</button>
                </div>
              </div>

              <div v-if="loadingPerms" class="loader-wrap"><div class="loader"></div></div>
              <div v-else class="perm-groups">
                <div v-for="group in groupedPermissions" :key="group.group" class="perm-group">
                  <div class="perm-group__header">
                    <p class="perm-group__title">{{ group.group }}</p>
                    <button type="button" @click="toggleGroup(group)" class="btn-link btn-link--sm">
                      {{ isGroupSelected(group) ? 'Désélectionner' : 'Sélectionner' }}
                    </button>
                  </div>
                  <div class="perm-group__items">
                    <label v-for="p in group.items" :key="p" class="perm-check">
                      <input type="checkbox" :value="p" v-model="form.permissions" class="perm-check__input" />
                      <span>{{ p }}</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <p v-if="formError" class="form-error">{{ formError }}</p>

            <footer class="modal__footer">
              <button type="button" @click="closeModal" class="btn btn-outline">Annuler</button>
              <button type="submit" class="btn btn-primary" :disabled="saving">
                {{ saving ? 'Enregistrement…' : (editingRole ? 'Enregistrer' : 'Créer') }}
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
            <h2>Supprimer le rôle</h2>
            <button @click="deleteTarget = null" class="modal__close"><XMarkIcon class="w-5 h-5" /></button>
          </header>
          <div class="modal__body">
            <p class="delete-confirm__text">
              Supprimer le rôle <strong>{{ deleteTarget.name }}</strong> ?
              Les utilisateurs ayant ce rôle le perdront.
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
import { PlusIcon, PencilIcon, TrashIcon, XMarkIcon, LockClosedIcon } from '@heroicons/vue/24/outline'
import api from '@/api'

const protectedRoles = ['admin', 'staff', 'customer']

const roles   = ref([])
const loading = ref(true)

/* ── Permissions ── */
const groupedPermissions = ref([])
const allPermissionNames = ref([])
const loadingPerms = ref(false)

async function loadPermissions() {
  loadingPerms.value = true
  try {
    const { data } = await api.get('/admin/permissions')
    groupedPermissions.value = data.grouped
    allPermissionNames.value = data.list.map(p => p.name)
  } finally {
    loadingPerms.value = false
  }
}

/* ── Modal ── */
const showModal   = ref(false)
const editingRole = ref(null)
const saving      = ref(false)
const formError   = ref('')
const deleteTarget = ref(null)

const form = reactive({ name: '', permissions: [] })

function openCreate() {
  editingRole.value = null
  Object.assign(form, { name: '', permissions: [] })
  formError.value = ''
  showModal.value = true
}

function openEdit(role) {
  editingRole.value = role
  Object.assign(form, { name: role.name, permissions: [...role.permissions] })
  formError.value = ''
  showModal.value = true
}

function closeModal() { showModal.value = false; editingRole.value = null }

function selectAll() { form.permissions = [...allPermissionNames.value] }
function clearAll()  { form.permissions = [] }

function isGroupSelected(group) {
  return group.items.every(p => form.permissions.includes(p))
}

function toggleGroup(group) {
  if (isGroupSelected(group)) {
    form.permissions = form.permissions.filter(p => !group.items.includes(p))
  } else {
    const toAdd = group.items.filter(p => !form.permissions.includes(p))
    form.permissions.push(...toAdd)
  }
}

async function saveRole() {
  saving.value = true; formError.value = ''
  try {
    const payload = { name: form.name, permissions: form.permissions }
    if (editingRole.value) {
      await api.put(`/admin/roles/${editingRole.value.id}`, payload)
    } else {
      await api.post('/admin/roles', payload)
    }
    closeModal()
    fetchRoles()
  } catch (e) {
    formError.value = e.response?.data?.message
      || Object.values(e.response?.data?.errors || {})[0]?.[0]
      || 'Une erreur est survenue.'
  } finally {
    saving.value = false
  }
}

function confirmDelete(role) { deleteTarget.value = role }

async function doDelete() {
  saving.value = true
  try {
    await api.delete(`/admin/roles/${deleteTarget.value.id}`)
    deleteTarget.value = null
    fetchRoles()
  } catch (e) {
    alert(e.response?.data?.message || 'Erreur.')
  } finally {
    saving.value = false
  }
}

async function fetchRoles() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/roles')
    roles.value = data
  } finally {
    loading.value = false
  }
}

onMounted(() => { fetchRoles(); loadPermissions() })
</script>

<style scoped>
.admin-page { display: flex; flex-direction: column; gap: var(--space-5); }

.roles-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
  gap: var(--space-4);
}

.role-card { padding: var(--space-5); }

.role-card__header {
  display: flex; align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-3);
}
.role-card__info { display: flex; align-items: center; gap: var(--space-3); }
.role-card__name {
  font-size: 1rem; font-weight: 700; color: var(--gray-800);
  text-transform: capitalize;
}
.role-card__actions { display: flex; align-items: center; gap: var(--space-1); }
.role-card__protected { color: var(--gray-300); display: flex; padding: 8px; }

.role-card__permissions { display: flex; flex-wrap: wrap; gap: var(--space-1-5); }
.role-card__empty-perm { font-size: 0.8125rem; color: var(--gray-400); font-style: italic; }

.perm-tag {
  display: inline-flex; align-items: center;
  padding: 2px 8px; border-radius: var(--radius-full);
  background: var(--cream-100); color: var(--gray-600);
  font-size: 0.75rem; font-family: var(--font-mono, monospace);
  border: 1px solid var(--cream-200);
}
.perm-tag--more {
  background: var(--rose-50); color: var(--rose-500); border-color: var(--rose-100);
}

/* ── Permission editor ── */
.perm-section { margin-top: var(--space-5); }
.perm-section__header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: var(--space-3);
}
.perm-section__actions { display: flex; align-items: center; gap: var(--space-2); }
.perm-section__sep { color: var(--gray-300); }

.btn-link {
  background: none; border: none; padding: 0;
  font-size: 0.8125rem; color: var(--rose-500); cursor: pointer;
  text-decoration: underline; text-underline-offset: 2px;
}
.btn-link:hover { color: var(--rose-700); }
.btn-link--sm { font-size: 0.75rem; }

.perm-groups { display: flex; flex-direction: column; gap: var(--space-4); max-height: 360px; overflow-y: auto; padding-right: var(--space-1); }

.perm-group__header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: var(--space-2);
}
.perm-group__title {
  font-size: 0.75rem; font-weight: 600; text-transform: uppercase;
  letter-spacing: 0.06em; color: var(--gray-500);
}
.perm-group__items { display: flex; flex-wrap: wrap; gap: var(--space-2); }
.perm-check { display: inline-flex; align-items: center; gap: 6px; font-size: 0.8125rem; color: var(--gray-700); cursor: pointer; }
.perm-check__input { accent-color: var(--rose-500); width: 14px; height: 14px; cursor: pointer; }

.delete-confirm__text { font-size: 0.9375rem; color: var(--gray-700); line-height: 1.6; margin-bottom: var(--space-5); }
.btn-danger {
  background: #ef4444; color: #fff; border: none; padding: 10px 20px;
  border-radius: var(--radius-full); font-size: 0.875rem; font-weight: 600;
  cursor: pointer; transition: background var(--transition-fast);
}
.btn-danger:hover { background: #dc2626; }
.btn-danger:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
