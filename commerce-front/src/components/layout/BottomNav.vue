<template>
  <Transition name="bottom-nav-slide">
    <nav v-show="!cartStore.isOpen && !hideNav" class="bottom-nav" aria-label="Navigation principale">
      <RouterLink
        to="/"
        class="bottom-nav__item"
        :class="{ 'bottom-nav__item--active': route.path === '/' }"
      >
        <svg
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.8"
          stroke-linecap="round"
        >
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
          <polyline points="9 22 9 12 15 12 15 22" />
        </svg>
<!--        <span>Accueil</span>-->
      </RouterLink>

      <RouterLink
        to="/products"
        class="bottom-nav__item"
        :class="{ 'bottom-nav__item--active': route.path.startsWith('/products') }"
      >
        <svg
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.8"
          stroke-linecap="round"
        >
          <circle cx="11" cy="11" r="8" />
          <path d="m21 21-4.35-4.35" />
        </svg>
<!--        <span>Produits</span>-->
      </RouterLink>

      <RouterLink
        to="/cart"
        class="bottom-nav__item bottom-nav__item--cart"
        :class="{ 'bottom-nav__item--active': route.path.startsWith('/cart') }"
      >
        <div class="bottom-nav__cart-wrap">
          <svg
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
          >
            <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
            <line x1="3" y1="6" x2="21" y2="6" />
            <path d="M16 10a4 4 0 0 1-8 0" />
          </svg>
          <span v-if="cartStore.itemCount > 0" class="bottom-nav__badge">{{
            cartStore.itemCount
          }}</span>
        </div>
<!--        <span>Panier</span>-->
      </RouterLink>
    </nav>
  </Transition>
</template>

<script setup>
import { computed } from "vue";
import { useRoute, RouterLink } from "vue-router";
import { useCartStore } from "@/features/cart/cart.store";

const route = useRoute();
const cartStore = useCartStore();

// Pages "focus" : on masque la barre de navigation pour que l'utilisateur
// se concentre sur la finalisation (checkout, retour de paiement).
const FOCUS_ROUTES = ["checkout", "payment-return"];
const hideNav = computed(() =>
  FOCUS_ROUTES.includes(route.name) || String(route.path).startsWith("/checkout")
);
</script>

<style scoped>
.bottom-nav {
  display: none; /* visible uniquement sur mobile via media query */
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  z-index: 50;
  background: rgba(255, 252, 250, 0.97);
  backdrop-filter: blur(16px);
  border-top: 1px solid var(--cream-200);
  padding: 6px var(--space-4) env(safe-area-inset-bottom, 6px);
  grid-template-columns: repeat(3, 1fr);
  box-shadow: 0 -4px 24px rgba(0, 0, 0, 0.06);
  /* Fix Chrome iOS : forcer un nouveau stacking context pour éviter
     que la barre se détache pendant l'animation du URL bar */
  transform: translateZ(0);
  will-change: transform;
  -webkit-backface-visibility: hidden;
}

.bottom-nav__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  min-height: 44px;         /* cible tactile confortable (recommandation WCAG/Apple) */
  padding: 8px var(--space-2);
  color: var(--gray-400);
  text-decoration: none;
  font-size: 0.5625rem;
  font-weight: 500;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  border: none;
  background: none;
  cursor: pointer;
  transition: color var(--transition-fast);
}
.bottom-nav__item svg {
  transition: transform var(--transition-fast);
}
.bottom-nav__item:hover {
  color: var(--rose-400);
}
.bottom-nav__item:hover svg {
  transform: translateY(-1px);
}

.bottom-nav__item--active {
  color: var(--rose-500);
}
.bottom-nav__item--active svg {
  stroke: var(--rose-500);
}

.bottom-nav__item--cart {
  position: relative;
}
.bottom-nav__cart-wrap {
  position: relative;
  display: flex;
}
.bottom-nav__badge {
  position: absolute;
  top: -6px;
  right: -8px;
  min-width: 15px;
  height: 15px;
  background: var(--rose-500);
  color: #fff;
  border-radius: 9999px;
  font-size: 0.5625rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 3px;
  border: 1.5px solid #fff;
}

@media (max-width: 768px) {
  .bottom-nav {
    display: grid;
  }
}

.bottom-nav-slide-enter-active,
.bottom-nav-slide-leave-active {
  transition: transform 0.25s ease;
}
.bottom-nav-slide-enter-from,
.bottom-nav-slide-leave-to {
  transform: translateY(100%);
}
</style>
