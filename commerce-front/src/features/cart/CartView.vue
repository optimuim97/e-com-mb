<template>
  <main class="cart-page">
    <!-- En-tête -->
    <section class="cart-hero">
      <div class="container">
        <span class="eyebrow">{{ $t('cart.eyebrow') }}</span>
        <h1 class="display-lg cart-hero__title">
          {{ $t('cart.title') }}
        </h1>
        <p v-if="cartStore.cart.items?.length" class="cart-hero__count">
          {{ $t('cart.itemCount', cartStore.cart.items.length) }}
        </p>
      </div>
    </section>

    <div class="container cart-content">
      <!-- Empty -->
      <div v-if="!cartStore.cart.items?.length" class="cart-empty card">
        <div class="cart-empty__icon"><FlowerMark /></div>
        <h2 class="display-md">{{ $t('cart.empty') }}</h2>
        <p>{{ $t('cart.emptyDesc') }}</p>
        <RouterLink to="/products" class="btn btn-primary btn-lg">
          {{ $t('common.discoverProducts') }}
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
        </RouterLink>
      </div>

      <div v-else class="cart-grid">
        <!-- Items -->
        <div class="cart-items">
          <div v-for="item in cartStore.cart.items" :key="item.id" class="cart-item card">
            <div class="cart-item__img-wrap">
              <img v-if="item.product?.images?.[0]?.url" :src="item.product.images[0].url"
                :alt="item.product.name" class="cart-item__img" />
              <div v-else class="cart-item__img-fallback"><FlowerMark /></div>
            </div>

            <div class="cart-item__info">
              <span class="cart-item__cat">{{ item.product?.category?.name }}</span>
              <h3 class="cart-item__name">{{ item.product?.name }}</h3>
              <p v-if="item.variant" class="cart-item__variant">{{ item.variant.label }}</p>
              <p class="cart-item__price">{{ formatPrice(item.unit_price) }}</p>
            </div>

            <div class="cart-item__actions">
              <button @click="removeItem(item.id)"
                :disabled="removingItemId === item.id"
                class="cart-item__remove" :aria-label="$t('cart.remove')">
                <span v-if="removingItemId === item.id" class="cart-item__spinner"></span>
                <XMarkIcon v-else class="w-4 h-4" />
              </button>
              <div class="cart-item__qty">
                <button @click="changeQty(item, -1)" :disabled="loadingItemId === item.id"
                  class="cart-item__qty-btn" :aria-label="$t('cart.decrease')">
                  <span v-if="loadingItemId === item.id" class="cart-item__spinner"></span>
                  <MinusIcon v-else class="w-3 h-3" />
                </button>
                <span class="cart-item__qty-value">{{ item.quantity }}</span>
                <button @click="changeQty(item, 1)" :disabled="loadingItemId === item.id"
                  class="cart-item__qty-btn cart-item__qty-btn--primary" :aria-label="$t('cart.increase')">
                  <PlusIcon class="w-3 h-3" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Récap -->
        <aside class="cart-summary card">
          <h2 class="cart-summary__title">{{ $t('cart.summary') }}</h2>

          <!-- Coupon -->
          <div class="cart-summary__coupon">
            <div v-if="!cartStore.cart.coupon" class="cart-summary__coupon-form">
              <input v-model="couponCode" class="input" :placeholder="$t('cart.promoPlaceholder')"
                :disabled="couponLoading" @keydown.enter="applyCoupon" />
              <button @click="applyCoupon"
                :disabled="couponLoading"
                class="btn btn-outline btn-sm cart-summary__coupon-btn">
                <span v-if="couponLoading" class="cart-item__spinner"></span>
                <span v-else>{{ $t('common.apply') }}</span>
              </button>
            </div>
            <p v-if="couponError" class="cart-summary__coupon-error">{{ couponError }}</p>
            <div v-if="cartStore.cart.coupon" class="cart-summary__coupon-applied">
              <span>{{ cartStore.cart.coupon.code }}</span>
              <button @click="removeCoupon" :disabled="removingCoupon" :aria-label="$t('cart.removeCoupon')">
                <span v-if="removingCoupon" class="cart-item__spinner"></span>
                <XMarkIcon v-else class="w-4 h-4" />
              </button>
            </div>
          </div>

          <ul class="cart-summary__lines">
            <li>
              <span>{{ $t('common.subtotal') }}</span>
              <span>{{ formatPrice(cartStore.cart.subtotal) }}</span>
            </li>
            <li v-if="cartStore.cart.discount > 0" class="cart-summary__line--discount">
              <span>{{ $t('common.discount') }}</span>
              <span>-{{ formatPrice(cartStore.cart.discount) }}</span>
            </li>
            <li class="cart-summary__line--total">
              <span>{{ $t('common.total') }}</span>
              <span>{{ formatPrice(cartStore.cart.total) }}</span>
            </li>
          </ul>

          <RouterLink :to="{name : 'checkout'}" class="btn btn-primary btn-lg cart-summary__cta">
            {{ $t('cart.checkout') }}
          </RouterLink>

          <p class="cart-summary__guarantee">
            {{ $t('cart.guarantee') }}
          </p>
        </aside>
      </div>
    </div>
  </main>
