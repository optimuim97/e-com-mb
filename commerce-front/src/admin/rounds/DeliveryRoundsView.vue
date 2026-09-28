<template>
  <div class="admin-page">
    <header class="page-header">
      <div>
        <span class="eyebrow">Livraison</span>
        <h1 class="page-header__title">Tournées</h1>
        <p class="page-header__sub">
          L'extraction de 21 h prépare la tournée du lendemain : au matin,
          désignez le livreur et confirmez le départ. Une tournée se compose
          aussi à la main depuis les commandes. Au retour du livreur, dépliez-la
          ici et pointez chaque livraison.
        </p>
      </div>
      <RouterLink class="btn btn-primary btn-sm" :to="{ name: 'admin.orders' }">
        Préparer une tournée
      </RouterLink>
    </header>

    <!-- Recherche par code : le geste du retour de tournée. -->
    <div class="card filters-bar">
      <input
        v-model="search"
        type="text"
        class="input filters-bar__search"
        placeholder="Code de tournée (TR-2808-A) ou titre…"
        @input="debounced"
      />
      <div class="status-tabs">
        <button
          v-for="tab in ONGLETS"
          :key="tab.value"
          class="status-tab"
          :class="{ 'status-tab--active': statut === tab.value }"
          @click="setStatut(tab.value)"
        >
          {{ tab.label }}
        </button>
      </div>
    </div>

    <div class="card">
      <div v-if="loading" class="loader-wrap"><div class="loader"></div></div>

      <div v-else-if="!rounds.length" class="empty-state">
        <p v-if="search">Aucune tournée ne correspond à « {{ search }} ».</p>
        <p v-else>Aucune tournée pour l'instant.</p>
      </div>

      <div v-else class="table-scroll">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Code</th>
              <th>Titre</th>
              <th>Zone</th>
              <th>Livreur</th>
              <th>Départ</th>
              <th>Livraisons</th>
              <th title="Nombre d'articles que le livreur emporte">Articles</th>
              <th>À encaisser</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <template v-for="r in rounds" :key="r.id">
              <tr :class="{ 'tr--open': ouverte === r.code }">
                <td class="admin-table__mono tr__code">
                  {{ r.code }}
                  <span v-if="r.status === 'draft'" class="badge badge-warning">à faire partir</span>
                  <span v-else-if="r.status === 'closed'" class="badge badge-gray">clôturée</span>
                </td>
                <td>{{ r.label || '—' }}</td>
                <td>
                  {{ r.zone_name || '—' }}
                  <span v-if="r.zone_group" class="tr__group">{{ r.zone_group }}</span>
                </td>
                <td>{{ r.courier?.name || 'Non affecté' }}</td>
                <td>{{ r.status === 'draft' ? 'pas encore partie' : formatDate(r.dispatched_at) }}</td>
                <td>{{ r.orders_count }}</td>
                <td>{{ r.items_count }}</td>
                <td class="admin-table__total">{{ formatPrice(r.expected_total) }}</td>
                <td>
                  <div class="tr__actions">
                    <button
                      v-if="r.status === 'draft'"
                      type="button"
                      class="btn btn-xs btn-primary"
                      @click="aConfirmer = r"
                    >
                      Faire partir
                    </button>
                    <button type="button" class="btn btn-xs" :class="r.status === 'draft' ? 'btn-outline' : 'btn-primary'" @click="basculer(r)">
                      {{ ouverte === r.code ? 'Fermer' : (r.status === 'draft' ? 'Voir' : 'Pointer') }}
                    </button>
                    <button type="button" class="btn btn-xs btn-outline" @click="telechargerFeuille(r)">
                      Feuille
                    </button>
                    <button
                      v-if="r.status === 'draft'"
                      type="button"
                      class="btn btn-xs btn-outline tr__abandon"
                      title="Rend leur liberté aux commandes retenues"
                      @click="abandonner(r)"
                    >
                      Abandonner
                    </button>
                  </div>
                </td>
              </tr>

              <tr v-if="ouverte === r.code" class="admin-table__detail-row">
                <td :colspan="9">
                  <div v-if="chargementDetail" class="tr__loading">Chargement…</div>

                  <div v-else-if="detail" class="pointage">
                    <!--
                      Le chargement : ce qu'on pose à côté des colis pour
                      vérifier que rien ne manque avant le départ.
                    -->
                    <div v-if="detail.loading_list?.length" class="chargement">
                      <span class="chargement__titre">
                        À emporter · {{ detail.items_count }} article{{ detail.items_count > 1 ? 's' : '' }}
                      </span>
                      <ul class="chargement__liste">
                        <li v-for="a in detail.loading_list" :key="a.name">
                          <strong>{{ a.quantity }}</strong> × {{ a.name }}
                        </li>
                      </ul>
                    </div>

                    <table class="admin-table pointage__lines">
                      <thead>
                        <tr>
                          <th>#</th>
                          <th>Commande</th>
                          <th>Cliente</th>
                          <th>Lieu</th>
                          <th>Attendu</th>
                          <th class="pointage__th-action">Pointage</th>
                        </tr>
                      </thead>
                      <tbody>
                        <template v-for="l in detail.lines" :key="l.id">
                          <tr :class="`pointage__row--${l.status}`">
                            <td>{{ l.position }}</td>
                            <td class="admin-table__mono">
                              {{ l.order?.number }}
                              <span class="pointage__articles">{{ l.items_count }} art.</span>
                            </td>
                            <td>
                              <div>{{ l.order?.name || '—' }}</div>
                              <a v-if="l.order?.phone" :href="`tel:${l.order.phone}`" class="pointage__phone">
                                {{ l.order.phone }}
                              </a>
                            </td>
                            <td>{{ l.order?.locality || '—' }}</td>
                            <td class="admin-table__total">
                              {{ l.amount_expected > 0 ? formatPrice(l.amount_expected) : 'réglée' }}
                            </td>

                            <td>
                              <!-- Déjà pointée : le résultat, et de quoi le corriger tant que la tournée est ouverte. -->
                              <div v-if="l.status !== 'pending'" class="pointage__done">
                                <span :class="badgePointage(l.status)">{{ LIBELLES[l.status] }}</span>
                                <span v-if="l.amount_collected > 0" class="pointage__collected">
                                  {{ formatPrice(l.amount_collected) }}
                                  <span v-if="ecart(l)" class="pointage__ecart">{{ ecart(l) }}</span>
                                </span>
                                <span v-if="l.failure_reason" class="pointage__reason">{{ l.failure_reason }}</span>
                                <button
                                  v-if="pointable"
                                  type="button"
                                  class="pointage__link"
                                  @click="rouvrir(l)"
                                >
                                  Corriger
                                </button>
                              </div>

                              <!-- À pointer -->
                              <div v-else-if="pointable" class="pointage__todo">
                                <!--
                                  Le montant est pré-rempli au montant attendu.
                                  À vingt lignes, exiger une saisie par livraison
                                  ferait abandonner l'écran pour la feuille de
                                  papier : l'agent ne corrige que les écarts.
                                -->
                                <input
                                  v-if="l.amount_expected > 0"
                                  v-model.number="montants[l.id]"
                                  type="number"
                                  min="0"
                                  class="input pointage__amount"
                                  :placeholder="String(l.amount_expected)"
                                  title="Montant réellement encaissé"
                                />
                                <button
                                  type="button"
                                  class="btn btn-xs btn-primary"
                                  :disabled="enCours === l.id"
                                  @click="pointer(l, 'delivered')"
                                >
                                  Livré
                                </button>
                                <button type="button" class="btn btn-xs btn-outline" @click="demanderMotif(l, 'failed')">
                                  Échec
                                </button>
                                <button type="button" class="btn btn-xs btn-outline" @click="demanderMotif(l, 'postponed')">
                                  Reporté
                                </button>
                              </div>

                              <!--
                                Un brouillon n'a rien à pointer : les colis
                                sont encore en boutique.
                              -->
                              <span v-else class="pointage__reason">
                                {{ detail.status === 'draft' ? 'en attente du départ' : 'non pointée' }}
                              </span>
                            </td>
                          </tr>

                          <!--
                            Motif obligatoire sur un échec : trois jours plus
                            tard, personne ne se souvient pourquoi la cliente
                            n'a pas été livrée.
                          -->
                          <tr v-if="motif.lineId === l.id" class="pointage__reason-row">
                            <td colspan="6">
                              <div class="pointage__reason-form">
                                <span class="pointage__reason-label">
                                  {{ motif.outcome === 'failed' ? 'Pourquoi la livraison a-t-elle échoué ?' : 'Pourquoi est-elle reportée ?' }}
                                </span>
                                <input
                                  v-model="motif.text"
                                  type="text"
                                  class="input"
                                  maxlength="500"
                                  placeholder="Cliente absente, adresse introuvable, colis refusé…"
                                  @keyup.enter="confirmerMotif"
                                />
                                <button
                                  type="button"
                                  class="btn btn-xs btn-primary"
                                  :disabled="!motif.text.trim()"
                                  @click="confirmerMotif"
                                >
                                  Enregistrer
                                </button>
                                <button type="button" class="btn btn-xs btn-outline" @click="annulerMotif">
                                  Annuler
                                </button>
                              </div>
                            </td>
                          </tr>
                        </template>
                      </tbody>
                    </table>

                    <!-- Le compte de la tournée -->
                    <div class="pointage__foot">
                      <div v-if="detail.status === 'draft'" class="pointage__stats">
                        <span><strong>{{ detail.summary.pending }}</strong> commande(s) prêtes à partir</span>
                      </div>

                      <div v-else class="pointage__stats">
                        <span><strong>{{ detail.summary.delivered }}</strong> livrée(s)</span>
                        <span v-if="detail.summary.failed"><strong>{{ detail.summary.failed }}</strong> échec(s)</span>
                        <span v-if="detail.summary.postponed"><strong>{{ detail.summary.postponed }}</strong> reportée(s)</span>
                        <span v-if="detail.summary.pending" class="pointage__pending">
                          <strong>{{ detail.summary.pending }}</strong> à pointer
                        </span>
                      </div>

                      <!--
                        Rien n'est encaissé avant le départ : afficher « 0 sur
                        0 » sur un brouillon ferait lire un écart de caisse là
                        où il n'y a pas encore de caisse.
                      -->
                      <div v-if="detail.status === 'draft'" class="pointage__caisse">
                        <span>À encaisser <strong>{{ formatPrice(detail.expected_total) }}</strong></span>
                      </div>

                      <div v-else class="pointage__caisse">
                        <span>Encaissé <strong>{{ formatPrice(detail.summary.collected) }}</strong></span>
                        <span class="pointage__sur">sur {{ formatPrice(detail.summary.expected_on_pointed) }}</span>
                        <span
                          v-if="Math.abs(detail.summary.difference) >= 1"
                          class="pointage__diff"
                          :class="detail.summary.difference < 0 ? 'pointage__diff--manque' : 'pointage__diff--plus'"
                        >
                          {{ detail.summary.difference > 0 ? '+' : '' }}{{ formatPrice(detail.summary.difference) }}
                        </span>
                      </div>

                      <div class="pointage__close">
                        <span v-if="detail.status === 'closed'" class="badge badge-gray">
                          Clôturée le {{ formatDate(detail.closed_at) }}
                        </span>
                        <button
                          v-else-if="detail.status === 'draft'"
                          type="button"
                          class="btn btn-sm btn-primary"
                          @click="aConfirmer = rounds.find(r => r.code === detail.code) ?? detail"
                        >
                          Faire partir
                        </button>
                        <button
                          v-else
                          type="button"
                          class="btn btn-sm btn-primary"
                          :disabled="detail.summary.pending > 0 || cloture"
                          :title="detail.summary.pending > 0
                            ? 'Pointez toutes les livraisons avant de clôturer'
                            : 'Arrête les comptes de la tournée'"
                          @click="cloturer"
                        >
                          {{ cloture ? '…' : 'Clôturer la tournée' }}
                        </button>
                      </div>
                    </div>

                    <p v-if="erreur" class="pointage__error">{{ erreur }}</p>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <DispatchRoundModal
      v-if="aConfirmer"
      :round="aConfirmer"
      @close="aConfirmer = null"
      @dispatched="apresDepart"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { usePersistedRef } from '@/admin/utils/persistedFilters'
