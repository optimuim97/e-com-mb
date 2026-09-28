<template>
  <div class="admin-page dash">

    <!-- ── Bandeau de bienvenue ── -->
    <div class="welcome-bar">
      <div class="welcome-bar__left">
        <p class="welcome-bar__date">{{ formattedDate }}</p>
        <h1 class="welcome-bar__title">Bonjour</h1>
      </div>
      <div class="welcome-bar__pills" v-if="!loading">
        <span class="today-pill today-pill--orders">
          <svg class="today-pill__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
          <span><strong>{{ stats.today_orders ?? 0 }}</strong> commande{{ (stats.today_orders ?? 0) !== 1 ? 's' : '' }} aujourd'hui</span>
        </span>
        <span class="today-pill today-pill--revenue" v-if="canFinance">
          <svg class="today-pill__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
          <span><strong>{{ fmtCompact(stats.today_revenue ?? 0) }}</strong> de CA</span>
        </span>
        <span class="today-pill today-pill--week">
          <svg class="today-pill__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <span><strong>{{ stats.week_orders ?? 0 }}</strong> cette semaine</span>
        </span>
      </div>
    </div>

    <!-- ── KPI Cards ── -->
    <div class="kpi-grid">
      <div v-for="kpi in kpis" :key="kpi.label"
        class="kpi-card"
        :style="`--kpi-accent:${kpi.color}; --kpi-bg:${kpi.bg}`"
      >
        <div class="kpi-card__top">
          <div class="kpi-card__icon-wrap">
            <component :is="kpi.icon" class="kpi-card__icon" />
          </div>
          <span class="kpi-card__delta" v-if="kpi.delta !== undefined" :class="kpi.delta >= 0 ? 'kpi-card__delta--up' : 'kpi-card__delta--down'">
            {{ kpi.delta >= 0 ? '↑' : '↓' }} {{ Math.abs(kpi.delta) }}
          </span>
        </div>
        <p class="kpi-card__value">{{ kpi.value }}</p>
        <p class="kpi-card__label">{{ kpi.label }}</p>
        <p v-if="kpi.sub" class="kpi-card__sub">{{ kpi.sub }}</p>
      </div>
    </div>

    <!-- ── Ligne milieu : graphique + statuts ── -->
    <div class="mid-grid">

      <!-- Courbe du CA — finance uniquement -->
      <section class="card chart-card" v-if="canFinance">
        <div class="chart-card__head">
          <div>
            <span class="eyebrow">Tendance</span>
            <h2 class="section-title">Chiffre d'affaires – {{ PERIODES[periode].titre }}</h2>
          </div>
          <div class="chart-head__right">
            <!--
              La semaine est le defaut : une boutique qui livre par tournees a
              des journees en dents de scie, illisibles au jour le jour.
            -->
            <div class="chart-periodes" role="group" aria-label="Granularite">
              <button
                v-for="(def, cle) in PERIODES"
                :key="cle"
                type="button"
                class="chart-periode"
                :class="{ 'chart-periode--active': periode === cle }"
                :disabled="loading"
                @click="changerPeriode(cle)"
              >{{ def.label }}</button>
            </div>
            <div class="chart-legend">
              <span class="chart-legend__dot chart-legend__dot--rose"></span> CA livre
            </div>
          </div>
        </div>
        <div class="chart-wrap" v-if="!loading && salesChart.length">
          <svg
            class="chart-svg"
            viewBox="0 0 560 130"
            preserveAspectRatio="none"
            aria-hidden="true"
          >
            <defs>
              <linearGradient id="chartGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%"   style="stop-color: var(--rose-500)" stop-opacity="0.18"/>
                <stop offset="100%" style="stop-color: var(--rose-500)" stop-opacity="0"/>
              </linearGradient>
            </defs>
            <!-- Grid lines -->
            <line v-for="y in [26, 52, 78, 104]" :key="y"
              :x1="0" :y1="y" :x2="560" :y2="y"
              stroke="#f0e8dc" stroke-width="1"
            />
            <!-- Area fill -->
            <path :d="chartAreaPath" fill="url(#chartGrad)" />
            <!-- Line stroke -->
            <path :d="chartLinePath" fill="none" style="stroke: var(--rose-500)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
            <!-- Dots on peaks -->
            <circle
              v-for="pt in chartPeaks"
              :key="pt.x"
              :cx="pt.x" :cy="pt.y" r="3"
              style="fill: var(--rose-500)" stroke="#fff" stroke-width="1.5"
            />
          </svg>
          <!-- X labels -->
          <div class="chart-xlabels">
            <span>{{ chartXLabels[0] }}</span>
            <span>{{ chartXLabels[1] }}</span>
            <span>{{ chartXLabels[2] }}</span>
          </div>
        </div>
        <div v-else-if="loading" class="skel skel--chart"></div>
        <div v-else class="chart-empty">Aucune livraison sur {{ PERIODES[periode].titre }}</div>

        <!-- Mini stats sous le graphique -->
        <div v-if="!loading && salesChart.length" class="chart-footer">
          <div class="chart-footer__stat">
            <span class="chart-footer__label">Total période</span>
            <strong class="chart-footer__val">{{ fmt(chartTotal) }}</strong>
          </div>
          <div class="chart-footer__stat">
            <span class="chart-footer__label">Meilleur {{ PERIODES[periode].unite }}</span>
            <strong class="chart-footer__val">{{ fmt(chartMax) }}</strong>
          </div>
          <div class="chart-footer__stat">
            <span class="chart-footer__label">Moyenne/{{ PERIODES[periode].unite }}</span>
            <strong class="chart-footer__val">{{ fmt(chartAvg) }}</strong>
          </div>
          <div class="chart-footer__stat">
            <span class="chart-footer__label">Commandes</span>
            <strong class="chart-footer__val">{{ chartOrdersTotal }}</strong>
          </div>
        </div>
      </section>

      <!-- Répartition par statut -->
      <section class="card status-card">
        <span class="eyebrow">Vue d'ensemble</span>
        <h2 class="section-title">Statuts des commandes</h2>

        <div v-if="loading" class="status-skel">
          <div v-for="i in 5" :key="i" class="skel" style="height:36px"></div>
        </div>
        <div v-else class="status-bars">
          <div v-for="s in statusRows" :key="s.key" class="status-row"
            @click="router.push('/admin/orders?status=' + s.key)"
          >
            <div class="status-row__top">
              <span class="status-row__dot" :style="`background:${s.color}`"></span>
              <span class="status-row__label">{{ s.label }}</span>
              <span class="status-row__count">{{ s.count }}</span>
            </div>
            <div class="status-row__track">
              <div class="status-row__bar"
                :style="`width:${statusPct(s.count)}%; background:${s.color}`"
              ></div>
            </div>
          </div>
        </div>

        <div class="status-total" v-if="!loading">
          <span>Total</span>
          <strong>{{ stats.orders ?? 0 }} commandes</strong>
        </div>
      </section>
    </div>

    <!-- ── Ligne basse : commandes récentes + stock ── -->
    <div class="dash-grid">

      <!-- Commandes récentes -->
      <section class="card dash-orders">
        <header class="section-head">
          <div>
            <span class="eyebrow">Activité récente</span>
            <h2 class="section-title">Dernières commandes</h2>
          </div>
          <RouterLink to="/admin/orders" class="dash-link">
            Voir tout
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </RouterLink>
        </header>

        <div v-if="loading" class="skel-rows">
          <div v-for="i in 6" :key="i" class="skel" style="height:48px;border-radius:8px"></div>
        </div>
        <table v-else class="dash-table">
          <thead>
            <tr>
              <th>N° commande</th>
              <th>Cliente</th>
              <th>Montant</th>
              <th>Statut</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in recentOrders" :key="order.id"
              @click="router.push(`/admin/orders/${order.id}`)">
              <td class="dash-table__num">{{ order.number }}</td>
              <td class="dash-table__client">{{ order.user?.name ?? 'Invitée' }}</td>
              <td class="dash-table__total">{{ fmt(order.total) }}</td>
              <td><span :class="statusBadge(order.status)">{{ statusLabel(order.status) }}</span></td>
              <td class="dash-table__date">{{ formatDate(order.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <!-- Stock faible -->
      <section class="card dash-stock">
        <header class="section-head">
          <div>
            <span class="eyebrow">Alerte inventaire</span>
            <h2 class="section-title">Stock faible</h2>
          </div>
          <RouterLink to="/admin/products" class="dash-link">Gérer →</RouterLink>
        </header>

        <div v-if="loading" class="skel-rows">
          <div v-for="i in 4" :key="i" class="skel" style="height:52px;border-radius:8px"></div>
        </div>
        <div v-else-if="!lowStock.length" class="empty-state">
          <span class="empty-state__icon">✅</span>
          <p>Tous les stocks sont OK</p>
        </div>
        <ul v-else class="stock-list">
          <li v-for="p in lowStock" :key="p.id" class="stock-item">
            <div class="stock-item__info">
              <span class="stock-item__name">{{ p.name }}</span>
              <span class="stock-item__threshold">Seuil : {{ p.low_stock_threshold }}</span>
            </div>
            <span class="stock-item__badge" :class="p.stock === 0 ? 'stock-item__badge--empty' : 'stock-item__badge--low'">
              {{ p.stock === 0 ? 'Rupture' : p.stock + ' restant(s)' }}
            </span>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import {
  BanknotesIcon, ShoppingBagIcon, UserGroupIcon, CubeIcon,
} from '@heroicons/vue/24/outline'
import api from '@/api'
import { useAuthStore } from '@/features/auth/auth.store'

const auth         = useAuthStore()
// Finance privée : les blocs monétaires ne s'affichent qu'avec `finance.view`.
const canFinance   = computed(() => auth.can('finance.view'))
const router       = useRouter()
const stats        = ref({})
const recentOrders = ref([])
const lowStock     = ref([])
const salesChart   = ref([])
const loading      = ref(true)

// ── Date du jour ─────────────────────────────────────────────────────────────
const formattedDate = computed(() =>
  new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
)

// ── KPI Cards ─────────────────────────────────────────────────────────────────
const kpis = computed(() => [
  // Carte CA : réservée à la finance
  ...(canFinance.value ? [{
    label: 'Chiffre d\'affaires',
    value: fmtCompact(stats.value.revenue ?? 0),
    sub:   'Commandes livrées / expédiées',
    icon:  BanknotesIcon,
    bg:    '#dcfce7', color: '#15803d',
  }] : []),
  {
    label: 'Commandes',
    value: stats.value.orders ?? 0,
    sub:   `${stats.value.today_orders ?? 0} aujourd'hui · ${stats.value.week_orders ?? 0} cette semaine`,
    icon:  ShoppingBagIcon,
    bg:    'var(--rose-100)', color: 'var(--rose-600)',
    delta: stats.value.today_orders,
  },
  {
    label: 'Clientes',
    value: stats.value.customers ?? 0,
    sub:   'Comptes enregistrés',
    icon:  UserGroupIcon,
    bg:    '#dbeafe', color: '#1d4ed8',
  },
  {
    label: 'Produits actifs',
    value: stats.value.products ?? 0,
    sub:   'En ligne & publiés',
    icon:  CubeIcon,
    bg:    'var(--gold-100, #fef9c3)', color: 'var(--gold-600, #b45309)',
  },
])

// ── Répartition statuts ───────────────────────────────────────────────────────
const statusRows = computed(() => [
  { key: 'pending',    label: 'En attente',   count: stats.value.pending    ?? 0, color: '#f59e0b' },
  { key: 'processing', label: 'En cours',     count: stats.value.processing ?? 0, color: '#8b5cf6' },
  { key: 'shipped',    label: 'Expédiées',    count: stats.value.shipped    ?? 0, color: '#3b82f6' },
  { key: 'delivered',  label: 'Livrées',      count: stats.value.delivered  ?? 0, color: '#10b981' },
  { key: 'cancelled',  label: 'Annulées',     count: stats.value.cancelled  ?? 0, color: '#ef4444' },
])

const statusMax = computed(() => Math.max(1, ...statusRows.value.map(s => s.count)))
const statusPct = (count) => Math.round((count / statusMax.value) * 100)

// ── Graphique SVG ─────────────────────────────────────────────────────────────
const W = 560, H = 110, PAD = 8

const chartPts = computed(() => {
  if (!salesChart.value.length) return []
  const maxRev = Math.max(...salesChart.value.map(d => d.revenue), 1)
  return salesChart.value.map((d, i) => ({
    x: PAD + (i / (salesChart.value.length - 1 || 1)) * (W - PAD * 2),
    y: PAD + (1 - d.revenue / maxRev) * (H - PAD * 2),
    revenue: d.revenue,
    orders:  d.orders,
    date:    d.date,
  }))
})

// Chemin Bezier lissé
function smoothPath(pts) {
  if (pts.length < 2) return ''
  let d = `M ${pts[0].x.toFixed(1)} ${pts[0].y.toFixed(1)}`
  for (let i = 1; i < pts.length; i++) {
    const cx = ((pts[i-1].x + pts[i].x) / 2).toFixed(1)
    d += ` C ${cx} ${pts[i-1].y.toFixed(1)} ${cx} ${pts[i].y.toFixed(1)} ${pts[i].x.toFixed(1)} ${pts[i].y.toFixed(1)}`
  }
  return d
}

const chartLinePath = computed(() => smoothPath(chartPts.value))

const chartAreaPath = computed(() => {
  const pts = chartPts.value
  if (!pts.length) return ''
  const line = smoothPath(pts)
  const last  = pts[pts.length - 1]
  const first = pts[0]
  return `${line} L ${last.x.toFixed(1)} ${H} L ${first.x.toFixed(1)} ${H} Z`
})

// Afficher les points au-dessus de la moyenne (pics)
const chartPeaks = computed(() => {
  const pts = chartPts.value
  if (!pts.length) return []
  const avgY = pts.reduce((s, p) => s + p.y, 0) / pts.length
  return pts.filter(p => p.y < avgY - 8) // y plus petit = valeur plus haute
})

const chartXLabels = computed(() => {
  const data = salesChart.value
  if (!data.length) return ['', '', '']
  // Au mois, « 1 sept. » n'apprend rien : on n'affiche alors que le mois.
  const fmt = (d) => new Date(d).toLocaleDateString('fr-FR',
    periode.value === 'month' ? { month: 'short', year: '2-digit' } : { day: 'numeric', month: 'short' })
  return [
    fmt(data[0].date),
    fmt(data[Math.floor(data.length / 2)].date),
    fmt(data[data.length - 1].date),
  ]
})

const chartTotal       = computed(() => salesChart.value.reduce((s, d) => s + d.revenue, 0))
const chartMax         = computed(() => Math.max(...salesChart.value.map(d => d.revenue), 0))
const chartAvg         = computed(() => salesChart.value.length ? chartTotal.value / salesChart.value.length : 0)
const chartOrdersTotal = computed(() => salesChart.value.reduce((s, d) => s + d.orders, 0))

// ── Helpers ───────────────────────────────────────────────────────────────────
function fmt(v) {
  // Montant masqué (finance privée) → tiret
  if (v === null || v === undefined) return '—'
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', minimumFractionDigits: 0 }).format(Number(v))
}
function fmtCompact(v) {
  const n = Number(v ?? 0)
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace('.0', '') + 'M FCFA'
  if (n >= 1_000)     return (n / 1_000).toFixed(0) + 'k FCFA'
  return n.toLocaleString('fr-FR') + ' FCFA'
}
function formatDate(val) {
  if (!val) return '—'
  return new Date(val).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' })
}
function statusLabel(s) {
  return { pending: 'En attente', confirmed: 'Confirmée', processing: 'En cours',
           shipped: 'Expédiée', delivered: 'Livrée', cancelled: 'Annulée', refunded: 'Remboursée' }[s] ?? s
}
function statusBadge(s) {
  return { pending: 'badge badge-warning', confirmed: 'badge badge-primary',
           processing: 'badge badge-primary', shipped: 'badge badge-primary',
           delivered: 'badge badge-success', cancelled: 'badge badge-danger',
           refunded: 'badge badge-gray' }[s] ?? 'badge badge-gray'
}

/*
 * Granularite de la courbe. La semaine est le defaut, cote serveur comme ici :
 * une boutique qui livre par tournees a des journees en dents de scie, et la
 * lecture au jour le jour ne dit rien de la tendance.
 */
const PERIODES = {
  week:  { label: 'Semaine', titre: '12 dernieres semaines', unite: 'semaine' },
  day:   { label: 'Jour',    titre: '30 derniers jours',     unite: 'jour' },
  month: { label: 'Mois',    titre: '12 derniers mois',      unite: 'mois' },
}

const periode = ref('week')

async function charger() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/dashboard', { params: { period: periode.value } })
    stats.value        = data.stats
    recentOrders.value = data.recent_orders
    lowStock.value     = data.low_stock
    salesChart.value   = data.sales_chart ?? []
    // Le serveur tranche la granularite : une valeur inconnue retombe sur la
    // semaine, et l'ecran doit afficher ce qui a reellement ete rendu.
    if (data.period) periode.value = data.period
  } finally {
    loading.value = false
  }
}

function changerPeriode(cle) {
  if (periode.value === cle || loading.value) return
  periode.value = cle
  charger()
}

onMounted(charger)
</script>

<style scoped>
.dash { display: flex; flex-direction: column; gap: var(--space-5); }

/* ── Welcome bar ── */
.welcome-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: var(--space-4);
  padding: var(--space-5) var(--space-6);
  background: linear-gradient(135deg, var(--rose-50) 0%, #fff 60%);
  border-radius: var(--radius-lg);
  border: 1px solid var(--rose-100);
}
.welcome-bar__date {
  font-size: 0.75rem;
  color: var(--gray-400);
  text-transform: capitalize;
  letter-spacing: 0.03em;
  margin-bottom: 2px;
}
.welcome-bar__title {
  font-family: var(--font-display);
  font-size: 1.5rem;
  font-weight: 500;
  color: var(--gray-800);
}
.welcome-bar__pills {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}
.today-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: var(--radius-full);
  font-size: 0.8125rem;
  font-weight: 400;
  color: var(--gray-700);
}
.today-pill strong { font-weight: 700; }
.today-pill__icon { display: block; flex-shrink: 0; opacity: 0.75; }
.today-pill--orders  { background: var(--rose-50);  border: 1px solid var(--rose-100); }
.today-pill--revenue { background: #dcfce7;          border: 1px solid #bbf7d0; color: #15803d; }
.today-pill--week    { background: #dbeafe;           border: 1px solid #bfdbfe; color: #1d4ed8; }

/* ── KPI Grid ── */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--space-4);
}
.kpi-card {
  background: #fff;
  border-radius: var(--radius-lg);
  border: 1px solid var(--color-border-light, #f0e8dc);
  padding: var(--space-5);
  position: relative;
  overflow: hidden;
  transition: box-shadow 0.2s, transform 0.2s;
}
.kpi-card::before {
  content: '';
  position: absolute;
  left: 0; top: 0; bottom: 0;
  width: 4px;
  background: var(--kpi-accent);
  border-radius: 2px 0 0 2px;
}
.kpi-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.07); transform: translateY(-2px); }

