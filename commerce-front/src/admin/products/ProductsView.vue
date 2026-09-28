<template>
  <div class="admin-page">
    <!-- Header -->
    <header class="page-header">
      <div>
        <span class="eyebrow">Boutique</span>
        <h1 class="page-header__title">Produits</h1>
        <p class="page-header__sub">{{ meta.total ?? 0 }} produit(s) au total</p>
      </div>
      <RouterLink to="/admin/products/create" class="btn btn-primary">
        <PlusIcon class="w-4 h-4" /> Nouveau produit
      </RouterLink>
    </header>

    <!-- Filtres -->
    <div class="card filters-bar">
      <input v-model="search" class="input" placeholder="Rechercher…" />
      <AppSelect v-model="statusFilter" :options="statusFilterOptions" placeholder="Tous les statuts" class="filters-bar__select" />
    </div>

    <!-- Table -->
    <div class="card">
      <div v-if="loading" class="loader-wrap">
        <div class="loader"></div>
      </div>
      <div v-else-if="!products.length" class="empty-state">
        <div class="empty-state__icon">🌸</div>
        <p>Aucun produit pour le moment.</p>
      </div>
      <div v-else class="table-scroll">
        <table class="admin-table">
          <thead>
            <tr>
              <th class="admin-table__grip-col"></th>
              <th>Produit</th>
              <th>Catégorie</th>
              <th>Prix</th>
              <th>Stock</th>
              <th>Statut</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <!--
              Glisser-déposer : l'ordre de cette liste est celui de la vitrine.
              La poignée porte le `draggable`, pas la ligne entière — sinon
              sélectionner un nom de produit démarre un déplacement.
            -->
            <tr
              v-for="(p, index) in products"
              :key="p.id"
              class="admin-table__row--sortable"
              :class="{
                'admin-table__row--dragged': dragIndex === index,
                'admin-table__row--over': overIndex === index && dragIndex !== index,
              }"
              @dragover.prevent="overIndex = index"
              @drop.prevent="deposer(index)"
              @dragend="finDeplacement"
            >
              <td class="admin-table__grip-col">
                <span
                  class="admin-table__grip"
                  :class="{ 'admin-table__grip--busy': rangeant }"
                  :draggable="!rangeant"
                  :title="rangeant ? 'Enregistrement en cours…' : 'Glisser pour ranger'"
                  @dragstart="debutDeplacement(index, $event)"
                >⠿</span>
              </td>
              <td>
                <div class="admin-table__product">
                  <div class="admin-table__thumb">
                    <img v-if="p.images?.[0]?.url" :src="p.images[0].url" :alt="p.name" />
                    <PhotoIcon v-else class="w-5 h-5" />
                  </div>
                  <span class="admin-table__client">{{ p.name }}</span>
                </div>
              </td>
              <td>{{ p.category?.name ?? '—' }}</td>
              <td class="admin-table__total">{{ formatPrice(p.price) }}</td>
              <td>
                <span :class="p.stock <= 5 ? 'badge badge-danger' : 'badge badge-success'">{{ p.stock }}</span>
                <!--
                  Le compteur est déjà net des commandes en cours : le stock est
                  décompté à la création, pas à la confirmation. On rappelle donc
                  ce qui est vendu mais pas encore parti, sans quoi le comptage
                  en rayon ne retomberait jamais sur ses pieds.
                -->
                <span
                  v-if="p.reserved > 0"
                  class="stock-reserve"
                  :title="`${p.reserved} réservé(s) par des commandes non expédiées — ${p.physical_stock} en rayon`"
                >
                  +{{ p.reserved }} réservé{{ p.reserved > 1 ? 's' : '' }}
                </span>
              </td>
              <td>
                <span :class="p.is_active ? 'badge badge-success' : 'badge badge-gray'">
                  {{ p.is_active ? 'Actif' : 'Inactif' }}
                </span>
              </td>
              <td class="admin-table__action-cell">
                <RouterLink :to="`/admin/products/${p.id}/edit`" class="icon-btn icon-btn--edit" aria-label="Modifier">
                  <PencilIcon class="w-4 h-4" />
                </RouterLink>
                <button @click="deleteProduct(p)" class="icon-btn icon-btn--delete" aria-label="Supprimer">
                  <TrashIcon class="w-4 h-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <AdminPagination
        :current-page="currentPage"
        :last-page="meta.last_page"
        :total="meta.total"
        :show-per-page="false"
        item-singular="produit"
        item-plural="produits"
        @update:page="p => currentPage = p"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { usePersistedRef } from '@/admin/utils/persistedFilters'
import { RouterLink } from 'vue-router'
import AdminPagination from '@/admin/components/AdminPagination.vue'
import { PlusIcon, PencilIcon, TrashIcon, PhotoIcon } from '@heroicons/vue/24/outline'
import api from '@/api'

const products     = ref([])
const meta         = ref({})
const loading      = ref(true)
// Conservés d'une visite à l'autre, comme sur les autres listes.
const search       = usePersistedRef('products.search', '')
const statusFilter = usePersistedRef('products.status', '')

const statusFilterOptions = [
  { value: '1', label: 'Actifs' },
  { value: '0', label: 'Inactifs' },
]
const currentPage  = ref(1)

