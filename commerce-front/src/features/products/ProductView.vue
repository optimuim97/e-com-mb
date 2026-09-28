<template>
  <main class="product-page">
    <!-- Loading -->
    <div v-if="loading" class="product-loader">
      <div class="product-loader__spinner"></div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="product-error">
      <div class="product-error__icon"><FlowerMark /></div>
      <h2 class="display-md">{{ $t('product.notFound') }}</h2>
      <p>{{ $t('product.notFoundDesc') }}</p>
      <RouterLink to="/products" class="btn btn-primary">{{ $t('product.backToShop') }}</RouterLink>
    </div>

    <!-- Product -->
    <div v-else-if="product" class="container product-inner">
      <!-- Breadcrumb -->
      <nav class="product-breadcrumb" :aria-label="$t('product.breadcrumbHome')">
        <RouterLink to="/">{{ $t('product.breadcrumbHome') }}</RouterLink>
        <span aria-hidden="true">/</span>
        <RouterLink to="/products">{{ $t('product.breadcrumbShop') }}</RouterLink>
        <span aria-hidden="true">/</span>
        <span>{{ product.name }}</span>
      </nav>

      <div class="product-grid">
        <!-- Gallery -->
        <div class="product-gallery">
          <div class="product-gallery__main">
            <img :src="activeImage" :alt="product.name" class="product-gallery__img" />
            <span v-if="discountBadge" class="product-gallery__discount badge badge-rose">
              {{ discountBadge }}
            </span>
          </div>
          <div v-if="product.images && product.images.length > 1" class="product-gallery__thumbs">
            <button
              v-for="(img, idx) in product.images"
              :key="idx"
              @click="activeImage = img"
              class="product-gallery__thumb"
              :class="{ 'product-gallery__thumb--active': activeImage === img }"
              :aria-label="`Image ${idx + 1}`"
            >
              <img :src="img" :alt="product.name + ' ' + (idx + 1)" />
            </button>
          </div>
        </div>

        <!-- Info -->
        <div class="product-info">
          <span v-if="product.category" class="eyebrow">{{ product.category.name }}</span>

          <h1 class="display-lg product-info__title">{{ product.name }}</h1>

          <!-- Rating (mocked if absent) -->
          <div v-if="reviewsEnabled && product.rating_avg" class="product-info__rating">
            <div class="product-info__stars">
              <span v-for="i in 5" :key="i" class="product-info__star"
                :class="{ 'product-info__star--filled': i <= Math.round(product.rating_avg) }">★</span>
            </div>
            <span class="product-info__reviews">{{ $t('product.reviews', { count: product.reviews_count ?? 0 }) }}</span>
          </div>

          <!-- Price -->
          <div class="product-info__price">
            <span class="product-info__price-current">{{ formatPrice(sellingPrice) }}</span>
            <span v-if="referencePrice"
              class="product-info__price-old">{{ formatPrice(referencePrice) }}</span>
            <span v-if="savedAmount" class="product-info__price-saving">
              {{ $t('product.youSave', { amount: formatPrice(savedAmount) }) }}
            </span>
          </div>

          <!-- Origin of the discount: a named campaign reassures more than a
               bare struck-through price. -->
          <p v-if="product.promotion?.name" class="product-info__promo">
            <SparklesIcon class="w-4 h-4" />
            <span>{{ product.promotion.name }}</span>
            <span v-if="promoEndsLabel" class="product-info__promo-until">{{ promoEndsLabel }}</span>
          </p>

          <!-- Description -->
          <div v-if="product.description" class="product-info__desc" v-html="product.description"></div>

          <!-- Variants -->
          <div v-if="product.variants && product.variants.length > 0" class="product-info__group">
            <span class="label">{{ $t('product.variant') }}</span>
            <div class="product-info__variants">
              <button
                v-for="variant in product.variants"
                :key="variant.id"
                @click="selectedVariant = variant.id"
                class="product-info__variant"
                :class="{ 'product-info__variant--active': selectedVariant === variant.id }"
              >
                {{ variant.name }}
              </button>
            </div>
          </div>

          <!-- Quantity -->
          <div class="product-info__group">
            <span class="label">{{ $t('product.quantity') }}</span>
            <div class="product-info__qty">
              <button @click="qty > 1 && qty--" class="product-info__qty-btn" :aria-label="$t('product.decrease')">
                <MinusIcon class="w-4 h-4" />
              </button>
              <span class="product-info__qty-value">{{ qty }}</span>
              <!--
                Borné au vendable : laisser choisir 10 quand 3 restent en ligne,
                c'est reporter le refus au paiement.
              -->
              <button
                @click="(maxQty === null || qty < maxQty) && qty++"
                :disabled="maxQty !== null && qty >= maxQty"
                class="product-info__qty-btn"
                :aria-label="$t('product.increase')"
              >
                <PlusIcon class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!--
            Disponibilité en ligne, décidée par le serveur. Trois cas : se vend
            (avec le nombre quand il est limité), quota de la semaine atteint
            (« de retour lundi » — la marchandise existe), réserve vide.
          -->
          <p class="product-info__stock"
            :class="orderable ? 'product-info__stock--ok' : 'product-info__stock--ko'">
            <span class="product-info__stock-dot"></span>
            <template v-if="orderable">
              {{ maxQty !== null ? $t('product.inStock', { count: maxQty }) : $t('product.inStockNoCount') }}
            </template>
            <template v-else-if="unavailableKey === 'product.soldOut'">
              {{ $t('product.outOfStock') }}
            </template>
            <template v-else>
              {{ $t(unavailableKey) }} · {{ $t('product.quotaReachedHint') }}
            </template>
          </p>

          <!-- Actions -->
          <div class="product-info__actions">
            <button @click="addToCart" :disabled="!orderable || cartLoading"
              class="btn btn-primary btn-lg product-info__cta">
              <span v-if="cartLoading" class="product-info__spinner"></span>
              <span v-else>{{ $t('product.addToCartWithPrice', { price: formatPrice(sellingPrice * qty) }) }}</span>
            </button>
            <WishlistButton
              v-if="product?.id"
              :product-id="product.id"
              class="product-info__wish"
            />
          </div>

          <!-- Trust badges -->
          <div class="product-info__trust">
            <div v-for="badge in trustBadges" :key="badge.label" class="product-info__trust-badge">
              <span v-html="badge.icon"></span>
              <span>{{ badge.label }}</span>
            </div>
          </div>

          <!-- Success message -->
          <transition name="fade">
            <p v-if="cartSuccess" class="product-info__success">
              {{ $t('product.addedToCart') }}
            </p>
          </transition>
        </div>
      </div>

      <!-- ─── Reviews Section ─────────────────────────────────────── -->
      <section class="reviews-section" v-if="product && reviewsEnabled">
        <div class="reviews-section__header">
          <h2 class="reviews-section__title">
            {{ $t('reviews.title') }}
            <span v-if="product.reviews_count" class="reviews-section__count">
              ({{ product.reviews_count }})
            </span>
          </h2>

          <!-- Average rating summary -->
          <div v-if="product.rating_avg" class="reviews-section__avg">
            <span class="reviews-section__avg-score">{{ product.rating_avg }}</span>
            <div class="reviews-section__avg-stars">
              <StarRating :model-value="Math.round(product.rating_avg)" />
              <span class="reviews-section__avg-label">{{ $t('reviews.outOf5') }}</span>
            </div>
          </div>
        </div>

        <!-- Review form -->
        <div v-if="isAuth" class="review-form-card">
          <h3 class="review-form-card__title">{{ $t('reviews.leaveReview') }}</h3>

          <div v-if="reviewSubmitted" class="review-form-card__success">
            {{ $t('reviews.submitted') }}
          </div>

          <div v-else-if="reviewAlreadyExists" class="review-form-card__info">
            {{ $t('reviews.alreadyExists') }}
          </div>

          <form v-else @submit.prevent="submitReview" class="review-form">
            <div class="review-form__field">
              <label class="label">{{ $t('reviews.ratingLabel') }}</label>
              <StarRating
                v-model="reviewForm.rating"
                :interactive="true"
              />
              <span v-if="reviewErrors.rating" class="review-form__error">{{ reviewErrors.rating }}</span>
            </div>

            <div class="review-form__field">
              <label class="label">{{ $t('reviews.titleLabel') }}</label>
              <input
                v-model="reviewForm.title"
                type="text"
                class="input"
                :placeholder="$t('reviews.titlePlaceholder')"
                maxlength="120"
              />
            </div>

            <div class="review-form__field">
              <label class="label">{{ $t('reviews.commentLabel') }}</label>
              <textarea
                v-model="reviewForm.comment"
                class="input"
                rows="4"
                :placeholder="$t('reviews.commentPlaceholder')"
                maxlength="2000"
              ></textarea>
              <span v-if="reviewErrors.comment" class="review-form__error">{{ reviewErrors.comment }}</span>
            </div>

            <div class="review-form__footer">
              <button type="submit" class="btn btn-primary" :disabled="reviewForm.rating === 0 || reviewLoading">
                <span v-if="reviewLoading" class="product-info__spinner"></span>
                <span v-else>{{ $t('reviews.submit') }}</span>
              </button>
              <span class="review-form__note">{{ $t('reviews.moderationNote') }}</span>
            </div>
          </form>
        </div>

        <div v-else class="review-form-card review-form-card--guest">
          <RouterLink to="/login" class="btn btn-outline">
            {{ $t('reviews.loginToReview') }}
          </RouterLink>
        </div>

        <!-- Reviews list -->
        <div v-if="reviewsLoading" class="reviews-list__loading">{{ $t('reviews.loading') }}</div>

        <div v-else-if="reviews.length === 0" class="reviews-list__empty">
          <span class="reviews-list__empty-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></span>
          <p>{{ $t('reviews.empty') }}</p>
        </div>

        <ul v-else class="reviews-list">
          <li v-for="review in reviews" :key="review.id" class="review-card">
            <div class="review-card__header">
              <StarRating :model-value="review.rating" />
              <span class="review-card__author">{{ review.user?.name ?? $t('reviews.anonymous') }}</span>
              <span class="review-card__date">{{ review.created_at }}</span>
            </div>
            <p v-if="review.title" class="review-card__title">{{ review.title }}</p>
            <p v-if="review.comment" class="review-card__comment">{{ review.comment }}</p>
          </li>
        </ul>

        <!-- Pagination -->
        <div v-if="reviewsMeta && reviewsMeta.last_page > 1" class="reviews-pagination">
          <button
            class="btn btn-outline btn-sm"
            :disabled="reviewsPage <= 1"
            @click="reviewsPage--; loadReviews()"
          >{{ $t('reviews.previous') }}</button>
          <span class="reviews-pagination__info">{{ reviewsPage }} / {{ reviewsMeta.last_page }}</span>
          <button
            class="btn btn-outline btn-sm"
            :disabled="reviewsPage >= reviewsMeta.last_page"
            @click="reviewsPage++; loadReviews()"
          >{{ $t('reviews.next') }}</button>
        </div>
      </section>

      <!-- ─── Produits similaires ─────────────────────────────────── -->
      <section v-if="related.length" class="related-section">
        <h2 class="related-section__title">{{ $t('product.related') }}</h2>
        <div class="related-grid">
          <ProductCard
            v-for="p in related"
            :key="p.id"
            :product="p"
          />
        </div>
      </section>
    </div>
  </main>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useCurrencyStore } from '@/stores/currency'
