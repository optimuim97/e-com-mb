<template>
  <div class="purchase-orders">

    <div class="page-header">
      <div>
        <h1 class="page-header__title">Bons d'achat</h1>
        <p class="page-header__sub">
          Ce qu'on commande, ce qu'on paie. La réception fait entrer le stock,
          fige le prix de revient et met à jour le coût moyen.
        </p>
      </div>
      <button class="btn btn-primary" @click="ouvrirFormulaire()">Nouveau bon d'achat</button>
    </div>

    <div class="filters-bar">
      <input
        v-model="filtres.search"
        type="search"
        class="input input--sm"
        placeholder="Numéro ou fournisseur…"
        @keyup.enter="charger(1)"
      />
      <select v-model="filtres.status" class="input input--sm" @change="charger(1)">
        <option value="">Tous les états</option>
        <option value="draft">En préparation</option>
        <option value="received">Reçus</option>
        <option value="cancelled">Annulés</option>
      </select>
    </div>

    <div class="card table-scroll">
      <div v-if="loading" class="empty-state">Chargement…</div>
      <!--
        Un échec de chargement ne doit pas se lire « aucun bon d'achat » : le
        module peut simplement être éteint, et l'écran affirmait alors le
        contraire de la vérité.
      -->
      <div v-else-if="chargementErreur" class="empty-state">
        <p>{{ chargementErreur }}</p>
        <RouterLink v-if="moduleEteint" class="btn btn-outline btn-sm" :to="{ name: 'admin.settings' }">
          Ouvrir les paramètres
        </RouterLink>
      </div>
      <div v-else-if="bons.length === 0" class="empty-state">Aucun bon d'achat.</div>
      <table v-else class="admin-table">
        <thead>
          <tr>
            <th>Numéro</th>
            <th>Fournisseur</th>
            <th>Commandé le</th>
            <th class="text-right">Total</th>
            <th>État</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="bon in bons" :key="bon.id">
            <td class="font-medium">{{ bon.number }}</td>
            <td class="text-muted">{{ bon.supplier?.name ?? '—' }}</td>
            <td class="text-muted text-sm">{{ formatDate(bon.ordered_at) }}</td>
            <td class="text-right font-semibold">{{ fmt(bon.total) }}</td>
            <td><span class="badge" :class="badgeEtat(bon.status)">{{ bon.status_label }}</span></td>
            <td class="text-right">
              <button class="btn btn-ghost btn-sm" @click="ouvrirDetail(bon)">Détail</button>
              <button v-if="bon.editable" class="btn btn-ghost btn-sm" @click="ouvrirFormulaire(bon)">Modifier</button>
              <button v-if="bon.editable" class="btn btn-primary btn-sm" @click="receptionner(bon)">Réceptionner</button>
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
      item-singular="bon d'achat"
      item-plural="bons d'achat"
      @update:page="charger"
    />

    <!-- ── Saisie / modification ───────────────────────────────────────────
      Classes globales d'app.css : `.modal-overlay` porte le fond plein écran,
      `.modal` le panneau. L'inverse était écrit ici, si bien que le fond
      héritait du `max-width: 540px` du panneau et ne couvrait plus l'écran.
    -->
    <Teleport to="body">
      <div v-if="formulaireOuvert" class="modal-overlay" @click.self="fermerFormulaire">
        <div class="modal modal--lg">
          <header class="modal__header">
            <h2>{{ form.id ? `Modifier ${form.number}` : "Nouveau bon d'achat" }}</h2>
            <button class="modal__close" type="button" aria-label="Fermer" @click="fermerFormulaire">✕</button>
          </header>

          <div class="modal__body">

        <div class="modal__grid">
          <div class="field">
            <label class="label">Fournisseur</label>
            <select v-model.number="form.supplier_id" class="input">
              <option :value="null">—</option>
              <option v-for="f in fournisseurs" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </div>
          <div class="field">
            <label class="label">Date de commande</label>
            <input v-model="form.ordered_at" type="date" class="input" />
          </div>
        </div>

        <!-- Lignes -->
        <h3 class="section-title">Articles</h3>
        <div class="field">
          <input
            v-model="rechercheProduit"
            type="search"
            class="input"
            placeholder="Ajouter un produit…"
            @input="chercherProduits"
          />
          <ul v-if="suggestions.length" class="suggestions">
            <li v-for="p in suggestions" :key="p.id" @click="ajouterLigne(p)">
              <span>{{ p.name }}</span>
              <span class="text-muted text-sm">stock : {{ p.stock }}</span>
            </li>
          </ul>
        </div>

        <table v-if="form.items.length" class="lignes">
          <thead>
            <tr>
              <th>Article</th>
              <th class="text-right">Qté</th>
              <th class="text-right">Coût unitaire</th>
              <th class="text-right">Total</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(ligne, i) in form.items" :key="i">
              <td>{{ ligne.product_name }}</td>
              <td class="text-right">
                <input v-model.number="ligne.quantity" type="number" min="1" class="input input--mini" />
              </td>
              <td class="text-right">
                <input v-model.number="ligne.unit_cost" type="number" min="0" step="1" class="input input--mini" />
              </td>
              <td class="text-right font-semibold">{{ fmt(ligne.quantity * ligne.unit_cost) }}</td>
              <td class="text-right">
                <button class="lien-retirer" @click="form.items.splice(i, 1)">retirer</button>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-else class="vide">Aucun article. Cherchez un produit ci-dessus pour l'ajouter.</p>

        <!-- Frais annexes -->
        <h3 class="section-title">Frais annexes</h3>
        <p class="aide">
          Transport, douane, manutention. Ils sont répartis sur les lignes au
          prorata de leur valeur, et entrent dans le prix de revient.
        </p>
        <div class="modal__grid">
          <div class="field">
            <label class="label">Transport</label>
            <input v-model.number="form.shipping_cost" type="number" min="0" class="input" />
          </div>
          <div class="field">
            <label class="label">Autres frais</label>
            <input v-model.number="form.other_costs" type="number" min="0" class="input" />
          </div>
        </div>

        <div class="totaux">
          <div><span>Articles</span><strong>{{ fmt(totalLignes) }}</strong></div>
          <div><span>Frais annexes</span><strong>{{ fmt(totalFrais) }}</strong></div>
          <div class="totaux__final"><span>Total</span><strong>{{ fmt(totalLignes + totalFrais) }}</strong></div>
        </div>

        <div class="field">
          <label class="label">Notes</label>
          <textarea v-model="form.notes" class="input" rows="2" maxlength="2000"></textarea>
        </div>

        <p v-if="erreur" class="erreur">{{ erreur }}</p>

          </div>

          <footer class="modal__footer">
            <button class="btn btn-ghost" @click="fermerFormulaire">Annuler</button>
            <button class="btn btn-primary" :disabled="!form.items.length || envoi" @click="enregistrer">
              {{ envoi ? 'Enregistrement…' : 'Enregistrer' }}
            </button>
          </footer>
        </div>
      </div>
    </Teleport>

    <!-- ── Détail ────────────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="detail" class="modal-overlay" @click.self="detail = null">
        <div class="modal modal--lg">
          <header class="modal__header">
            <h2>{{ detail.number }}</h2>
            <button class="modal__close" type="button" aria-label="Fermer" @click="detail = null">✕</button>
          </header>

          <div class="modal__body">
        <p class="detail-meta">
          {{ detail.supplier?.name ?? 'Sans fournisseur' }} ·
          <span class="badge" :class="badgeEtat(detail.status)">{{ detail.status_label }}</span>
          <span v-if="detail.received_at"> · reçu le {{ formatDate(detail.received_at) }}</span>
        </p>

        <table class="lignes">
          <thead>
            <tr>
              <th>Article</th>
              <th class="text-right">Qté</th>
              <th class="text-right">Coût d'achat</th>
              <th class="text-right">Prix de revient</th>
              <th class="text-right">CMUP produit</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="l in detail.items" :key="l.id">
              <td>
                {{ l.product?.name ?? '—' }}
                <span v-if="l.variant?.label" class="text-muted text-sm"> · {{ l.variant.label }}</span>
              </td>
              <td class="text-right">{{ l.quantity }}</td>
              <td class="text-right">{{ fmt(l.unit_cost) }}</td>
              <td class="text-right font-semibold">
                {{ l.landed_unit_cost ? fmt(l.landed_unit_cost) : '—' }}
              </td>
              <td class="text-right text-muted">
                {{ l.product?.average_cost ? fmt(l.product.average_cost) : '—' }}
              </td>
            </tr>
          </tbody>
        </table>

        <div class="totaux">
          <div><span>Articles</span><strong>{{ fmt(detail.items_total) }}</strong></div>
          <div><span>Transport</span><strong>{{ fmt(detail.shipping_cost) }}</strong></div>
          <div><span>Autres frais</span><strong>{{ fmt(detail.other_costs) }}</strong></div>
          <div class="totaux__final"><span>Total</span><strong>{{ fmt(detail.total) }}</strong></div>
        </div>

        <p v-if="detail.notes" class="detail-notes">{{ detail.notes }}</p>

          </div>

          <footer class="modal__footer">
            <button class="btn btn-ghost" @click="detail = null">Fermer</button>
            <button v-if="detail.editable" class="btn btn-primary" @click="receptionner(detail)">Réceptionner</button>
          </footer>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { usePersistedRef } from '@/admin/utils/persistedFilters'
