<template>
  <main class="products-page">

    <!-- ── Barre fixe : recherche + tri ── -->
    <div class="products-topbar">
      <div class="container products-topbar__inner">
        <div class="products-topbar__left">
          <h1 class="products-topbar__title">{{ $t('products.title') }}</h1>
          <span class="products-topbar__count">{{ meta.total ?? products.length }} {{ $t('products.product', meta.total ?? products.length) }}</span>
        </div>
        <div class="products-topbar__search">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="products-search-icon">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
          </svg>
          <input
            ref="searchInput"
            v-model="filters.search"
            type="search"
            :placeholder="$t('products.searchPlaceholder')"
            class="products-search-input"
            @input="debouncedSearch"
          />
        </div>
        <div class="products-sort">
          <AppSelect v-model="filters.sort" :options="sortOptions" :placeholder="$t('products.mostRecent')" />
        </div>
      </div>

      <!-- Tags catégories scrollables -->
      <div class="products-cats" ref="catsEl">
        <div class="container products-cats__inner">
          <button
            class="products-cat-tag"
            :class="{ 'products-cat-tag--active': !filters.category }"
            @click="setCategory(null)"
          >
            {{ $t('common.all') }}
          </button>
          <button
            v-for="cat in categories"
            :key="cat.id"
            class="products-cat-tag"
            :class="{ 'products-cat-tag--active': filters.category === cat.slug }"
            @click="setCategory(cat.slug)"
          >
            {{ cat.name }}
          </button>
        </div>
      </div>
    </div>

    <!-- Spacer pour le topbar fixe -->
    <div class="products-topbar-spacer"></div>

    <!-- ── Grille ── -->
    <div class="container products-body">
      <div v-if="loading" class="products-grid">
        <div v-for="i in 12" :key="i" class="product-skeleton" />
      </div>
      <div v-else-if="products.length" class="products-grid">
        <ProductCard
          v-for="p in products"
          :key="p.id"
          :product="p"
          @add-to-cart="addToCart"
        />
      </div>
      <div v-else class="products-empty">
        <div class="products-empty__icon"><FlowerMark /></div>
        <h3>{{ $t('products.noProduct') }}</h3>
        <p>{{ $t('products.noProductDesc') }}</p>
        <button class="btn btn-outline" @click="resetFilters">{{ $t('products.clearFilters') }}</button>
      </div>

      <!-- Pagination -->
      <div v-if="meta.last_page > 1" class="products-pagination">
        <button
          v-for="page in meta.last_page"
          :key="page"
          class="page-btn"
          :class="{ 'page-btn--active': page === filters.page }"
          @click="filters.page = page; scrollTop()"
        >{{ page }}</button>
      </div>
    </div>

  </main>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/api'
import { storeToRefs } from 'pinia'
import { useCartStore } from '@/features/cart/cart.store'
import { useHomeStore } from '@/features/home/home.store'
import ProductCard from '@/components/ui/ProductCard.vue'
import AppSelect   from '@/components/ui/AppSelect.vue'
import { useSeo, itemListJsonLd } from '@/composables/useSeo'
import { isOrderable } from '@/utils/availability'

const { t }     = useI18n()
const route     = useRoute()
const router    = useRouter()
const cartStore = useCartStore()
const searchInput = ref(null)

// Focalise le champ de recherche quand on arrive via le bouton loupe (?focus=search)
function focusSearchIfRequested() {
  if (route.query.focus !== 'search') return
  nextTick(() => {
    searchInput.value?.focus()
    // Nettoie l'URL pour ne pas re-focaliser à chaque navigation
    const q = { ...route.query }
    delete q.focus
    router.replace({ query: q })
  })
}
const homeStore = useHomeStore()
const products  = ref([])
const { categories } = storeToRefs(homeStore)
const meta      = ref({})
const loading   = ref(true)