.kpi-card__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-3);
}
.kpi-card__icon-wrap {
  width: 40px; height: 40px;
  border-radius: 10px;
  background: var(--kpi-bg);
  display: flex; align-items: center; justify-content: center;
}
.kpi-card__icon { width: 20px; height: 20px; color: var(--kpi-accent); }

.kpi-card__delta {
  font-size: 0.6875rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: var(--radius-full);
}
.kpi-card__delta--up   { background: #dcfce7; color: #15803d; }
.kpi-card__delta--down { background: #fee2e2; color: #b91c1c; }

.kpi-card__value {
  font-family: var(--font-display);
  font-size: 2rem;
  font-weight: 600;
  color: var(--gray-800);
  letter-spacing: -0.02em;
  line-height: 1;
  margin-bottom: var(--space-1);
}
.kpi-card__label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--gray-700);
}
.kpi-card__sub {
  font-size: 0.75rem;
  color: var(--gray-400);
  margin-top: 4px;
}

/* ── Mid grid ── */
.mid-grid {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: var(--space-5);
}

/* ── Chart ── */
.chart-card { padding: var(--space-5); }
.chart-card__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: var(--space-4);
}
.chart-head__right {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  flex-wrap: wrap;
}
.chart-periodes {
  display: inline-flex;
  gap: 2px;
  padding: 2px;
  border-radius: var(--radius-full);
  background: var(--cream-100);
}
.chart-periode {
  padding: 4px 12px;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--gray-500);
  cursor: pointer;
  transition: all var(--transition-fast);
}
.chart-periode:hover:not(:disabled) { color: var(--rose-600); }
.chart-periode:disabled { cursor: default; opacity: 0.6; }
.chart-periode--active {
  background: #fff;
  color: var(--rose-600);
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

.chart-legend {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.75rem;
  color: var(--gray-500);
}
.chart-legend__dot {
  width: 8px; height: 8px; border-radius: 50%;
}
.chart-legend__dot--rose { background: var(--rose-500); }

.chart-wrap { position: relative; }
.chart-svg {
  width: 100%;
  height: 130px;
  display: block;
}
.chart-xlabels {
  display: flex;
  justify-content: space-between;
  font-size: 0.6875rem;
  color: var(--gray-400);
  margin-top: 6px;
  padding: 0 4px;
}
.chart-empty {
  height: 130px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.875rem;
  color: var(--gray-400);
}

.chart-footer {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--space-3);
  margin-top: var(--space-4);
  padding-top: var(--space-4);
  border-top: 1px solid var(--cream-200);
}
.chart-footer__stat { text-align: center; }
.chart-footer__label {
  display: block;
  font-size: 0.6875rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--gray-400);
  font-weight: 600;
  margin-bottom: 3px;
}
.chart-footer__val {
  font-family: var(--font-sans);
  font-size: 1rem;
  color: var(--gray-800);
}

