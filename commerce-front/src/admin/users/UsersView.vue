<template>
  <div class="admin-page">
    <!-- Header -->
    <header class="page-header">
      <div>
        <span class="eyebrow">Communauté</span>
        <h1 class="page-header__title">Utilisateurs</h1>
      </div>
      <div class="header-actions">
        <span class="badge badge-gray">{{ pagination.total ?? 0 }} utilisateur(s)</span>
        <button v-if="auth.isAdmin" @click="openCreate" class="btn btn-primary">
          <PlusIcon class="w-4 h-4" />
          Nouvel utilisateur
        </button>
      </div>
    </header>

    <!-- Filters -->
    <div class="card filters-bar">
      <input
        v-model="search"
        @input="debouncedFetch"
        type="text"
        class="input filters-bar__search"
        placeholder="Rechercher par nom ou email…"
      />
      <select v-model="roleFilter" @change="() => { page = 1; fetchUsers() }" class="input filters-bar__select">
        <option value="">Tous les rôles</option>
        <option value="admin">Admin</option>
        <option value="manager">Gérant</option>
        <option value="staff">Staff</option>
        <option value="livreur">Livreur</option>
        <option value="customer">Client</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loader-wrap"><div class="loader"></div></div>

    <!-- Empty -->
    <div v-else-if="users.length === 0" class="card empty-state">
      <div class="empty-state__icon">👤</div>
      <p>Aucun utilisateur trouvé.</p>
    </div>

    <!-- Table -->
    <div v-else class="card">
      <div class="table-scroll">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Utilisateur</th>
              <th>Téléphone</th>
              <th>Email</th>
              <th>Rôle</th>
              <th>Commandes</th>
              <th>Total dépensé</th>
              <th>Inscrit le</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.id">
              <td>
                <div class="user-cell">
                  <div class="user-cell__avatar">{{ initials(user.name) }}</div>
                  <span class="admin-table__client">{{ user.name }}</span>
                </div>
              </td>
              <td>{{ user.phone || '—' }}</td>
              <td>{{ user.email || '—' }}</td>
              <td>
                <span :class="roleBadgeClass(user.role)">{{ roleLabel(user.role) }}</span>
                <span v-if="user.username" class="users__login">{{ user.username }}</span>
              </td>
              <td class="text-center">{{ user.orders_count ?? 0 }}</td>
              <td class="admin-table__total">{{ formatPrice(user.orders_sum_total ?? 0) }}</td>
              <td class="user-cell__date">{{ formatDate(user.created_at) }}</td>
              <td class="admin-table__action-cell">
                <!--
                  Ses actions dans le journal. Réservé à qui a l'habilitation :
                  le journal est un outil d'encadrement, pas une consultation
                  entre collègues.
                -->
                <RouterLink
                  v-if="auth.can('activity.view')"
                  :to="{ name: 'admin.activity', query: { operator_id: user.id } }"
                  class="icon-btn"
                  aria-label="Ses actions"
                  title="Ses actions dans le journal"
                >
                  <ClockIcon class="w-4 h-4" />
                </RouterLink>
                <button @click="openEdit(user)" class="icon-btn icon-btn--edit" aria-label="Modifier">
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button
                  v-if="auth.isAdmin && user.id !== auth.user?.id"
                  @click="confirmDelete(user)"
                  class="icon-btn icon-btn--delete"
                  aria-label="Supprimer"
                >
                  <TrashIcon class="w-4 h-4" />
                </button>
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
        item-singular="utilisateur"
        item-plural="utilisateurs"
        @update:page="changePage"
      />
    </div>

    <!-- Create / Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
        <div class="modal">
          <header class="modal__header">
            <h2>{{ editingUser ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur' }}</h2>
            <button @click="closeModal" class="modal__close" aria-label="Fermer">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </header>

          <form @submit.prevent="saveUser" class="modal__body">
            <div class="modal__grid">
              <div>
                <label class="label">Nom *</label>
                <input v-model="form.name" type="text" class="input" required placeholder="Jean Dupont" />
              </div>
              <div>
                <label class="label">Email *</label>
                <input v-model="form.email" type="email" class="input" required placeholder="jean@exemple.com" />
              </div>
              <div>
                <label class="label">Téléphone</label>
                <input v-model="form.phone" type="text" class="input" placeholder="+225 07 00 00 00 00" />
              </div>
              <div>
                <!--
                  Laissé vide, il se déduit du nom : « Sidik Ouattara » donne
                  « souattara ». C'est ce que saisit un livreur qui n'a pas
                  d'adresse électronique.
                -->
                <label class="label">Identifiant de connexion</label>
                <input
                  v-model="form.username"
                  type="text"
                  class="input"
                  autocapitalize="none"
                  placeholder="déduit du nom si vide"
                />
              </div>
              <div>
                <label class="label">Rôle *</label>
                <select v-model="form.role" class="input" required>
                  <option value="customer">Client</option>
                  <option value="livreur">Livreur</option>
                  <option value="staff">Staff</option>
                  <option value="manager">Gérant</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
              <div class="modal__full">
                <label class="label">
                  {{ editingUser ? 'Nouveau mot de passe (laisser vide = inchangé)' : 'Mot de passe *' }}
                </label>
                <input
                  v-model="form.password"
                  type="password"
                  class="input"
                  :required="!editingUser"
                  placeholder="••••••••"
                  autocomplete="new-password"
                />
              </div>

              <!-- Permissions directes (admin only, edition) -->
              <div v-if="auth.isAdmin && editingUser && allPermissions.length" class="modal__full">
                <label class="label">Permissions directes (en plus du rôle)</label>
                <div class="perm-groups">
                  <div v-for="group in groupedPermissions" :key="group.group" class="perm-group">
                    <p class="perm-group__title">{{ group.group }}</p>
                    <div class="perm-group__items">
                      <label v-for="p in group.items" :key="p" class="perm-check">
                        <input type="checkbox" :value="p" v-model="form.permissions" class="perm-check__input" />
                        <span>{{ p }}</span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <p v-if="formError" class="form-error">{{ formError }}</p>

            <footer class="modal__footer">
              <button type="button" @click="closeModal" class="btn btn-outline">Annuler</button>
              <button type="submit" class="btn btn-primary" :disabled="saving">
                {{ saving ? 'Enregistrement…' : (editingUser ? 'Enregistrer' : 'Créer') }}
              </button>
            </footer>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Delete confirm modal -->
    <Teleport to="body">
      <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget = null">
        <div class="modal modal--sm">
          <header class="modal__header">
            <h2>Supprimer l'utilisateur</h2>
            <button @click="deleteTarget = null" class="modal__close"><XMarkIcon class="w-5 h-5" /></button>
          </header>
          <div class="modal__body">
            <p class="delete-confirm__text">
              Êtes-vous sûr de vouloir supprimer <strong>{{ deleteTarget.name }}</strong> ?
              Cette action est irréversible.
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
import { usePersistedRef } from '@/admin/utils/persistedFilters'
import { RouterLink } from 'vue-router'
import { PlusIcon, PencilIcon, TrashIcon, XMarkIcon, ClockIcon } from '@heroicons/vue/24/outline'
import api from '@/api'
import AdminPagination from '@/admin/components/AdminPagination.vue'
import { readPagination } from '@/admin/utils/pagination'
import { useAuthStore } from '@/features/auth/auth.store'

const auth = useAuthStore()

const users      = ref([])
const loading    = ref(true)
// Conservés d'une visite à l'autre, comme sur les autres listes.
const search     = usePersistedRef('users.search', '')
const roleFilter = usePersistedRef('users.role', '')
const pagination = ref({})
const page       = ref(1)

/* ── Modal state ── */
const showModal   = ref(false)
const editingUser = ref(null)
const saving      = ref(false)
const formError   = ref('')
const deleteTarget = ref(null)

const form = reactive({
  name: '', email: '', username: '', phone: '', role: 'customer', password: '', permissions: [],
})

/* ── Permissions (for direct assignment) ── */
const allPermissions    = ref([])
const groupedPermissions = ref([])

async function loadPermissions() {
  if (!auth.isAdmin) return
  try {
    const { data } = await api.get('/admin/permissions')
    allPermissions.value    = data.list
    groupedPermissions.value = data.grouped
  } catch {}
}

/* ── Fetch ── */
let debounceTimer = null
function debouncedFetch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { page.value = 1; fetchUsers() }, 400)
}

