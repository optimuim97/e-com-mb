<template>
  <div class="wa-inbox">

    <!-- Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Messages WhatsApp</h1>
        <p class="page-subtitle">
          Chaque message reçu sur le WhatsApp Business finit converti en commande ou écarté.
          Ce qui reste ici n'a pas encore été traité.
        </p>
      </div>
      <button class="btn btn-outline btn-sm" :disabled="loading" @click="reloadAll">
        {{ loading ? 'Actualisation…' : '↻ Actualiser' }}
      </button>
    </div>

    <!-- Compteurs -->
    <div class="stats-grid" v-if="counts">
      <button class="stat-card stat-card--clickable" :class="{ 'stat-card--active': filter === 'pending' }" @click="setFilter('pending')">
        <div class="stat-card__icon stat-card__icon--amber">⏳</div>
        <div class="stat-card__body">
          <p class="stat-card__value">{{ counts.pending }}</p>
          <p class="stat-card__label">À traiter</p>
        </div>
      </button>
      <button class="stat-card stat-card--clickable" :class="{ 'stat-card--active': filter === 'converted' }" @click="setFilter('converted')">
        <div class="stat-card__icon stat-card__icon--green">✓</div>
        <div class="stat-card__body">
          <p class="stat-card__value">{{ counts.converted }}</p>
          <p class="stat-card__label">Commandes créées</p>
        </div>
      </button>
      <button class="stat-card stat-card--clickable" :class="{ 'stat-card--active': filter === 'ignored' }" @click="setFilter('ignored')">
        <div class="stat-card__icon stat-card__icon--gray">✕</div>
        <div class="stat-card__body">
          <p class="stat-card__value">{{ counts.ignored }}</p>
          <p class="stat-card__label">Écartés</p>
        </div>
      </button>
      <div class="stat-card stat-card--wide">
        <div class="stat-card__icon stat-card__icon--rose">%</div>
        <div class="stat-card__body">
          <p class="stat-card__value">{{ tauxTraitement }}</p>
          <p class="stat-card__label">Traités sur 14 jours — vise 100 % avant de couper la double saisie</p>
        </div>
      </div>
    </div>

    <!-- Filtres -->
    <div class="filters-bar">
      <select v-model="filter" class="input input--sm" @change="load(1)">
        <option value="">Tous les messages</option>
        <option value="pending">À traiter</option>
        <option value="converted">Convertis</option>
        <option value="ignored">Écartés</option>
      </select>
      <input
        v-model="search"
        class="input input--sm"
        type="search"
        placeholder="Nom, numéro ou texte…"
        @input="debouncedSearch"
      />
      <button class="btn btn-ghost btn-sm" @click="showReconciliation = !showReconciliation">
        {{ showReconciliation ? 'Masquer' : 'Voir' }} le suivi jour par jour
      </button>
    </div>

    <!-- Réconciliation : le contrôle de la double saisie -->
    <div v-if="showReconciliation" class="table-card">
      <div v-if="!reconciliation" class="table-empty">Chargement du suivi…</div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>Jour</th>
            <th>Messages reçus</th>
            <th>Convertis</th>
            <th>Écartés</th>
            <th>En attente</th>
            <th>Commandes du jour</th>
            <th>dont caisse</th>
            <th>Chiffre</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="j in reconciliation.jours" :key="j.date" class="data-table__row">
            <td class="font-medium">{{ formatDay(j.date) }}</td>
            <td>{{ j.messages_recus || '—' }}</td>
            <td class="text-green">{{ j.convertis || '—' }}</td>
            <td class="text-muted">{{ j.ecartes || '—' }}</td>
            <td>
              <span v-if="j.en_attente" class="badge badge--warning">{{ j.en_attente }}</span>
              <span v-else class="text-muted">—</span>
            </td>
            <td>{{ j.commandes_total || '—' }}</td>
            <td class="text-muted">{{ j.commandes_caisse || '—' }}</td>
            <td class="font-semibold text-rose">{{ formatPrice(j.chiffre) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- File -->
    <div class="table-card">
      <div v-if="loading" class="table-empty">Chargement…</div>
      <div v-else-if="!messages.length" class="table-empty">
        <template v-if="filter === 'pending'">✓ File vide — tous les messages ont été traités.</template>
        <template v-else>Aucun message pour ce filtre.</template>
      </div>

      <ul v-else class="wa-list">
        <li v-for="m in messages" :key="m.id" class="wa-item" :class="`wa-item--${m.status}`">

          <div class="wa-item__head">
            <div class="wa-item__who">
              <strong>{{ m.contact_name || 'Contact inconnu' }}</strong>
              <a :href="`https://wa.me/${digits(m.from_phone)}`" target="_blank" rel="noopener" class="wa-item__phone">
                {{ formatPhone(m.from_phone) }} ↗
              </a>
            </div>
            <div class="wa-item__meta">
              <span class="text-muted text-sm">{{ formatDateTime(m.received_at) }}</span>
              <span class="badge" :class="badgeClass(m.status)">{{ statusLabel(m.status) }}</span>
            </div>
          </div>

          <p v-if="m.body" class="wa-item__body">{{ m.body }}</p>
          <p v-else class="wa-item__body wa-item__body--empty">
            Message sans texte ({{ m.type }}) — à ouvrir dans WhatsApp.
          </p>

          <!-- Issue déjà donnée -->
          <p v-if="m.order" class="wa-item__outcome">
            → Commande
            <RouterLink :to="{ name: 'admin.order', params: { id: m.order.id } }">{{ m.order.number }}</RouterLink>
            · {{ formatPrice(m.order.total) }}
            <span v-if="m.handler" class="text-muted">· par {{ m.handler.name }}</span>
          </p>
          <p v-else-if="m.status === 'ignored'" class="wa-item__outcome text-muted">
            → Écarté{{ m.handling_note ? ` : ${m.handling_note}` : '' }}
            <span v-if="m.handler">· par {{ m.handler.name }}</span>
          </p>

          <!-- Actions -->
          <div class="wa-item__actions">
            <template v-if="m.status === 'pending'">
              <button class="btn btn-primary btn-sm" @click="openInPos(m)">
                🧾 Saisir en caisse
              </button>
              <button v-if="canConvert" class="btn btn-outline btn-sm" @click="startLink(m)">
                🔗 Rattacher à une commande
              </button>
              <button v-if="canConvert" class="btn btn-ghost btn-sm" @click="startIgnore(m)">
                Écarter
              </button>
            </template>
            <button v-else-if="canConvert" class="btn btn-ghost btn-sm" @click="reopen(m)">
              ↩ Remettre dans la file
            </button>
          </div>

          <!-- Rattachement -->
          <div v-if="linking === m.id" class="wa-item__form">
            <input
              v-model="linkOrderNumber"
              class="input input--sm"
              placeholder="N° de commande (ex. ORD-2026-00042)"
              @keyup.enter="confirmLink(m)"
            />
            <button class="btn btn-primary btn-sm" :disabled="busy" @click="confirmLink(m)">Rattacher</button>
            <button class="btn btn-ghost btn-sm" @click="linking = null">Annuler</button>
          </div>

          <!-- Écartement -->
          <div v-if="ignoring === m.id" class="wa-item__form">
            <select v-model="ignoreReason" class="input input--sm">
              <option value="Pas une commande">Pas une commande</option>
              <option value="Demande d'information">Demande d'information</option>
              <option value="Doublon">Doublon</option>
              <option value="Commande annulée par la cliente">Commande annulée par la cliente</option>
              <option value="">Autre…</option>
            </select>
            <input v-if="!ignoreReason" v-model="ignoreCustom" class="input input--sm" placeholder="Motif" />
            <button class="btn btn-primary btn-sm" :disabled="busy" @click="confirmIgnore(m)">Écarter</button>
            <button class="btn btn-ghost btn-sm" @click="ignoring = null">Annuler</button>
          </div>

        </li>
      </ul>
    </div>

    <p v-if="error" class="wa-error">{{ error }}</p>

    <AdminPagination
      :current-page="page"
      :last-page="meta?.last_page"
      :total="meta?.total"
      :show-per-page="false"
      item-singular="message"
      item-plural="messages"
      @update:page="load"
    />

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePersistedRef } from '@/admin/utils/persistedFilters'
import { RouterLink, useRouter } from 'vue-router'
import { adminWhatsAppApi } from './whatsapp.api'
import AdminPagination from '@/admin/components/AdminPagination.vue'
import { useAuthStore } from '@/features/auth/auth.store'
import api from '@/api'

const auth   = useAuthStore()
const router = useRouter()

const messages = ref([])
const counts   = ref(null)
const meta     = ref(null)
const loading  = ref(false)
const error    = ref('')
const page     = ref(1)

// La file s'ouvre sur ce qui reste à faire, pas sur l'historique.
const filter = ref('pending')
// Conservée d'une visite à l'autre, comme sur les autres listes.
const search = usePersistedRef('whatsapp.search', '')

const showReconciliation = ref(false)
const reconciliation     = ref(null)

const linking         = ref(null)
const linkOrderNumber = ref('')
const ignoring        = ref(null)
const ignoreReason    = ref('Pas une commande')
const ignoreCustom    = ref('')
const busy            = ref(false)

const canConvert = computed(() => auth.can('whatsapp.convert'))

const tauxTraitement = computed(() => {
  const taux = reconciliation.value?.totaux?.taux_traitement
  return taux === null || taux === undefined ? '—' : `${taux} %`
})

// ── Chargement ───────────────────────────────────────────────────────────────
async function load(p = 1) {
  page.value    = p
  loading.value = true
  error.value   = ''
  try {
    const params = { page: p }
    if (filter.value) params.status = filter.value
    if (search.value.trim()) params.search = search.value.trim()
    const { data } = await adminWhatsAppApi.list(params)
    messages.value = data.data
    meta.value     = data.meta
    counts.value   = data.counts
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Impossible de charger la file.'
  } finally {
    loading.value = false
  }
}

async function loadReconciliation() {
  try {
    const { data } = await adminWhatsAppApi.reconciliation()
    reconciliation.value = data
  } catch { /* le suivi est un complément : son échec ne bloque pas la file */ }
}

function reloadAll() {
  load(page.value)
  loadReconciliation()
}

let searchTimer = null
function debouncedSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => load(1), 350)
}