import { RouterLink } from 'vue-router'
import api from '@/api'
import DispatchRoundModal from './DispatchRoundModal.vue'

const ONGLETS = [
  { value: '',           label: 'Toutes' },
  { value: 'draft',      label: 'À faire partir' },
  { value: 'dispatched', label: 'En cours' },
  { value: 'closed',     label: 'Clôturées' },
]

const LIBELLES = {
  delivered: 'Livrée',
  failed:    'Échec',
  postponed: 'Reportée',
  pending:   'À pointer',
}

const rounds  = ref([])
const loading = ref(false)
// Conservés d'une visite à l'autre, comme sur les autres listes.
const search  = usePersistedRef('rounds.search', '')
const statut  = usePersistedRef('rounds.status', '')

const ouverte          = ref(null)   // code de la tournée dépliée
const detail           = ref(null)
const chargementDetail = ref(false)
const enCours          = ref(null)   // id de la ligne en cours d'envoi
const cloture          = ref(false)
const erreur           = ref('')

/** Tournée dont on confirme le départ. Non nulle = modale ouverte. */
const aConfirmer = ref(null)

/**
 * Une tournée ne se pointe qu'entre le départ et la clôture : avant, les colis
 * sont en boutique ; après, les comptes sont arrêtés.
 */
const pointable = computed(() =>
  detail.value !== null && detail.value.status !== 'closed' && detail.value.status !== 'draft',
)