/* ── SEO ────────────────────────────────────────────────────────────────────
   Les pages filtrées (catégorie, recherche, pagination) pointent leur canonique
   vers /products : sans ça, chaque combinaison de filtres deviendrait une page
   concurrente du catalogue sur les mêmes produits. */
useSeo(() => {
  const categorie = route.query.category
    ? categories.value.find(c => c.slug === route.query.category)
    : null
  const recherche = route.query.search

  return {
    title: categorie
      ? t('products.seoTitleCategory', { category: categorie.name })
      : t('products.seoTitle'),
    description: categorie?.description || t('products.seoDescription'),
    canonical: '/products',
    noindex: !!recherche,
    jsonLd: itemListJsonLd(products.value),
  }
})

const sortOptions = computed(() => [
  { value: 'price_asc',  label: t('products.priceAsc') },
  { value: 'price_desc', label: t('products.priceDesc') },
])

const filters = reactive({
  category:  route.query.category ?? null,
  search:    route.query.search ?? null,
  min_price: null,
  max_price: null,
  sort:      '',
  page:      1,
  featured:  route.query.featured ?? null,
  // Arrivée depuis la bannière d'accueil : n'afficher que les articles de
  // l'opération en cours.
  promotion: route.query.promotion ?? null,
})

function setCategory(slug) {
  filters.category = slug
  filters.page = 1
}

let searchTimer = null
function debouncedSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { filters.page = 1; loadProducts() }, 400)
}

function addToCart(product) {
  if (!isOrderable(product)) return
  cartStore.add(product.id ?? product, 1, null, { snapshot: product })
}

async function loadProducts() {
  loading.value = true
  const params = Object.fromEntries(
    Object.entries(filters).filter(([, v]) => v != null && v !== '')
  )
  const { data } = await api.get('/products', { params })
  products.value = data.data
  meta.value = data.meta ?? { total: data.total, last_page: data.last_page }
  loading.value = false
}

watch(
  () => [filters.category, filters.min_price, filters.max_price, filters.sort, filters.featured, filters.promotion],
  () => { filters.page = 1; loadProducts() }
)
watch(() => filters.page, loadProducts)

function scrollTop() { window.scrollTo({ top: 0, behavior: 'smooth' }) }

function resetFilters() {
  Object.assign(filters, {
    category: null, search: null, min_price: null, max_price: null,
    sort: '', page: 1, featured: null, promotion: null,
  })
}

onMounted(async () => {
  await Promise.all([loadProducts(), homeStore.fetchCategories()])
  focusSearchIfRequested()
})

// Si on est déjà sur /products et qu'on reclique la loupe, le focus se refait
watch(() => route.query.focus, focusSearchIfRequested)
</script>

<style scoped>
.products-page {
  min-height: 100vh;
  background: var(--color-bg);
}

/* ── Topbar fixe ── */
.products-topbar {
  position: sticky;
  top: var(--navbar-height); /* toujours sous la navbar, bande annonce comprise */
  z-index: 20;
  background: rgba(255, 252, 250, 0.97);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--cream-200);
  box-shadow: 0 1px 12px rgba(0,0,0,0.04);
}

.products-topbar__inner {
  display: flex;
  align-items: center;
  gap: var(--space-4);
  padding-top: var(--space-3);
  padding-bottom: var(--space-3);
}

.products-topbar__left {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex-shrink: 0;
}

.products-topbar__title {
  font-family: var(--font-display);
  font-size: 1.0625rem;
  font-weight: 600;
  color: var(--gray-800);
  line-height: 1;
}

.products-topbar__count {
  font-size: 0.75rem;
  color: var(--gray-400);
}

/* ── Recherche ── */
.products-topbar__search {
  position: relative;
  flex: 1;
  max-width: 380px;
}

.products-search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--gray-300);
  pointer-events: none;
}

.products-search-input {
  width: 100%;
  padding: 9px 14px 9px 36px;
  border: 1.5px solid var(--cream-300);
  border-radius: var(--radius-full);
  background: #fff;
  font-size: 0.875rem;
  color: var(--gray-700);
  outline: none;
  transition: border-color var(--transition-fast);
}
.products-search-input:focus { border-color: var(--rose-300); }