.skel--chart { height: 130px; border-radius: var(--radius-md); }

/* ── Status card ── */
.status-card { padding: var(--space-5); }

.status-skel { display: flex; flex-direction: column; gap: var(--space-2); }

.status-bars { display: flex; flex-direction: column; gap: var(--space-3); margin-top: var(--space-4); }
.status-row { cursor: pointer; }
.status-row__top {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 5px;
}
.status-row__dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.status-row__label {
  font-size: 0.8125rem;
  color: var(--gray-600);
  flex: 1;
}
.status-row__count {
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--gray-800);
}
.status-row__track {
  height: 6px;
  background: var(--cream-100);
  border-radius: 3px;
  overflow: hidden;
}
.status-row__bar {
  height: 100%;
  border-radius: 3px;
  transition: width 0.6s cubic-bezier(.4,0,.2,1);
  opacity: 0.85;
}
.status-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: var(--space-4);
  padding-top: var(--space-3);
  border-top: 1px solid var(--cream-200);
  font-size: 0.8125rem;
  color: var(--gray-400);
}
.status-total strong { color: var(--gray-700); font-weight: 700; }

/* ── Dash grid (bas) ── */
.dash-grid {
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: var(--space-5);
}
.dash-orders, .dash-stock { padding: var(--space-5); }