</template>

<script setup>
import {onMounted, ref} from 'vue';
import { useCurrencyStore } from '@/stores/currency';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { XMarkIcon, PlusIcon, MinusIcon } from '@heroicons/vue/24/outline';
import { useCartStore } from '@/features/cart/cart.store';

const { t } = useI18n();
const cartStore       = useCartStore();
const couponCode      = ref('');
const couponLoading   = ref(false);
const couponError     = ref('');
const removingCoupon  = ref(false);
const loadingItemId   = ref(null);
const removingItemId  = ref(null);

async function changeQty(item, delta) {
  if (loadingItemId.value) return;
  loadingItemId.value = item.id;
  try {
    const newQty = item.quantity + delta;
    if (newQty <= 0) await cartStore.remove(item.id);
    else await cartStore.update(item.id, newQty);
  } finally {
    loadingItemId.value = null;
  }
}

async function removeItem(itemId) {
  if (removingItemId.value) return;
  removingItemId.value = itemId;
  try {
    await cartStore.remove(itemId);
  } finally {
    removingItemId.value = null;
  }
}

async function removeCoupon() {
  removingCoupon.value = true;
  try {
    await cartStore.removeCoupon();
  } finally {
    removingCoupon.value = false;
  }
}

function formatPrice(price) {
  return useCurrencyStore().format(price ?? 0);
}

async function applyCoupon() {
  if (!couponCode.value.trim()) return;
  couponLoading.value = true;
  couponError.value   = '';
  try {
    await cartStore.applyCoupon(couponCode.value.trim());
    couponCode.value = '';
  } catch (e) {
    if (!e._serverError) {
      couponError.value = e.response?.data?.message ?? t('cart.codeInvalid');
    }
  } finally {
    couponLoading.value = false;
  }
}

// onMounted(async () => {
//    await cartStore.fetch();
// })
</script>

<style scoped>
.cart-page {
  min-height: 100vh;
  background: var(--color-bg);
}

/* ── Hero ── */
.cart-hero {
  background: linear-gradient(160deg, var(--rose-50) 0%, var(--cream-100) 100%);
  padding: var(--space-12) 0 var(--space-10);
  border-bottom: 1px solid var(--cream-200);
}
.cart-hero__title {
  color: var(--gray-800);
  margin-top: var(--space-2);
}
.cart-hero__title em { font-style: italic; color: var(--color-primary); }
.cart-hero__count {
  margin-top: var(--space-2);
  color: var(--gray-500);
  font-size: 0.9375rem;
}

.cart-content { padding: var(--space-10) var(--space-6) var(--space-16); }

/* ── Empty ── */
.cart-empty {
  text-align: center;
  padding: var(--space-16) var(--space-8);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-4);
}
.cart-empty__icon { font-size: 4rem; opacity: 0.6; }
.cart-empty p { color: var(--gray-500); max-width: 360px; }

/* ── Grid ── */
.cart-grid {
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: var(--space-8);
  align-items: start;
}

/* ── Items ── */
.cart-items {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.cart-item {
  display: grid;
  grid-template-columns: 96px 1fr auto;
  gap: var(--space-4);
  padding: var(--space-4);
  align-items: center;
}

.cart-item__img-wrap {
  width: 96px;
  height: 96px;
  border-radius: var(--radius-md);
  overflow: hidden;
  background: linear-gradient(145deg, var(--rose-50), var(--cream-100));
}
.cart-item__img {
  width: 100%; height: 100%; object-fit: cover;
}
.cart-item__img-fallback {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  font-size: 2rem;
}

.cart-item__info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.cart-item__cat {
  font-size: 0.6875rem;
  font-weight: 500;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--rose-400);
}
.cart-item__name {
  font-family: var(--font-display);
  font-size: 1.0625rem;
  font-weight: 500;
  color: var(--gray-800);
  line-height: 1.3;
}
.cart-item__variant { font-size: 0.75rem; color: var(--gray-400); }
.cart-item__price {
  font-family: var(--font-sans);
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--rose-600);
  margin-top: var(--space-1);
}

