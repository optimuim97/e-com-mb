<template>
  <div class="admin-page">
    <header class="page-header">
      <div>
        <span class="eyebrow">Équipe</span>
        <h1 class="page-header__title">Journal des actions</h1>
        <p class="page-header__sub">
          Qui a modifié quoi, et ce que valait le champ avant. Consultation
          seule : un journal qu'on peut corriger ne prouve rien.
        </p>
      </div>
      <span v-if="pagination.total" class="badge badge-gray">
        {{ pagination.total.toLocaleString('fr-FR') }} entrée(s)
      </span>
    </header>

    <div class="card filters-bar">
      <select v-model="filtres.operator_id" class="input journal__filtre" @change="rechercher">
        <option :value="''">Tout le monde</option>
        <option v-for="o in operators" :key="o.id" :value="String(o.id)">{{ o.name }}</option>
      </select>

      <select v-model="filtres.subject_type" class="input journal__filtre" @change="rechercher">
        <option :value="''">Tous les objets</option>
        <option v-for="t in subjectTypes" :key="t" :value="t">{{ OBJETS[t] ?? t }}</option>
      </select>

      <select v-model="filtres.event" class="input journal__filtre" @change="rechercher">
        <option :value="''">Toutes les actions</option>
        <option value="created">Créations</option>
        <option value="updated">Modifications</option>
        <option value="deleted">Suppressions</option>
      </select>

      <input v-model="filtres.date_from" type="date" class="input journal__date" title="À partir du" @change="rechercher" />
      <input v-model="filtres.date_to"   type="date" class="input journal__date" title="Jusqu'au"    @change="rechercher" />

      <button v-if="filtree" type="button" class="btn btn-sm btn-outline" @click="reinitialiser">
        Tout afficher
      </button>
    </div>

    <div class="card">
      <div v-if="loading" class="loader-wrap"><div class="loader"></div></div>

      <div v-else-if="!entrees.length" class="empty-state">
        <p v-if="filtree">Aucune action ne correspond à ces critères.</p>
        <p v-else>Le journal est vide.</p>
      </div>

      <div v-else class="table-scroll">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Quand</th>
              <th>Qui</th>
              <th>Action</th>
              <th>Objet</th>
              <th>Ce qui a changé</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="e in entrees" :key="e.id">
              <td class="journal__quand">
                <div>{{ formatDate(e.created_at) }}</div>
                <span class="journal__heure">{{ formatHeure(e.created_at) }}</span>
              </td>

              <td>
                <span v-if="e.operator">{{ e.operator.name }}</span>
                <!--
                  Une écriture sans opérateur vient d'une tâche planifiée ou de
                  la boutique elle-même : l'attribuer à personne serait faux.
                -->
                <span v-else class="journal__systeme">Automatique</span>
              </td>

              <td><span :class="badgeAction(e.event)">{{ ACTIONS[e.event] ?? e.event }}</span></td>

              <td>
                <RouterLink v-if="lienObjet(e.subject)" :to="lienObjet(e.subject)" class="journal__lien">
                  {{ OBJETS[e.subject.type] ?? e.subject.type }} #{{ e.subject.id }}
                </RouterLink>
                <span v-else>{{ OBJETS[e.subject.type] ?? e.subject.type }} #{{ e.subject.id }}</span>
              </td>

              <td class="journal__changes">
                <span v-if="!e.changes.length" class="journal__vide">—</span>

                <ul v-else class="journal__liste">
                  <li v-for="c in visibles(e)" :key="c.field">
                    <span class="journal__champ">{{ CHAMPS[c.field] ?? c.field }}</span>
                    <!--
                      Une création n'a pas d'« avant » : afficher « — → valeur »
                      sur chaque champ noierait l'information utile.
                    -->
                    <template v-if="e.event !== 'created'">
                      <span class="journal__avant">{{ valeur(c.from) }}</span>
                      <span class="journal__fleche">→</span>
                    </template>
                    <span class="journal__apres">{{ valeur(c.to) }}</span>
                  </li>
                </ul>

                <button
                  v-if="e.changes.length > REPLI"
                  type="button"
                  class="journal__plus"
                  @click="basculer(e.id)"
                >
                  {{ deplies.has(e.id) ? 'Réduire' : `+ ${e.changes.length - REPLI} autre(s)` }}
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
        :per-page="pagination.per_page"
        item-singular="action"
        item-plural="actions"
        @update:page="changerPage"
        @update:per-page="changerParPage"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import api from '@/api'
