<template>
  <div class="admin-page">
    <header class="page-header">
      <div>
        <span class="eyebrow">Opérations</span>
        <h1 class="page-header__title">À traiter</h1>
        <p class="page-header__sub">
          Ce qui a dépassé son délai, le plus ancien d'abord. Rien n'est envoyé
          ni annulé automatiquement : la décision reste ici.
        </p>
      </div>
      <button class="btn btn-outline btn-sm" :disabled="loading" @click="load">
        {{ loading ? 'Chargement…' : 'Rafraîchir' }}
      </button>
    </header>

    <p v-if="error" class="att-error">{{ error }}</p>

    <!-- Un compteur par motif : on choisit par quoi commencer. -->
    <div v-if="!loading && total" class="att-tabs">
      <button
        type="button"
        class="att-tab"
        :class="{ 'att-tab--on': filtre === '' }"
        @click="filtre = ''"
      >
        Tout <span class="att-tab__count">{{ total }}</span>
      </button>
      <button
        v-for="(nombre, code) in counts"
        :key="code"
        type="button"
        class="att-tab"
        :class="{ 'att-tab--on': filtre === code }"
        @click="filtre = filtre === code ? '' : code"
      >
        {{ MOTIFS[code] ?? code }} <span class="att-tab__count">{{ nombre }}</span>
      </button>
    </div>

    <div v-if="loading" class="loader-wrap"><div class="loader"></div></div>

    <!-- Le bon état de cet écran est d'être vide. -->
    <div v-else-if="!lignes.length" class="att-empty">
      <strong>Rien à traiter.</strong>
      <p>Aucune commande ne dépasse son délai. C'est l'état normal de cet écran.</p>
    </div>

    <div v-else class="att-list">
      <article
        v-for="ligne in lignes"
        :key="ligne.order.id"
        class="card att-row"
        :class="`att-row--${ligne.level}`"
      >
        <div class="att-row__main">
          <div class="att-row__head">
            <RouterLink :to="{ name: 'admin.orders', query: { commande: ligne.order.id } }" class="att-row__number">
              {{ ligne.order.number }}
            </RouterLink>
            <span class="att-row__client">{{ nomClient(ligne.order) }}</span>
            <span class="att-row__status">{{ ligne.order.status_label }}</span>
            <span class="att-row__age">{{ anciennete(ligne.since) }}</span>
          </div>

          <ul class="att-reasons">
            <li v-for="r in ligne.attention" :key="r.code" :class="`att-reason att-reason--${r.level}`">
              <strong>{{ r.label }}</strong>
              <span class="att-reason__hint">{{ r.hint }}</span>
            </li>
          </ul>
        </div>

        <div class="att-row__side">
          <span v-if="ligne.order.total !== null" class="att-row__total">{{ prix(ligne.order.total) }}</span>
          <a
            v-if="lienWhatsapp(ligne.order)"
            :href="lienWhatsapp(ligne.order)"
            target="_blank"
            rel="noopener"
            class="btn btn-sm btn-outline"
          >WhatsApp</a>
          <RouterLink
            :to="{ name: 'admin.orders', query: { commande: ligne.order.id } }"
            class="btn btn-sm btn-primary"
          >Ouvrir</RouterLink>
        </div>
      </article>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/api'

/** Libellés courts des motifs, pour les onglets. */
const MOTIFS = {
  to_call:           'À appeler',
  unpaid:            'Règlement attendu',
  cancel_suggested:  'Annulation à décider',
  fee_pending:       'Frais à fixer',
  missed_extraction: 'Oubliée à l\'extraction',
  late_delivery:     'Livraison en retard',
}

const data    = ref([])
const counts  = ref({})
const total   = ref(0)
const loading = ref(true)
const error   = ref('')
const filtre  = ref('')

const lignes = computed(() => filtre.value
  ? data.value.filter(l => l.attention.some(r => r.code === filtre.value))
  : data.value)