import { RouterLink } from 'vue-router'
import api from '@/api'
import AdminPagination from '@/admin/components/AdminPagination.vue'
import { useCurrencyStore } from '@/stores/currency'

const bons    = ref([])
const meta    = ref(null)
const loading = ref(false)
const page    = ref(1)
// Conservés d'une visite à l'autre, comme sur les autres listes.
const filtres = usePersistedRef('purchase-orders', { search: '', status: '' })

/** Pourquoi la liste est vide, quand elle l'est pour une mauvaise raison. */
const chargementErreur = ref('')
const moduleEteint     = ref(false)

const fournisseurs = ref([])

function fmt(valeur) {
  return useCurrencyStore().format(Number(valeur ?? 0))
}

function formatDate(valeur) {
  if (!valeur) return '—'
  return new Date(valeur).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function badgeEtat(statut) {
  return {
    draft:     'badge-warning',
    received:  'badge-success',
    cancelled: 'badge-gray',
  }[statut] ?? 'badge-gray'
}

async function charger(p = 1) {
  page.value    = p
  loading.value = true
  chargementErreur.value = ''
  moduleEteint.value     = false
  try {
    const params = { page: p }
    if (filtres.value.search) params.search = filtres.value.search
    if (filtres.value.status) params.status = filtres.value.status
    const { data } = await api.get('/admin/purchase-orders', { params })
    bons.value = data.data
    meta.value = data.meta
  } catch (e) {
    bons.value = []
    meta.value = null
    // Le module Achats est éteint par défaut : c'est la cause la plus
    // fréquente, et celle qu'on peut corriger soi-même.
    moduleEteint.value     = e.response?.status === 403
    chargementErreur.value = e.response?.data?.message
      ?? "Les bons d'achat n'ont pas pu être chargés."
  } finally {
    loading.value = false
  }
}

async function chargerFournisseurs() {
  try {
    const { data } = await api.get('/admin/suppliers', { params: { active_only: 1, per_page: 100 } })
    fournisseurs.value = data.data ?? []
  } catch { /* la saisie reste possible sans fournisseur */ }
}

// ── Saisie ───────────────────────────────────────────────────────────────────

const formulaireOuvert = ref(false)
const envoi            = ref(false)
const erreur           = ref('')
const rechercheProduit = ref('')
const suggestions      = ref([])

const VIDE = {
  id: null, number: '', supplier_id: null, ordered_at: '',
  shipping_cost: 0, other_costs: 0, notes: '', items: [],
}
const form = ref({ ...VIDE, items: [] })

const totalLignes = computed(() =>
  form.value.items.reduce((s, l) => s + (Number(l.quantity) || 0) * (Number(l.unit_cost) || 0), 0)
)
const totalFrais = computed(() =>
  (Number(form.value.shipping_cost) || 0) + (Number(form.value.other_costs) || 0)
)

async function ouvrirFormulaire(bon = null) {
  erreur.value           = ''
  rechercheProduit.value = ''
  suggestions.value      = []

  if (!bon) {
    form.value = { ...VIDE, items: [], ordered_at: new Date().toISOString().slice(0, 10) }
    formulaireOuvert.value = true
    return
  }

  // La liste ne porte pas les lignes : on recharge le bon complet.
  const { data } = await api.get(`/admin/purchase-orders/${bon.id}`)
  const complet = data.data
  form.value = {
    id: complet.id,
    number: complet.number,
    supplier_id: complet.supplier?.id ?? null,
    ordered_at: complet.ordered_at ? String(complet.ordered_at).slice(0, 10) : '',
    shipping_cost: Number(complet.shipping_cost) || 0,
    other_costs: Number(complet.other_costs) || 0,
    notes: complet.notes ?? '',
    items: (complet.items ?? []).map(l => ({
      product_id: l.product_id,
      variant_id: l.variant_id,
      product_name: l.product?.name ?? 'Article',
      quantity: l.quantity,
      unit_cost: Number(l.unit_cost) || 0,
    })),
  }
  formulaireOuvert.value = true
}

function fermerFormulaire() {
  formulaireOuvert.value = false
}

let minuteur = null
function chercherProduits() {
  clearTimeout(minuteur)
  const terme = rechercheProduit.value.trim()
  if (terme.length < 2) {
    suggestions.value = []
    return
  }
  minuteur = setTimeout(async () => {
    try {
      const { data } = await api.get('/admin/products', { params: { search: terme, per_page: 8 } })
      suggestions.value = data.data ?? []
    } catch {
      suggestions.value = []
    }
  }, 250)
}

function ajouterLigne(produit) {
  // Le coût d'achat connu sert de point de départ : on ne resaisit que ce qui
  // a changé chez le fournisseur.
  form.value.items.push({
    product_id: produit.id,
    variant_id: null,
    product_name: produit.name,
    quantity: 1,
    unit_cost: Number(produit.cost_price) || 0,
  })
  rechercheProduit.value = ''
  suggestions.value      = []
}

async function enregistrer() {
  envoi.value  = true
  erreur.value = ''
  try {
    const charge = {
      supplier_id:   form.value.supplier_id,
      shipping_cost: form.value.shipping_cost || 0,
      other_costs:   form.value.other_costs   || 0,
      ordered_at:    form.value.ordered_at    || null,
      notes:         form.value.notes         || null,
      items: form.value.items.map(l => ({
        product_id: l.product_id,
        variant_id: l.variant_id,
        quantity:   l.quantity,
        unit_cost:  l.unit_cost,
      })),
    }
    if (form.value.id) await api.put(`/admin/purchase-orders/${form.value.id}`, charge)
    else               await api.post('/admin/purchase-orders', charge)

    fermerFormulaire()
    charger(page.value)
  } catch (e) {
    erreur.value = e.response?.data?.message ?? "Le bon d'achat n'a pas pu être enregistré."
  } finally {
    envoi.value = false
  }
}

// ── Détail et réception ──────────────────────────────────────────────────────

const detail = ref(null)

async function ouvrirDetail(bon) {
  const { data } = await api.get(`/admin/purchase-orders/${bon.id}`)
  detail.value = data.data
}

async function receptionner(bon) {
  const message = "Réceptionner ce bon ? Le stock entre, les coûts se figent — c'est définitif."
  if (!window.confirm(message)) return

  try {
    await api.post(`/admin/purchase-orders/${bon.id}/receive`)
    detail.value = null
    fermerFormulaire()
    charger(page.value)
  } catch (e) {
    window.alert(e.response?.data?.message ?? 'La réception a échoué.')
  }
}

onMounted(() => {
  charger(1)
  chargerFournisseurs()
})
</script>

<style scoped>
/* Espacement vertical de l'écran, comme les autres pages de l'admin. */
.purchase-orders { display: flex; flex-direction: column; gap: var(--space-4); }
.purchase-orders > .page-header { margin-bottom: 0; }
.purchase-orders > .filters-bar { margin-bottom: 0; }
.purchase-orders > .stats-grid  { margin-bottom: 0; }
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
  margin-bottom: 16px;
}