import AdminPagination from '../components/AdminPagination.vue'
import { readPagination } from '../utils/pagination'
import { usePersistedFilters } from '../utils/persistedFilters'

/** Au-delà, les modifications se replient : une ligne qui déborde ne se lit plus. */
const REPLI = 3

const ACTIONS = { created: 'Création', updated: 'Modification', deleted: 'Suppression' }

/** Noms d'objets en clair. Un nom inconnu s'affiche tel quel. */
const OBJETS = {
  Product: 'Produit', ProductVariant: 'Déclinaison', Category: 'Catégorie',
  ProductLine: 'Gamme', Order: 'Commande', Promotion: 'Promotion',
  Coupon: 'Coupon', Post: 'Article', PostCategory: 'Catégorie du blog',
  DeliveryZone: 'Zone de livraison', DeliveryRound: 'Tournée', Courier: 'Livreur',
  PurchaseOrder: "Bon d'achat", Supplier: 'Fournisseur',
  ProgramTier: 'Palier fidélité', ProgramBenefit: 'Avantage fidélité',
  NewsletterCampaign: 'Campagne', GalleryPhoto: 'Photo de galerie',
  WeeklyStockAllocation: 'Stock de la semaine',
}

/**
 * Noms de champs en clair.
 *
 * La liste couvre ce qui se modifie vraiment au quotidien ; le reste s'affiche
 * sous son nom technique, ce qui reste plus honnête qu'une traduction devinée.
 */
const CHAMPS = {
  name: 'Nom', title: 'Titre', slug: 'Identifiant d\'URL',
  price: 'Prix', compare_price: 'Prix barré', cost_price: 'Prix de revient',
  stock: 'Stock', low_stock_threshold: 'Seuil d\'alerte', track_stock: 'Suivi du stock',
  is_active: 'Actif', is_featured: 'Mis en avant', status: 'Statut',
  description: 'Description', short_description: 'Description courte',
  sku: 'Référence', quantity: 'Quantité', note: 'Note', notes: 'Notes',
  value: 'Valeur', discount_type: 'Type de remise', scope: 'Portée',
  starts_at: 'Début', ends_at: 'Fin',
  shipping_cost: 'Frais de livraison', total: 'Total', paid_at: 'Payée le',
  courier_id: 'Livreur', dispatched_at: 'Départ', closed_at: 'Clôture',
  zone_name: 'Zone', zone_group: 'Groupe de zones', label: 'Libellé',
  position: 'Position', sort_order: 'Ordre',
}

const route = useRoute()

const entrees      = ref([])
const operators    = ref([])
const subjectTypes = ref([])
const loading      = ref(false)
const pagination   = ref({ current_page: 1, last_page: 1, total: 0, per_page: 40 })
const deplies      = ref(new Set())

const filtres = usePersistedFilters('activity', {
  operator_id: '', subject_type: '', event: '', date_from: '', date_to: '', per_page: 40,
})

const filtree = computed(() =>
  ['operator_id', 'subject_type', 'event', 'date_from', 'date_to'].some(k => filtres[k]),
)

async function charger(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: filtres.per_page }
    for (const cle of ['operator_id', 'subject_type', 'event', 'date_from', 'date_to']) {
      if (filtres[cle]) params[cle] = filtres[cle]
    }

    const { data } = await api.get('/admin/activity', { params })

    entrees.value      = data.data ?? []
    operators.value    = data.operators ?? []
    subjectTypes.value = data.subject_types ?? []
    pagination.value   = readPagination(data)
    deplies.value      = new Set()
  } catch (e) {
    console.error('Journal indisponible', e)
    entrees.value = []
  } finally {
    loading.value = false
  }
}

/** Un changement de critère ramène en page 1 : rester page 7 d'une autre liste n'a pas de sens. */
function rechercher() {
  charger(1)
}

function reinitialiser() {
  filtres.$reset()
  charger(1)
}