/* ── Section heads ── */
.section-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: var(--space-4);
}
.section-title {
  font-family: var(--font-display);
  font-size: 1.125rem;
  font-weight: 500;
  color: var(--gray-800);
  margin-top: 3px;
}
.eyebrow {
  display: block;
  font-size: 0.625rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--rose-400);
  font-weight: 700;
  margin-bottom: 1px;
}
.dash-link {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: var(--rose-500);
  font-size: 0.8125rem;
  font-weight: 500;
  transition: gap var(--transition-fast);
  white-space: nowrap;
  margin-top: 4px;
}
.dash-link:hover { gap: 8px; }

/* ── Skeletons ── */
.skel-rows { display: flex; flex-direction: column; gap: var(--space-2); }
.skel {
  border-radius: var(--radius-md);
  background: var(--cream-100);
  animation: shimmer 1.4s ease-in-out infinite;
}
@keyframes shimmer {
  0%, 100% { opacity: 1 }
  50%       { opacity: 0.45 }
}

/* ── Dash table ── */
.dash-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}
.dash-table thead th {
  text-align: left;
  font-size: 0.625rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--gray-400);
  padding: 0 0 var(--space-3);
  border-bottom: 1px solid var(--cream-200);
}
.dash-table tbody tr {
  cursor: pointer;
  transition: background 0.12s;
}
.dash-table tbody tr:hover td { background: var(--rose-50); }
.dash-table tbody td {
  padding: var(--space-3) var(--space-1);
  border-bottom: 1px solid var(--cream-100);
  color: var(--gray-600);
  vertical-align: middle;
}
.dash-table tbody tr:last-child td { border-bottom: none; }
.dash-table__num   { font-family: ui-monospace, monospace; font-size: 0.75rem; color: var(--gray-500); }
.dash-table__client { font-weight: 500; color: var(--gray-800); }
.dash-table__total { font-weight: 700; color: var(--rose-600); }
.dash-table__date  { font-size: 0.75rem; color: var(--gray-400); white-space: nowrap; }

