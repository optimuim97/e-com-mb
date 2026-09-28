<template>
  <header ref="headerEl" class="navbar" :class="{ 'navbar--scrolled': isScrolled, 'navbar--open': mobileMenuOpen }">
    <!-- Bande d'annonce -->
    <div class="navbar__announce" v-if="settings.announceEnabled && !announceDismissed">
      <div class="navbar__announce-inner container">
        <span>{{ settings.announceText }}</span>
        <button class="navbar__announce-close" @click="announceDismissed = true" :aria-label="$t('common.close')">
          ×
        </button>
      </div>
    </div>

    <!-- Navbar principale -->
    <nav class="navbar__main container">
      <!-- Logo -->
      <RouterLink to="/" class="navbar__logo" :aria-label="settings.shopName + ' - ' + $t('common.home')">
        <BrandLogo img-class="navbar__logo-img" size="md" width="180" height="180" />
      </RouterLink>

      <!-- Navigation desktop -->
      <ul class="navbar__nav" role="list">
        <!-- Accueil -->
        <li>
          <RouterLink to="/" class="navbar__link" :class="{ 'navbar__link--active': $route.path === '/' }">
            {{ $t('common.home') }}
          </RouterLink>
        </li>

        <!-- Produits avec dropdown catégories -->
        <li class="navbar__dropdown-wrap" @mouseenter="productsOpen = true" @mouseleave="productsOpen = false">
          <RouterLink
            to="/products"
            class="navbar__link navbar__link--has-arrow"
            :class="{ 'navbar__link--active': $route.path.startsWith('/products') || $route.path.startsWith('/gammes') }"
          >
            {{ $t('common.products') }}
            <svg class="navbar__arrow" :class="{ 'navbar__arrow--open': productsOpen }" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
              <polyline points="6 9 12 15 18 9"/>
            </svg>
          </RouterLink>

          <!-- Dropdown -->
          <Transition name="dropdown">
            <div v-if="productsOpen" class="navbar__dropdown">
              <div class="navbar__dropdown-inner">
                <!-- Toutes les catégories -->
                <div class="navbar__dropdown-col">
                  <p class="navbar__dropdown-heading">Catégories</p>
                  <RouterLink
                    v-for="cat in homeStore.categories"
                    :key="cat.id"
                    :to="`/products?category=${cat.slug}`"
                    class="navbar__dropdown-link"
                    @click="productsOpen = false"
                  >
                    <span class="navbar__dropdown-dot"></span>
                    {{ cat.name }}
                  </RouterLink>
                  <RouterLink to="/products" class="navbar__dropdown-all" @click="productsOpen = false">
                    Voir tous les produits →
                  </RouterLink>
                </div>

                <!-- Gammes — uniquement si des gammes existent en BD -->
                <div v-if="productLines.length > 0" class="navbar__dropdown-col navbar__dropdown-col--border">
                  <p class="navbar__dropdown-heading">Gammes</p>
                  <RouterLink
                    v-for="line in productLines"
                    :key="line.id"
                    :to="`/gammes/${line.slug}`"
                    class="navbar__dropdown-link"
                    @click="productsOpen = false"
                  >
                    <span class="navbar__dropdown-dot navbar__dropdown-dot--rose"></span>
                    {{ line.name }}
                  </RouterLink>
                  <RouterLink to="/gammes" class="navbar__dropdown-all" @click="productsOpen = false">
                    Toutes les gammes →
                  </RouterLink>
                </div>
              </div>
            </div>
          </Transition>
        </li>

        <!-- À propos -->
        <li>
          <RouterLink to="/a-propos" class="navbar__link" :class="{ 'navbar__link--active': $route.path === '/a-propos' }">
            {{ $t('common.about') }}
          </RouterLink>
        </li>

        <!-- Blog -->
        <li v-if="settings.moduleEnabled('blog')">
          <RouterLink to="/blog" class="navbar__link" :class="{ 'navbar__link--active': $route.path.startsWith('/blog') }">
            {{ $t('common.blog') }}
          </RouterLink>
        </li>

        <!-- Contact -->
        <li>
          <RouterLink to="/contact" class="navbar__link" :class="{ 'navbar__link--active': $route.path === '/contact' }">
            Contact
          </RouterLink>
        </li>
      </ul>

      <!-- Actions droite -->
      <div class="navbar__actions">
        <!-- Devise -->
        <CurrencySwitcher class="hide-mobile" v-if="settings.shopCurrencyIsActive" />

        <!-- Language switcher — repris dans le menu mobile -->
        <LanguageSwitcher class="hide-mobile" />

        <!-- Recherche -->
        <button class="navbar__icon-btn" @click="$emit('open-search')" :aria-label="$t('nav.searchLabel')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
          </svg>
        </button>

        <!-- Compte / Déconnexion -->
        <RouterLink v-if="!auth.isLoggedIn" to="/login" class="navbar__icon-btn" :aria-label="$t('nav.loginLabel')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
            <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
          </svg>
        </RouterLink>
        <!--
          Administration, favoris et profil quittent la barre sur mobile : à
          sept contrôles, la rangée dépassait la largeur de l'écran. Les trois
          sont repris dans le menu déroulant, où ils sont bien plus faciles à
          viser au doigt qu'une icône de 40 px collée à ses voisines.
        -->
        <template v-else>
          <RouterLink v-if="auth.isAdmin && auth.isStaff" to="/admin" class="navbar__icon-btn hide-mobile" :aria-label="$t('nav.adminLabel')">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
              <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
            </svg>
          </RouterLink>

          <!-- Favoris -->
          <RouterLink v-if="settings.moduleEnabled('wishlist')" to="/wishlist" class="navbar__icon-btn navbar__icon-btn--relative hide-mobile" :aria-label="$t('nav.wishlistLabel')">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            <span v-if="wishlistCount > 0" class="navbar__badge navbar__badge--wish">{{ wishlistCount }}</span>
          </RouterLink>

          <!-- Profil utilisateur -->
          <RouterLink to="/profil" class="navbar__icon-btn navbar__icon-btn--relative hide-mobile" :aria-label="'Mon profil'">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
              <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
            </svg>
            <!-- Point rouge si profil à compléter -->
            <span v-if="auth.isQuickOrderUser" class="navbar__badge navbar__badge--alert" title="Complétez votre profil"></span>
          </RouterLink>
        </template>

        <!-- Panier -->
        <RouterLink to="/cart" class="navbar__cart-btn" :aria-label="$t('nav.cartLabel')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
            <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
            <line x1="3" y1="6" x2="21" y2="6"/>
            <path d="M16 10a4 4 0 0 1-8 0"/>
          </svg>
          <span v-if="cartCount > 0" class="navbar__badge navbar__badge--cart">{{ cartCount }}</span>
        </RouterLink>

        <!-- Burger mobile -->
        <button
          class="navbar__burger"
          @click="mobileMenuOpen = !mobileMenuOpen"
          :aria-expanded="mobileMenuOpen"
          :aria-label="$t('nav.menuLabel')"
        >
          <span></span><span></span><span></span>
        </button>
      </div>
    </nav>

    <!-- Menu mobile -->
    <Transition name="slide-down">
      <div v-if="mobileMenuOpen" class="navbar__mobile">
        <ul role="list">
          <li v-for="link in navLinks" :key="link.to">
            <RouterLink
              :to="link.to"
              class="navbar__mobile-link"
              @click="mobileMenuOpen = false"
            >
              {{ link.label }}
              <span v-if="link.badge" class="badge badge-rose">{{ link.badge }}</span>
            </RouterLink>
          </li>
        </ul>
        <div class="navbar__mobile-cta">
          <RouterLink to="/products" class="btn btn-primary" @click="mobileMenuOpen = false">
            {{ $t('common.viewAllProducts') }}
          </RouterLink>
        </div>

        <!-- Langue et devise, chassées de la barre faute de place -->
        <div class="navbar__mobile-switchers">
          <LanguageSwitcher />
          <CurrencySwitcher v-if="settings.shopCurrencyIsActive" />
        </div>
      </div>
    </Transition>
  </header>

  <!-- Spacer pour compenser la navbar fixed -->
  <div :style="{ height: spacerHeight + 'px' }"></div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore }     from '@/features/auth/auth.store'
