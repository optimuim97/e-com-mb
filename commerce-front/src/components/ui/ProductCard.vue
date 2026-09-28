<template>
  <article
    class="product-card"
    :class="{ 'product-card--featured': product.is_featured }"
  >
    <!-- ── Zone image ── -->
    <div
      class="product-card__img-wrap"
      @mouseenter="hovered = true"
      @mouseleave="hovered = false"
    >
      <!-- Badges top-left -->
      <div class="product-card__badges">
        <span v-if="unavailableKey" class="badge badge-dark">{{ $t(unavailableKey) }}</span>
        <span v-if="product.is_featured" class="badge badge-rose">{{
          $t("product.featured")
        }}</span>
        <span v-if="discountPercent" class="badge badge-gold"
          >-{{ discountPercent }}%</span
        >
      </div>

      <!-- Wishlist -->
      <div class="absolute top-3 right-3 z-10" @click.prevent>
        <WishlistButton :productId="product.id" />
      </div>

      <!-- Image principale -->
      <RouterLink :to="`/products/${product.slug}`" class="product-card__img-link">
        <img
          v-if="cover"
          :src="cover"
          :alt="product.name"
          class="product-card__img"
          :class="{
            'product-card__img--zoom': hovered && coverAlt,
            'product-card__img--grayscale': !orderable,
          }"
          loading="lazy"
        />
        <!-- Image de survol (2e image du produit) -->
        <img
          v-if="coverAlt"
          :src="coverAlt"
          :alt="product.name + ' — vue alternative'"
          class="product-card__img product-card__img--hover"
          :class="{ 'product-card__img--visible': hovered }"
          loading="lazy"
        />
        <!-- Fallback -->
        <div v-if="!cover" class="product-card__img-placeholder"><FlowerMark /></div>
      </RouterLink>

      <!-- Ajout rapide au survol -->
      <div
        v-if="orderable"
        class="product-card__quick-add"
        :class="{ 'product-card__quick-add--visible': hovered }"
      >
        <button class="product-card__add-btn" @click.prevent="addToCart">
          <svg
            width="15"
            height="15"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
          >
            <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
            <line x1="3" y1="6" x2="21" y2="6" />
            <path d="M16 10a4 4 0 0 1-8 0" />
          </svg>
          {{ $t("product.addToCart") }}
        </button>
      </div>
    </div>

    <!-- ── Infos produit ── -->
    <div class="product-card__info">
      <div class="product-card__meta">
        <span class="product-card__cat">{{ product.category?.name }}</span>
        <RouterLink
          v-if="product.product_line"
          :to="`/gammes/${product.product_line.slug}`"
          class="product-card__line"
          :style="{
            color: product.product_line.color_hex,
            borderColor: product.product_line.color_hex + '44',
            background: product.product_line.color_hex + '12',
          }"
          @click.stop
        >
          {{ product.product_line.name }}
        </RouterLink>
      </div>

      <RouterLink :to="`/products/${product.slug}`" class="product-card__name-link">
        <h3 class="product-card__name">{{ product.name }}</h3>
      </RouterLink>

      <!-- Footer : prix + bouton panier -->
      <div class="product-card__footer">
        <div class="product-card__price">
          <span v-if="referencePrice" class="product-card__price-old">
            {{ formatPrice(referencePrice) }}
          </span>
          <span class="product-card__price-current">{{
            formatPrice(sellingPrice)
          }}</span>
        </div>

        <button
          class="product-card__cart-btn"
          :class="{
            'product-card__cart-btn--added': added,
            'product-card__cart-btn--out': !orderable,
          }"
          :disabled="!orderable"
          @click.prevent="addToCart"
          :aria-label="$t('product.addToCart') + ' ' + product.name"
        >
          <Transition name="swap" mode="out-in">
            <svg
              v-if="!added"
              key="bag"
              width="17"
              height="17"
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
            <svg
              v-else
              key="check"
              width="17"
              height="17"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.5"
              stroke-linecap="round"
            >
              <polyline points="20 6 9 17 4 12" />
            </svg>
          </Transition>
        </button>
      </div>
    </div>
  </article>
</template>

<script setup>
import { ref, computed } from "vue";
import { RouterLink } from "vue-router";
import { useI18n } from "vue-i18n";
import WishlistButton from "@/features/wishlist/WishlistButton.vue";
import { useCurrencyStore } from "@/stores/currency";
import * as pricing from "@/utils/pricing";
import { isOrderable, unavailableLabelKey } from "@/utils/availability";

