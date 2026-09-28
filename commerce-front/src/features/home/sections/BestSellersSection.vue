<template>
  <section class="featured-section">
    <div class="container">
      <div class="featured-header">
        <div>
          <span class="eyebrow">{{ $t('bestSellers.eyebrow') }}</span>
          <h2 class="display-md featured-title">{{ $t('bestSellers.title') }}</h2>
          <p class="section-desc">{{ $t('bestSellers.desc') }}</p>
        </div>
        <RouterLink
          :to="activeTab === 'products' ? '/products' : '/products'"
          class="btn btn-outline hide-mobile"
        >
          {{ $t('bestSellers.seeAll') }}
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
        </RouterLink>
      </div>

      <!-- Tabs : uniquement si des gammes existent -->
      <div v-if="productLines.length > 0" class="featured-tabs">
        <button
          class="featured-tab"
          :class="{ 'featured-tab--active': activeTab === 'products' }"
          @click="activeTab = 'products'"
        >
          {{ $t('bestSellers.tabProducts') }}
        </button>
        <button
          class="featured-tab"
          :class="{ 'featured-tab--active': activeTab === 'lines' }"
          @click="activeTab = 'lines'"
        >
          {{ $t('bestSellers.tabLines') }}
        </button>
      </div>

      <!-- ── Produits (toujours visible si pas de gammes, sinon via tab) ── -->
      <div v-if="productLines.length === 0 || activeTab === 'products'">
        <div v-if="loading" class="products-grid">
          <div v-for="i in 4" :key="i" class="product-skeleton" />
        </div>
        <div v-else class="products-grid">
          <ProductCard
            v-for="p in products"
            :key="p.id"
            :product="p"
            @add-to-cart="$emit('add-to-cart', $event)"
          />
        </div>
      </div>

      <!-- ── Tab: Gammes ── -->
      <div v-if="productLines.length > 0 && activeTab === 'lines'">
        <div v-if="loadingLines" class="lines-grid">
          <div v-for="i in 3" :key="i" class="line-skeleton" />
        </div>
        <div v-else class="lines-grid">
          <RouterLink
            v-for="line in productLines"
            :key="line.id"
            :to="`/gammes/${line.slug}`"
            class="line-card"
          >
            <div class="line-card__img-wrap">
              <img v-if="line.cover_url" :src="line.cover_url" :alt="line.name" class="line-card__img" />
              <!-- Pas de couverture → décor listant les produits composants -->
              <div
                v-else
                class="line-card__deco"
                :style="{ '--line-color': line.color_hex || 'var(--rose-500)' }"
              >
                <span class="line-card__deco-flower" aria-hidden="true"><FlowerMark /></span>
                <ul v-if="line.item_names?.length" class="line-card__deco-list">
                  <li
                    v-for="(name, i) in line.item_names.slice(0, 4)"
                    :key="i"
                    class="line-card__deco-item"
                  >{{ name }}</li>
                  <li
                    v-if="line.item_names.length > 4"
                    class="line-card__deco-item line-card__deco-item--more"
                  >{{ $t('bestSellers.moreItems', { count: line.item_names.length - 4 }) }}</li>
                </ul>
                <span v-else class="line-card__deco-name">{{ line.name }}</span>
              </div>
            </div>
            <div class="line-card__body">
              <h3 class="line-card__name">{{ line.name }}</h3>
              <p v-if="line.tagline" class="line-card__tagline">{{ line.tagline }}</p>
              <span class="line-card__count">{{ line.products_count ?? 0 }} {{ $t('products.product', line.products_count ?? 0) }}</span>
              <span class="line-card__cta">{{ $t('bestSellers.discover') }} →</span>
            </div>
          </RouterLink>
        </div>
      </div>

      <!-- CTA mobile -->
      <div class="featured-cta hide-desktop">
        <RouterLink to="/products" class="btn btn-outline">{{ $t('bestSellers.seeAll') }}</RouterLink>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import ProductCard from '@/components/ui/ProductCard.vue'
import api from '@/api'

defineProps({
  products: { type: Array,   required: true },
  loading:  { type: Boolean, default: false },
})
defineEmits(['add-to-cart'])

const activeTab    = ref('products')
const productLines = ref([])
const loadingLines = ref(false)

onMounted(async () => {
  loadingLines.value = true
  try {
    const res = await api.get('/product-lines')
    productLines.value = res.data.data ?? res.data
  } catch { /* silencieux */ }
  finally { loadingLines.value = false }
})
</script>

<style scoped>
.featured-section {
  padding: var(--space-16) 0;
  background: linear-gradient(160deg, var(--rose-50) 0%, var(--cream-100) 100%);
}

.featured-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  margin-bottom: var(--space-8);
  gap: var(--space-4);
}