/** Montants corrigés, par id de ligne. Vide = le montant attendu s'applique. */
const montants = reactive({})

/** Ligne en attente d'un motif d'échec ou de report. */
const motif = reactive({ lineId: null, outcome: null, text: '' })

async function charger() {
  loading.value = true
  try {
    const params = {}
    if (search.value) params.search = search.value
    if (statut.value) params.status = statut.value

    const { data } = await api.get('/admin/delivery-rounds', { params })
    rounds.value = data.data ?? []
  } catch (e) {
    console.error('Tournées indisponibles', e)
    rounds.value = []
  } finally {
    loading.value = false
  }
}

let minuteur = null
function debounced() {
  clearTimeout(minuteur)
  minuteur = setTimeout(charger, 300)
}

function setStatut(valeur) {
  statut.value = valeur
  charger()
}

async function basculer(round) {
  if (ouverte.value === round.code) {
    ouverte.value = null
    detail.value  = null
    return
  }

  ouverte.value          = round.code
  detail.value           = null
  erreur.value           = ''
  annulerMotif()
  chargementDetail.value = true
  try {
    detail.value = await recharger(round.code)
  } catch (e) {
    console.error('Détail de tournée indisponible', e)
    ouverte.value = null
  } finally {
    chargementDetail.value = false
  }
}