function setFilter(value) {
  filter.value = filter.value === value ? '' : value
  load(1)
}

// ── Traitement ───────────────────────────────────────────────────────────────

/**
 * La caisse fait déjà tout le travail de saisie : on lui passe l'identifiant
 * du message plutôt que de dupliquer ici un second formulaire de commande.
 */
function openInPos(message) {
  router.push({ name: 'admin.pos', query: { wa: message.id } })
}

function startLink(message) {
  ignoring.value        = null
  linking.value         = message.id
  linkOrderNumber.value = ''
}

async function confirmLink(message) {
  const numero = linkOrderNumber.value.trim()
  if (!numero) return

  busy.value  = true
  error.value = ''
  try {
    // L'agent connaît le numéro de commande, pas son identifiant technique.
    const { data } = await api.get('/admin/orders', { params: { search: numero, per_page: 5 } })
    const order = (data.data ?? []).find(o => o.number?.toLowerCase() === numero.toLowerCase())
    if (!order) {
      error.value = `Aucune commande « ${numero} ».`
      return
    }
    await adminWhatsAppApi.link(message.id, order.id)
    linking.value = null
    reloadAll()
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Rattachement impossible.'
  } finally {
    busy.value = false
  }
}

function startIgnore(message) {
  linking.value      = null
  ignoring.value     = message.id
  ignoreReason.value = 'Pas une commande'
  ignoreCustom.value = ''
}

