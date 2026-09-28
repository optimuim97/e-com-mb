<template>
  <main v-if="line" class="line-page" :style="heroStyle">
    <!-- ── Hero gamme ── -->
    <section class="line-hero">
      <!-- Cover image ou placeholder SVG -->
      <div class="line-hero__cover">
        <img
          v-if="line.cover_url"
          :src="line.cover_url"
          :alt="line.name"
          class="line-hero__cover-img"
        />
        <div v-else class="line-hero__cover-placeholder" :style="placeholderStyle">
          <svg class="line-hero__cover-rose" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <circle cx="100" cy="100" r="88" fill="rgba(255,255,255,.07)" />
            <ellipse cx="100" cy="60"  rx="22" ry="38" fill="rgba(255,255,255,.18)" transform="rotate(0 100 100)" />
            <ellipse cx="100" cy="60"  rx="22" ry="38" fill="rgba(255,255,255,.15)" transform="rotate(60 100 100)" />
            <ellipse cx="100" cy="60"  rx="22" ry="38" fill="rgba(255,255,255,.15)" transform="rotate(120 100 100)" />
            <ellipse cx="100" cy="60"  rx="22" ry="38" fill="rgba(255,255,255,.12)" transform="rotate(180 100 100)" />
            <ellipse cx="100" cy="60"  rx="22" ry="38" fill="rgba(255,255,255,.12)" transform="rotate(240 100 100)" />
            <ellipse cx="100" cy="60"  rx="22" ry="38" fill="rgba(255,255,255,.10)" transform="rotate(300 100 100)" />
            <circle cx="100" cy="100" r="22" fill="rgba(255,255,255,.22)" />
          </svg>
        </div>
        <div class="line-hero__cover-overlay"></div>
      </div>

      <div class="container line-hero__inner">
        <RouterLink to="/products" class="line-breadcrumb">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M19 12H5M12 5l-7 7 7 7"/></svg>
          {{ $t('productLines.allProducts') }}
        </RouterLink>

        <div class="line-hero__badge" :style="badgeStyle">{{ $t('productLines.badge') }}</div>

        <h1 class="line-hero__name">{{ line.name }}</h1>

        <p v-if="line.tagline" class="line-hero__tagline">{{ line.tagline }}</p>
        <p v-if="line.description" class="line-hero__desc">{{ line.description }}</p>

        <!-- Prix + CTA -->
        <div class="line-hero__actions">
          <!-- Prix : kit s'il est défini, sinon somme des composants -->
          <div v-if="heroPrice > 0" class="line-hero__price-wrap">
            <span class="line-hero__price-label">{{ $t('productLines.fullRange') }}</span>
            <span class="line-hero__price">{{ formatPrice(heroPrice) }}</span>
          </div>

          <button
            class="btn-add-gamme"
            :class="{ 'btn-add-gamme--added': justAdded }"
            @click="addGameToCart"
            :disabled="addingToCart || cartableProducts.length === 0"
          >
            <span class="btn-add-gamme__icon" :class="{ 'btn-add-gamme__icon--spin': addingToCart }">
              <svg v-if="justAdded" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
              <svg v-else-if="addingToCart" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
              <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            </span>
            {{ justAdded ? $t('productLines.added') : addingToCart ? $t('productLines.adding') : $t('productLines.addToCart') }}
          </button>
        </div>
      </div>
    </section>

    <!-- ── Composition de la gamme ── -->
    <section v-if="compositionProducts.length" class="container line-composition">
      <header class="kit-head">
        <div class="kit-head__title">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
          <h2>{{ $t('productLines.contains') }}</h2>
        </div>
        <span class="kit-head__count">{{ $t('productLines.careCount', { count: compositionProducts.length }, compositionProducts.length) }}</span>
      </header>

      <!-- Grille produits -->
      <div class="kit-grid">
        <a
          v-for="(p, idx) in compositionProducts"
          :key="p.id"
          :href="`/products/${p.slug}`"
          target="_blank"
          rel="noopener"
          class="kit-card"
        >
          <span class="kit-card__step">{{ idx + 1 }}</span>
          <div class="kit-card__avatar" :style="avatarStyle(p)">
            <img
              v-if="p.images?.[0]?.url"
              :src="p.images[0].url"
              :alt="p.name"
              class="kit-card__img"
            />
            <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".6"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
          </div>
          <div class="kit-card__info">
            <span class="kit-card__name">{{ p.name }}</span>
            <span v-if="p.price" class="kit-card__price">{{ formatPrice(p.price) }}</span>
          </div>
          <svg class="kit-card__ext" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        </a>
      </div>

      <!-- Récapitulatif gamme -->
      <div v-if="line.price" class="kit-summary">
        <div class="kit-summary__info">
          <span class="kit-summary__count">{{ $t('productLines.ritualCount', { count: compositionProducts.length }, compositionProducts.length) }}</span>
          <span v-if="savings > 0" class="kit-summary__value">
            {{ $t('productLines.unitValue') }} <s>{{ formatPrice(itemsTotal) }}</s>
          </span>
        </div>
        <div class="kit-summary__price">
          <div class="kit-summary__price-block">
            <span class="kit-summary__price-label">{{ $t('productLines.rangePrice') }}</span>
            <span class="kit-summary__price-val">{{ formatPrice(line.price) }}</span>
          </div>
          <span v-if="savings > 0" class="kit-summary__badge">
            {{ $t('productLines.savings', { amount: formatPrice(savings) }) }}
          </span>
        </div>
      </div>
    </section>
  </main>

  <div v-else-if="loading" class="loader-wrap"><div class="loader"></div></div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useCartStore } from '@/features/cart/cart.store'
