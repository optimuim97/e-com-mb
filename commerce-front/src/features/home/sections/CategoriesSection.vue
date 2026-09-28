<template>
  <section class="categories-section">
    <div class="container">
      <SectionHeader
        :eyebrow="$t('categories.eyebrow')"
        :desc="$t('categories.desc')"
      >
        {{ $t('categories.title') }}
      </SectionHeader>

      <!-- Skeleton -->
      <div v-if="loading" class="categories-grid">
        <div v-for="i in 5" :key="i" class="category-skeleton" />
      </div>

      <!-- Grille -->
      <div v-else class="categories-grid">
        <RouterLink
          v-for="cat in categories"
          :key="cat.id"
          :to="`/products?category=${cat.slug}`"
          class="category-card"
        >
          <!-- Image de la catégorie (admin), sinon dégradé -->
          <div class="category-card__img-wrap">
            <img
              v-if="cat.image || categoryFallbackImage(cat)"
              :src="cat.image || categoryFallbackImage(cat)"
              :alt="cat.name"
              class="category-card__img"
              loading="lazy"
              @error="$event.target.style.display='none'"
            />
            <div v-else class="category-card__img-placeholder" :style="placeholderStyle(cat)"></div>
            <!-- Overlay dégradé au survol -->
            <div class="category-card__overlay"></div>
            <span class="category-card__label">{{ cat.name }}</span>
          </div>
        </RouterLink>
      </div>
    </div>
  </section>
</template>

<script setup>
import { RouterLink } from 'vue-router'
import SectionHeader from '@/components/ui/SectionHeader.vue'

defineProps({
  categories: { type: Array,   required: true },
  loading:    { type: Boolean, default: false },
})

// ── Fallback images ─────────────────────────────────────────────────────────
// Priorité : image admin > dégradé générique. Pas de photo par défaut : une
// photo d'un autre catalogue serait trompeuse pour la boutique.
function categoryFallbackImage() {
  return null
}

const PLACEHOLDER_GRADIENTS = [
  'linear-gradient(135deg, #f8d7e3, #f4a7bb)',
  'linear-gradient(135deg, #fde8d8, #f9c4a0)',
  'linear-gradient(135deg, #e8f4f8, #b8dce8)',
  'linear-gradient(135deg, #f0e8f8, #d4b8e8)',
  'linear-gradient(135deg, #e8f8ee, #b8e8cc)',
]

function placeholderStyle(cat) {
  const idx = (cat.id ?? 0) % PLACEHOLDER_GRADIENTS.length
  return { background: PLACEHOLDER_GRADIENTS[idx] }
}
</script>

<style scoped>
.categories-section {
  padding: var(--space-16) 0;
  background: var(--color-bg);
}

.categories-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: var(--space-4);
}

/* ── Card ── */
.category-card {
  display: block;
  text-decoration: none;
  border-radius: var(--radius-xl);
  overflow: hidden;
  position: relative;
  box-shadow: 0 2px 12px rgba(0,0,0,0.06);
  transition: transform var(--transition-normal), box-shadow var(--transition-normal);
}
.category-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 28px rgba(168, 50, 80, 0.12);
}

.category-card__img-wrap {
  position: relative;
  aspect-ratio: 1 / 1;
  overflow: hidden;
}

.category-card__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.6s ease;
}
.category-card:hover .category-card__img { transform: scale(1.07); }

.category-card__img-placeholder {
  width: 100%;
  height: 100%;
}

/* Overlay : vignettage doux teinté rose, juste assez pour lire le nom */
.category-card__overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    to bottom,
    transparent 0%,
    rgba(42, 22, 30, 0.04) 45%,
    rgba(42, 22, 30, 0.42) 100%
  );
  transition: background 0.35s ease;
}
.category-card:hover .category-card__overlay {
  background: linear-gradient(
    to bottom,
    transparent 0%,
    rgba(42, 22, 30, 0.08) 40%,
    rgba(42, 22, 30, 0.5) 100%
  );
}

/* Nom en bas de la carte */
.category-card__label {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: var(--space-4) var(--space-3) var(--space-3);
  font-family: var(--font-display);
  font-size: 1.25rem;
  font-weight: 700;
  color: #fff;
  letter-spacing: 0.015em;
  text-shadow: 0 1px 8px rgba(0,0,0,0.4);
  text-align: center;
  transition: padding 0.3s ease;
}
.category-card:hover .category-card__label {
  padding-bottom: var(--space-4);
}

/* Skeleton */
.category-skeleton {
  aspect-ratio: 1 / 1;
  border-radius: var(--radius-xl);
  background: var(--cream-100);
  animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0.5; }
}

@media (max-width: 1024px) { .categories-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 640px)  { .categories-grid { grid-template-columns: repeat(2, 1fr); } }
</style>