import { useSettingsStore } from '@/stores/settings'
import BrandLogo from '@/components/ui/BrandLogo.vue'
import { useHomeStore }     from '@/features/home/home.store'
import api from '@/api'
import LanguageSwitcher from '@/components/ui/LanguageSwitcher.vue'
import CurrencySwitcher from '@/components/ui/CurrencySwitcher.vue'

const props = defineProps({
  cartCount:     { type: Number, default: 0 },
  wishlistCount: { type: Number, default: 0 },
})

defineEmits(['open-search'])

const { t } = useI18n()
const route    = useRoute()
const auth     = useAuthStore()
const settings = useSettingsStore()
const homeStore = useHomeStore()
const isScrolled = ref(false)
const mobileMenuOpen = ref(false)
const announceDismissed = ref(false)
const productsOpen = ref(false)
const productLines = ref([])

// Mobile nav links (gardés pour menu burger)
const navLinks = computed(() => [
  { to: '/',          label: t('common.home') },
  { to: '/products',  label: t('common.products') },
  { to: '/gammes',    label: t('common.productLines') },
  { to: '/a-propos',  label: t('common.about') },
  ...(settings.moduleEnabled('blog') ? [{ to: '/blog', label: t('common.blog') }] : []),
  { to: '/contact',   label: t('common.contact') },
  ...(settings.moduleEnabled('program') ? [{ to: '/programme', label: t('common.club') }] : []),
  ...(auth.isLoggedIn && settings.moduleEnabled('wishlist') ? [{ to: '/wishlist', label: t('common.wishlist'), badge: props.wishlistCount > 0 ? props.wishlistCount : null }] : []),
  ...(auth.isLoggedIn ? [{ to: '/orders',   label: t('common.myOrders') }] : []),
  ...(auth.isLoggedIn ? [{ to: '/profil',   label: t('common.profile'), badge: auth.isQuickOrderUser ? '!' : null }] : []),
  // L'icône d'administration disparaît de la barre sur mobile, faute de place :
  // sans cette entrée, le tableau de bord deviendrait inatteignable au doigt.
  ...(auth.isLoggedIn && auth.isAdmin && auth.isStaff ? [{ to: '/admin', label: t('nav.adminLabel') }] : []),
])

