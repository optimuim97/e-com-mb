<template>
  <div>
    <!-- 1. Accroche émotionnelle plein écran -->
    <HeroSection
      :heroProduct="heroProduct"
      :flashProduct="flashProduct"
      :ratingAvg="homeStats.rating_avg || 4.9"
      :reviewsCount="homeStats.reviews_count || 0"
      :clientsCount="homeStats.clients_count || 0"
      :featuredReview="homeStats.featured_review"
      :heroEyebrow="settings.homeHeroEyebrow"
      :heroTitle="settings.homeHeroTitle"
      :heroSubtitle="settings.homeHeroSubtitle"
      @add-to-cart="addToCart"
    />

    <!-- 1b. Opération en cours — juste sous l'accroche, le créneau standard
         d'une campagne : visible sans repousser la marque. Rien ne s'affiche
         hors période de promotion. -->
    <PromoBannerSection
      v-if="homeStats.promo_campaign"
      :campaign="homeStats.promo_campaign"
    />

    <!-- 2. Bande de réassurance — rassure dès le 1er scroll (naturel, livraison…) -->
    <BenefitsSection :items="benefits" />

    <!-- 3. Best-sellers — intention d'achat + preuve sociale immédiate -->
    <BestSellersSection
      :products="featured"
      :loading="loadingProducts"
      @add-to-cart="addToCart"
    />

    <!-- 4. Univers / catégories — naviguer par besoin -->
    <CategoriesSection :categories="categories" :loading="loadingCats" />

    <!-- 5. Vente flash — urgence / promo, au cœur de la page (pas avant la marque) -->
    <FlashSaleSection
      v-if="homeStats.flash_sales && homeStats.flash_sales.length"
      :products="homeStats.flash_sales"
      :label="settings.homeFlashLabel || $t('flashSale.defaultLabel')"
      @add-to-cart="addToCart"
    />

    <!-- 6. Nouveautés -->
    <NewCollectionSection
      v-if="homeStats.new_collection && homeStats.new_collection.length"
      :products="homeStats.new_collection"
      @add-to-cart="addToCart"
    />

    <!-- 7. Stories blog — nouveautés / conseils beauté -->
    <BlogStoriesSection v-if="settings.moduleEnabled('blog')" />

    <!-- 8. Notre histoire — connexion émotionnelle à la marque -->
    <AboutSection
      :shopName="settings.shopName"
      :tagline="settings.shopTagline || FALLBACK_TAGLINE"
      :promises="promises"
      :stats="dynamicStats"
    />

    <!-- 9. Galerie photos -->
    <GallerySection v-if="settings.moduleEnabled('gallery')" />

    <!-- 9b. Gammes de soins — ouverture vers les rituels complets -->
    <ProductLinesSection />

    <!-- 10. Avis clients — dernière preuve sociale avant le footer -->
    <TestimonialsSection :items="testimonials" />

    <!-- 11. Teasers : Histoire / Club / Blog — invitation à poursuivre -->
    <ExploreSection />
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useCartStore }     from '@/features/cart/cart.store'
import { useSettingsStore } from '@/stores/settings'
import { useHomeStore }     from '@/features/home/home.store'

import HeroSection          from '@/features/home/sections/HeroSection.vue'
import GallerySection       from '@/features/home/sections/GallerySection.vue'
import BenefitsSection      from '@/features/home/sections/BenefitsSection.vue'
import CategoriesSection    from '@/features/home/sections/CategoriesSection.vue'
import ProductLinesSection  from '@/features/home/sections/ProductLinesSection.vue'
import BestSellersSection   from '@/features/home/sections/BestSellersSection.vue'
import NewCollectionSection from '@/features/home/sections/NewCollectionSection.vue'
import FlashSaleSection     from '@/features/home/sections/FlashSaleSection.vue'
import PromoBannerSection   from '@/features/home/sections/PromoBannerSection.vue'
import AboutSection         from '@/features/home/sections/AboutSection.vue'
import ExploreSection       from '@/features/home/sections/ExploreSection.vue'
import TestimonialsSection  from '@/features/home/sections/TestimonialsSection.vue'
import BlogStoriesSection   from '@/features/home/sections/BlogStoriesSection.vue'
import { useSeo, organizationJsonLd, webSiteJsonLd } from '@/composables/useSeo'