function changerPage(p) {
  charger(p)
}

function changerParPage(n) {
  filtres.per_page = n
  charger(1)
}

function basculer(id) {
  const suivant = new Set(deplies.value)
  suivant.has(id) ? suivant.delete(id) : suivant.add(id)
  deplies.value = suivant
}

function visibles(entree) {
  return deplies.value.has(entree.id) ? entree.changes : entree.changes.slice(0, REPLI)
}

/**
 * Vers la fiche de l'objet, quand l'écran existe.
 *
 * Sans lien, le journal signale un problème sans permettre d'aller le regarder.
 * Les objets qui n'ont pas d'écran dédié restent en texte : un lien mort est
 * pire que pas de lien.
 */
function lienObjet(subject) {
  const id = subject?.id
  if (!id) return null

  switch (subject.type) {
    case 'Product':     return { name: 'admin.products.edit',      params: { id } }
    case 'Order':       return { name: 'admin.order',              params: { id } }
    case 'Post':        return { name: 'admin.blog.edit',          params: { id } }
    case 'ProductLine': return { name: 'admin.product-lines.edit', params: { id } }
    default:            return null
  }
}

function badgeAction(event) {
  return {
    created: 'badge badge-success',
    updated: 'badge badge-warning',
    deleted: 'badge badge-danger',
  }[event] ?? 'badge badge-gray'
}

/**
 * Une valeur du journal, telle qu'on la lit.
 *
 * Les booléens et les champs vides sortent bruts de la base : « false » et une
 * cellule blanche ne disent pas la même chose à qui relit une décision.
 */
function valeur(v) {
  if (v === null || v === undefined || v === '') return '(vide)'
  if (v === true  || v === 'true'  || v === 1) return 'oui'
  if (v === false || v === 'false' || v === 0) return 'non'

  if (typeof v === 'object') return JSON.stringify(v)

  const texte = String(v)
  return texte.length > 60 ? texte.slice(0, 60) + '…' : texte
}

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: '2-digit' })
}

function formatHeure(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}

onMounted(() => {
  // Arrivée depuis la fiche d'un utilisateur : le filtre de l'URL prime sur
  // celui qui était conservé, sinon le lien « Ses actions » ne montrerait pas
  // les actions de la personne sur laquelle on a cliqué.
  if (route.query.operator_id) {
    filtres.operator_id = String(route.query.operator_id)
  }
  charger(1)
})
</script>

<style scoped>
.page-header__sub {
  font-size: 0.8125rem; color: var(--gray-500);
  max-width: 62ch; margin: 4px 0 0;
}
.filters-bar {
  display: flex; align-items: center; gap: var(--space-3);
  flex-wrap: wrap; padding: var(--space-3) var(--space-4);
}
.journal__filtre { flex: 0 1 190px; }
.journal__date   { flex: 0 1 160px; }

.journal__quand { white-space: nowrap; }
.journal__heure { font-size: 0.6875rem; color: var(--gray-400); }
.journal__systeme { font-size: 0.8125rem; color: var(--gray-400); font-style: italic; }
.journal__lien { color: var(--rose-600); text-decoration: none; }
.journal__lien:hover { text-decoration: underline; }

.journal__changes { max-width: 520px; }
.journal__vide { color: var(--gray-400); }
.journal__liste { margin: 0; padding: 0; list-style: none; }
.journal__liste li {
  display: flex; align-items: baseline; gap: 6px; flex-wrap: wrap;
  font-size: 0.8125rem; line-height: 1.7;
}
.journal__champ { font-weight: 600; color: var(--gray-700); }
.journal__avant {
  color: var(--gray-400); text-decoration: line-through;
  overflow-wrap: anywhere;
}
.journal__fleche { color: var(--gray-300); }
.journal__apres { color: var(--gray-800); overflow-wrap: anywhere; }
.journal__plus {
  margin-top: 2px; padding: 0; background: none; border: none; cursor: pointer;
  font-size: 0.75rem; color: var(--rose-600);
}
.journal__plus:hover { text-decoration: underline; }

@media (max-width: 900px) {
  .journal__filtre, .journal__date { flex: 1 1 140px; }
}
</style>