function formatPrice(price) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', minimumFractionDigits: 0 }).format(price)
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/products', {
      params: { search: search.value || null, is_active: statusFilter.value || null, page: currentPage.value },
    })
    products.value = data.data
    meta.value     = data.meta ?? {}
  } catch {
    // garder l'existant en cas d'erreur
  } finally {
    loading.value = false
  }
}

// ── Rangement par glisser-déposer ────────────────────────────────────────────
//
// L'ordre de cette liste est celui de la vitrine. Le déplacement est appliqué
// à l'écran tout de suite puis envoyé au serveur : attendre la réponse ferait
// « sauter » la vignette sous le doigt.
const dragIndex = ref(null)
const overIndex = ref(null)
const rangeant  = ref(false)

function debutDeplacement(index, evenement) {
  dragIndex.value = index
  // Firefox n'amorce pas le déplacement sans données transportées.
  evenement.dataTransfer.effectAllowed = 'move'
  evenement.dataTransfer.setData('text/plain', String(index))
}

function finDeplacement() {
  dragIndex.value = null
  overIndex.value = null
}

async function deposer(cible) {
  const source = dragIndex.value
  finDeplacement()

  if (source === null || source === cible || rangeant.value) return

  const avant = [...products.value]
  const liste = [...products.value]
  liste.splice(cible, 0, ...liste.splice(source, 1))
  products.value = liste

  rangeant.value = true
  try {
    await api.put('/admin/products/reorder', { ids: liste.map((p) => p.id) })
  } catch {
    // Le serveur a refusé : on remet la liste telle qu'elle était, sans quoi
    // l'écran afficherait un ordre que la vitrine n'a pas.
    products.value = avant
  } finally {
    rangeant.value = false
  }
}

async function deleteProduct(p) {
  if (!confirm(`Archiver "${p.name}" ?`)) return
  try {
    await api.delete(`/admin/products/${p.id}`)
    load()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Impossible de supprimer ce produit.')
  }
}

watch([search, statusFilter], () => { currentPage.value = 1; load() })
watch(currentPage, load)
onMounted(load)
</script>

<style scoped>
/* ── Rangement par glisser-déposer ── */
.admin-table__grip-col { width: 34px; padding-right: 0; }

.admin-table__grip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 26px;
  border-radius: 6px;
  color: #c4b8b1;
  font-size: 15px;
  line-height: 1;
  cursor: grab;
  user-select: none;
  transition: color .15s ease, background .15s ease;
}
.admin-table__grip:hover { color: #8a7f78; background: #f5efec; }
.admin-table__grip:active { cursor: grabbing; }
.admin-table__grip--busy { opacity: .4; cursor: progress; }

/* La ligne déplacée s'efface : c'est l'emplacement d'arrivée qui compte. */
.admin-table__row--dragged { opacity: .4; }

/* Trait d'insertion sous le curseur, plutôt qu'un fond coloré : il montre où
   la ligne va se poser, pas seulement qu'on la survole. */
.admin-table__row--over td {
  box-shadow: inset 0 2px 0 var(--rose-400, #e8869f);
}

/* Réservé : indication discrète à côté du compteur, pas un second chiffre
   concurrent — c'est le stock qui reste l'information principale. */
.stock-reserve {
  display: block;
  margin-top: 3px;
  font-size: 10px;
  color: #a89a92;
  white-space: nowrap;
  cursor: help;
}

.admin-page { display: flex; flex-direction: column; gap: var(--space-5); }

.page-header__sub {
  font-size: 0.875rem;
  color: var(--gray-500);
  margin-top: 4px;
}

.filters-bar {
  padding: var(--space-4);
  display: flex;
  gap: var(--space-3);
  flex-wrap: wrap;
}
.filters-bar .input { flex: 1; max-width: 320px; }
.filters-bar__select { max-width: 200px; }

.table-scroll { overflow-x: auto; }

/* ── Table modifiers ── */
.admin-table__product {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}
.admin-table__thumb {
  width: 40px; height: 40px;
  border-radius: var(--radius-md);
  overflow: hidden;
  background: var(--cream-100);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--gray-300);
  flex-shrink: 0;
}
.admin-table__thumb img { width: 100%; height: 100%; object-fit: cover; }

.admin-table__client { font-weight: 500; color: var(--gray-800); }
.admin-table__total { font-weight: 600; color: var(--rose-600) !important; }

.admin-table__action-cell {
  display: flex;
  gap: var(--space-2);
  justify-content: flex-end;
}

.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-3) var(--space-4);
  border-top: 1px solid var(--cream-200);
}
.pagination p { font-size: 0.8125rem; color: var(--gray-400); }
.pagination__actions { display: flex; gap: var(--space-1); }
.page-btn {
  width: 32px; height: 32px;
  border-radius: var(--radius-md);
  font-size: 0.8125rem;
  font-weight: 500;
  background: var(--cream-100);
  color: var(--gray-600);
  transition: all var(--transition-fast);
}
.page-btn:hover { background: var(--rose-50); color: var(--rose-500); }
.page-btn--active {
  background: var(--rose-500);
  color: #fff;
  box-shadow: var(--shadow-rose);
}
</style>