.text-right { text-align: right; }

.section-title {
  font-size: 14px;
  font-weight: 700;
  margin: 22px 0 10px;
}

.aide {
  font-size: 12px;
  color: #8a7f78;
  margin-bottom: 10px;
  line-height: 1.5;
}

.suggestions {
  list-style: none;
  margin: 6px 0 0;
  padding: 0;
  border: 1px solid #f0e8e3;
  border-radius: 10px;
  max-height: 200px;
  overflow-y: auto;
}

.suggestions li {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 9px 12px;
  cursor: pointer;
  font-size: 14px;
}

.suggestions li:hover { background: #f9f4f1; }

.lignes {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  margin-bottom: 8px;
}

.lignes th {
  text-align: left;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: .04em;
  color: #a89a92;
  padding: 6px 8px;
  border-bottom: 1px solid #f0e8e3;
}

.lignes th.text-right { text-align: right; }

.lignes td {
  padding: 7px 8px;
  border-bottom: 1px solid #faf6f4;
}

.input--mini {
  width: 78px;
  text-align: right;
  padding: 4px 8px;
  font-size: 13px;
}

.lien-retirer {
  border: none;
  background: none;
  color: #b91c1c;
  font-size: 12px;
  cursor: pointer;
  text-decoration: underline;
}

.vide {
  font-size: 13px;
  color: #a89a92;
  padding: 10px 0;
}

.totaux {
  margin: 14px 0 4px;
  padding: 12px 14px;
  background: #f9f4f1;
  border-radius: 12px;
  font-size: 13px;
}

.totaux > div {
  display: flex;
  justify-content: space-between;
  padding: 3px 0;
}

.totaux__final {
  border-top: 1px solid #ede3dd;
  margin-top: 6px;
  padding-top: 8px !important;
  font-size: 15px;
}

.detail-meta {
  font-size: 13px;
  color: #6b5f58;
  margin-bottom: 18px;
}

.detail-notes {
  margin-top: 14px;
  font-size: 13px;
  color: #6b5f58;
  font-style: italic;
}

/* Les styles de modale viennent d'app.css — voir le commentaire du gabarit. */

.erreur {
  margin-top: 14px;
  padding: 10px 12px;
  border-radius: 10px;
  background: #fee2e2;
  color: #b91c1c;
  font-size: 13px;
}
</style>