async function confirmIgnore(message) {
  busy.value  = true
  error.value = ''
  try {
    await adminWhatsAppApi.ignore(message.id, ignoreReason.value || ignoreCustom.value.trim() || null)
    ignoring.value = null
    reloadAll()
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Impossible d\'écarter ce message.'
  } finally {
    busy.value = false
  }
}

async function reopen(message) {
  busy.value = true
  try {
    await adminWhatsAppApi.reopen(message.id)
    reloadAll()
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Impossible de remettre ce message dans la file.'
  } finally {
    busy.value = false
  }
}

// ── Affichage ────────────────────────────────────────────────────────────────
function statusLabel(status) {
  return { pending: 'À traiter', converted: 'Converti', ignored: 'Écarté' }[status] ?? status
}

function badgeClass(status) {
  return { pending: 'badge--warning', converted: 'badge--success', ignored: 'badge--muted' }[status] ?? ''
}

function digits(phone) {
  return String(phone ?? '').replace(/\D/g, '')
}

/** 2250707849883 → +225 07 07 84 98 83 */
function formatPhone(phone) {
  const d = digits(phone)
  const local = d.startsWith('225') ? d.slice(3) : d
  return (d.startsWith('225') ? '+225 ' : '') + local.replace(/(\d{2})(?=\d)/g, '$1 ').trim()
}

