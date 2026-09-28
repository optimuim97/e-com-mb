<template>
  <main class="wishlist-page">
    <!-- Hero -->
    <section class="wishlist-hero">
      <div class="container">
        <span class="eyebrow">{{ $t('wishlist.eyebrow') }}</span>
        <h1 class="display-lg wishlist-hero__title">
          {{ $t('wishlist.title') }}
        </h1>
        <p class="wishlist-hero__desc">
          {{ $t('wishlist.desc') }}
        </p>
      </div>
    </section>

    <div class="container wishlist-content">
      <!-- Loading -->
      <div v-if="wishlist.loading" class="wishlist-loader">
        <div class="wishlist-loader__spinner"></div>
      </div>

      <!-- Vide -->
      <div v-else-if="wishlist.items.length === 0" class="wishlist-empty card">
        <div class="wishlist-empty__icon"><FlowerMark /></div>
        <h2 class="display-md">{{ $t('wishlist.empty') }}</h2>
        <p>{{ $t('wishlist.emptyDesc') }}</p>
        <RouterLink to="/products" class="btn btn-primary btn-lg">
          {{ $t('common.discoverProducts') }}
        </RouterLink>
      </div>

      <!-- Liste -->
      <template v-else>
        <p class="wishlist-count">{{ wishlist.items.length }} {{ $t('common.article', wishlist.items.length) }}</p>

        <div class="wishlist-grid">
          <div
            v-for="item in wishlist.items"
            :key="item.product_id"
            class="wishlist-card card card-hover"
          >
            <!-- Image -->
            <RouterLink :to="`/products/${item.product_slug}`" class="wishlist-card__img-wrap">
              <img
                v-if="item.product_image"
                :src="item.product_image"
                :alt="item.product_name"
                class="wishlist-card__img"
                @error="e => e.target.style.display = 'none'"
              />
              <div v-else class="wishlist-card__fallback"><FlowerMark /></div>

              <!-- Badge rupture -->
              <span v-if="unavailableLabelKey(item, 'common')" class="wishlist-card__badge">{{ $t(unavailableLabelKey(item, 'common')) }}</span>
              <!-- Badge promo -->
              <span v-else-if="item.discount_percent" class="wishlist-card__badge wishlist-card__badge--promo">
                -{{ item.discount_percent }}%
              </span>
            </RouterLink>

            <!-- Infos -->
            <div class="wishlist-card__body">
              <RouterLink :to="`/products/${item.product_slug}`" class="wishlist-card__name">
                {{ item.product_name }}
              </RouterLink>

              <div class="wishlist-card__price-row">
                <span class="wishlist-card__price">{{ formatPrice(item.product_price) }}</span>
                <span v-if="item.product_compare" class="wishlist-card__compare">
                  {{ formatPrice(item.product_compare) }}
                </span>
              </div>

              <p class="wishlist-card__date">{{ $t('wishlist.addedOn', { date: formatDate(item.added_at) }) }}</p>
            </div>

            <!-- Actions -->
            <div class="wishlist-card__actions">
              <button
                v-if="isOrderable(item)"
                class="btn btn-primary btn-sm"
                @click="addToCart(item)"
                :disabled="addingId === item.product_id"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                  <line x1="3" y1="6" x2="21" y2="6"/>
                  <path d="M16 10a4 4 0 0 1-8 0"/>
                </svg>
                {{ addingId === item.product_id ? '…' : $t('common.addToCart') }}
              </button>
              <!--
                Quota atteint : « de retour lundi ». C'est sur la liste d'envies
                que la cliente revient voir, elle doit savoir quand repasser.
              -->
              <span v-else class="wishlist-card__stock-badge">{{ $t(unavailableLabelKey(item, 'common') ?? 'common.outOfStock') }}</span>

              <button
                class="wishlist-card__remove"
                @click="remove(item.product_id)"
                :aria-label="$t('wishlist.removeLabel')"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                  <polyline points="3 6 5 6 21 6"/>
                  <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                  <path d="M10 11v6M14 11v6"/>
                  <path d="M9 6V4h6v2"/>
                </svg>
              </button>
            </div>
          </div>
        </div>
      </template>
    </div>
  </main>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useCurrencyStore } from '@/stores/currency'
import { RouterLink } from 'vue-router'
import { useWishlistStore } from '@/features/wishlist/wishlist.store'
import { useCartStore } from '@/features/cart/cart.store'
import { isOrderable, unavailableLabelKey } from '@/utils/availability'