.cart-item__actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  justify-content: space-between;
  height: 100%;
  gap: var(--space-3);
}
.cart-item__remove {
  width: 28px; height: 28px;
  border-radius: 50%;
  color: var(--gray-300);
  display: flex; align-items: center; justify-content: center;
  transition: all var(--transition-fast);
}
.cart-item__remove:hover { background: #fee2e2; color: #ef4444; }

.cart-item__qty {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}
.cart-item__qty-btn {
  width: 28px; height: 28px;
  border-radius: 50%;
  border: 1.5px solid var(--cream-300);
  background: #fff;
  color: var(--gray-600);
  display: flex; align-items: center; justify-content: center;
  transition: all var(--transition-fast);
}
.cart-item__qty-btn:hover:not(:disabled) {
  border-color: var(--rose-400);
  color: var(--rose-500);
  background: var(--rose-50);
}
.cart-item__qty-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.cart-item__qty-btn--primary {
  background: var(--rose-500);
  border-color: var(--rose-500);
  color: #fff;
}
.cart-item__qty-btn--primary:hover:not(:disabled) {
  background: var(--rose-600);
  border-color: var(--rose-600);
  color: #fff;
}
.cart-item__qty-value {
  font-weight: 600;
  color: var(--gray-800);
  min-width: 20px;
  text-align: center;
}
.cart-item__spinner {
  width: 12px; height: 12px;
  border: 1.5px solid currentColor;
  border-top-color: transparent;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Récap ── */
.cart-summary {
  padding: var(--space-6);
  position: sticky;
  top: calc(var(--navbar-height) + var(--space-6));
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.cart-summary__title {
  font-family: var(--font-display);
  font-size: 1.25rem;
  font-weight: 500;
  color: var(--gray-800);
  padding-bottom: var(--space-3);
  border-bottom: 1px solid var(--cream-200);
}

.cart-summary__coupon-form {
  display: flex;
  gap: var(--space-2);
}
.cart-summary__coupon-form .input { flex: 1; }
.cart-summary__coupon-btn {
  min-width: 88px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.cart-summary__coupon-error {
  font-size: 0.75rem;
  color: #b91c1c;
  margin-top: var(--space-1);
}

.cart-summary__coupon-applied {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  background: var(--rose-50);
  border: 1px solid var(--rose-200);
  border-radius: var(--radius-full);
  color: var(--rose-700);
  font-size: 0.8125rem;
  font-weight: 600;
  letter-spacing: 0.05em;
}
.cart-summary__coupon-applied button {
  color: var(--rose-400);
  transition: color var(--transition-fast);
}
.cart-summary__coupon-applied button:hover { color: var(--rose-700); }

.cart-summary__lines {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-3) 0;
  border-top: 1px solid var(--cream-200);
  border-bottom: 1px solid var(--cream-200);
}
.cart-summary__lines li {
  display: flex;
  justify-content: space-between;
  font-size: 0.9375rem;
  color: var(--gray-600);
}
.cart-summary__line--discount { color: #15803d; }
.cart-summary__line--total {
  font-family: var(--font-sans);
  font-size: 1.375rem !important;
  font-weight: 600;
  color: var(--gray-800) !important;
  padding-top: var(--space-2);
}
.cart-summary__line--total span:last-child { color: var(--rose-600); }

.cart-summary__cta {
  width: 100%;
  justify-content: center;
}

.cart-summary__guarantee {
  font-size: 0.75rem;
  color: var(--gray-400);
  text-align: center;
}

/* ── Responsive ── */
@media (max-width: 900px) {
  .cart-grid { grid-template-columns: 1fr; }
  .cart-summary { position: static; }
}

@media (max-width: 540px) {
  .cart-item { grid-template-columns: 72px 1fr; }
  .cart-item__img-wrap { width: 72px; height: 72px; }
  .cart-item__actions {
    grid-column: 1 / -1;
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
  }
}
</style>