function formatDateTime(value) {
  if (!value) return '—'
  return new Date(value).toLocaleString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}

function formatDay(value) {
  return new Date(value).toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit', month: 'short' })
}

function formatPrice(val) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', maximumFractionDigits: 0 }).format(val ?? 0)
}

onMounted(() => { load(1); loadReconciliation() })
</script>

<style scoped>
.wa-inbox { display: flex; flex-direction: column; gap: var(--space-6); }

.page-header { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: var(--space-4); }
.page-title  { font-family: var(--font-display); font-size: 1.75rem; font-weight: 700; color: var(--gray-800); }
.page-subtitle { font-size: 0.875rem; color: var(--gray-500); max-width: 62ch; }

.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: var(--space-3); }
.stat-card {
  display: flex; align-items: center; gap: var(--space-3);
  padding: var(--space-4); background: #fff; border: 1px solid var(--cream-200);
  border-radius: var(--radius-md); text-align: left;
}
.stat-card--clickable { cursor: pointer; transition: all var(--transition-fast); }
.stat-card--clickable:hover { border-color: var(--rose-400); }
.stat-card--active { border-color: var(--rose-500); background: var(--rose-50); }
.stat-card--wide { grid-column: span 2; }
.stat-card__icon {
  width: 36px; height: 36px; border-radius: var(--radius-md); flex-shrink: 0;
  display: flex; align-items: center; justify-content: center; font-weight: 700;
}
.stat-card__icon--amber { background: #fef3c7; color: #b45309; }
.stat-card__icon--green { background: #dcfce7; color: #15803d; }
.stat-card__icon--gray  { background: var(--cream-100); color: var(--gray-500); }
.stat-card__icon--rose  { background: var(--rose-50); color: var(--rose-600); }
.stat-card__value { font-size: 1.375rem; font-weight: 700; color: var(--gray-800); }
.stat-card__label { font-size: 0.75rem; color: var(--gray-500); }

.filters-bar { display: flex; flex-wrap: wrap; gap: var(--space-2); align-items: center; }

.table-card { background: #fff; border: 1px solid var(--cream-200); border-radius: var(--radius-md); overflow: hidden; }
.table-empty { padding: var(--space-6); text-align: center; color: var(--gray-400); font-size: 0.875rem; }

.wa-list { list-style: none; display: flex; flex-direction: column; }
.wa-item {
  padding: var(--space-4);
  border-bottom: 1px solid var(--cream-200);
  display: flex; flex-direction: column; gap: var(--space-2);
}
.wa-item:last-child { border-bottom: none; }
/* Le traité s'efface pour laisser la file lisible d'un coup d'œil. */
.wa-item--converted, .wa-item--ignored { background: var(--cream-50, #fdfbf9); }
.wa-item--converted .wa-item__body, .wa-item--ignored .wa-item__body { color: var(--gray-500); }

.wa-item__head { display: flex; justify-content: space-between; align-items: flex-start; gap: var(--space-3); flex-wrap: wrap; }
.wa-item__who { display: flex; flex-direction: column; }
.wa-item__who strong { color: var(--gray-800); font-size: 0.9375rem; }
.wa-item__phone { font-size: 0.8125rem; color: #25d366; text-decoration: none; }
.wa-item__phone:hover { text-decoration: underline; }
.wa-item__meta { display: flex; align-items: center; gap: var(--space-2); }

.wa-item__body {
  font-size: 0.9375rem; color: var(--gray-700); line-height: 1.5;
  white-space: pre-wrap; word-break: break-word;
  background: var(--cream-100); border-radius: var(--radius-md);
  padding: var(--space-2) var(--space-3);
}
.wa-item__body--empty { color: var(--gray-400); font-style: italic; }

.wa-item__outcome { font-size: 0.8125rem; color: var(--gray-600); }
.wa-item__outcome a { color: var(--rose-600); font-weight: 600; }

.wa-item__actions { display: flex; flex-wrap: wrap; gap: var(--space-2); }
.wa-item__form { display: flex; flex-wrap: wrap; gap: var(--space-2); align-items: center; padding-top: var(--space-1); }

.badge--muted { background: var(--cream-200); color: var(--gray-600); }
.text-green { color: #15803d; }
.wa-error { color: #b91c1c; font-size: 0.875rem; }

@media (max-width: 640px) {
  .stat-card--wide { grid-column: span 1; }
}
</style>