/*
 * Hauteur du cale-vide qui compense le header `fixed`.
 *
 * Elle était figée à 96 + 40 px. Deux choses la démentaient : la barre ne fait
 * pas la même hauteur selon l'appareil, et la bande d'annonce passe à deux
 * lignes dès que le texte est un peu long sur mobile — le haut du contenu
 * finissait alors caché sous la barre. On mesure donc l'élément réel.
 */
const headerEl     = ref(null)
const spacerHeight = ref(96)

let observer = null

onMounted(() => {
  if (!headerEl.value) return

  const mesurer = () => {
    // Menu ouvert, le panneau déroulant recouvre le contenu : il ne doit pas
    // entrer dans le calcul, sans quoi la page sauterait à chaque ouverture.
    const menu = headerEl.value.querySelector('.navbar__mobile')
    spacerHeight.value = headerEl.value.offsetHeight - (menu?.offsetHeight ?? 0)
  }

  observer = new ResizeObserver(mesurer)
  observer.observe(headerEl.value)
  mesurer()
})

onUnmounted(() => observer?.disconnect())

// Expose la hauteur totale comme variable CSS → tous les sticky en dessous s'ajustent
watch(spacerHeight, (h) => {
  document.documentElement.style.setProperty('--navbar-height', `${h}px`)
}, { immediate: true })

function handleScroll() {
  isScrolled.value = window.scrollY > 16
}

onMounted(async () => {
  window.addEventListener('scroll', handleScroll, { passive: true })
  homeStore.fetchCategories()
  try {
    const res = await api.get('/product-lines')
    productLines.value = res.data.data ?? res.data
  } catch { /* silencieux */ }
})
onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll)
})
</script>

<style scoped>
/* ── Bande annonce ── */
.navbar__announce {
  background: var(--rose-500);
  color: #fff;
  padding: 8px 0;
  font-size: 0.75rem;
  letter-spacing: 0.03em;
  font-weight: 500;
  position: relative;
  z-index: calc(var(--z-sticky) + 1);
}
.navbar__announce-inner {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-4);
}
.navbar__announce-close {
  margin-left: auto;
  color: rgba(255,255,255,0.7);
  font-size: 1.25rem;
  line-height: 1;
  transition: color var(--transition-fast);
}
.navbar__announce-close:hover { color: #fff; }

/* ── Navbar principale ── */
.navbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: var(--z-sticky);
  background: rgba(253, 250, 247, 0.92);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-bottom: 1px solid transparent;
  transition: all var(--transition-normal);
}

.navbar--scrolled {
  background: rgba(253, 250, 247, 0.98);
  border-bottom-color: var(--cream-300);
  box-shadow: 0 2px 20px rgba(168, 50, 80, 0.07);
}

.navbar__main {
  display: flex;
  align-items: center;
  gap: var(--space-8);
  height: 96px;
}

