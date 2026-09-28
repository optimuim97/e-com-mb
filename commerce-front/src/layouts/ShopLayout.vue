<template>
  <div class="min-h-screen flex flex-col" style="background: var(--color-bg);">
    <AppNavbar
      :cart-count="cartStore.itemCount"
      :wishlist-count="wishlistStore.count"
      @open-search="handleSearch"
    />

    <main class="flex-1">
      <RouterView v-slot="{ Component }">
        <Transition name="page" mode="out-in">
          <!--
            keep-alive pour les pages "liste" : la data et le DOM sont préservés
            entre navigations. Les pages détail (ProductView, OrderView…) ne sont
            PAS dans include, donc elles se recréent normalement avec la bonne clé.
          -->
          <keep-alive :include="CACHED_VIEWS" :max="6">
            <component
              :is="Component"
              :key="route.meta.stableKey ?? route.path"
            />
          </keep-alive>
        </Transition>
      </RouterView>
    </main>

    <CartDrawer />
    <BottomNav />
    <AppFooter />
  </div>
</template>

<script setup>
import { watch } from 'vue'
import { RouterView, useRouter, useRoute } from 'vue-router'

// Pages dont le DOM + état est préservé entre navigations
// Le nom doit correspondre à l'option `name` du composant Vue (ou au nom du fichier)
const CACHED_VIEWS = ['HomeView', 'ProductsView', 'BlogView', 'WishlistView']
import { useCartStore }     from '@/features/cart/cart.store'
import { useWishlistStore } from '@/features/wishlist/wishlist.store'
import { useSettingsStore } from '@/stores/settings'
import { useAuthStore }     from '@/features/auth/auth.store'
import AppNavbar  from '@/components/layout/AppNavbar.vue'
import AppFooter  from '@/components/layout/AppFooter.vue'
import CartDrawer from '@/features/cart/CartDrawer.vue'
import BottomNav  from '@/components/layout/BottomNav.vue'

const cartStore     = useCartStore()
const wishlistStore = useWishlistStore()
const settingsStore = useSettingsStore()
const authStore     = useAuthStore()
const router = useRouter()
const route  = useRoute()

// Chargement initial du panier
// Si un brouillon de commande est en attente (l'utilisateur vient de se connecter/inscrire
// depuis le panier), on ne recharge PAS le panier serveur pour ne pas écraser les articles
// en mémoire. Le brouillon sera restauré par CartDrawer.onMounted.
if (!sessionStorage.getItem('shop_checkout_draft')) {
  cartStore.fetch().catch(() => {})
}

// Charger la wishlist dès que l'user est authentifié
watch(
  () => authStore.user,
  async (user) => {
    if (user) {
      await settingsStore.fetch()
      if (settingsStore.moduleEnabled('wishlist')) wishlistStore.fetch()
    } else {
      wishlistStore.clear()
    }
  },
  { immediate: true }
)

function handleSearch() {
  // Navigue vers la page produits et demande le focus auto du champ de recherche.
  // Le timestamp force le changement de query même si on est déjà sur /products.
  router.push({ name: 'products', query: { ...route.query, focus: 'search' } })
}
</script>

<style scoped>
.page-enter-active,
.page-leave-active { transition: opacity 0.2s ease; }
.page-enter-from,
.page-leave-to { opacity: 0; }

/* Espace pour la bottom nav sur mobile */
@media (max-width: 768px) {
  .flex-1 { padding-bottom: 72px; }
}
</style>
