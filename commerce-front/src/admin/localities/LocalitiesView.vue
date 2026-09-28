<template>
  <div class="admin-page">
    <header class="page-header">
      <div>
        <span class="eyebrow">Logistique</span>
        <h1 class="page-header__title">Villes et communes</h1>
        <p class="page-header__sub">
          Ce que la cliente peut choisir comme adresse. Les tarifs, eux, se règlent
          dans <RouterLink to="/admin/delivery-zones">Zones de livraison</RouterLink>.
        </p>
      </div>
      <button class="btn btn-primary" @click="openCreate(null)">+ Nouvelle ville</button>
    </header>

    <div class="loc-toolbar">
      <input
        v-model="search"
        type="search"
        class="input loc-search"
        placeholder="Rechercher une ville ou une commune…"
      />
      <label class="loc-filter">
        <input v-model="hideInactive" type="checkbox" />
        Masquer les désactivées
      </label>
      <span class="loc-counts" v-if="!loading">
        {{ meta.cities }} ville{{ meta.cities > 1 ? 's' : '' }} ·
        {{ meta.communes }} commune{{ meta.communes > 1 ? 's' : '' }}
      </span>
    </div>

    <p v-if="error" class="loc-error">{{ error }}</p>

    <div v-if="loading" class="loader-wrap"><div class="loader"></div></div>

    <p v-else-if="!visibleCities.length" class="loc-empty">
      Aucune localité ne correspond.
    </p>

    <!--
      One card per city, communes folded inside. 111 cities and 518 communes
      laid flat would be unreadable; the search opens the ones that match.
    -->
    <div v-else class="loc-list">
      <article
        v-for="city in visibleCities"
        :key="city.id"
        class="card loc-city"
        :class="{ 'loc-city--off': !city.active }"
      >
        <header class="loc-city__head">
          <button type="button" class="loc-city__toggle" @click="toggleCity(city.id)">
            <span class="loc-city__chevron" :class="{ 'loc-city__chevron--open': isOpen(city) }">›</span>
            <strong>{{ city.name }}</strong>
            <span v-if="city.region" class="loc-city__region">{{ city.region }}</span>
            <span class="loc-city__count">{{ communesOf(city).length }}</span>
            <span v-if="!city.active" class="loc-tag loc-tag--off">Désactivée</span>
          </button>

          <div class="loc-city__actions">
            <button class="btn btn-sm btn-outline" @click="openCreate(city)">+ Commune</button>
            <button class="btn btn-sm btn-ghost" @click="toggleActive(city)">
              {{ city.active ? 'Désactiver' : 'Réactiver' }}
            </button>
            <button class="btn btn-sm btn-ghost" @click="openEdit(city)">Modifier</button>
            <button v-if="can('localities.delete')" class="btn btn-sm btn-ghost loc-danger" @click="destroy(city)">
              Supprimer
            </button>
          </div>
        </header>

        <!--
          Abidjan is the exception: the district actually served is wider than
          the official split (Abatta, Faya, Riviera), so its communes come from
          the delivery zones. Editing them here would change nothing for the
          customer, and saying so beats letting someone find out.
        -->
        <p v-if="isAbidjan(city) && isOpen(city)" class="loc-note">
          Les communes proposées pour Abidjan viennent des
          <RouterLink to="/admin/delivery-zones">zones de livraison</RouterLink>,
          qui couvrent aussi les quartiers desservis hors découpage officiel.
        </p>

        <ul v-show="isOpen(city)" class="loc-communes">
          <li v-for="c in communesOf(city)" :key="c.id" class="loc-commune" :class="{ 'loc-commune--off': !c.active }">
            <span class="loc-commune__name">
              {{ c.name }}
              <span v-if="!c.active" class="loc-tag loc-tag--off">Désactivée</span>
            </span>
            <span class="loc-commune__actions">
              <button class="btn btn-sm btn-ghost" @click="toggleActive(c)">
                {{ c.active ? 'Désactiver' : 'Réactiver' }}
              </button>
              <button class="btn btn-sm btn-ghost" @click="openEdit(c)">Modifier</button>
              <button v-if="can('localities.delete')" class="btn btn-sm btn-ghost loc-danger" @click="destroy(c)">
                Supprimer
              </button>
            </span>
          </li>
          <li v-if="!communesOf(city).length" class="loc-commune loc-commune--empty">
            Aucune commune. La ville reste choisissable telle quelle.
          </li>
        </ul>
      </article>
    </div>

    <!--
      Créer ou modifier. Téléportée dans `body` et bâtie sur les classes
      globales (.modal-overlay / .modal) comme les autres modales de l'admin :
      elle utilisait une classe inexistante, donc s'affichait sans voile ni
      centrage, en simple bloc au bas de la page.
    -->
    <Teleport to="body">
      <div v-if="form" class="modal-overlay" @click.self="closeForm">
        <div class="modal modal--sm" role="dialog" aria-modal="true" :aria-label="modalTitle">
          <header class="modal__header">
            <h2>{{ modalTitle }}</h2>
            <button type="button" class="modal__close" aria-label="Fermer" @click="closeForm">✕</button>
          </header>

          <div class="modal__body">
            <p v-if="form.parentName" class="loc-modal__parent">
              Commune de <strong>{{ form.parentName }}</strong>
            </p>

            <div class="field">
              <label class="label">Nom</label>
              <input
                ref="nameInput"
                v-model="form.name"
                type="text"
                class="input"
                :placeholder="form.parent_id ? 'Cocody' : 'Korhogo'"
                @keydown.enter.prevent="submit"
              />
            </div>

            <div v-if="!form.parent_id" class="field">
              <label class="label">Région <span class="loc-optional">(facultatif)</span></label>
              <input
                v-model="form.region"
                type="text"
                class="input"
                placeholder="Poro"
                @keydown.enter.prevent="submit"
              />
            </div>

            <label class="loc-filter">
              <input v-model="form.active" type="checkbox" />
              Proposée aux clientes
            </label>

            <p v-if="formError" class="loc-error loc-modal__error">{{ formError }}</p>
          </div>

          <footer class="modal__footer">
            <button class="btn btn-outline" @click="closeForm">Annuler</button>
            <button class="btn btn-primary" :disabled="saving || !form.name.trim()" @click="submit">
              {{ saving ? 'Enregistrement…' : 'Enregistrer' }}
            </button>
          </footer>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import api from '@/api'