async function recharger(code) {
  const { data } = await api.get(`/admin/delivery-rounds/${encodeURIComponent(code)}`)
  return data.data
}

// ── Départ d'une tournée préparée ───────────────────────────────────────────

/**
 * La tournée est partie : le serveur renvoie son état complet, y compris les
 * commandes écartées au dernier moment. On recharge la liste pour que la ligne
 * change de camp.
 */
async function apresDepart(reponse) {
  aConfirmer.value = null
  if (ouverte.value === reponse.data.code) detail.value = reponse.data
  await charger()
}

async function abandonner(round) {
  const ok = window.confirm(
    `Abandonner la tournée ${round.code} ? Ses ${round.orders_count} commande(s) `
    + 'redeviennent disponibles pour une autre tournée.',
  )
  if (!ok) return

  try {
    await api.delete(`/admin/delivery-rounds/${encodeURIComponent(round.code)}`)
    if (ouverte.value === round.code) {
      ouverte.value = null
      detail.value  = null
    }
    await charger()
  } catch (e) {
    erreur.value = e.response?.data?.message ?? "La tournée n'a pas pu être abandonnée."
  }
}

// ── Pointage ────────────────────────────────────────────────────────────────

/**
 * Enregistre le résultat d'un passage. Le serveur renvoie la tournée entière :
 * le récapitulatif et l'état de clôture restent alignés sans second appel.
 */