function changePage(p) { page.value = p; fetchUsers() }

async function fetchUsers() {
  loading.value = true
  try {
    const params = { page: page.value }
    if (search.value)     params.search = search.value
    if (roleFilter.value) params.role   = roleFilter.value
    const { data } = await api.get('/admin/users', { params })
    users.value = data.data
    pagination.value = readPagination(data)
  } finally {
    loading.value = false
  }
}

/* ── Modal ── */
function openCreate() {
  editingUser.value = null
  Object.assign(form, { name: '', email: '', username: '', phone: '', role: 'customer', password: '', permissions: [] })
  formError.value = ''
  showModal.value = true
}

function openEdit(user) {
  editingUser.value = user
  Object.assign(form, {
    name:        user.name,
    email:       user.email || '',
    phone:       user.phone || '',
    username:    user.username || '',
    role:        user.role || 'customer',
    password:    '',
    permissions: [...(user.permissions || [])],
  })
  formError.value = ''
  showModal.value = true
}

function closeModal() { showModal.value = false; editingUser.value = null }

async function saveUser() {
  saving.value = true
  formError.value = ''
  try {
    const payload = {
      name:  form.name,
      email: form.email,
      phone: form.phone || null,
      username: form.username || null,
      role:  form.role,
    }
    if (form.password) payload.password = form.password
    if (editingUser.value && auth.isAdmin) payload.permissions = form.permissions

    if (editingUser.value) {
      await api.put(`/admin/users/${editingUser.value.id}`, payload)
    } else {
      payload.password = form.password
      await api.post('/admin/users', payload)
    }
    closeModal()
    fetchUsers()
  } catch (e) {
    formError.value = e.response?.data?.message
      || Object.values(e.response?.data?.errors || {})[0]?.[0]
      || 'Une erreur est survenue.'
  } finally {
    saving.value = false
  }
}