.section-desc {
  font-size: 0.9375rem;
  color: var(--gray-400);
}

/* ── Tabs ── */
.featured-tabs {
  display: flex;
  gap: 4px;
  margin-bottom: var(--space-8);
  background: var(--color-surface);
  border-radius: var(--radius-full);
  padding: 4px;
  width: fit-content;
}

.featured-tab {
  padding: 8px 24px;
  border-radius: var(--radius-full);
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--gray-500);
  transition: all var(--transition-fast);
  background: transparent;
}

.featured-tab--active {
  background: var(--rose-500);
  color: #fff;
  box-shadow: 0 2px 8px rgba(var(--rose-500-rgb), 0.25);
}

.featured-tab:hover:not(.featured-tab--active) {
  color: var(--gray-700);
  background: var(--cream-100);
}

/* ── Produits grid ── */
.products-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--space-5);
}

.product-skeleton {
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  aspect-ratio: 3/4;
  animation: pulse 1.5s ease-in-out infinite;
}

/* ── Gammes grid ── */
.lines-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-5);
}

.line-skeleton {
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  height: 320px;
  animation: pulse 1.5s ease-in-out infinite;
}

.line-card {
  display: flex;
  flex-direction: column;
  background: #fff;
  border-radius: var(--radius-xl);
  overflow: hidden;
  text-decoration: none;
  box-shadow: 0 2px 12px rgba(0,0,0,0.05);
  transition: all var(--transition-normal);
  border: 1px solid var(--cream-200);
}
.line-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 32px rgba(0,0,0,0.10);
}

.line-card__img-wrap {
  aspect-ratio: 4/3;
  overflow: hidden;
  background: var(--cream-50);
  display: flex;
  align-items: center;
  justify-content: center;
}
.line-card__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.5s ease;
}
.line-card:hover .line-card__img { transform: scale(1.04); }

/* ── Décor de repli (gamme sans couverture) ── */
.line-card__deco {
  position: relative;
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--space-3);
  padding: var(--space-5) var(--space-4);
  overflow: hidden;
  background: var(--cream-100); /* repli si color-mix non supporté */
  background:
    radial-gradient(circle at 30% 20%, color-mix(in srgb, var(--line-color) 18%, #fff), transparent 60%),
    linear-gradient(140deg, color-mix(in srgb, var(--line-color) 10%, #fff), var(--cream-50));
}
/* Filet décoratif en haut */
.line-card__deco::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg, transparent, var(--line-color), transparent);
  opacity: 0.5;
}
/* Fleur en filigrane, grande et discrète */
.line-card__deco-flower {
  position: absolute;
  right: -18px;
  bottom: -22px;
  font-size: 7rem;
  line-height: 1;
  color: var(--line-color);
  opacity: 0.10;
  transform: rotate(-12deg);
  pointer-events: none;
}
.line-card__deco-list {
  position: relative;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 7px;
  text-align: center;
  width: 100%;
}
.line-card__deco-item {
  font-family: var(--font-display);
  font-size: 0.9375rem;
  font-weight: 500;
  color: var(--gray-700);
  line-height: 1.3;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
}
/* Puce florale devant chaque composant */
.line-card__deco-item::before {
  content: '';
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: var(--line-color);
  flex-shrink: 0;
  opacity: 0.7;
}
.line-card__deco-item--more {
  font-family: var(--font-body);
  font-size: 0.75rem;
  font-style: italic;
  color: var(--gray-400);
}
.line-card__deco-item--more::before { display: none; }
.line-card__deco-name {
  position: relative;
  font-family: var(--font-display);
  font-size: 1.5rem;
  font-weight: 500;
  color: var(--gray-700);
  text-align: center;
  letter-spacing: -0.01em;
}

.line-card__body {
  padding: var(--space-5) var(--space-6);
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.line-card__name {
  font-family: var(--font-display);
  font-size: 1.125rem;
  font-weight: 500;
  color: var(--gray-800);
}
.line-card__tagline {
  font-size: 0.875rem;
  color: var(--gray-500);
  line-height: 1.5;
}
.line-card__count {
  font-size: 0.75rem;
  color: var(--gray-400);
  margin-top: 4px;
}
.line-card__cta {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-primary);
  margin-top: var(--space-2);
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0.5; }
}

.featured-cta {
  text-align: center;
  margin-top: var(--space-8);
}

@media (max-width: 1024px) {
  .products-grid   { grid-template-columns: repeat(2, 1fr); }
  .lines-grid      { grid-template-columns: repeat(2, 1fr); }
  .featured-header { flex-direction: column; align-items: flex-start; }
}

@media (max-width: 640px) {
  .lines-grid { grid-template-columns: 1fr; }
}
</style>