async function pointer(line, outcome, reason = null) {
  if (enCours.value) return
  enCours.value = line.id
  erreur.value  = ''

  try {
    const charge = { status: outcome }

    if (outcome === 'delivered') {
      const saisi = montants[line.id]
      if (saisi !== undefined && saisi !== null && saisi !== '') {
        charge.amount_collected = Number(saisi)
      }
    } else {
      charge.reason = reason
    }

    const { data } = await api.patch(
      `/admin/delivery-rounds/${encodeURIComponent(detail.value.code)}/lines/${line.id}`,
      charge,
    )
    detail.value = data.data
    delete montants[line.id]
    annulerMotif()
  } catch (e) {
    erreur.value = e.response?.data?.message ?? "Le pointage n'a pas pu être enregistré."
  } finally {
    enCours.value = null
  }
}

function demanderMotif(line, outcome) {
  motif.lineId  = line.id
  motif.outcome = outcome
  motif.text    = ''
}

function annulerMotif() {
  motif.lineId  = null
  motif.outcome = null
  motif.text    = ''
}

function confirmerMotif() {
  if (!motif.text.trim()) return
  const ligne = detail.value.lines.find(l => l.id === motif.lineId)
  if (ligne) pointer(ligne, motif.outcome, motif.text.trim())
}

/** Repasse une ligne en attente pour corriger une saisie. */
function rouvrir(line) {
  detail.value = {
    ...detail.value,
    lines: detail.value.lines.map(l => (l.id === line.id ? { ...l, status: 'pending' } : l)),
  }
}

async function cloturer() {
  if (cloture.value) return
  cloture.value = true
  erreur.value  = ''
  try {
    const { data } = await api.post(
      `/admin/delivery-rounds/${encodeURIComponent(detail.value.code)}/close`,
    )
    detail.value = data.data
    // Le statut change aussi dans la liste au-dessus.
    await charger()
  } catch (e) {
    erreur.value = e.response?.data?.message ?? "La tournée n'a pas pu être clôturée."
  } finally {
    cloture.value = false
  }
}

// ── Divers ──────────────────────────────────────────────────────────────────

/** Écart d'une ligne, affiché seulement quand il existe. */
function ecart(line) {
  if (line.amount_collected === null) return ''
  const d = Number(line.amount_collected) - Number(line.amount_expected)
  if (Math.abs(d) < 1) return ''
  return (d > 0 ? '+' : '') + formatPrice(d)
}

function badgePointage(status) {
  return {
    delivered: 'badge badge-success',
    failed:    'badge badge-danger',
    postponed: 'badge badge-warning',
  }[status] ?? 'badge badge-gray'
}

async function telechargerFeuille(round) {
  try {
    const res  = await api.get(
      `/admin/delivery-rounds/${encodeURIComponent(round.code)}/sheet`,
      { responseType: 'blob' },
    )
    const href = URL.createObjectURL(new Blob([res.data], { type: 'text/plain;charset=utf-8' }))
    const a    = document.createElement('a')
    a.href     = href
    a.download = `tournee_${round.code}.txt`
    document.body.appendChild(a); a.click(); a.remove()
    URL.revokeObjectURL(href)
  } catch (e) {
    console.error('Feuille indisponible', e)
  }
}

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('fr-FR', {
    day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit',
  })
}

function formatPrice(v) {
  if (v === null || v === undefined || v === '') return '—'
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency', currency: 'XOF', maximumFractionDigits: 0,
  }).format(Number(v) || 0)
}

onMounted(charger)
</script>