/* ── Stores ─────────────────────────────────────────────────────────────── */
const { t }     = useI18n()
const cartStore = useCartStore()
const settings  = useSettingsStore()
const homeStore = useHomeStore()

/* ── storeToRefs : déstructuration réactive (conserve la réactivité) ──── */
const {
  categories,
  featured,
  homeStats,
  loadingCats,
  loadingProducts,
} = storeToRefs(homeStore)

const FALLBACK_TAGLINE = computed(() => t('about.defaultTagline'))

/* ── SEO ────────────────────────────────────────────────────────────────────
   La page d'accueil porte les blocs Store et WebSite : ce sont eux que Google
   rattache au domaine (encart marque, barre de recherche dans les résultats). */
useSeo(() => ({
  title: settings.homeHeroTitle
    ? `${settings.shopName} — ${settings.homeHeroTitle}`
    : t('home.seoTitle'),
  description: settings.shopTagline || t('home.seoDescription'),
  canonical: '/',
  image: settings.seoOgImage || '/og-default.jpg',
  jsonLd: [
    organizationJsonLd({
      shop_name: settings.shopName,
      shop_tagline: settings.shopTagline,
      shop_phone: settings.shopPhone,
      shop_email: settings.shopEmail,
      shop_address: settings.shopAddress,
    }),
    webSiteJsonLd(),
  ],
}))

/* ── Chargement : ne refetch que si le TTL de 5 min est dépassé ─────────── */
onMounted(() => homeStore.fetchAll())

/* ── Produit hero : best-seller en priorité, sinon premier featured ──────── */
const heroProduct  = computed(() => homeStats.value.best_seller ?? featured.value[0] ?? null)
const flashProduct = computed(() => homeStats.value.flash_sales?.[0] ?? null)

/* ── Témoignages ─────────────────────────────────────────────────────────── */
const FALLBACK_TESTIMONIALS = computed(() => [
  { name: 'Aminata K.',   location: settings.homeCity, quote: t('testimonials.fallback1'), rating: 5 },
  { name: 'Fatoumata D.', location: settings.homeCity, quote: t('testimonials.fallback2'), rating: 5 },
  { name: 'Nathalie B.',  location: 'Bouaké',  quote: t('testimonials.fallback3'), rating: 5 },
])
const testimonials = computed(() =>
  homeStats.value.testimonials?.length ? homeStats.value.testimonials : FALLBACK_TESTIMONIALS.value
)

/* ── Panier ─────────────────────────────────────────────────────────────── */
function addToCart(product) {
  cartStore.add(product.id ?? product, 1, null, { snapshot: product })
}

/* ── Bénéfices ───────────────────────────────────────────────────────────── */
const benefits = computed(() => [
  {
    icon: `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>`,
    title: t('benefits.naturalFormulas'),
    desc:  t('benefits.naturalDesc'),
  },
  {
    icon: `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>`,
    title: t('benefits.pureRose'),
    desc:  t('benefits.pureRoseDesc'),
  },
  {
    icon: `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>`,
    title: t('benefits.visibleResults'),
    desc:  t('benefits.visibleResultsDesc'),
  },
  {
    icon: `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>`,
    title: t('benefits.deliveryTitle', { delay: settings.shippingDelay }),
    // Pas de promesse de livraison gratuite (aucune n'existe réellement → risque de litige)
    desc:  t('benefits.deliveryDesc'),
  },
])

const promises = computed(() => [
  t('about.promises.ingredients'),
  t('about.promises.dermatological'),
  t('about.promises.cruelfree'),
  t('about.promises.madeInCI'),
])

/* ── Stats dynamiques ────────────────────────────────────────────────────── */
const dynamicStats = computed(() => [
  {
    // Volontairement statique : clients_count ne compte que les comptes
    // créés sur le site (une poignée), pas les clientes servies. L'afficher
    // donnait « 11+ », en contradiction avec la page À propos.
    value: '5 000+',
    label: t('about.satisfiedClients'),
  },
  { value: '100%', label: t('about.naturalPure') },
  {
    value: categories.value.length || 5,
    label: t('about.careRanges'),
  },
  {
    value: settings.shippingDelay,
    label: t('about.deliveryDelay'),
  },
])
</script>