/* ── Delete ── */
function confirmDelete(user) { deleteTarget.value = user }

async function doDelete() {
  saving.value = true
  try {
    await api.delete(`/admin/users/${deleteTarget.value.id}`)
    deleteTarget.value = null
    fetchUsers()
  } catch (e) {
    alert(e.response?.data?.message || 'Erreur lors de la suppression.')
  } finally {
    saving.value = false
  }
}

/* ── Helpers ── */
function initials(name) {
  if (!name) return '?'
  return name.split(' ').slice(0, 2).map(n => n[0].toUpperCase()).join('')
}

function roleLabel(role) {
  return {
    admin: 'Admin', manager: 'Gérant', staff: 'Staff',
    livreur: 'Livreur', customer: 'Client',
  }[role] ?? role
}

function roleBadgeClass(role) {
  return {
    admin:    'badge badge-primary',
    manager:  'badge badge-primary',
    staff:    'badge badge-warning',
    livreur:  'badge badge-success',
    customer: 'badge badge-gray',
  }[role] ?? 'badge badge-gray'
}

function formatDate(val) {
  if (!val) return '—'
  return new Date(val).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatPrice(val) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', maximumFractionDigits: 0 }).format(val ?? 0)
}

onMounted(() => { fetchUsers(); loadPermissions() })
</script>

<style scoped>
/* L'identifiant de connexion, sous le rôle : c'est lui qu'on dicte au
   téléphone, pas l'adresse de service. */
.users__login {
  display: block;
  margin-top: 3px;
  font-family: ui-monospace, monospace;
  font-size: 0.6875rem;
  color: var(--gray-400);
}
.admin-page { display: flex; flex-direction: column; gap: var(--space-5); }

.header-actions { display: flex; align-items: center; gap: var(--space-3); }

.filters-bar {
  padding: var(--space-4);
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
  align-items: center;
}
.filters-bar__search { flex: 1; min-width: 220px; }
.filters-bar__select { width: 160px; }

.table-scroll { overflow-x: auto; }

.user-cell { display: flex; align-items: center; gap: var(--space-3); }
.user-cell__avatar {
  width: 36px; height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--rose-200), var(--rose-400));
  color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-weight: 600; font-size: 0.8125rem; flex-shrink: 0;
}
.user-cell__date { font-size: 0.75rem; color: var(--gray-400); }
.admin-table__total { font-weight: 600; color: var(--rose-600) !important; }
.text-center { text-align: center; }

/* ── Permissions grid ── */
.perm-groups { display: flex; flex-direction: column; gap: var(--space-4); }
.perm-group__title {
  font-size: 0.75rem; font-weight: 600; text-transform: uppercase;
  letter-spacing: 0.06em; color: var(--gray-500); margin-bottom: var(--space-2);
}
.perm-group__items { display: flex; flex-wrap: wrap; gap: var(--space-2); }
.perm-check {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: 0.8125rem; color: var(--gray-700); cursor: pointer;
}
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