/* ── Logo ── */
.navbar__logo {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  transition: opacity var(--transition-fast);
}
.navbar__logo:hover { opacity: 0.85; }

.navbar__logo-img {
  height: 88px;
  width: auto;
  object-fit: contain;
  display: block;
}
/* La taille mobile du logo est fixée avec le reste des règles ≤640 px, en bas
   de la feuille — deux blocs concurrents pour la même propriété se contredisent
   vite au fil des retouches. */

/* ── Nav links ── */
.navbar__nav {
  display: flex;
  align-items: center;
  gap: 0;
  list-style: none;
  margin-left: auto;
}

.navbar__link {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  padding: 8px 16px;
  font-size: 0.875rem;
  font-weight: 400;
  color: var(--gray-600);
  border-radius: var(--radius-full);
  transition: all var(--transition-fast);
  position: relative;
}

.navbar__link:hover {
  color: var(--color-primary);
  background: var(--rose-50);
}

.navbar__link--active {
  color: var(--color-primary);
  font-weight: 500;
}

.navbar__link--active::after {
  content: '';
  position: absolute;
  bottom: -2px;
  left: 50%;
  transform: translateX(-50%);
  width: 20px;
  height: 2px;
  background: var(--color-primary);
  border-radius: var(--radius-full);
}

.navbar__link-badge {
  background: var(--rose-500);
  color: #fff;
  font-size: 0.6rem;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: var(--radius-full);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

/* ── Dropdown ── */
.navbar__dropdown-wrap {
  position: relative;
}

.navbar__link--has-arrow {
  display: flex;
  align-items: center;
  gap: 4px;
}

.navbar__arrow {
  transition: transform var(--transition-fast);
  opacity: 0.6;
}
.navbar__arrow--open { transform: rotate(180deg); }

.navbar__dropdown {
  position: absolute;
  top: calc(100% + 8px);
  left: 50%;
  transform: translateX(-50%);
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 12px 40px rgba(0,0,0,0.12), 0 1px 4px rgba(0,0,0,0.06);
  border: 1px solid var(--cream-200);
  z-index: 200;
  min-width: 360px;
}

.navbar__dropdown-inner {
  display: flex;
  padding: 8px;
  gap: 0;
}

.navbar__dropdown-col {
  flex: 1;
  padding: 12px 16px;
}

.navbar__dropdown-col--border {
  border-left: 1px solid var(--cream-200);
}

.navbar__dropdown-heading {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1.2px;
  color: var(--gray-400);
  margin-bottom: 10px;
  padding-left: 8px;
}

.navbar__dropdown-link {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 8px;
  border-radius: 8px;
  font-size: 0.875rem;
  color: var(--gray-600);
  font-weight: 400;
  transition: all var(--transition-fast);
  text-decoration: none;
}
.navbar__dropdown-link:hover {
  background: var(--rose-50);
  color: var(--color-primary);
}

.navbar__dropdown-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: var(--gray-300);
  flex-shrink: 0;
}
.navbar__dropdown-dot--rose { background: var(--rose-400); }

.navbar__dropdown-all {
  display: block;
  margin-top: 10px;
  padding: 8px;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-primary);
  text-decoration: none;
  transition: opacity var(--transition-fast);
}
.navbar__dropdown-all:hover { opacity: 0.75; }

/* Dropdown transition */
.dropdown-enter-active,
.dropdown-leave-active {
  transition: all 0.18s ease;
  transform-origin: top center;
}
.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateX(-50%) translateY(-6px) scale(0.97);
}
.dropdown-enter-to,
.dropdown-leave-from {
  opacity: 1;
  transform: translateX(-50%) translateY(0) scale(1);
}

/* ── Actions ── */
.navbar__actions {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  margin-left: var(--space-2);
}

.navbar__icon-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: var(--radius-full);
  color: var(--gray-600);
  transition: all var(--transition-fast);
  position: relative;
}
.navbar__icon-btn:hover {
  background: var(--rose-50);
  color: var(--color-primary);
}

.navbar__cart-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  background: var(--rose-500);
  color: #fff;
  border-radius: var(--radius-full);
  transition: all var(--transition-normal);
  position: relative;
  box-shadow: var(--shadow-rose);
}
.navbar__cart-btn:hover {
  background: var(--rose-600);
  transform: translateY(-1px);
  box-shadow: 0 8px 24px rgba(var(--rose-500-rgb), 0.35);
}