const wishlist  = useWishlistStore()
const cart      = useCartStore()
const addingId  = ref(null)

onMounted(wishlist.fetch)

function formatDate(val) {
  if (!val) return ''
  return new Date(val).toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' })
}

function formatPrice(val) {
  return useCurrencyStore().format(val ?? 0)
}

async function addToCart(item) {
  if (addingId.value) return
  addingId.value = item.product_id
  try {
    await cart.add(item.product_id, 1)
  } finally {
    addingId.value = null
  }
}

async function remove(productId) {
  await wishlist.remove(productId)
}
</script>

<style scoped>
.wishlist-page { min-height: 100vh; background: var(--color-bg); }

.wishlist-hero {
  background: linear-gradient(160deg, var(--rose-50) 0%, var(--cream-100) 100%);
  padding: var(--space-12) 0 var(--space-10);
  border-bottom: 1px solid var(--cream-200);
}
.wishlist-hero__title { color: var(--gray-800); margin-top: var(--space-2); }
.wishlist-hero__title em { font-style: italic; color: var(--color-primary); }
.wishlist-hero__desc {
  color: var(--gray-500);
  font-size: 0.9375rem;
  margin-top: var(--space-2);
  max-width: 540px;
}

.wishlist-content {
  padding: var(--space-10) var(--space-6) var(--space-16);
}

/* Loader */
.wishlist-loader { display: flex; justify-content: center; padding: var(--space-16) 0; }
.wishlist-loader__spinner {
  width: 32px; height: 32px;
  border: 2px solid var(--rose-100);
  border-top-color: var(--rose-500);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Vide */
.wishlist-empty {
  text-align: center;
  padding: var(--space-16);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-4);
  max-width: 480px;
  margin: 0 auto;
}
.wishlist-empty__icon { font-size: 4rem; opacity: 0.6; }
.wishlist-empty p { color: var(--gray-500); }

/* Compteur */
.wishlist-count {
  color: var(--gray-400);
  font-size: 0.875rem;
  margin-bottom: var(--space-6);
}

/* Grille */
.wishlist-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: var(--space-5);
}

/* Card */
.wishlist-card {
  display: flex;
  flex-direction: column;
  border-radius: var(--radius-xl);
  overflow: hidden;
}

.wishlist-card__img-wrap {
  display: block;
  position: relative;
  aspect-ratio: 4/3;
  overflow: hidden;
  background: var(--rose-50);
}
.wishlist-card__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s ease;
}
.wishlist-card:hover .wishlist-card__img { transform: scale(1.05); }

.wishlist-card__fallback {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3rem;
}

.wishlist-card__badge {
  position: absolute;
  top: var(--space-2);
  left: var(--space-2);
  background: var(--gray-800);
  color: #fff;
  font-size: 0.6875rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: var(--radius-full);
}
.wishlist-card__badge--promo {
  background: var(--rose-500);
}

.wishlist-card__body {
  padding: var(--space-4);
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.wishlist-card__name {
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--gray-800);
  line-height: 1.3;
  transition: color var(--transition-fast);
}
.wishlist-card__name:hover { color: var(--color-primary); }

.wishlist-card__price-row {
  display: flex;
  align-items: baseline;
  gap: var(--space-2);
  margin-top: var(--space-1);
}
.wishlist-card__price {
  font-family: var(--font-sans);
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--rose-600);
}
.wishlist-card__compare {
  font-size: 0.8125rem;
  color: var(--gray-400);
  text-decoration: line-through;
}

.wishlist-card__date {
  font-size: 0.75rem;
  color: var(--gray-400);
  margin-top: auto;
  padding-top: var(--space-2);
}

.wishlist-card__actions {
  padding: var(--space-3) var(--space-4) var(--space-4);
  display: flex;
  align-items: center;
  gap: var(--space-2);
  border-top: 1px solid var(--cream-200);
}

.wishlist-card__stock-badge {
  font-size: 0.75rem;
  color: var(--gray-400);
  font-style: italic;
  flex: 1;
}

.wishlist-card__actions .btn { flex: 1; justify-content: center; gap: var(--space-1); }

.wishlist-card__remove {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: var(--radius-full);
  color: var(--gray-400);
  transition: all var(--transition-fast);
  flex-shrink: 0;
}
.wishlist-card__remove:hover {
  color: var(--rose-500);
  background: var(--rose-50);
}
</style>