import { useCurrencyStore } from '@/stores/currency'
import { useToast } from 'vue-toastification'
import api from '@/api'

const { t }     = useI18n()
const route     = useRoute()
const cartStore = useCartStore()
const toast     = useToast()

const line           = ref(null)
const itemsProducts  = ref([])
const linkedProducts = ref([])
const loading        = ref(true)
const addingToCart   = ref(false)
const justAdded      = ref(false)

const primaryColor = computed(() => line.value?.color_hex ?? '#E8336D')

// Produits affichés dans la composition : items explicites en priorité,
// sinon produits rattachés à la gamme (product_line_id)
const compositionProducts = computed(() =>
  itemsProducts.value.length ? itemsProducts.value : linkedProducts.value
)
const cartableProducts = computed(() => compositionProducts.value)

// Somme des prix unitaires des produits composants
const itemsTotal = computed(() =>
  compositionProducts.value.reduce((sum, p) => sum + (Number(p.price) || 0), 0)
)
// Économie = somme individuelle - prix gamme (>0 seulement si la gamme est moins chère)
const savings = computed(() => {
  if (!line.value?.price || !itemsTotal.value) return 0
  return Math.max(0, itemsTotal.value - Number(line.value.price))
})

// Prix affiché dans le hero : prix kit s'il est défini, sinon la somme
// des prix des produits composants.
const heroPrice = computed(() =>
  Number(line.value?.price) || itemsTotal.value || 0
)

function avatarStyle(p) {
  const color = line.value?.color_hex ?? '#E8336D'
  return p.images?.[0]?.url
    ? {}
    : { background: color + '20', color }
}

const heroStyle = computed(() => ({
  '--line-color': primaryColor.value,
}))

const badgeStyle = computed(() => ({
  background:   primaryColor.value + '22',
  color:        primaryColor.value,
  borderColor:  primaryColor.value + '55',
}))

const placeholderStyle = computed(() => ({
  background: `linear-gradient(135deg, ${primaryColor.value}cc 0%, ${primaryColor.value}88 100%)`,
}))

function formatPrice(val) {
  return useCurrencyStore().format(val ?? 0)
}

async function fetchLine() {
  const { data } = await api.get(`/product-lines/${route.params.slug}`)
  const payload       = data.data ?? data
  line.value          = payload
  itemsProducts.value = payload.items_products ?? []
  // Produits déjà inclus dans la réponse (relation paginée)
  linkedProducts.value = payload.products?.data ?? payload.products ?? []
}

async function addGameToCart() {
  const toAdd = cartableProducts.value
  if (addingToCart.value || !toAdd.length) return
  addingToCart.value = true
  try {
    for (const product of toAdd) {
      await cartStore.add(product.id, 1, null, { silent: true, snapshot: product })
    }
    justAdded.value = true
    toast.success(
      t('productLines.addedToast', { name: line.value.name, count: toAdd.length }, toAdd.length),
      { timeout: 3500 }
    )
    setTimeout(() => { justAdded.value = false }, 3500)
  } finally {
    addingToCart.value = false
  }
}

