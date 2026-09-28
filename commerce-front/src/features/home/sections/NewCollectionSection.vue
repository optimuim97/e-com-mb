<template>
  <section class="new-section">
    <div class="container">
      <header class="new-header">
        <span class="new-eyebrow">
          <!-- TODO: import svg -->
          <div style="display : flex;">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 200 200"
              width="25"
              height="25"
            >
              <!-- tout le contenu reste inchangé -->
              <defs>
                <radialGradient id="glow" cx="50%" cy="50%" r="50%">
                  <stop offset="0%" stop-color="#FFFDE7" stop-opacity="1" />
                  <stop offset="30%" stop-color="#FFD54F" stop-opacity="0.9" />
                  <stop offset="60%" stop-color="#FFB300" stop-opacity="0.6" />
                  <stop offset="100%" stop-color="#FF8F00" stop-opacity="0" />
                </radialGradient>
                <radialGradient id="centerGlow" cx="50%" cy="50%" r="50%">
                  <stop offset="0%" stop-color="#FFFFFF" />
                  <stop offset="40%" stop-color="#FFF9C4" />
                  <stop offset="100%" stop-color="#FFD54F" />
                </radialGradient>
                <filter id="blur1"><feGaussianBlur stdDeviation="3" /></filter>
                <filter id="blur2"><feGaussianBlur stdDeviation="6" /></filter>
              </defs>

              <circle
                cx="100"
                cy="100"
                r="85"
                fill="url(#glow)"
                filter="url(#blur2)"
                opacity="0.7"
              />
              <polygon
                points="100,15 120,72 178,72 132,108 150,168 100,135 50,168 68,108 22,72 80,72"
                fill="#FFD54F"
                stroke="#FFB300"
                stroke-width="2"
                opacity="0.95"
              />
              <polygon
                points="100,38 114,75 154,75 122,102 134,142 100,118 66,142 78,102 46,75 86,75"
                fill="#FFECB3"
                stroke="#FFCA28"
                stroke-width="1.5"
              />
              <circle
                cx="100"
                cy="100"
                r="24"
                fill="url(#centerGlow)"
                filter="url(#blur1)"
              />
              <circle cx="100" cy="100" r="8" fill="#FFFFFF" opacity="0.9" />
              <circle cx="40" cy="50" r="3" fill="#FFD54F" opacity="0.8" />
              <circle cx="160" cy="45" r="2.5" fill="#FFD54F" opacity="0.7" />
              <circle cx="45" cy="150" r="2" fill="#FFECB3" opacity="0.8" />
              <circle cx="155" cy="155" r="3" fill="#FFD54F" opacity="0.7" />
              <circle cx="25" cy="100" r="2" fill="#FFECB3" opacity="0.6" />
              <circle cx="175" cy="100" r="2.5" fill="#FFECB3" opacity="0.6" />
            </svg>
            {{ $t("newCollection.eyebrow") }}
          </div>
        </span>
        <h2 class="display-md new-title">
          {{ $t("newCollection.title").split(" ")[0] }}
          <em>{{ $t("newCollection.title").split(" ").slice(1).join(" ") }}</em>
        </h2>
        <p class="new-subtitle">
          {{ $t("newCollection.subtitle") }}
        </p>
      </header>

      <!-- Carrousel horizontal scrollable -->
      <div class="new-rail">
        <article v-for="p in products" :key="p.id" class="new-card">
          <RouterLink :to="`/products/${p.slug}`" class="new-card__img-wrap">
            <img
              v-if="p.images?.[0]?.url"
              :src="p.images[0].url"
              :alt="p.name"
              class="new-card__img"
              loading="lazy"
            />
            <div v-else class="new-card__placeholder"><FlowerMark /></div>

            <span class="new-card__badge">{{ $t("newCollection.badge") }}</span>
          </RouterLink>

          <div class="new-card__body">
            <span v-if="p.category?.name" class="new-card__category">{{
              p.category.name
            }}</span>
            <h3 class="new-card__name">{{ p.name }}</h3>

            <div v-if="p.rating_avg" class="new-card__rating">
              <span
                v-for="i in 5"
                :key="i"
                :class="{ filled: i <= Math.round(p.rating_avg) }"
                >★</span
              >
              <span class="new-card__reviews">({{ p.reviews_count }})</span>
            </div>

            <div class="new-card__bottom">
              <span class="new-card__price">{{
                formatPrice(p.effective_price || p.price)
              }}</span>
              <button
                class="new-card__btn"
                @click="$emit('add-to-cart', p)"
                :aria-label="$t('common.addToCart')"
              >
                <svg
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                >
                  <line x1="12" y1="5" x2="12" y2="19" />
                  <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
              </button>
            </div>
          </div>
        </article>
      </div>

      <div class="new-cta-wrap">
        <RouterLink to="/products" class="btn btn-outline">
          {{ $t("newCollection.seeAll") }}
          <svg
            width="14"
            height="14"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
          >
            <path d="M5 12h14M12 5l7 7-7 7" />
          </svg>
        </RouterLink>
      </div>
    </div>
  </section>