import { useAuthStore } from '@/features/auth/auth.store'

const auth = useAuthStore()
const can = (permission) => auth.can(permission)

const cities        = ref([])
const meta          = ref({ cities: 0, communes: 0 })
const loading       = ref(true)
const error         = ref('')
const search        = ref('')
const hideInactive  = ref(false)
const openCities    = ref(new Set())

const form      = ref(null)
const saving    = ref(false)
const formError = ref('')
const nameInput = ref(null)

const modalTitle = computed(() => {
  if (!form.value) return ''
  if (form.value.id) return 'Modifier la localité'
  return form.value.parent_id ? 'Nouvelle commune' : 'Nouvelle ville'
})

const normalize = (v) =>
  String(v ?? '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim()

/** Cities kept by the search and the "hide disabled" filter. */
const visibleCities = computed(() => {
  const q = normalize(search.value)

  return cities.value.filter((city) => {
    if (hideInactive.value && !city.active) return false
    if (!q) return true
    return normalize(city.name).includes(q)
      || (city.communes ?? []).some((c) => normalize(c.name).includes(q))
  })
})

/** Communes of a city, filtered the same way so a search shows its match. */
function communesOf(city) {
  const q = normalize(search.value)

  return (city.communes ?? []).filter((c) => {
    if (hideInactive.value && !c.active) return false
    if (!q || normalize(city.name).includes(q)) return true
    return normalize(c.name).includes(q)
  })
}

// A search opens the matching cities on its own: leaving them folded would
// show a count with nothing behind it.
function isOpen(city) {
  return openCities.value.has(city.id) || normalize(search.value) !== ''
}

const isAbidjan = (city) => normalize(city.name) === 'abidjan'

function toggleCity(id) {
  const next = new Set(openCities.value)
  next.has(id) ? next.delete(id) : next.add(id)
  openCities.value = next
}

async function load() {
  loading.value = true
  error.value   = ''
  try {
    const { data } = await api.get('/admin/localities')
    cities.value = data.data ?? []
    meta.value   = data.meta ?? { cities: 0, communes: 0 }
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Chargement impossible.'
  } finally {
    loading.value = false
  }
}

async function focusName() {
  await nextTick()
  nameInput.value?.focus()
}

function closeForm() {
  form.value      = null
  formError.value = ''
}

function openCreate(city) {
  formError.value = ''
  form.value = {
    id: null,
    name: '',
    region: '',
    active: true,
    parent_id: city?.id ?? null,
    parentName: city?.name ?? '',
  }
  focusName()
}

function openEdit(locality) {
  formError.value = ''
  form.value = {
    id: locality.id,
    name: locality.name,
    region: locality.region ?? '',
    active: locality.active,
    parent_id: locality.parent_id ?? null,
    parentName: locality.parent_id
      ? cities.value.find((c) => c.id === locality.parent_id)?.name ?? ''
      : '',
  }
  focusName()
}

async function submit() {
  saving.value    = true
  formError.value = ''

  const payload = {
    name: form.value.name.trim(),
    parent_id: form.value.parent_id,
    active: form.value.active,
    // A commune carries no region: it belongs to its city's.
    region: form.value.parent_id ? null : (form.value.region?.trim() || null),
  }

  try {
    form.value.id
      ? await api.patch(`/admin/localities/${form.value.id}`, payload)
      : await api.post('/admin/localities', payload)

    if (form.value.parent_id) openCities.value = new Set(openCities.value).add(form.value.parent_id)
    closeForm()
    await load()
  } catch (e) {
    formError.value = e.response?.data?.message ?? "Enregistrement impossible."
  } finally {
    saving.value = false
  }
}

/** One click to stop offering a locality, without losing it. */
async function toggleActive(locality) {
  try {
    await api.patch(`/admin/localities/${locality.id}`, {
      name: locality.name,
      active: !locality.active,
    })
    await load()
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Modification impossible.'
  }
}

async function destroy(locality) {
  const communes = locality.communes?.length ?? 0
  const warning = communes
    ? `Supprimer « ${locality.name} » et ses ${communes} commune(s) ?`
    : `Supprimer « ${locality.name} » ?`

  if (!confirm(warning)) return

  try {
    await api.delete(`/admin/localities/${locality.id}`)
    await load()
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Suppression impossible.'
  }
}

// Échap ferme la modale : c'est le réflexe, et sans lui il faut viser le voile.
function onKeydown(event) {
  if (event.key === 'Escape' && form.value) closeForm()
}

onMounted(() => {
  load()
  window.addEventListener('keydown', onKeydown)
})

onUnmounted(() => window.removeEventListener('keydown', onKeydown))
</script>

<style scoped>
.loc-toolbar {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  flex-wrap: wrap;
  margin-bottom: var(--space-4);
}
.loc-search { flex: 1; min-width: 220px; max-width: 420px; }
.loc-filter {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8125rem;
  color: var(--gray-600);
  cursor: pointer;
}
.loc-counts { font-size: 0.8125rem; color: var(--gray-400); margin-left: auto; }

.loc-error {
  color: #b91c1c;
  font-size: 0.8125rem;
  margin: 0 0 var(--space-3);
}
.loc-empty { color: var(--gray-400); font-size: 0.875rem; }

.loc-list { display: flex; flex-direction: column; gap: var(--space-2); }

.loc-city { padding: 0; overflow: hidden; }
.loc-city--off { opacity: 0.65; }

.loc-city__head {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-3);
}