const props = defineProps({
  product: { type: Object, required: true },
});

const { t } = useI18n();
const currency = useCurrencyStore();
const emit = defineEmits(["add-to-cart", "toggle-wishlist"]);

const hovered = ref(false);
const wishlisted = ref(props.product.wishlisted ?? false);
const added = ref(false);

/* Images */
const cover = computed(() => props.product.images?.[0]?.url ?? null);
const coverAlt = computed(() => props.product.images?.[1]?.url ?? null);

/* Price shown to the customer: promotions and flash sales included. */
const sellingPrice = computed(() => pricing.sellingPrice(props.product));
const referencePrice = computed(() => pricing.referencePrice(props.product));
const discountPercent = computed(() => pricing.discountPercent(props.product));

function formatPrice(val) {
  return currency.format(val);
}

/* Disponibilité : décidée par le serveur, voir utils/availability. */
const orderable = computed(() => isOrderable(props.product));
const unavailableKey = computed(() => unavailableLabelKey(props.product));

function addToCart() {
  if (!orderable.value) return;
  emit("add-to-cart", props.product);
  added.value = true;
  setTimeout(() => {
    added.value = false;
  }, 1800);
}

function toggleWishlist() {
  wishlisted.value = !wishlisted.value;
  emit("toggle-wishlist", { product: props.product, wishlisted: wishlisted.value });
}
</script>

<style scoped>
/* ── Carte ── */
.product-card {
  display: flex;
  flex-direction: column;
  border-radius: var(--radius-lg);
  overflow: hidden;
  background: var(--color-surface);
  border: 1px solid var(--color-border-light);
  transition: all var(--transition-normal);
  cursor: pointer;
}
.product-card:hover {
  box-shadow: var(--shadow-md);
  transform: translateY(-4px);
  border-color: var(--rose-200);
}
.product-card--featured {
  border: 2px solid var(--rose-300);
  box-shadow: var(--shadow-rose);
}

/* ── Zone image ── */
.product-card__img-wrap {
  position: relative;
  aspect-ratio: 1;
  overflow: hidden;
  background: linear-gradient(145deg, var(--rose-50), var(--cream-100));
}
.product-card__img-link {
  display: block;
  width: 100%;
  height: 100%;
}
.product-card__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: all var(--transition-slow);
}
.product-card__img--zoom {
  transform: scale(1.06);
}
.product-card__img--grayscale {
  opacity: 0.5;
  filter: grayscale(1);
}
.product-card__img--hover {
  position: absolute;
  inset: 0;
  opacity: 0;
  transition: opacity var(--transition-normal);
}
.product-card__img--visible {
  opacity: 1;
}
.product-card__img-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3.5rem;
}

/* ── Badges ── */
.product-card__badges {
  position: absolute;
  top: var(--space-3);
  left: var(--space-3);
  z-index: 2;
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

/* ── Wishlist ── */
.product-card__wish {
  position: absolute;
  top: var(--space-3);
  right: var(--space-3);
  z-index: 2;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.92);
  color: var(--gray-400);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all var(--transition-fast);
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--cream-200);
}
.product-card__wish:hover {
  background: #fff;
  color: var(--rose-400);
  transform: scale(1.1);
}
.product-card__wish--active {
  color: var(--rose-500);
  background: var(--rose-50);
  border-color: var(--rose-200);
}

/* ── Ajout rapide ── */
.product-card__quick-add {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: var(--space-3);
  background: linear-gradient(to top, rgba(0, 0, 0, 0.38), transparent);
  opacity: 0;
  transform: translateY(8px);
  transition: all var(--transition-normal);
}
.product-card__quick-add--visible {
  opacity: 1;
  transform: translateY(0);
}
.product-card__add-btn {
  width: 100%;
  padding: 10px;
  background: rgba(255, 255, 255, 0.95);
  border-radius: var(--radius-full);
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--gray-800);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  transition: all var(--transition-fast);
}
.product-card__add-btn:hover {
  background: var(--rose-500);
  color: #fff;
}