.navbar__badge {
  position: absolute;
  top: 4px;
  right: 4px;
  min-width: 16px;
  height: 16px;
  background: var(--gray-800);
  color: #fff;
  font-size: 0.625rem;
  font-weight: 600;
  border-radius: var(--radius-full);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 4px;
  border: 1.5px solid var(--cream-50);
}

.navbar__badge--cart {
  background: #fff;
  color: var(--rose-600);
  top: 2px;
  right: 2px;
}

.navbar__badge--wish {
  top: 2px;
  right: 2px;
  background: var(--rose-500);
  color: #fff;
}

.navbar__badge--alert {
  top: 4px;
  right: 4px;
  width: 8px;
  height: 8px;
  min-width: unset;
  padding: 0;
  background: #f59e0b;
  border: 1.5px solid var(--cream-50);
  border-radius: 50%;
  animation: pulse-alert 2s ease-in-out infinite;
}

@keyframes pulse-alert {
  0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.5); }
  50%       { box-shadow: 0 0 0 4px rgba(245, 158, 11, 0); }
}

.navbar__icon-btn--relative {
  position: relative;
}

/* ── Burger ── */
.navbar__burger {
  display: none;
  flex-direction: column;
  gap: 5px;
  padding: 8px;
  border-radius: var(--radius-sm);
  transition: all var(--transition-fast);
}
.navbar__burger span {
  display: block;
  width: 22px;
  height: 1.5px;
  background: var(--gray-700);
  border-radius: 1px;
  transition: all var(--transition-normal);
}
.navbar--open .navbar__burger span:nth-child(1) {
  transform: translateY(6.5px) rotate(45deg);
}
.navbar--open .navbar__burger span:nth-child(2) {
  opacity: 0;
  transform: scaleX(0);
}
.navbar--open .navbar__burger span:nth-child(3) {
  transform: translateY(-6.5px) rotate(-45deg);
}

/* ── Mobile menu ── */
.navbar__mobile {
  background: var(--color-surface);
  border-top: 1px solid var(--cream-200);
  padding: var(--space-4) 0 var(--space-6);
  box-shadow: 0 16px 40px rgba(0,0,0,0.08);
}

.navbar__mobile ul {
  list-style: none;
  padding: 0 var(--space-4);
}

.navbar__mobile-link {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-4) var(--space-2);
  font-size: 1.0625rem;
  font-weight: 400;
  color: var(--gray-700);
  border-bottom: 1px solid var(--cream-200);
  transition: color var(--transition-fast);
}
.navbar__mobile-link:hover { color: var(--color-primary); }

.navbar__mobile-cta {
  padding: var(--space-6) var(--space-6) 0;
  display: flex;
  gap: var(--space-3);
}
.navbar__mobile-cta .btn { flex: 1; justify-content: center; }

/* ── Transitions ── */
.slide-down-enter-active,
.slide-down-leave-active {
  transition: all var(--transition-normal);
  overflow: hidden;
}
.slide-down-enter-from,
.slide-down-leave-to {
  opacity: 0;
  max-height: 0;
}
.slide-down-enter-to,
.slide-down-leave-from {
  opacity: 1;
  max-height: 500px;
}

.navbar__mobile-switchers {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-4) 0;
}

/* ── Responsive ── */
@media (max-width: 1024px) {
  .navbar__nav { display: none; }
  .navbar__burger { display: flex; }

  /*
   * C'est `.navbar__nav` qui portait le `margin-left: auto` poussant tout vers
   * la droite. Masqué, il emportait l'alignement avec lui et les actions
   * venaient se coller au logo.
   */
  .navbar__actions { margin-left: auto; }

  /* Le menu vit dans un header `fixed` : sans hauteur bornée, dix entrées
     poussent le bouton d'appel hors de l'écran sur un petit téléphone. */
  .navbar__mobile {
    max-height: calc(100dvh - 64px);
    overflow-y: auto;
    overscroll-behavior: contain;
  }
}

@media (max-width: 640px) {
  /* 96 px de barre plus 68 px de logo mangeaient un tiers d'un écran de
     téléphone. On revient aux proportions habituelles d'une barre mobile. */
  .navbar__main {
    height: 64px;
    gap: var(--space-2);
  }
  .navbar__logo-img { height: 48px; }

  .navbar__icon-btn {
    width: 36px;
    height: 36px;
  }
  .navbar__cart-btn {
    width: 40px;
    height: 40px;
  }
}

</style>