.loc-city__toggle {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex: 1;
  min-width: 0;
  border: 0;
  background: none;
  cursor: pointer;
  text-align: left;
  font-size: 0.875rem;
  color: var(--gray-800);
}

.loc-city__chevron {
  display: inline-block;
  transition: transform 0.15s ease;
  color: var(--gray-400);
}
.loc-city__chevron--open { transform: rotate(90deg); }

.loc-city__region { font-size: 0.75rem; color: var(--gray-400); }
.loc-city__count {
  font-size: 0.6875rem;
  padding: 1px 7px;
  border-radius: var(--radius-full, 999px);
  background: var(--cream-100, #f5f0eb);
  color: var(--gray-600);
}

.loc-city__actions { display: flex; gap: 4px; flex-shrink: 0; }

.loc-tag {
  font-size: 0.625rem;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: var(--radius-full, 999px);
}
.loc-tag--off { background: #fee2e2; color: #b91c1c; }

.loc-communes {
  list-style: none;
  margin: 0;
  padding: 0 var(--space-3) var(--space-2) calc(var(--space-3) + 18px);
  border-top: 1px solid var(--cream-200);
}

.loc-commune {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  padding: 4px 0;
  font-size: 0.8125rem;
  color: var(--gray-700);
  border-bottom: 1px solid var(--cream-100, #f5f0eb);
}
.loc-commune:last-child { border-bottom: 0; }
.loc-commune--off { color: var(--gray-400); }
.loc-commune--empty { color: var(--gray-400); font-style: italic; }
.loc-commune__name { display: inline-flex; align-items: center; gap: 6px; }
.loc-commune__actions { display: flex; gap: 2px; flex-shrink: 0; }

.loc-danger { color: #b91c1c; }

.loc-note {
  margin: 0;
  padding: 6px var(--space-3) 6px calc(var(--space-3) + 18px);
  font-size: 0.75rem;
  color: var(--gray-500);
  background: var(--cream-50);
  border-top: 1px solid var(--cream-200);
}

.loc-modal__parent { font-size: 0.8125rem; color: var(--gray-500); margin: 0; }
.loc-modal__error { margin: 0; }
.loc-optional { font-weight: 400; color: var(--gray-400); font-size: 0.75rem; }
</style>