/* ── Low stock ── */
.empty-state {
  text-align: center;
  padding: var(--space-8) 0;
  color: var(--gray-400);
  font-size: 0.875rem;
}
.empty-state__icon { display: block; font-size: 2rem; margin-bottom: var(--space-2); }

.stock-list { list-style: none; display: flex; flex-direction: column; gap: var(--space-2); }
.stock-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-md);
  background: #fffbeb;
  border: 1px solid #fde68a;
}
.stock-item__info { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.stock-item__name {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--gray-800);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.stock-item__threshold { font-size: 0.6875rem; color: var(--gray-400); }
.stock-item__badge {
  flex-shrink: 0;
  font-size: 0.6875rem;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: var(--radius-full);
  letter-spacing: 0.04em;
}
.stock-item__badge--low   { background: #fef3c7; color: #92400e; }
.stock-item__badge--empty { background: #fee2e2; color: #b91c1c; }

/* ── Responsive ── */
@media (max-width: 1200px) {
  .mid-grid  { grid-template-columns: 1fr; }
  .dash-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
  .kpi-grid         { grid-template-columns: repeat(2, 1fr); }
  .welcome-bar      { flex-direction: column; align-items: flex-start; }
  .chart-footer     { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 480px) {
  .kpi-grid { grid-template-columns: 1fr 1fr; }
}
</style>
