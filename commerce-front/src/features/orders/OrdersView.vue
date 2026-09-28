<template>
  <main class="orders-page">
    <!-- Hero -->
    <section class="orders-hero">
      <div class="container">
        <span class="eyebrow">{{ $t('orders.eyebrow') }}</span>
        <h1 class="display-lg orders-hero__title">
          {{ $t('orders.title') }}
        </h1>
        <p class="orders-hero__desc">{{ $t('orders.desc') }}</p>
      </div>
    </section>

    <div class="container orders-content">
      <!-- Tabs -->
      <div class="orders-tabs">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          class="orders-tab"
          :class="{ 'orders-tab--active': activeTab === tab.value }"
          @click="selectTab(tab.value)"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="orders-loader">
        <div class="orders-loader__spinner"></div>
      </div>

      <!-- Empty -->
      <div v-else-if="orders.length === 0" class="orders-empty card">
        <div class="orders-empty__icon"><FlowerMark /></div>
        <h2 class="display-md">{{ $t('orders.empty') }}</h2>
        <p>{{ $t('orders.emptyDesc') }}</p>
        <RouterLink to="/products" class="btn btn-primary btn-lg">
          {{ $t('common.discoverProducts') }}
        </RouterLink>
      </div>

      <!-- List -->
      <div v-else class="orders-list">
        <div v-for="order in orders" :key="order.id" class="order-row card card-hover">
          <div class="order-row__main">
            <div class="order-row__head">
              <span class="order-row__number">{{ order.number }}</span>
              <span :class="statusBadge(order.status)">{{ statusLabel(order.status) }}</span>
            </div>
            <p class="order-row__date">{{ formatDate(order.created_at) }}</p>
            <p class="order-row__items">{{ $t('orders.articleCount', { count: order.items?.length ?? '—' }) }}</p>
          </div>

          <div class="order-row__right">
            <p class="order-row__total">{{ formatPrice(order.total) }}</p>
            <RouterLink
              v-if="order.number"
              :to="{ name: 'order', params: { number: order.number } }"
              class="order-row__link"
            >
              {{ $t('orders.viewDetail') }}
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M5 12h14M12 5l7 7-7 7"/>
              </svg>
            </RouterLink>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="pagination && pagination.last_page > 1" class="orders-pagination">
        <button
          class="btn btn-outline btn-sm"
          :disabled="pagination.current_page <= 1"
          @click="goToPage(pagination.current_page - 1)"
        >
          {{ $t('orders.prev') }}
        </button>
        <span class="orders-pagination__info">
          {{ pagination.current_page }} / {{ pagination.last_page }}
        </span>
        <button
          class="btn btn-outline btn-sm"
          :disabled="pagination.current_page >= pagination.last_page"
          @click="goToPage(pagination.current_page + 1)"
        >
          {{ $t('orders.next') }}
        </button>
      </div>
    </div>
  </main>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useCurrencyStore } from '@/stores/currency'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/api'

const { t } = useI18n()
const orders    = ref([])
const loading   = ref(true)
const activeTab = ref('')
const pagination = ref(null)

const STATUSES = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'] // 'refunded'

const tabs = computed(() => [
  { value: '', label: t('orders.tabAll') },
  ...STATUSES.map(s => ({ value: s, label: t(`orders.status.${s}`) })),
])

async function fetchOrders(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: 10 }
    if (activeTab.value) params.status = activeTab.value
    const { data } = await api.get('/orders', { params })
    orders.value    = data.data ?? data
    pagination.value = data.meta ?? null
  } catch {
    orders.value = []
  } finally {
    loading.value = false
  }
}

function selectTab(value) {
  activeTab.value = value
  fetchOrders(1)
}

function goToPage(page) {
  fetchOrders(page)
}

function formatDate(val) {
  if (!val) return '—'
  return new Date(val).toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' })
}

function formatPrice(val) {
  return useCurrencyStore().format(val ?? 0)
}