/* ── Infos ── */
.product-card__info {
  padding: var(--space-4) var(--space-4) var(--space-5);
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  flex: 1;
}
.product-card__meta {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  flex-wrap: wrap;
}
/* Catégorie : contexte discret, pas de compétition visuelle */
.product-card__cat {
  font-size: 0.5625rem;
  font-weight: 500;
  letter-spacing: 0.13em;
  text-transform: uppercase;
  color: var(--gray-400);
}
/* Tag gamme : toute petite pilule colorée, presque un détail */
.product-card__line {
  font-size: 0.5375rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  padding: 1px 5px;
  border-radius: var(--radius-full);
  border: 1px solid;
  transition: opacity var(--transition-fast);
  line-height: 1.7;
}
.product-card__line:hover {
  opacity: 0.7;
}
.product-card__name-link {
  display: block;
}
/* Nom : vedette principale de la carte */
.product-card__name {
  font-family: var(--font-display);
  font-size: 1.3125rem;
  font-weight: 700;
  color: var(--gray-900, #1a1416);
  line-height: 1.3;
  letter-spacing: -0.01em;
  transition: color var(--transition-fast);
}
.product-card__name-link:hover .product-card__name {
  color: var(--color-primary);
}

/* ── Footer ── */
.product-card__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  margin-top: auto;
  padding-top: var(--space-3);
  border-top: 1px solid var(--cream-200);
}

/*
 * `min-width: 0` est ce qui empêche le débordement : par défaut un élément
 * flex vaut `min-width: auto` et refuse de descendre sous la largeur de son
 * contenu. Un prix long poussait donc le bouton panier hors de la carte, et
 * d'autant plus loin que le montant était grand.
 */
.product-card__price {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  flex: 1 1 auto;
}
/* Le montant et sa devise ne se séparent jamais : « 2 500 » sur une ligne et
   « XOF » sur la suivante se lit comme deux informations distinctes. */
.product-card__price-old,
.product-card__price-current {
  white-space: nowrap;
}
.product-card__price-old {
  font-size: 0.75rem;
  color: var(--gray-300);
  text-decoration: line-through;
}
/*
 * Taille fluide plutôt que fixe : sur une carte étroite, un montant à sept
 * chiffres tient ainsi sur une ligne au lieu de se couper en deux.
 */
.product-card__price-current {
  font-family: var(--font-sans);
  font-size: clamp(1rem, 0.72rem + 1.4vw, 1.375rem);
  line-height: 1.15;
  font-weight: 800;
  color: var(--rose-600);
  letter-spacing: -0.01em;
}

/* ── Bouton panier ── */
.product-card__cart-btn {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--rose-50);
  border: 1.5px solid var(--rose-200);
  color: var(--rose-500);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all var(--transition-normal);
  flex-shrink: 0;
}
.product-card__cart-btn:hover {
  background: var(--rose-500);
  border-color: var(--rose-500);
  color: #fff;
  transform: scale(1.08);
  box-shadow: var(--shadow-rose);
}
.product-card__cart-btn--added {
  background: var(--rose-500);
  border-color: var(--rose-500);
  color: #fff;
}
.product-card__cart-btn--out {
  opacity: 0.35;
  cursor: not-allowed;
  pointer-events: none;
}

/* ── Transition icône panier ↔ check ── */
.swap-enter-active,
.swap-leave-active {
  transition: all 0.18s;
}
.swap-enter-from {
  opacity: 0;
  transform: scale(0.5) rotate(20deg);
}
.swap-leave-to {
  opacity: 0;
  transform: scale(0.5) rotate(-20deg);
}

/* ── Mobile : ne garder que l'essentiel ── */
@media (max-width: 767px) {
  .product-card__info {
    padding: var(--space-3) var(--space-3) var(--space-3);
    gap: var(--space-1);
  }
  .product-card__meta,
  .product-card__line,
  .product-card__cat {
    display: none;
  }
  .product-card__name {
    font-size: 1.0625rem;
    line-height: 1.25;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
  .product-card__footer {
    padding-top: var(--space-2);
    border-top: none;
    gap: var(--space-2);
  }
  /*
   * Deux colonnes sur mobile : la carte fait environ 165 px, dont 24 px de
   * padding, 34 px de bouton et 8 px d'écart — il reste une centaine de
   * pixels pour le prix. À 14 px, « 125 000 XOF » y tient d'une seule ligne,
   * devise comprise, sans jamais pousser le panier.
   */
  .product-card__price-current {
    font-size: 0.875rem;
  }
  .product-card__price-old {
    font-size: 0.6875rem;
  }
  .product-card__cart-btn {
    width: 34px;
    height: 34px;
  }
  /* Badges + wishlist : un peu plus compacts */
  .product-card__badges {
    top: var(--space-2);
    left: var(--space-2);
  }
}
</style>
