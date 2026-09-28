<template>
  <div class="admin-page">
    <!-- Header -->
    <header class="page-header">
      <div>
        <span class="eyebrow">Promotions</span>
        <h1 class="page-header__title">Coupons de réduction</h1>
      </div>
      <button @click="openModal" class="btn btn-primary">
        <PlusIcon class="w-4 h-4" />
        Nouveau coupon
      </button>
    </header>

    <!-- Loading -->
    <div v-if="loading" class="loader-wrap">
      <div class="loader"></div>
    </div>

    <!-- Empty -->
    <div v-else-if="coupons.length === 0" class="card empty-state">
      <div class="empty-state__icon">🌸</div>
      <p>Aucun coupon. Créez-en un pour commencer.</p>
    </div>

    <!-- Table -->
    <div v-else class="card">
      <div class="table-scroll">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Code</th>
              <th>Type</th>
              <th>Valeur</th>
              <th>Min. commande</th>
              <th>Porte sur</th>
              <th>Utilisations</th>
              <th>Expiration</th>
              <th>Statut</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="coupon in coupons" :key="coupon.id">
              <td class="admin-table__mono coupon-code">{{ coupon.code }}</td>
              <td>{{ coupon.type === 'percentage' ? 'Pourcentage' : 'Montant fixe' }}</td>
              <td class="admin-table__client">
                {{ coupon.type === 'percentage' ? coupon.value + '%' : formatPrice(coupon.value) }}
              </td>
              <td>{{ coupon.minimum_amount ? formatPrice(coupon.minimum_amount) : '—' }}</td>
              <td>
                <span v-if="!coupon.products?.length" class="coupon-scope">Tout le panier</span>
                <span
                  v-else
                  class="coupon-scope coupon-scope--targeted"
                  :title="coupon.products.map(p => p.name).join(', ')"
                >
                  {{ coupon.products.length }} article{{ coupon.products.length > 1 ? 's' : '' }}
                </span>
              </td>
              <td>
                {{ coupon.used_count ?? 0 }}
                <span v-if="coupon.usage_limit" class="coupon-max">/ {{ coupon.usage_limit }}</span>
              </td>
              <td>{{ coupon.expires_at ? formatDate(coupon.expires_at) : '—' }}</td>
              <td>
                <span :class="coupon.is_active ? 'badge badge-success' : 'badge badge-gray'">
                  {{ coupon.is_active ? 'Actif' : 'Inactif' }}
                </span>
              </td>
              <td class="admin-table__action-cell">
                <button @click="openEdit(coupon)" class="icon-btn icon-btn--edit" aria-label="Modifier">
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button @click="deleteCoupon(coupon)" class="icon-btn icon-btn--delete" aria-label="Supprimer">
                  <TrashIcon class="w-4 h-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
        <div class="modal">
          <header class="modal__header">
            <h2>{{ editingId ? 'Modifier le coupon' : 'Nouveau coupon' }}</h2>
            <button @click="closeModal" class="modal__close" aria-label="Fermer">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </header>

          <form @submit.prevent="saveCoupon" class="modal__body">
            <div class="modal__grid">
              <div class="modal__full">
                <label class="label">Code *</label>
                <input v-model="form.code" type="text" class="input modal__uppercase" required placeholder="PROMO20" />
              </div>

              <div>
                <label class="label">Type *</label>
                <AppSelect v-model="form.type" :options="couponTypeOptions" />
              </div>

              <div>
                <label class="label">Valeur *</label>
                <input v-model.number="form.value" type="number" min="0" class="input" required placeholder="20" />
              </div>

              <div>
                <label class="label">Min. commande</label>
                <input v-model.number="form.minimum_amount" type="number" min="0" class="input" placeholder="5000" />
              </div>

              <div>
                <label class="label">Utilisations max</label>
                <input v-model.number="form.usage_limit" type="number" min="1" class="input" placeholder="100" />
              </div>

              <div class="modal__full">
                <label class="label">Date d'expiration</label>
                <input v-model="form.expires_at" type="date" class="input" />
              </div>

              <!--
                Ciblage : sans article, le code porte sur toute la commande.
                Avec, il ne remise que ces articles-là — de quoi écouler une
                référence sans brader le reste du panier.
              -->
              <div class="modal__full">
                <label class="label">
                  Articles ciblés
                  <span class="coupon-hint">— vide = tout le panier</span>
                </label>

                <input
                  v-model="productSearch"
                  type="search"
                  class="input"
                  placeholder="Rechercher un article…"
                />

                <ul v-if="productMatches.length" class="coupon-picker__results">
                  <li v-for="p in productMatches" :key="p.id">
                    <button type="button" class="coupon-picker__add" @click="addProduct(p)">
                      + {{ p.name }}
                    </button>
                  </li>
                </ul>
                <p v-else-if="productSearch.trim()" class="coupon-picker__empty">
                  Aucun article ne correspond.
                </p>

                <ul v-if="form.products.length" class="coupon-chips">
                  <li v-for="p in form.products" :key="p.id" class="coupon-chip">
                    {{ p.name }}
                    <button
                      type="button"
                      class="coupon-chip__x"
                      aria-label="Retirer"
                      @click="removeProduct(p.id)"
                    >×</button>
                  </li>
                </ul>
                <p v-else class="coupon-picker__all">
                  Ce code s'appliquera à l'ensemble du panier.
                </p>
              </div>

              <div class="modal__full modal__toggle-row">
                <span>Coupon actif</span>
                <button
                  type="button"
                  @click="form.is_active = !form.is_active"
                  class="toggle"
                  :class="{ 'toggle--on': form.is_active }"
                >
                  <span class="toggle__dot"></span>
                </button>
              </div>
            </div>

            <p v-if="formError" class="form-error">{{ formError }}</p>

            <div class="modal__actions">
              <button type="button" @click="closeModal" class="btn btn-ghost">Annuler</button>
              <button type="submit" :disabled="saving" class="btn btn-primary">
                {{ saving ? '…' : (editingId ? 'Mettre à jour' : 'Créer') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/api'
import { PlusIcon, PencilIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const coupons = ref([])
const loading = ref(true)
const showModal = ref(false)
const editingId = ref(null)
const saving = ref(false)
const formError = ref('')

/* ── Sélecteur d'articles ciblés ──────────────────────────────────────────
   Le catalogue est chargé une fois à l'ouverture de l'écran : il tient en
   quelques dizaines de références, une recherche serveur à chaque frappe
   coûterait plus qu'elle ne rapporte. */
const catalogue     = ref([])
const productSearch = ref('')

const productMatches = computed(() => {
  const q = productSearch.value.trim().toLowerCase()
  if (!q) return []

  const dejaPris = new Set(form.products.map(p => p.id))

  return catalogue.value
    .filter(p => !dejaPris.has(p.id) && p.name.toLowerCase().includes(q))
    .slice(0, 6)
})

function addProduct(product) {
  form.products.push({ id: product.id, name: product.name })
  productSearch.value = ''
}

function removeProduct(id) {
  form.products = form.products.filter(p => p.id !== id)
}

async function fetchCatalogue() {
  try {
    const { data } = await api.get('/admin/products', { params: { per_page: 200 } })
    catalogue.value = (data.data ?? data ?? []).map(p => ({ id: p.id, name: p.name }))
  } catch {
    // Le ciblage devient inutilisable, le reste du formulaire reste valable.
    catalogue.value = []
  }
}

const couponTypeOptions = [
  { value: 'percentage', label: 'Pourcentage (%)' },
  { value: 'fixed',      label: 'Montant fixe (FCFA)' },
]

/*
 * Les noms suivent ceux de l'API. Le formulaire envoyait `min_order`,
 * `max_uses` et un type « percent » : les deux premiers n'existent pas côté
 * serveur et partaient à la poubelle sans un mot, le troisième était refusé par
 * la validation. Le minimum d'achat et la limite d'usage saisis ici n'étaient
 * donc jamais enregistrés.
 */
const form = reactive({
  code: '',
  type: 'percentage',
  value: '',
  minimum_amount: '',
  usage_limit: '',
  expires_at: '',
  is_active: true,
  // Articles visés. Vide = le code porte sur tout le panier.
  products: [],
})

function resetForm() {
  form.code = ''
  form.type = 'percentage'
  form.value = ''
  form.minimum_amount = ''
  form.usage_limit = ''
  form.expires_at = ''
  form.is_active = true
  form.products = []
  productSearch.value = ''
  formError.value = ''
}

function openModal() {
  editingId.value = null
  resetForm()
  showModal.value = true
}

function openEdit(coupon) {
  editingId.value = coupon.id
  form.code = coupon.code
  form.type = coupon.type
  form.value = coupon.value
  form.minimum_amount = coupon.minimum_amount ?? ''
  form.usage_limit = coupon.usage_limit ?? ''
  form.expires_at = coupon.expires_at ? coupon.expires_at.substring(0, 10) : ''
  form.is_active = coupon.is_active
  form.products = (coupon.products ?? []).map(p => ({ id: p.id, name: p.name }))
  productSearch.value = ''
  formError.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingId.value = null
}

async function fetchCoupons() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/coupons')
    coupons.value = data.data ?? data
  } finally {
    loading.value = false
  }
}

async function saveCoupon() {
  saving.value = true
  formError.value = ''
  try {
    const payload = {
      code: form.code.toUpperCase(),
      type: form.type,
      value: form.value,
      minimum_amount: form.minimum_amount || null,
      usage_limit: form.usage_limit || null,
      expires_at: form.expires_at || null,
      is_active: form.is_active,
      // Toujours envoyé, tableau vide compris : c'est ainsi qu'on retire le
      // ciblage d'un coupon pour le rendre à nouveau applicable partout.
      product_ids: form.products.map(p => p.id),
    }
    if (editingId.value) {
      await api.patch(`/admin/coupons/${editingId.value}`, payload)
    } else {
      await api.post('/admin/coupons', payload)
    }
    closeModal()
    await fetchCoupons()
  } catch (e) {
    formError.value = e.response?.data?.message ?? 'Erreur.'
  } finally {
    saving.value = false
  }
}

async function deleteCoupon(coupon) {
  if (!confirm(`Supprimer le coupon "${coupon.code}" ?`)) return
  try {
    await api.delete(`/admin/coupons/${coupon.id}`)
    await fetchCoupons()
  } catch {
    alert('Impossible de supprimer ce coupon.')
  }
}

function formatDate(val) {
  if (!val) return '—'
  return new Date(val).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatPrice(val) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', maximumFractionDigits: 0 }).format(val ?? 0)
}

onMounted(() => {
  fetchCoupons()
  fetchCatalogue()
})
</script>

<style scoped>
.admin-page { display: flex; flex-direction: column; gap: var(--space-5); }

.table-scroll { overflow-x: auto; }

/* ── Table modifiers ── */
.coupon-code { font-weight: 600 !important; color: var(--rose-700) !important; letter-spacing: 0.06em; }
.coupon-max { color: var(--gray-300); }
.admin-table__client { font-weight: 500; color: var(--gray-800); }
.admin-table__action-cell { display: flex; gap: var(--space-2); justify-content: flex-end; }

/* ── Modal ── */
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: var(--z-modal);
  background: rgba(0,0,0,0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
  backdrop-filter: blur(4px);
}
.modal {
  background: #fff;
  border-radius: var(--radius-lg);
  width: 100%;
  max-width: 520px;
  max-height: 90vh;
  overflow: auto;
  box-shadow: var(--shadow-lg);
}
.modal__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-5) var(--space-6);
  border-bottom: 1px solid var(--cream-200);
}
.modal__header h2 {
  font-family: var(--font-display);
  font-size: 1.25rem;
  font-weight: 500;
  color: var(--gray-800);
}
.modal__close {
  width: 32px; height: 32px;
  border-radius: 50%;
  color: var(--gray-400);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all var(--transition-fast);
}
.modal__close:hover { background: var(--cream-200); color: var(--gray-700); }