/* ── Tri ── */
.products-sort { flex-shrink: 0; }

/* ── Tags catégories ── */
.products-cats {
  border-top: 1px solid var(--cream-100);
  overflow: hidden;
}
.products-cats__inner {
  display: flex;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-6) var(--space-3);
  overflow-x: auto;
  scrollbar-width: none;
  -webkit-overflow-scrolling: touch;
  /* Premier et dernier tag ne touchent pas les bords */
  scroll-padding-inline: var(--space-6);
}
.products-cats__inner::-webkit-scrollbar { display: none; }
/* Dégradé pour signaler qu'il y a du contenu à scroller */
.products-cats {
  mask-image: linear-gradient(to right, transparent 0, black 16px, black calc(100% - 16px), transparent 100%);
  -webkit-mask-image: linear-gradient(to right, transparent 0, black 16px, black calc(100% - 16px), transparent 100%);
}

.products-cat-tag {
  flex-shrink: 0;
  padding: 6px 16px;
  border-radius: var(--radius-full);
  border: 1.5px solid var(--cream-300);
  background: #fff;
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--gray-600);
  transition: all var(--transition-fast);
  white-space: nowrap;
}
/* Hover uniquement sur appareils qui survolent vraiment (évite le hover
   "collé" après un tap sur mobile → texte rose invisible sur fond rose) */
@media (hover: hover) {
  .products-cat-tag:hover {
    border-color: var(--rose-300);
    color: var(--rose-500);
  }
}
.products-cat-tag--active,
.products-cat-tag--active:hover {
  background: var(--rose-500);
  border-color: var(--rose-500);
  color: #fff;
  box-shadow: 0 2px 8px rgba(var(--rose-500-rgb), 0.22);
}

/* Spacer = hauteur du topbar */
.products-topbar-spacer {
  height: 0; /* topbar est sticky, pas besoin de spacer supplémentaire */
}

/* ── Body ── */
.products-body {
  padding-top: var(--space-6);
  padding-bottom: var(--space-16);
}

/* ── Grille produits ── */
.products-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--space-5);
}

.product-skeleton {
  border-radius: var(--radius-lg);
  background: var(--cream-100);
  aspect-ratio: 3/4;
  animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0.5; }
}

/* ── Vide ── */
.products-empty {
  text-align: center;
  padding: var(--space-16) var(--space-8);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-4);
}
.products-empty__icon { font-size: 3rem; opacity: 0.5; }
.products-empty h3 {
  font-family: var(--font-display);
  font-size: 1.25rem;
  color: var(--gray-600);
}
.products-empty p { color: var(--gray-400); }

/* ── Pagination ── */
.products-pagination {
  display: flex;
  justify-content: center;
  gap: var(--space-2);
  margin-top: var(--space-10);
  flex-wrap: wrap;
}
.page-btn {
  width: 36px;
  height: 36px;
  border-radius: var(--radius-md);
  font-size: 0.875rem;
  font-weight: 500;
  border: 1.5px solid var(--cream-300);
  background: #fff;
  color: var(--gray-600);
  transition: all var(--transition-fast);
}
.page-btn:hover { border-color: var(--rose-300); color: var(--rose-500); }
.page-btn--active {
  background: var(--rose-500);
  border-color: var(--rose-500);
  color: #fff;
}

/* ── Responsive ── */
@media (max-width: 1024px) {
  .products-grid { grid-template-columns: repeat(3, 1fr); }
  .products-topbar__left { display: none; }
}

@media (max-width: 640px) {
  .products-grid { grid-template-columns: repeat(2, 1fr); gap: var(--space-3); }
  .products-topbar__search { max-width: unset; }
  .products-sort { display: none; }  /* tri masqué sur mobile, dans les tags */
  .products-body { padding-top: var(--space-4); }
}
</style>