async function load() {
  loading.value = true
  error.value   = ''
  try {
    const { data: reponse } = await api.get('/admin/orders/attention')
    data.value   = reponse.data ?? []
    counts.value = reponse.counts ?? {}
    total.value  = reponse.total ?? 0
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Chargement impossible.'
  } finally {
    loading.value = false
  }
}

function nomClient(order) {
  const a = order.shipping_address ?? {}
  return [a.first_name, a.last_name].filter(Boolean).join(' ') || order.user?.name || '—'
}

/** Depuis quand le délai est dépassé — c'est l'ancienneté qui fait agir. */
function anciennete(since) {
  if (!since) return ''
  const heures = Math.max(0, Math.round((Date.now() - new Date(since)) / 3600000))
  if (heures < 24) return `depuis ${heures} h`
  return `depuis ${Math.round(heures / 24)} j`
}

function prix(v) {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency', currency: 'XOF', minimumFractionDigits: 0,
  }).format(Number(v ?? 0))
}

/**
 * Le numéro de la cliente, prêt à appeler sur WhatsApp. Presque tous ces
 * motifs se règlent par un message : autant l'avoir sous la main.
 */
function lienWhatsapp(order) {
  const brut = order.shipping_address?.phone ?? order.user?.phone ?? ''
  const num  = String(brut).replace(/\D/g, '')
  if (!num) return null
  return `https://wa.me/${num.startsWith('225') ? num : '225' + num.replace(/^0+/, '')}`
}

onMounted(load)
</script>

<style scoped>
.att-error { color: #b91c1c; font-size: 0.8125rem; }

.att-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: var(--space-4);
}
.att-tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border: 1px solid var(--cream-300);
  border-radius: var(--radius-full, 999px);
  background: #fff;
  font-size: 0.8125rem;
  color: var(--gray-600);
  cursor: pointer;
}
.att-tab--on { border-color: var(--rose-400); color: var(--rose-600); background: var(--rose-50); }
.att-tab__count { font-weight: 700; }

.att-empty {
  padding: var(--space-6);
  text-align: center;
  color: var(--gray-500);
}
.att-empty strong { display: block; color: var(--gray-700); margin-bottom: 4px; }
.att-empty p { margin: 0; font-size: 0.875rem; }

.att-list { display: flex; flex-direction: column; gap: var(--space-2); }

.att-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-4);
  padding: var(--space-3) var(--space-4);
  border-left: 3px solid transparent;
}
.att-row--warning  { border-left-color: #f59e0b; }
.att-row--critical { border-left-color: #dc2626; }

.att-row__main { min-width: 0; flex: 1; }

.att-row__head {
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
  gap: var(--space-2);
  margin-bottom: 6px;
}
.att-row__number { font-weight: 600; color: var(--gray-800); text-decoration: none; }
.att-row__number:hover { color: var(--rose-600); }
.att-row__client { font-size: 0.8125rem; color: var(--gray-600); }
.att-row__status {
  font-size: 0.6875rem;
  padding: 1px 7px;
  border-radius: var(--radius-full, 999px);
  background: var(--cream-100, #f5f0eb);
  color: var(--gray-600);
}
.att-row__age { font-size: 0.75rem; color: var(--gray-400); }

.att-reasons { list-style: none; margin: 0; padding: 0; }
.att-reason { font-size: 0.8125rem; padding: 2px 0; }
.att-reason strong { font-weight: 600; }
.att-reason--warning strong  { color: #b45309; }
.att-reason--critical strong { color: #b91c1c; }
.att-reason__hint { display: block; font-size: 0.75rem; color: var(--gray-500); }

.att-row__side {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex-shrink: 0;
}
.att-row__total { font-size: 0.8125rem; color: var(--gray-600); white-space: nowrap; }

@media (max-width: 720px) {
  .att-row { flex-direction: column; }
  .att-row__side { width: 100%; justify-content: flex-end; }
}
</style>