.modal__body {
  padding: var(--space-6);
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}
.modal__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-4);
}
.modal__full { grid-column: 1 / -1; }
.modal__uppercase { text-transform: uppercase; letter-spacing: 0.05em; }

.modal__toggle-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-3) 0;
}
.modal__toggle-row span {
  font-size: 0.875rem;
  color: var(--gray-700);
  font-weight: 500;
}

.modal__actions {
  display: flex;
  gap: var(--space-3);
  margin-top: var(--space-2);
}
.modal__actions .btn { flex: 1; justify-content: center; }

/* ── Ciblage d'articles ── */
.coupon-hint {
  font-weight: 400;
  color: var(--gray-400);
  font-size: 0.75rem;
}

.coupon-picker__results {
  list-style: none;
  margin: 4px 0 0;
  padding: 4px;
  border: 1px solid var(--cream-200);
  border-radius: var(--radius-md, 8px);
  background: #fff;
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
  max-height: 180px;
  overflow-y: auto;
}

.coupon-picker__add {
  display: block;
  width: 100%;
  text-align: left;
  padding: 6px 10px;
  border: 0;
  background: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.8125rem;
  color: var(--gray-700);
}
.coupon-picker__add:hover { background: var(--rose-50); color: var(--rose-600); }

.coupon-picker__empty,
.coupon-picker__all {
  margin: 6px 0 0;
  font-size: 0.75rem;
  color: var(--gray-400);
}

.coupon-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  list-style: none;
  padding: 0;
  margin: 8px 0 0;
}

.coupon-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 6px 4px 10px;
  border-radius: var(--radius-full, 999px);
  background: var(--rose-50);
  color: var(--rose-600);
  font-size: 0.75rem;
  font-weight: 500;
}

.coupon-chip__x {
  border: 0;
  background: none;
  color: inherit;
  cursor: pointer;
  font-size: 0.9375rem;
  line-height: 1;
  padding: 0 2px;
}
.coupon-chip__x:hover { color: var(--rose-700); }

.coupon-scope { font-size: 0.8125rem; color: var(--gray-500); }
.coupon-scope--targeted {
  color: var(--rose-600);
  font-weight: 600;
  cursor: help;
}
</style>
