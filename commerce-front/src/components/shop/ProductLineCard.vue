<template>
  <RouterLink :to="`/gammes/${line.slug}`" class="pl-card">
    <div class="pl-card__img-wrap">
      <img v-if="line.cover_url" :src="line.cover_url" :alt="line.name" class="pl-card__img" loading="lazy" />
      <!-- Pas de couverture → décor listant les produits composants -->
      <div
        v-else
        class="pl-card__deco"
        :style="{ '--line-color': line.color_hex || 'var(--rose-500)' }"
      >
        <span class="pl-card__deco-flower" aria-hidden="true"><FlowerMark /></span>
        <ul v-if="line.item_names?.length" class="pl-card__deco-list">
          <li v-for="(name, i) in line.item_names.slice(0, 4)" :key="i" class="pl-card__deco-item">{{ name }}</li>
          <li v-if="line.item_names.length > 4" class="pl-card__deco-item pl-card__deco-item--more">
            +{{ line.item_names.length - 4 }} autres
          </li>
        </ul>
        <span v-else class="pl-card__deco-name">{{ line.name }}</span>
      </div>
    </div>

    <div class="pl-card__body">
      <span class="pl-card__dot" :style="{ background: line.color_hex || 'var(--rose-400)' }"></span>
      <h3 class="pl-card__name">{{ line.name }}</h3>
      <p v-if="line.tagline" class="pl-card__tagline">{{ line.tagline }}</p>
      <span class="pl-card__count">{{ line.products_count ?? 0 }} produit{{ (line.products_count ?? 0) > 1 ? 's' : '' }}</span>
      <div v-if="displayPrice > 0" class="pl-card__price-row">
        <span class="pl-card__price-label">Gamme complète</span>
        <span class="pl-card__price">{{ formatPrice(displayPrice) }}</span>
      </div>
      <span class="pl-card__cta">
        Découvrir
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </span>
    </div>
  </RouterLink>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useCurrencyStore } from '@/stores/currency'

const props = defineProps({
  line: { type: Object, required: true },
})

// Prix de la gamme = prix kit s'il est défini, sinon la somme des prix
// des produits composants (items_total, calculé côté serveur).
const displayPrice = computed(() =>
  Number(props.line.price) || Number(props.line.items_total) || 0
)

function formatPrice(val) {
  return useCurrencyStore().format(val ?? 0)
}
</script>

<style scoped>
.pl-card {
  display: flex;
  flex-direction: column;
  background: #fff;
  border-radius: var(--radius-xl);
  overflow: hidden;
  text-decoration: none;
  box-shadow: 0 2px 12px rgba(0,0,0,0.05);
  border: 1px solid var(--cream-200);
  transition: transform var(--transition-normal), box-shadow var(--transition-normal);
}
@media (hover: hover) {
  .pl-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 36px rgba(0,0,0,0.10);
  }
  .pl-card:hover .pl-card__img { transform: scale(1.045); }
  .pl-card:hover .pl-card__cta { gap: 9px; }
}

.pl-card__img-wrap {
  aspect-ratio: 16/10;
  overflow: hidden;
  background: var(--cream-50);
}
.pl-card__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.5s ease;
}

/* ── Décor de repli (gamme sans couverture) ── */
.pl-card__deco {
  position: relative;
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--space-3);
  padding: var(--space-5) var(--space-4);
  overflow: hidden;
  background: var(--cream-100);
  background:
    radial-gradient(circle at 30% 20%, color-mix(in srgb, var(--line-color) 18%, #fff), transparent 60%),
    linear-gradient(140deg, color-mix(in srgb, var(--line-color) 10%, #fff), var(--cream-50));
}
.pl-card__deco::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg, transparent, var(--line-color), transparent);
  opacity: 0.5;
}
.pl-card__deco-flower {
  position: absolute;
  right: -18px;
  bottom: -22px;
  font-size: 7rem;
  line-height: 1;
  color: var(--line-color);
  opacity: 0.10;
  transform: rotate(-12deg);
  pointer-events: none;
}
.pl-card__deco-list {
  position: relative;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 7px;
  text-align: center;
  width: 100%;
}
.pl-card__deco-item {
  font-family: var(--font-display);
  font-size: 0.9375rem;
  font-weight: 500;
  color: var(--gray-700);
  line-height: 1.3;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
}
.pl-card__deco-item::before {
  content: '';
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: var(--line-color);
  flex-shrink: 0;
  opacity: 0.7;
}
.pl-card__deco-item--more {
  font-family: var(--font-body);
  font-size: 0.75rem;
  font-style: italic;
  color: var(--gray-400);
}
.pl-card__deco-item--more::before { display: none; }
.pl-card__deco-name {
  position: relative;
  font-family: var(--font-display);
  font-size: 1.5rem;
  font-weight: 500;
  color: var(--gray-700);
  text-align: center;
}

/* ── Body ── */
.pl-card__body {
  position: relative;
  padding: var(--space-5) var(--space-6) var(--space-6);
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}
.pl-card__dot {
  position: absolute;
  top: 22px;
  right: 24px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
}
.pl-card__name {
  font-family: var(--font-display);
  font-size: 1.25rem;
  font-weight: 500;
  color: var(--gray-800);
}
.pl-card__tagline {
  font-size: 0.875rem;
  color: var(--gray-500);
  line-height: 1.5;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.pl-card__count {
  font-size: 0.75rem;
  color: var(--gray-400);
  margin-top: 4px;
}
.pl-card__price-row {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin-top: var(--space-2);
  padding-top: var(--space-2);
  border-top: 1px dashed var(--cream-200);
}
.pl-card__price-label {
  font-size: 0.6875rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--gray-400);
}
.pl-card__price {
  font-family: var(--font-sans);
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--rose-600);
}
.pl-card__cta {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-primary);
  margin-top: var(--space-2);
  transition: gap var(--transition-fast);
}
</style>