</template>

<script setup>
import { RouterLink } from "vue-router";
import { useSettingsStore } from "@/stores/settings";

defineProps({ products: { type: Array, required: true } });
defineEmits(["add-to-cart"]);

const settings = useSettingsStore();
const formatPrice = (v) => settings.formatPrice(v);
</script>

<style scoped>
.new-section {
  padding: var(--space-12) 0;
  background: var(--cream-50);
}

.new-header {
  text-align: center;
  max-width: 600px;
  margin: 0 auto var(--space-8);
}

.new-eyebrow {
  display: inline-block;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--rose-600);
  margin-bottom: var(--space-2);
  letter-spacing: 0.04em;
}

.new-title {
  font-family: var(--font-display);
  font-size: clamp(2rem, 5vw, 3rem);
  color: var(--gray-800);
  font-weight: 500;
  line-height: 1.1;
  margin-bottom: var(--space-3);
}
.new-title em {
  font-style: italic;
  color: var(--rose-500);
}

.new-subtitle {
  font-size: 1rem;
  color: var(--gray-500);
  line-height: 1.6;
}

/* Rail horizontal scrollable */
.new-rail {
  display: flex;
  gap: var(--space-5);
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  padding: var(--space-2) 4px var(--space-6);
  margin: 0 -16px;
  padding-left: 16px;
  padding-right: 16px;
  scrollbar-width: thin;
  scrollbar-color: var(--rose-300) transparent;
}
.new-rail::-webkit-scrollbar {
  height: 6px;
}
.new-rail::-webkit-scrollbar-thumb {
  background: var(--rose-300);
  border-radius: 3px;
}

.new-card {
  flex: 0 0 240px;
  scroll-snap-align: start;
  background: white;
  border-radius: var(--radius-xl);
  overflow: hidden;
  border: 1px solid var(--cream-200);
  transition: all var(--transition-normal);
  display: flex;
  flex-direction: column;
}
.new-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-rose);
  border-color: var(--rose-200);
}

.new-card__img-wrap {
  position: relative;
  display: block;
  aspect-ratio: 4 / 5;
  background: var(--cream-50);
  overflow: hidden;
}
.new-card__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform var(--transition-slow);
}
.new-card:hover .new-card__img {
  transform: scale(1.05);
}

.new-card__placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3rem;
  background: linear-gradient(135deg, var(--cream-50), var(--rose-50));
}

.new-card__badge {
  position: absolute;
  top: 10px;
  left: 10px;
  background: linear-gradient(135deg, var(--rose-500), var(--rose-600));
  color: white;
  font-size: 0.6875rem;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: var(--radius-full);
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.new-card__body {
  padding: var(--space-3) var(--space-4) var(--space-4);
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
}

.new-card__category {
  font-size: 0.6875rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--rose-500);
  font-weight: 600;
}

.new-card__name {
  font-family: var(--font-display);
  font-weight: 500;
  font-size: 1rem;
  color: var(--gray-800);
  line-height: 1.3;
  min-height: 2.6em;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.new-card__rating {
  display: flex;
  align-items: center;
  gap: 2px;
  font-size: 0.8125rem;
  color: var(--gray-300);
}
.new-card__rating .filled {
  color: #fbbf24;
}
.new-card__reviews {
  margin-left: 4px;
  color: var(--gray-500);
  font-size: 0.75rem;
}

.new-card__bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: auto;
  padding-top: var(--space-2);
}
.new-card__price {
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--rose-600);
  font-family: var(--font-sans);
}
.new-card__btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--rose-500);
  color: white;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all var(--transition-normal);
}
.new-card__btn:hover {
  background: var(--rose-600);
  transform: scale(1.08);
  box-shadow: var(--shadow-rose);
}

.new-cta-wrap {
  text-align: center;
  margin-top: var(--space-6);
}

@media (max-width: 600px) {
  .new-card {
    flex: 0 0 180px;
  }
  .new-card__name {
    font-size: 0.875rem;
  }
  .new-card__price {
    font-size: 1rem;
  }
}
</style>