import { useRoute, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/api'
import { MinusIcon, PlusIcon, SparklesIcon } from '@heroicons/vue/24/outline'
import * as pricing from '@/utils/pricing'
import { useCartStore } from '@/features/cart/cart.store'
import { useAuthStore } from '@/features/auth/auth.store'
import StarRating from '@/features/reviews/StarRating.vue'
import { reviewApi } from '@/features/reviews/review.api'
import { useSettingsStore } from '@/stores/settings'
import WishlistButton from '@/features/wishlist/WishlistButton.vue'
import ProductCard from '@/components/shop/ProductCard.vue'
import { useSeo, productJsonLd, breadcrumbJsonLd } from '@/composables/useSeo'
import { isOrderable, unavailableLabelKey, maxQuantity } from '@/utils/availability'

const { t, locale } = useI18n()
const route = useRoute()
const cartStore = useCartStore()
const authStore = useAuthStore()
const isAuth = computed(() => !!authStore.user)
const settings = useSettingsStore()
const reviewsEnabled = computed(() => settings.moduleEnabled('reviews'))

const product = ref(null)
const loading = ref(true)
const error = ref(false)
const activeImage = ref('')
const selectedVariant = ref(null)
const qty = ref(1)
const cartLoading = ref(false)
const cartSuccess = ref(false)

// ─── Produits similaires ──────────────────────────────────────────
const related = ref([])

async function loadRelated() {
  try {
    const { data } = await api.get(`/products/${route.params.slug}/related`)
    related.value = data.data ?? data
  } catch {
    related.value = []
  }
}

// ─── Reviews state ────────────────────────────────────────────────
const reviews        = ref([])
const reviewsMeta    = ref(null)
const reviewsLoading = ref(false)
const reviewsPage    = ref(1)

const reviewForm = ref({ rating: 0, title: '', comment: '' })
const reviewErrors       = ref({})
const reviewLoading      = ref(false)
const reviewSubmitted    = ref(false)
const reviewAlreadyExists = ref(false)

/* Prices come from the shared rule so the sheet, the cards and the cart
   always agree on what the customer pays. */
const sellingPrice   = computed(() => pricing.sellingPrice(product.value))
const referencePrice = computed(() => pricing.referencePrice(product.value))
const discountBadge  = computed(() => pricing.discountBadge(product.value))

/* Disponibilité en ligne : voir utils/availability. */
const orderable      = computed(() => isOrderable(product.value))
const unavailableKey = computed(() => unavailableLabelKey(product.value))
const maxQty         = computed(() => maxQuantity(product.value))

// Une fiche rechargée avec moins de vendable qu'avant ne doit pas garder une
// quantité devenue impossible.
watch(maxQty, (max) => {
  if (max !== null && max > 0 && qty.value > max) qty.value = max
})

const savedAmount = computed(() => {
  const reference = referencePrice.value
  return reference ? reference - sellingPrice.value : 0
})

/* End date of the campaign, when there is one. An open-ended promotion says
   nothing here rather than inventing an urgency it does not have. */
const promoEndsLabel = computed(() => {
  const endsAt = product.value?.promotion?.ends_at
  if (!endsAt) return null

  const date = new Date(endsAt)
  if (Number.isNaN(date.getTime())) return null

  return t('product.promoUntil', {
    date: date.toLocaleDateString(locale.value === 'en' ? 'en-GB' : 'fr-FR', {
      day: 'numeric',
      month: 'long',
    }),
  })
})

const trustBadges = computed(() => [
  {
    icon: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>`,
    label: t('product.natural'),
  },
  {
    icon: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>`,
    label: t('product.delivery48h'),
  },
  {
    icon: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>`,
    label: t('product.return14d'),
  },
])

/* ── SEO ──────────────────────────────────────────────────────────
   Déclaré après `reviews` : watchEffect s'exécute dès le montage et lirait
   une référence non encore initialisée s'il était placé plus haut.
   Réévalué quand le produit arrive — au premier rendu il est encore nul, et
   un moteur ne doit pas mémoriser une fiche sans titre ni prix. */
useSeo(() => {
  if (!product.value) return { noindex: true }

  const p = product.value
  return {
    title: p.meta_title || p.name,
    description: p.meta_description || p.short_description || p.description,
    image: p.images?.[0]?.url,
    type: 'product',
    canonical: `/products/${p.slug}`,
    jsonLd: [
      productJsonLd(p, {
        rating: p.rating_avg ? { average: p.rating_avg, count: p.reviews_count } : null,
        reviews: reviews.value,
      }),
      breadcrumbJsonLd([
        { name: 'Accueil', path: '/' },
        { name: 'Boutique', path: '/products' },
        { name: p.name, path: `/products/${p.slug}` },
      ]),
    ],
  }
})

async function loadReviews() {
  await settings.fetch()
  if (!reviewsEnabled.value) return
  reviewsLoading.value = true
  try {
    const { data } = await reviewApi.list(route.params.slug, { page: reviewsPage.value })
    reviews.value   = data.data ?? data
    reviewsMeta.value = data.meta ?? null
  } catch {
    // silently fail
  } finally {
    reviewsLoading.value = false
  }
}

async function submitReview() {
  reviewErrors.value = {}
  reviewLoading.value = true
  try {
    await reviewApi.store(route.params.slug, reviewForm.value)
    reviewSubmitted.value = true
  } catch (err) {
    if (err.response?.status === 409) {
      reviewAlreadyExists.value = true
    } else if (err.response?.status === 422) {
      const errs = err.response.data.errors ?? {}
      reviewErrors.value = Object.fromEntries(
        Object.entries(errs).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
      )
    }
  } finally {
    reviewLoading.value = false
  }
}

async function fetchProduct() {
  loading.value = true
  error.value = false
  try {
    const { data } = await api.get(`/products/${route.params.slug}`)
    product.value = data.data ?? data
    const imgs = product.value.images?.map(i => i.url ?? i) ?? []
    if (imgs.length) activeImage.value = imgs[0]
    else if (product.value.image) activeImage.value = product.value.image
    product.value.images = imgs
    if (product.value.variants?.length) {
      selectedVariant.value = product.value.variants[0].id
    }
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
}

async function addToCart() {
  cartLoading.value = true
  try {
    await cartStore.add(product.value.id, qty.value, selectedVariant.value, { snapshot: product.value })
    cartSuccess.value = true
    setTimeout(() => { cartSuccess.value = false }, 3000)
  } finally {
    cartLoading.value = false
  }
}

function formatPrice(val) {
  return useCurrencyStore().format(val ?? 0)
}

onMounted(() => { fetchProduct(); loadReviews(); loadRelated() })
watch(() => route.params.slug, () => {
  fetchProduct()
  reviewsPage.value = 1
  reviewSubmitted.value = false
  reviewAlreadyExists.value = false
  reviewForm.value = { rating: 0, title: '', comment: '' }
  loadReviews()
  loadRelated()
  // Remonte en haut pour ne pas rester scrollé sur les avis du produit précédent
  window.scrollTo({ top: 0, behavior: 'smooth' })
})
</script>

<style scoped>
.product-page {
  min-height: 100vh;
  background: var(--color-bg);
  padding: var(--space-8) 0 var(--space-16);
}

/* ── Loader ── */
.product-loader {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 60vh;
}
.product-loader__spinner {
  width: 36px;
  height: 36px;
  border: 2px solid var(--rose-100);
  border-top-color: var(--rose-500);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Error ── */
.product-error {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-4);
  padding: var(--space-20) var(--space-4);
  text-align: center;
}
.product-error__icon { font-size: 3rem; opacity: 0.5; }
.product-error p { color: var(--gray-400); }

/* ── Layout ── */
.product-breadcrumb {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: 0.8125rem;
  color: var(--gray-400);
  margin-bottom: var(--space-8);
}
.product-breadcrumb a {
  color: var(--gray-500);
  transition: color var(--transition-fast);
}
.product-breadcrumb a:hover { color: var(--rose-500); }
.product-breadcrumb > span:last-child { color: var(--gray-700); font-weight: 500; }

/*
 * minmax(0, 1fr) et non 1fr : une piste de grille prend par défaut la largeur
 * minimale de son contenu, et le bouton « Ajouter au panier » est en
 * white-space: nowrap. Sur un écran de 360px, la colonne se résolvait à 402px
 * dans un conteneur de 328 — la fiche et le bouton favori sortaient de l'écran,
 * sans même une barre de défilement pour aller les chercher.
 */
.product-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: var(--space-12);
  align-items: start;
}

/* Même raison : sans ça, un enfant large repousse toute la colonne. */
.product-gallery,
.product-info { min-width: 0; }

/* ── Galerie ── */
.product-gallery { display: flex; flex-direction: column; gap: var(--space-3); }

.product-gallery__main {
  position: relative;
  aspect-ratio: 1;
  border-radius: var(--radius-xl);
  overflow: hidden;
  background: linear-gradient(145deg, var(--rose-50), var(--cream-100));
  box-shadow: var(--shadow-sm);
}
.product-gallery__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.product-gallery__discount {
  position: absolute;
  top: var(--space-4);
  left: var(--space-4);
}

.product-gallery__thumbs {
  display: flex;
  gap: var(--space-2);
  overflow-x: auto;
  padding-bottom: 4px;
  scroll-snap-type: x proximity;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: thin;
}
.product-gallery__thumb { scroll-snap-align: start; }
.product-gallery__thumb {
  width: 76px;
  height: 76px;
  flex-shrink: 0;
  border-radius: var(--radius-md);
  overflow: hidden;
  border: 2px solid transparent;
  background: var(--cream-100);
  transition: border-color var(--transition-fast);
  padding: 0;
}
.product-gallery__thumb img {
  width: 100%; height: 100%; object-fit: cover;
}
.product-gallery__thumb:hover { border-color: var(--rose-200); }
.product-gallery__thumb--active { border-color: var(--rose-500); }

/* ── Infos ── */
.product-info {
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

.product-info__title {
  color: var(--gray-800);
  font-size: clamp(1.75rem, 3vw, 2.5rem);
}

.product-info__rating {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}
.product-info__stars { display: flex; gap: 2px; }
.product-info__star { font-size: 0.875rem; color: var(--cream-300); }
.product-info__star--filled { color: var(--gold-400); }
.product-info__reviews { font-size: 0.8125rem; color: var(--gray-400); }

.product-info__price {
  display: flex;
  align-items: baseline;
  gap: var(--space-3);
}
.product-info__price-current {
  font-family: var(--font-sans);
  font-size: 2rem;
  font-weight: 600;
  color: var(--rose-600);
}
.product-info__price-old {
  font-size: 1.125rem;
  color: var(--gray-400);
  text-decoration: line-through;
}
/* L'économie réalisée, en clair : « −1 500 FCFA » parle plus qu'un pourcentage. */
.product-info__price-saving {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--rose-600);
  background: var(--rose-50);
  padding: 3px 10px;
  border-radius: var(--radius-full);
  white-space: nowrap;
}

.product-info__promo {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex-wrap: wrap;
  margin-top: calc(var(--space-3) * -1);
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--rose-600);
}
.product-info__promo-until {
  font-weight: 400;
  color: var(--gray-500);
}

/*
 * Contenu libre saisi en administration : une URL sans espace, une image ou un
 * tableau collé depuis Word suffisent à faire déborder la page sur mobile.
 * On contient tout ça ici plutôt que d'espérer une saisie propre.
 */
.product-info__desc {
  color: var(--gray-500);
  line-height: 1.75;
  font-size: 0.9375rem;
  padding: var(--space-4) 0;
  border-top: 1px solid var(--cream-200);
  border-bottom: 1px solid var(--cream-200);
  overflow-wrap: anywhere;
}
.product-info__desc :deep(img) {
  max-width: 100%;
  height: auto;
  border-radius: var(--radius-md);
}
.product-info__desc :deep(table) { width: 100%; display: block; overflow-x: auto; }
.product-info__desc :deep(pre)   { white-space: pre-wrap; }

.product-info__group { display: flex; flex-direction: column; gap: var(--space-2); }

.product-info__variants {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}
.product-info__variant {
  padding: 8px 16px;
  border-radius: var(--radius-full);
  border: 1.5px solid var(--cream-300);
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--gray-600);
  background: #fff;
  transition: all var(--transition-fast);
}
.product-info__variant:hover { border-color: var(--rose-300); color: var(--rose-500); }
.product-info__variant--active {
  background: var(--rose-500);
  border-color: var(--rose-500);
  color: #fff;
  box-shadow: var(--shadow-rose);
}

.product-info__qty {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  background: var(--color-surface);
  border: 1.5px solid var(--cream-300);
  border-radius: var(--radius-full);
  padding: 4px;
  width: fit-content;
}
.product-info__qty-btn {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--gray-600);
  transition: all var(--transition-fast);
}
.product-info__qty-btn:hover { background: var(--rose-50); color: var(--rose-500); }
.product-info__qty-value {
  min-width: 32px;
  text-align: center;
  font-weight: 600;
  color: var(--gray-800);
}

.product-info__stock {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: 0.8125rem;
}
.product-info__stock-dot {
  width: 8px; height: 8px; border-radius: 50%;
}
.product-info__stock--ok { color: #15803d; }
.product-info__stock--ok .product-info__stock-dot { background: #22c55e; }
.product-info__stock--ko { color: #b91c1c; }
.product-info__stock--ko .product-info__stock-dot { background: #ef4444; }

.product-info__actions {
  display: flex;
  gap: var(--space-2);
}
.product-info__cta {
  flex: 1;
  justify-content: center;
}
.product-info__wish {
  /* Aligne le WishlistButton (qui est circulaire par défaut) sur la
     hauteur du CTA panier qui est en btn-lg (~52px) */
  width: 52px !important;
  height: 52px !important;
  flex-shrink: 0;
}

.product-info__spinner {
  width: 16px; height: 16px;
  border: 2px solid rgba(255,255,255,0.4);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

.product-info__trust {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-4);
  padding-top: var(--space-4);
  border-top: 1px solid var(--cream-200);
}
.product-info__trust-badge {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: 0.75rem;
  color: var(--gray-500);
}
.product-info__trust-badge svg { color: var(--rose-400); }

.product-info__success {
  background: #dcfce7;
  color: #15803d;
  padding: 10px 16px;
  border-radius: var(--radius-md);
  font-size: 0.8125rem;
  font-weight: 500;
  text-align: center;
}

.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* ── Reviews Section ── */
.reviews-section {
  margin-top: var(--space-16);
  padding-top: var(--space-10);
  border-top: 2px solid var(--cream-200);
}

.reviews-section__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: var(--space-4);
  margin-bottom: var(--space-8);
}

.reviews-section__title {
  font-family: var(--font-display);
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--gray-800);
}

.reviews-section__count {
  font-size: 1rem;
  font-weight: 400;
  color: var(--gray-400);
}

.reviews-section__avg {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  background: var(--rose-50);
  padding: var(--space-3) var(--space-5);
  border-radius: var(--radius-xl);
}

.reviews-section__avg-score {
  font-size: 2rem;
  font-weight: 700;
  color: var(--rose-600);
  line-height: 1;
}

.reviews-section__avg-stars {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.reviews-section__avg-label {
  font-size: 0.75rem;
  color: var(--gray-500);
}

/* Review form card */
.review-form-card {
  background: var(--color-surface);
  border: 1.5px solid var(--cream-200);
  border-radius: var(--radius-xl);
  padding: var(--space-6);
  margin-bottom: var(--space-8);
}

.review-form-card--guest {
  text-align: center;
  padding: var(--space-8);
}

.review-form-card__title {
  font-size: 1rem;
  font-weight: 600;
  color: var(--gray-700);
  margin-bottom: var(--space-5);
}

.review-form-card__success {
  background: #dcfce7;
  color: #15803d;
  padding: var(--space-4);
  border-radius: var(--radius-md);
  font-size: 0.875rem;
}

.review-form-card__info {
  background: #fef3c7;
  color: #92400e;
  padding: var(--space-4);
  border-radius: var(--radius-md);
  font-size: 0.875rem;
}

.review-form {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.review-form__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.review-form__error {
  font-size: 0.75rem;
  color: var(--rose-500);
}

.review-form__footer {
  display: flex;
  align-items: center;
  gap: var(--space-4);
  flex-wrap: wrap;
}

.review-form__note {
  font-size: 0.75rem;
  color: var(--gray-400);
  font-style: italic;
}

/* Reviews list */
.reviews-list__loading,
.reviews-list__empty {
  text-align: center;
  padding: var(--space-10);
  color: var(--gray-400);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-3);
}

.reviews-list__empty-icon { font-size: 2rem; }

.reviews-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.review-card {
  background: var(--color-surface);
  border: 1px solid var(--cream-200);
  border-radius: var(--radius-xl);
  padding: var(--space-5) var(--space-6);
  transition: box-shadow var(--transition-fast);
}

.review-card:hover { box-shadow: var(--shadow-sm); }

.review-card__header {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  flex-wrap: wrap;
  margin-bottom: var(--space-3);
}

.review-card__author {
  font-weight: 600;
  font-size: 0.875rem;
  color: var(--gray-700);
}

.review-card__date {
  font-size: 0.75rem;
  color: var(--gray-400);
  margin-left: auto;
}

.review-card__title {
  font-weight: 600;
  color: var(--gray-700);
  margin-bottom: var(--space-1);
  font-size: 0.9375rem;
}

.review-card__comment {
  color: var(--gray-500);
  font-size: 0.875rem;
  line-height: 1.6;
}

/* Pagination */
.reviews-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-4);
  margin-top: var(--space-6);
}

.reviews-pagination__info {
  font-size: 0.875rem;
  color: var(--gray-500);
}

/* ── Produits similaires ── */
.related-section {
  margin-top: var(--space-16);
  padding-top: var(--space-10);
  border-top: 2px solid var(--cream-200);
}
.related-section__title {
  font-family: var(--font-display);
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--gray-800);
  margin-bottom: var(--space-8);
}
.related-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--space-5);
}

/* ══════════════════════════════════════════════════════════════════════════
   Responsive

   Regroupé en fin de feuille : ces règles doivent l'emporter sur toutes les
   précédentes. Le bloc vivait au milieu du fichier et les styles des avis,
   écrits plus bas, reprenaient le dessus sur mobile.

   Paliers alignés sur le site : 1024 tablette large, 900 rupture des deux
   colonnes, 768 mobile (celui du design system), 560 petit écran.
   ══════════════════════════════════════════════════════════════════════════ */

@media (max-width: 1024px) {
  .product-grid { gap: var(--space-8); }
  .related-grid { grid-template-columns: repeat(2, 1fr); }
}

/* Deux colonnes côte à côte deviennent trop étroites : la fiche passe dessous. */
@media (max-width: 900px) {
  .product-grid {
    grid-template-columns: minmax(0, 1fr);
    gap: var(--space-8);
  }
  /* L'image occupait tout l'écran en hauteur avant qu'on lise le prix. */
  .product-gallery__main { aspect-ratio: 4 / 3; }
}

@media (max-width: 768px) {
  .product-breadcrumb { margin-bottom: var(--space-5); }

  /* Respirations pensées pour le bureau : sur un téléphone, elles obligent
     à faire défiler dans le vide entre chaque section. */
  .reviews-section,
  .related-section {
    margin-top: var(--space-10);
    padding-top: var(--space-8);
  }
  .reviews-section__title,
  .related-section__title { font-size: 1.25rem; }
  .related-section__title { margin-bottom: var(--space-5); }
  .reviews-section__header { margin-bottom: var(--space-5); }

  /* Note moyenne : le bloc rose prend toute la largeur plutôt que de se
     coincer à côté du titre. */
  .reviews-section__avg { width: 100%; justify-content: center; }

  .review-form-card { padding: var(--space-5); margin-bottom: var(--space-6); }

  /* Le libellé « Connectez-vous pour laisser un avis » réclamait 346px sans
     pouvoir se replier : sur un écran de 360, il sortait de la carte. */
  .review-form-card--guest { padding: var(--space-6) var(--space-4); }
  .review-form-card--guest .btn {
    white-space: normal;
    width: 100%;
    justify-content: center;
    padding-left: var(--space-4);
    padding-right: var(--space-4);
  }
  .review-card { padding: var(--space-4) var(--space-5); }

  /* La date était poussée à droite par margin-left:auto ; une fois la ligne
     repliée elle se retrouvait seule et cadrée à droite. */
  .review-card__header { gap: var(--space-2); }
  .review-card__date { margin-left: 0; width: 100%; }

  /* Cibles tactiles : 32px est en dessous du seuil confortable. */
  .product-info__qty-btn { width: 40px; height: 40px; }
  .product-info__qty { padding: 3px; }

  .product-gallery__thumb { width: 64px; height: 64px; }
}

@media (max-width: 560px) {
  .product-info { gap: var(--space-4); }
  .product-info__price-current { font-size: 1.75rem; }

  /*
   * Le libellé porte le prix : « Ajouter au panier · 8 000 FCFA ». Avec le
   * white-space: nowrap du design system et 40px de marge intérieure de
   * chaque côté, il réclamait 342px à lui seul. On le laisse se replier sur
   * deux lignes ici, et on resserre — plutôt que de sacrifier le prix, qui
   * est justement ce qu'on veut montrer sur le bouton.
   */
  .product-info__cta {
    white-space: normal;
    padding: 14px var(--space-4);
    line-height: 1.3;
    min-height: 52px;
  }

  /* Deux colonnes lisibles au lieu d'un repli irrégulier à quatre éléments. */
  .product-info__trust {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-3);
  }

  .review-form__footer { gap: var(--space-2); }
  .review-form__footer .btn { width: 100%; justify-content: center; }

  .reviews-pagination { gap: var(--space-3); }
}

@media (max-width: 480px) {
  .related-grid { grid-template-columns: 1fr; }
}

/*
 * Très petits écrans : le bouton favori et le panier ne tiennent plus côte à
 * côte sans écraser le libellé. Le favori passe sous le panier, pleine
 * largeur — il reste atteignable, ce qui n'était pas le cas quand il sortait
 * de l'écran.
 */
@media (max-width: 380px) {
  .product-info__actions { flex-wrap: wrap; }
  .product-info__cta { flex: 1 1 100%; }
  .product-info__wish {
    width: 100% !important;
    border-radius: var(--radius-full);
  }
}
</style>