function statusLabel(status) {
  return t(`orders.status.${status}`) || status
}

function statusBadge(status) {
  const map = {
    pending:    'badge badge-warning',
    processing: 'badge badge-primary',
    shipped:    'badge badge-primary',
    delivered:  'badge badge-success',
    cancelled:  'badge badge-danger',
    // refunded:   'badge badge-gray',
  }
  return map[status] ?? 'badge badge-gray'
}

onMounted(() => fetchOrders())
</script>

<style scoped>
.orders-page { min-height: 100vh; background: var(--color-bg); }

.orders-hero {
  background: linear-gradient(160deg, var(--rose-50) 0%, var(--cream-100) 100%);
  padding: var(--space-12) 0 var(--space-10);
  border-bottom: 1px solid var(--cream-200);
}
.orders-hero__title { color: var(--gray-800); margin-top: var(--space-2); }
.orders-hero__title em { font-style: italic; color: var(--color-primary); }
.orders-hero__desc {
  color: var(--gray-500);
  font-size: 0.9375rem;
  margin-top: var(--space-2);
  max-width: 540px;
}

.orders-content {
  padding: var(--space-10) var(--space-6) var(--space-16);
  max-width: 880px;
}

/* ── Tabs ── */
.orders-tabs {
  display: flex;
  gap: var(--space-2);
  flex-wrap: wrap;
  margin-bottom: var(--space-6);
}
.orders-tab {
  padding: var(--space-1) var(--space-3);
  border-radius: var(--radius-full);
  border: 1.5px solid var(--cream-300);
  background: #fff;
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--gray-600);
  cursor: pointer;
  transition: all var(--transition-fast);
  white-space: nowrap;
}
.orders-tab:hover { border-color: var(--rose-300); color: var(--rose-500); }
.orders-tab--active {
  background: var(--rose-500);
  border-color: var(--rose-500);
  color: #fff;
}

/* ── Loader ── */
.orders-loader { display: flex; justify-content: center; padding: var(--space-16) 0; }
.orders-loader__spinner {
  width: 32px; height: 32px;
  border: 2px solid var(--rose-100);
  border-top-color: var(--rose-500);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Empty ── */
.orders-empty {
  text-align: center;
  padding: var(--space-16);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-4);
}
.orders-empty__icon { font-size: 4rem; opacity: 0.6; }
.orders-empty p { color: var(--gray-500); max-width: 360px; }

/* ── List ── */
.orders-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.order-row {
  display: flex;
  align-items: center;
  gap: var(--space-4);
  padding: var(--space-5) var(--space-6);
  flex-wrap: wrap;
}
.order-row__main { flex: 1; min-width: 0; }
.order-row__head {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  margin-bottom: var(--space-1);
  flex-wrap: wrap;
}
.order-row__number {
  font-family: var(--font-display);
  font-size: 1.0625rem;
  font-weight: 600;
  color: var(--gray-800);
  letter-spacing: 0.02em;
}
.order-row__date {
  font-size: 0.8125rem;
  color: var(--gray-500);
}
.order-row__items {
  font-size: 0.75rem;
  color: var(--gray-400);
  margin-top: 2px;
}

.order-row__right {
  text-align: right;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: var(--space-1);
}
.order-row__total {
  font-family: var(--font-sans);
  font-size: 1.375rem;
  font-weight: 600;
  color: var(--rose-600);
}
.order-row__link {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--rose-500);
  transition: gap var(--transition-fast);
}
.order-row__link:hover { gap: var(--space-2); }

/* ── Pagination ── */
.orders-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-4);
  margin-top: var(--space-8);
}
.orders-pagination__info {
  font-size: 0.875rem;
  color: var(--gray-500);
  min-width: 60px;
  text-align: center;
}

@media (max-width: 540px) {
  .order-row__right { text-align: left; align-items: flex-start; }
}
</style>
