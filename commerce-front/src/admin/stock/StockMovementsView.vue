<template>
  <div class="stock-movements">

    <!-- En-tête -->
    <div class="page-header">
      <div>
        <h1 class="page-header__title">Mouvements de stock</h1>
        <p class="page-header__sub">
          Toutes les entrées et sorties, avec leur origine. Un écart de comptage
          se retrouve ici.
        </p>
      </div>
      <button v-if="canAdjust" class="btn btn-primary" @click="ouvrirCorrection">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4z"/></svg>
        Corriger un stock
      </button>
    </div>

    <!-- Totaux de la période affichée -->
    <div class="stats-grid" v-if="summary">
      <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--green">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
        </div>
        <div class="stat-card__body">
          <p class="stat-card__value">+{{ summary.entrees }}</p>
          <p class="stat-card__label">Entrées</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--rose">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
        </div>
        <div class="stat-card__body">
          <p class="stat-card__value">−{{ summary.sorties }}</p>
          <p class="stat-card__label">Sorties</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--blue">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
        </div>
        <div class="stat-card__body">
          <p class="stat-card__value" :class="summary.net < 0 ? 'text-rose' : ''">
            {{ summary.net > 0 ? '+' : '' }}{{ summary.net }}
          </p>
          <p class="stat-card__label">Variation nette</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card__icon stat-card__icon--purple">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <div class="stat-card__body">
          <p class="stat-card__value">{{ summary.mouvements }}</p>
          <p class="stat-card__label">Mouvements</p>
        </div>
      </div>
    </div>

    <!-- Filtres -->
    <div class="filters-bar">
      <input
        v-model="filtres.search"
        type="search"
        class="input input--sm"
        placeholder="Produit ou référence…"
        @keyup.enter="charger(1)"
      />
      <select v-model="filtres.type" class="input input--sm" @change="charger(1)">
        <option value="">Tous les motifs</option>
        <option v-for="(libelle, cle) in TYPES" :key="cle" :value="cle">{{ libelle }}</option>
      </select>
      <select v-model="filtres.direction" class="input input--sm" @change="charger(1)">
        <option value="">Entrées et sorties</option>
        <option value="in">Entrées seules</option>
        <option value="out">Sorties seules</option>
      </select>
      <input v-model="filtres.from" type="date" class="input input--sm" @change="charger(1)" />
      <input v-model="filtres.to"   type="date" class="input input--sm" @change="charger(1)" />
      <button class="btn btn-ghost btn-sm" @click="reinitialiser">Réinitialiser</button>
    </div>

    <!-- Tableau -->
    <div class="card table-scroll">
      <div v-if="loading" class="empty-state">Chargement…</div>
      <!--
        Un échec de chargement ne doit pas se lire comme une liste vide : la
        cause est ailleurs, et l'écran affirmait le contraire de la vérité.
      -->
      <div v-else-if="chargementErreur" class="empty-state">
        <p>{{ chargementErreur }}</p>
      </div>
      <div v-else-if="mouvements.length === 0" class="empty-state">
        Aucun mouvement pour ces critères.
      </div>
      <table v-else class="admin-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Article</th>
            <th>Motif</th>
            <th class="text-right">Quantité</th>
            <th class="text-right">Stock</th>
            <th>Origine</th>
            <th>Par</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="m in mouvements" :key="m.id">
            <td class="text-muted text-sm">{{ formatDate(m.created_at) }}</td>
            <td>
              <span class="font-medium">{{ m.product?.name ?? '—' }}</span>
              <span v-if="m.variant?.label" class="text-muted text-sm"> · {{ m.variant.label }}</span>
              <span v-if="m.product?.sku" class="stock-sku">{{ m.product.sku }}</span>
            </td>
            <td>
              <span class="badge" :class="badgeMotif(m.type)">{{ m.type_label }}</span>
            </td>
            <td class="text-right font-semibold" :class="m.quantity < 0 ? 'text-rose' : 'text-green'">
              {{ m.quantity > 0 ? '+' : '' }}{{ m.quantity }}
            </td>
            <td class="text-right text-muted text-sm">
              {{ m.stock_before }} → <strong>{{ m.stock_after }}</strong>
            </td>
            <td class="text-sm">
              <RouterLink
                v-if="m.reference?.type === 'order'"
                :to="{ name: 'admin.order', params: { id: m.reference.id } }"
                class="stock-link"
              >
                {{ m.reference.label }}
              </RouterLink>
              <span v-else-if="m.reason" class="text-muted">{{ m.reason }}</span>
              <span v-else class="text-muted">—</span>
            </td>
            <td class="text-muted text-sm">{{ m.user?.name ?? 'Boutique' }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <AdminPagination
      :current-page="page"
      :last-page="meta?.last_page"
      :total="meta?.total"
      :show-per-page="false"
      item-singular="mouvement"
      item-plural="mouvements"
      @update:page="charger"
    />

    <!-- Correction après comptage -->
    <div v-if="correctionOuverte" class="stock-modal" @click.self="fermerCorrection">
      <div class="stock-modal__panel">
        <h2 class="stock-modal__title">Corriger un stock</h2>
        <p class="stock-modal__intro">
          Saisissez la quantité réellement comptée. L'écart est enregistré dans
          l'historique, avec votre nom.
        </p>

        <div class="field">
          <label class="label">Produit *</label>
          <input
            v-model="rechercheProduit"
            type="search"
            class="input"
            placeholder="Tapez un nom de produit…"
            @input="chercherProduits"
          />
          <ul v-if="suggestions.length" class="stock-suggestions">
            <li v-for="p in suggestions" :key="p.id" @click="choisirProduit(p)">
              <span>{{ p.name }}</span>
              <span class="text-muted text-sm">stock : {{ p.stock }}</span>
            </li>
          </ul>
          <p v-if="correction.product_id && !produitChoisi?.has_variants" class="stock-choisi">
            {{ produitChoisi?.name }} — stock actuel : <strong>{{ produitChoisi?.stock }}</strong>
          </p>
        </div>

        <!--
          Un produit à déclinaisons ne porte pas de compteur propre : son
          « stock » affiché est la somme de ses déclinaisons. Corriger ce total
          n'aurait aucun sens — on corrige la déclinaison qu'on vient de compter.
        -->
        <div v-if="produitChoisi?.has_variants" class="field">
          <label class="label">Déclinaison *</label>
          <select v-model.number="correction.variant_id" class="input" @change="surChoixDeclinaison">
            <option :value="null">Choisir…</option>
            <option v-for="v in declinaisons" :key="v.id" :value="v.id">
              {{ v.label || v.sku }} — stock : {{ v.stock }}
            </option>
          </select>
          <p v-if="chargementDeclinaisons" class="text-muted text-sm">Chargement des déclinaisons…</p>
        </div>

        <div class="field">
          <label class="label">Quantité comptée *</label>
          <input v-model.number="correction.counted" type="number" min="0" class="input" />
        </div>

        <div class="field">
          <label class="label">Motif *</label>
          <select v-model="correction.type" class="input">
            <option value="adjustment">Inventaire</option>
            <option value="loss">Perte / casse</option>
            <option value="return">Retour client</option>
          </select>
        </div>

        <div class="field">
          <label class="label">Explication *</label>
          <input
            v-model="correction.reason"
            type="text"
            class="input"
            maxlength="255"
            placeholder="Comptage du samedi, pot cassé en rayon…"
          />
        </div>

        <p v-if="erreurCorrection" class="stock-erreur">{{ erreurCorrection }}</p>

        <div class="stock-modal__actions">
          <button class="btn btn-ghost" @click="fermerCorrection">Annuler</button>
          <button class="btn btn-primary" :disabled="!correctionValide || envoiEnCours" @click="envoyerCorrection">
            {{ envoiEnCours ? 'Enregistrement…' : 'Enregistrer' }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { usePersistedRef } from '@/admin/utils/persistedFilters'
import { RouterLink } from 'vue-router'
import api from '@/api'
import AdminPagination from '@/admin/components/AdminPagination.vue'
import { useAuthStore } from '@/features/auth/auth.store'

const auth      = useAuthStore()
const canAdjust = computed(() => auth.can('stock.adjust'))

// Libellés alignés sur StockMovement::LABELS côté serveur.
const TYPES = {
  sale:         'Vente',
  cancellation: 'Annulation',
  purchase:     'Entrée achat',
  adjustment:   'Inventaire',
  return:       'Retour client',
  import:       'Import',
  loss:         'Perte',
}

const mouvements = ref([])
const summary    = ref(null)
const meta       = ref(null)
const loading    = ref(false)
const page       = ref(1)

/** Pourquoi la liste est vide, quand elle l'est pour une mauvaise raison. */
const chargementErreur = ref('')

// Conservés d'une visite à l'autre, comme sur les autres listes.
const filtres = usePersistedRef('stock-movements', { search: '', type: '', direction: '', from: '', to: '' })

function parametres() {
  const p = {}
  Object.entries(filtres.value).forEach(([cle, valeur]) => {
    if (valeur) p[cle] = valeur
  })
  return p
}

async function charger(p = 1) {
  page.value    = p
  loading.value = true
  chargementErreur.value = ''
  try {
    const { data } = await api.get('/admin/stock-movements', { params: { ...parametres(), page: p } })
    mouvements.value = data.data
    meta.value       = data.meta
  } catch (e) {
    mouvements.value = []
    meta.value = null
    chargementErreur.value = e.response?.data?.message ?? "Les mouvements de stock n'ont pas pu être chargés."
  } finally {
    loading.value = false
  }
  chargerTotaux()
}

async function chargerTotaux() {
  try {
    const { data } = await api.get('/admin/stock-movements/summary', { params: parametres() })
    summary.value = data
  } catch { /* les totaux sont un confort, pas un bloquant */ }
}

function reinitialiser() {
  filtres.value = { search: '', type: '', direction: '', from: '', to: '' }
  charger(1)
}

function badgeMotif(type) {
  return {
    sale:         'badge-rose',
    cancellation: 'badge-warning',
    purchase:     'badge-success',
    adjustment:   'badge-blue',
    return:       'badge-blue',
    import:       'badge-gray',
    loss:         'badge-danger',
  }[type] ?? 'badge-gray'
}

function formatDate(valeur) {
  if (!valeur) return '—'
  return new Date(valeur).toLocaleString('fr-FR', {
    day: '2-digit', month: '2-digit', year: '2-digit',
    hour: '2-digit', minute: '2-digit',
  })
}

// ── Correction après comptage ────────────────────────────────────────────────

const correctionOuverte = ref(false)
const rechercheProduit  = ref('')
const suggestions       = ref([])
const produitChoisi     = ref(null)
const envoiEnCours      = ref(false)
const erreurCorrection  = ref('')

const declinaisons            = ref([])
const chargementDeclinaisons  = ref(false)

const correction = ref({ product_id: null, variant_id: null, counted: 0, type: 'adjustment', reason: '' })

const correctionValide = computed(() =>
  !!correction.value.product_id
  && (!produitChoisi.value?.has_variants || !!correction.value.variant_id)
  && Number.isInteger(correction.value.counted)
  && correction.value.counted >= 0
  && correction.value.reason.trim().length > 0
)

function ouvrirCorrection() {
  correctionOuverte.value = true
  erreurCorrection.value  = ''
  rechercheProduit.value  = ''
  suggestions.value       = []
  produitChoisi.value     = null
  declinaisons.value = []
  correction.value = { product_id: null, variant_id: null, counted: 0, type: 'adjustment', reason: '' }
}

function fermerCorrection() {
  correctionOuverte.value = false
}

let minuteurRecherche = null
function chercherProduits() {
  clearTimeout(minuteurRecherche)
  const terme = rechercheProduit.value.trim()
  if (terme.length < 2) {
    suggestions.value = []
    return
  }
  minuteurRecherche = setTimeout(async () => {
    try {
      const { data } = await api.get('/admin/products', { params: { search: terme, per_page: 8 } })
      suggestions.value = data.data ?? []
    } catch {
      suggestions.value = []
    }
  }, 250)
}

async function choisirProduit(p) {
  produitChoisi.value          = p
  correction.value.product_id  = p.id
  correction.value.variant_id  = null
  correction.value.counted     = p.has_variants ? 0 : (p.stock ?? 0)
  rechercheProduit.value       = p.name
  suggestions.value            = []
  declinaisons.value           = []

  if (!p.has_variants) return

  chargementDeclinaisons.value = true
  try {
    const { data } = await api.get(`/admin/products/${p.id}`)
    declinaisons.value = data.data?.variants ?? []
  } catch {
    erreurCorrection.value = "Impossible de charger les déclinaisons de ce produit."
  } finally {
    chargementDeclinaisons.value = false
  }
}

// Le comptage part de la valeur actuelle : on ne saisit que ce qui diffère.
function surChoixDeclinaison() {
  const choisie = declinaisons.value.find(v => v.id === correction.value.variant_id)
  correction.value.counted = choisie?.stock ?? 0
}

async function envoyerCorrection() {
  envoiEnCours.value     = true
  erreurCorrection.value = ''
  try {
    await api.post('/admin/stock-movements/adjust', correction.value)
    fermerCorrection()
    charger(1)
  } catch (e) {
    erreurCorrection.value = e.response?.data?.message ?? "La correction n'a pas pu être enregistrée."
  } finally {
    envoiEnCours.value = false
  }
}

onMounted(() => charger(1))
</script>

<style scoped>
/* Espacement vertical de l'écran, comme les autres pages de l'admin. */
.stock-movements { display: flex; flex-direction: column; gap: var(--space-4); }
.stock-movements > .page-header { margin-bottom: 0; }
.stock-movements > .filters-bar { margin-bottom: 0; }
.stock-movements > .stats-grid  { margin-bottom: 0; }
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.filters-bar {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  align-items: center;
  margin-bottom: 16px;
}

.stock-sku {
  display: block;
  font-size: 11px;
  color: #a89a92;
  letter-spacing: .04em;
}

.stock-link {
  color: #b5876a;
  font-weight: 600;
  text-decoration: none;
}
.stock-link:hover { text-decoration: underline; }

.text-green { color: #15803d; }
.text-rose  { color: #b91c1c; }
.text-right { text-align: right; }

/* ── Correction ── */
.stock-modal {
  position: fixed;
  inset: 0;
  background: rgba(30, 20, 16, .45);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  z-index: 60;
}

.stock-modal__panel {
  background: #fff;
  border-radius: 16px;
  padding: 28px;
  width: 100%;
  max-width: 440px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 50px rgba(0, 0, 0, .2);
}

.stock-modal__title {
  font-size: 19px;
  font-weight: 700;
  margin-bottom: 6px;
}

.stock-modal__intro {
  font-size: 13px;
  color: #8a7f78;
  margin-bottom: 20px;
}

.stock-modal__actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 24px;
}

.stock-suggestions {
  list-style: none;
  margin: 6px 0 0;
  padding: 0;
  border: 1px solid #f0e8e3;
  border-radius: 10px;
  max-height: 200px;
  overflow-y: auto;
}

.stock-suggestions li {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 9px 12px;
  cursor: pointer;
  font-size: 14px;
}

.stock-suggestions li:hover { background: #f9f4f1; }

.stock-choisi {
  margin-top: 8px;
  font-size: 13px;
  color: #6b5f58;
}

.stock-erreur {
  margin-top: 14px;
  padding: 10px 12px;
  border-radius: 10px;
  background: #fee2e2;
  color: #b91c1c;
  font-size: 13px;
}
</style>