<style scoped>
.page-header__sub {
  font-size: 0.8125rem; color: var(--gray-500);
  max-width: 62ch; margin: 4px 0 0;
}
.filters-bar {
  display: flex; align-items: center; gap: var(--space-3);
  flex-wrap: wrap; padding: var(--space-3) var(--space-4);
  margin-bottom: var(--space-4);
}
.filters-bar__search { flex: 1 1 280px; }

.status-tabs { display: flex; flex-wrap: wrap; gap: 2px; }
.status-tab {
  padding: 6px 14px; border: none; background: none; cursor: pointer;
  font-size: 0.8125rem; color: var(--gray-500); border-radius: var(--radius-sm);
}
.status-tab:hover { background: var(--cream-200); color: var(--gray-700); }
.status-tab--active { background: var(--rose-500); color: #fff; }

.tr__code {
  font-weight: 700; letter-spacing: 0.05em; color: var(--rose-600);
}
.tr__group { display: block; font-size: 0.6875rem; color: var(--gray-400); }
.tr__actions { display: flex; gap: 6px; flex-wrap: wrap; }
.tr__abandon { color: var(--gray-500); }
.tr--open { background: var(--rose-50); }
.tr__loading { padding: var(--space-4); color: var(--gray-500); }

/* ── Chargement ── */
.chargement {
  padding: var(--space-3) var(--space-4);
  border-bottom: 1px solid var(--cream-200);
  background: var(--cream-50);
}
.chargement__titre { display: block; margin-bottom: 6px; font-size: 0.8125rem; font-weight: 600; color: var(--gray-700); }
.chargement__liste {
  margin: 0; padding: 0; list-style: none;
  display: flex; flex-wrap: wrap; gap: 4px 18px;
  font-size: 0.8125rem; color: var(--gray-600);
}

/* ── Pointage ── */
.pointage { background: #fff; border-radius: var(--radius-md); }
.pointage__articles { display: block; font-size: 0.6875rem; color: var(--gray-400); font-family: var(--font-body); }
.pointage__lines { margin: 0; }
.pointage__th-action { width: 260px; }
.pointage__phone { font-size: 0.75rem; color: var(--rose-600); text-decoration: none; }

.pointage__row--delivered { background: #f6fdf8; }
.pointage__row--failed    { background: #fef6f6; }
.pointage__row--postponed { background: #fffbf0; }

.pointage__todo { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.pointage__amount { width: 96px; padding: 4px 8px; font-size: 0.8125rem; }

.pointage__done { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.pointage__collected { font-size: 0.8125rem; font-weight: 600; color: var(--gray-700); }
.pointage__ecart { color: #b91c1c; font-weight: 600; }
.pointage__reason { font-size: 0.75rem; color: var(--gray-500); font-style: italic; }
.pointage__link {
  background: none; border: none; cursor: pointer;
  font-size: 0.75rem; color: var(--rose-600);
}
.pointage__link:hover { text-decoration: underline; }

.pointage__reason-row td { background: var(--cream-50); }
.pointage__reason-form {
  display: flex; align-items: center; gap: var(--space-2); flex-wrap: wrap;
}
.pointage__reason-form .input { flex: 1 1 280px; }
.pointage__reason-label { font-size: 0.8125rem; font-weight: 600; color: var(--gray-700); }

.pointage__foot {
  display: flex; align-items: center; justify-content: space-between;
  gap: var(--space-4); flex-wrap: wrap;
  padding: var(--space-3) var(--space-4);
  border-top: 1px solid var(--cream-200); background: var(--cream-50);
}
.pointage__stats, .pointage__caisse {
  display: flex; align-items: center; gap: var(--space-3);
  font-size: 0.8125rem; color: var(--gray-600);
}
.pointage__pending { color: #92400e; }
.pointage__sur { color: var(--gray-400); }
.pointage__diff { font-weight: 700; }
.pointage__diff--manque { color: #b91c1c; }
.pointage__diff--plus   { color: #15803d; }
.pointage__error {
  margin: 0; padding: var(--space-3) var(--space-4);
  background: #fee2e2; color: #991b1b; font-size: 0.8125rem;
}

@media (max-width: 900px) {
  .pointage__foot { flex-direction: column; align-items: stretch; }
}
</style>