onMounted(async () => {
  await fetchLine()
  loading.value = false
})
</script>

<style scoped>
.line-page { min-height: 80vh; }

/* ── Hero ── */
.line-hero {
  position: relative;
  padding: var(--space-20) 0 var(--space-16);
  overflow: hidden;
  min-height: 420px;
  display: flex;
  align-items: center;
}

.line-hero__cover {
  position: absolute;
  inset: 0;
  z-index: 0;
}
.line-hero__cover-img {
  width: 100%; height: 100%;
  object-fit: cover;
  object-position: center;
}
.line-hero__cover-placeholder {
  width: 100%; height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}
.line-hero__cover-rose {
  width: min(340px, 70%);
  height: min(340px, 70%);
  opacity: .55;
}
.line-hero__cover-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to bottom, rgba(10,2,6,.35) 0%, rgba(10,2,6,.68) 100%);
}

/* Le bloc suit le container (aligné à gauche comme les sections en dessous) ;
   seul le texte est contraint en largeur pour la lisibilité. */
.line-hero__inner {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--space-3);
}

.line-breadcrumb {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8125rem;
  color: rgba(255,255,255,.7);
  text-decoration: none;
  transition: color var(--transition-fast);
  margin-bottom: var(--space-2);
}
.line-breadcrumb:hover { color: #fff; }

.line-hero__badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: var(--radius-full);
  border: 1.5px solid;
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.line-hero__name {
  font-family: var(--font-display);
  font-size: clamp(2rem, 5vw, 3.5rem);
  font-weight: 700;
  line-height: 1.1;
  color: #fff;
  max-width: 680px;
}

.line-hero__tagline {
  font-size: 1.125rem;
  color: rgba(255,255,255,.85);
  font-style: italic;
  max-width: 620px;
}

.line-hero__desc {
  font-size: 0.9375rem;
  color: rgba(255,255,255,.75);
  line-height: 1.65;
  max-width: 620px;
}

/* Actions */
.line-hero__actions {
  display: flex;
  align-items: center;
  gap: var(--space-5);
  flex-wrap: wrap;
  margin-top: var(--space-3);
}

.line-hero__price-wrap {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: var(--space-3) var(--space-5);
  background: rgba(255,255,255,.12);
  backdrop-filter: blur(8px);
  border: 1.5px solid rgba(255,255,255,.25);
  border-radius: var(--radius-xl);
}
.line-hero__price-label {
  font-size: 0.75rem;
  font-weight: 500;
  color: rgba(255,255,255,.75);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.line-hero__price {
  font-family: var(--font-sans);
  font-size: 1.75rem;
  font-weight: 700;
  color: #fff;
  letter-spacing: -0.01em;
  line-height: 1;
}

/* Bouton ajouter gamme */
.btn-add-gamme {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  border-radius: 50px;
  border: none;
  cursor: pointer;
  font-size: 0.9375rem;
  font-weight: 700;
  font-family: var(--font-body);
  background: #fff;
  color: var(--line-color, var(--rose-500));
  box-shadow: 0 4px 20px rgba(0,0,0,.2);
  transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.2s, color 0.2s;
}
.btn-add-gamme:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 28px rgba(0,0,0,.28);
}
.btn-add-gamme:active:not(:disabled) { transform: translateY(0); }
.btn-add-gamme:disabled { opacity: .8; cursor: default; }
.btn-add-gamme--added {
  background: var(--line-color, var(--rose-500));
  color: #fff;
}

.btn-add-gamme__icon {
  display: flex;
  align-items: center;
  flex-shrink: 0;
}
.btn-add-gamme__icon--spin { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Composition ── */
.line-composition {
  padding: var(--space-10) var(--space-4) var(--space-12);
}

/* En-tête */
.kit-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  margin-bottom: var(--space-5);
}
.kit-head__title {
  display: flex;
  align-items: center;
  gap: 9px;
}
.kit-head__title svg { flex-shrink: 0; color: var(--line-color, var(--rose-500)); }
.kit-head__title h2 {
  font-family: var(--font-display);
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--gray-800);
  line-height: 1;
}
.kit-head__count {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--line-color, var(--rose-500));
  background: var(--rose-50, #fdf2f5);
  background: color-mix(in srgb, var(--line-color, var(--rose-500)) 10%, #fff);
  border: 1px solid var(--rose-100, #fbe0e8);
  border: 1px solid color-mix(in srgb, var(--line-color, var(--rose-500)) 22%, #fff);
  border-radius: var(--radius-full, 999px);
  padding: 4px 12px;
  white-space: nowrap;
}

/* ── Grille produits ── */
.kit-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
  gap: var(--space-3);
}

/* ── Carte produit ── */
.kit-card {
  position: relative;
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4) var(--space-3) var(--space-3);
  background: #fff;
  border: 1.5px solid var(--cream-200, #ede8e3);
  border-radius: var(--radius-xl, 16px);
  text-decoration: none;
  transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
  min-width: 0;
}
.kit-card:hover {
  border-color: var(--line-color, var(--rose-500));
  box-shadow: 0 6px 20px rgba(0,0,0,.08);
  transform: translateY(-2px);
}
.kit-card:active { transform: translateY(0); }

/* Numéro d'étape (rituel) */
.kit-card__step {
  position: absolute;
  top: -8px; left: -8px;
  width: 22px; height: 22px;
  border-radius: 50%;
  background: var(--line-color, var(--rose-500));
  color: #fff;
  font-size: 0.6875rem;
  font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 2px 6px rgba(0,0,0,.18);
  border: 2px solid #fff;
}

.kit-card__avatar {
  width: 52px; height: 52px;
  border-radius: 12px;
  overflow: hidden;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  background: var(--cream-100, #f5f0ec);
}
.kit-card__img { width: 100%; height: 100%; object-fit: cover; }

.kit-card__info {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
  flex: 1;
}
.kit-card__name {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--gray-800);
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.kit-card__price {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--line-color, var(--rose-500));
}

.kit-card__ext {
  flex-shrink: 0;
  color: var(--gray-300);
  transition: color 0.15s, transform 0.15s;
}
.kit-card:hover .kit-card__ext {
  color: var(--line-color, var(--rose-500));
  transform: translate(1px, -1px);
}

/* ── Récapitulatif ── */
.kit-summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-5);
  flex-wrap: wrap;
  margin-top: var(--space-6);
  padding: var(--space-5) var(--space-6);
  border-radius: var(--radius-xl, 18px);
  background: var(--rose-50, #fdf2f5);
  background: color-mix(in srgb, var(--line-color, var(--rose-500)) 6%, #fff);
  border: 1.5px solid var(--rose-100, #fbe0e8);
  border: 1.5px solid color-mix(in srgb, var(--line-color, var(--rose-500)) 16%, #fff);
}
.kit-summary__info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.kit-summary__count {
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--gray-800);
}
.kit-summary__value {
  font-size: 0.8125rem;
  color: var(--gray-500);
}
.kit-summary__value s { color: var(--gray-400); }

.kit-summary__price {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}
.kit-summary__price-block {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}
.kit-summary__price-label {
  font-size: 0.6875rem;
  font-weight: 500;
  color: var(--gray-500);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.kit-summary__price-val {
  font-family: var(--font-sans);
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--line-color, var(--rose-500));
  letter-spacing: -0.01em;
  line-height: 1;
}
.kit-summary__badge {
  font-size: 0.75rem;
  font-weight: 700;
  color: #1f8a4c;
  background: #e7f6ed;
  border-radius: var(--radius-full, 999px);
  padding: 5px 12px;
  white-space: nowrap;
}

/* ── Mobile ── */
@media (max-width: 640px) {
  .line-hero { min-height: 320px; padding: var(--space-12) 0 var(--space-10); }
  .line-hero__actions { flex-direction: column; align-items: flex-start; gap: var(--space-3); }
  .line-hero__price-wrap { width: 100%; }
  .btn-add-gamme { width: 100%; justify-content: center; }
  .kit-grid { grid-template-columns: 1fr; }
  .kit-summary { flex-direction: column; align-items: stretch; gap: var(--space-4); }
  .kit-summary__price { justify-content: space-between; }
  .kit-summary__price-block { align-items: flex-start; }
}
</style>
