<template>
  <Teleport to="body">
    <Transition name="fcart">
      <div v-if="isVisible" class="fcart-wrap">
        <!-- Fermer / masquer -->
        <button class="floating-cart__dismiss" @click="dismiss" :aria-label="$t('floatingCart.dismiss')">×</button>

        <button
          class="floating-cart"
          @click="showModal = true"
          :aria-label="$t('floatingCart.open')"
        >
          <!-- Icône panier -->
          <span class="floating-cart__icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
              <line x1="3" y1="6" x2="21" y2="6"/>
              <path d="M16 10a4 4 0 0 1-8 0"/>
            </svg>
            <span class="floating-cart__badge">{{ itemCount }}</span>
          </span>

          <!-- Texte -->
          <span class="floating-cart__body">
            <span class="floating-cart__label">
              {{ itemCount }} {{ $t('common.article', itemCount) }}
            </span>
            <span class="floating-cart__total">{{ formattedTotal }}</span>
          </span>

          <!-- Flèche -->
          <span class="floating-cart__cta">
            {{ $t('floatingCart.cta') }}
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
              <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
          </span>
        </button>

        <!-- Lien checkout classique -->
        <RouterLink :to="{ name: 'checkout' }" class="floating-cart-alt">
          {{ $t('floatingCart.viewCart') }}
        </RouterLink>
      </div>
    </Transition>

    <!-- Modal commande rapide -->
    <QuickOrderModal v-if="showModal" @close="showModal = false" />
  </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useCartStore } from '@/features/cart/cart.store'
import { useCurrencyStore } from '@/stores/currency'
import { useSettingsStore } from '@/stores/settings'
import QuickOrderModal from '@/features/checkout/QuickOrderModal.vue'

const route     = useRoute()
const cartStore = useCartStore()
const settings  = useSettingsStore()
const showModal = ref(false)
const dismissed = ref(false)

const itemCount      = computed(() => cartStore.itemCount)
const formattedTotal = computed(() => useCurrencyStore().format(cartStore.total))

function dismiss() { dismissed.value = true }
// Un nouvel ajout au panier réaffiche le bouton (masquage = ponctuel)
watch(itemCount, (n, old) => { if (n > old) dismissed.value = false })

// Pages où le bouton doit être masqué
const HIDDEN_ROUTES = new Set([
  'checkout',       // en cours de commande → redondant
  'cart',           // l'utilisateur voit déjà son panier
  'orders',         // historique commandes → déjà acheté
  'order',          // détail d'une commande passée
  'payment-return', // retour paiement / confirmation
  'login',          // page auth → hors contexte achat
  'register',       // page auth → hors contexte achat
  'profile',        // espace compte → pas de contexte commercial
])

const isVisible = computed(() =>
  settings.floatingCartEnabled &&
  itemCount.value > 0 &&
  !dismissed.value &&
  !HIDDEN_ROUTES.has(route.name)
)
</script>

<style scoped>
/* Conteneur : seul élément positionné en fixe, empilé AU-DESSUS du bouton WhatsApp */
.fcart-wrap {
  position: fixed;
  bottom: 92px; /* le bouton WhatsApp occupe 24px → 80px ; on laisse un écart au-dessus */
  right: 20px;
  z-index: 900;

  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
}

/* Bouton fermer (masquer le panier flottant) */
.floating-cart__dismiss {
  position: absolute;
  top: -8px;
  right: -6px;
  z-index: 2;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  border: none;
  background: #fff;
  color: var(--gray-500);
  font-size: 15px;
  line-height: 1;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0,0,0,0.18);
  display: flex;
  align-items: center;
  justify-content: center;
}
.floating-cart__dismiss:hover { color: var(--gray-800); }

.floating-cart {
  display: flex;
  align-items: center;
  gap: 10px;

  padding: 10px 16px 10px 12px;
  border-radius: 50px;
  border: none;
  cursor: pointer;

  background: var(--color-primary, var(--rose-500));
  color: #fff;
  box-shadow: 0 4px 20px rgba(var(--rose-500-rgb), .45);

  font-family: var(--font-body);
  text-align: left;
  transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.floating-cart:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 28px rgba(var(--rose-500-rgb), .55);
}
.floating-cart:active { transform: translateY(0); }

/* Icône + badge */
.floating-cart__icon {
  position: relative;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px; height: 36px;
  background: rgba(255,255,255,.18);
  border-radius: 50%;
}
.floating-cart__badge {
  position: absolute;
  top: -4px; right: -4px;
  min-width: 18px; height: 18px;
  padding: 0 4px;
  border-radius: 9px;
  background: #fff;
  color: var(--color-primary, var(--rose-500));
  font-size: 0.6875rem;
  font-weight: 700;
  line-height: 18px;
  text-align: center;
}

/* Corps texte */
.floating-cart__body {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 80px;
}
.floating-cart__label {
  font-size: 0.6875rem;
  font-weight: 500;
  opacity: .85;
  line-height: 1;
}
.floating-cart__total {
  font-size: 1rem;
  font-weight: 700;
  line-height: 1;
  letter-spacing: -0.01em;
}

/* CTA */
.floating-cart__cta {
  display: flex;
  align-items: center;
  gap: 5px;
  padding-left: 10px;
  border-left: 1px solid rgba(255,255,255,.3);
  font-size: 0.8125rem;
  font-weight: 600;
  white-space: nowrap;
}

/* ── Lien checkout classique ── */
.floating-cart-alt {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--gray-500);
  text-decoration: underline;
  text-underline-offset: 3px;
  text-align: right;
  padding: 2px 6px;
  background: rgba(255, 255, 255, .75);
  border-radius: 6px;
  backdrop-filter: blur(4px);
  transition: color var(--transition-fast);
}
.floating-cart-alt:hover { color: var(--gray-900); }

/* ── Transition ── */
.fcart-enter-active,
.fcart-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}
.fcart-enter-from,
.fcart-leave-to {
  opacity: 0;
  transform: translateY(16px) scale(0.95);
}

/* Mobile : pilule compacte alignée à droite, au-dessus de la bottom-nav.
   On ne prend pas toute la largeur et on masque les éléments redondants. */
@media (max-width: 480px) {
  .fcart-wrap {
    left: auto;
    right: 14px;
    /* Empilé AU-DESSUS du bouton WhatsApp (bottom 72px, ~48px de haut) pour
       ne plus le chevaucher. */
    bottom: calc(132px + env(safe-area-inset-bottom, 0px));
    align-items: flex-end;
    gap: 0;
  }
  .floating-cart {
    padding: 8px 14px 8px 8px;
    gap: 8px;
  }
  .floating-cart__icon { width: 32px; height: 32px; }
  /* Total seul (pas la ligne "X articles") pour rester compact */
  .floating-cart__label { display: none; }
  .floating-cart__total { font-size: 0.9375rem; }
  .floating-cart__cta { padding-left: 8px; }
  /* Lien "Voir mon panier" redondant → masqué sur mobile */
  .floating-cart-alt { display: none; }
}
</style>
