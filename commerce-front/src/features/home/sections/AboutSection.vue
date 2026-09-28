<template>
  <section class="about-section">
    <div class="container about-inner">

      <!-- Contenu gauche -->
      <div class="about-content">
        <span class="eyebrow animate-fade-up">{{ $t('about.eyebrow') }}</span>
        <h2 class="display-md about-title animate-fade-up animate-fade-up-delay-1">
          {{ $t('about.title', { shopName }) }}
        </h2>
        <p class="about-desc animate-fade-up animate-fade-up-delay-2">{{ tagline }}</p>
        <ul class="about-promises animate-fade-up animate-fade-up-delay-3">
          <li v-for="p in promises" :key="p" class="pr-item">
            <span class="promise-check">
              <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">
                <polyline points="20 6 9 17 4 12"/>
              </svg>
            </span>
            {{ p }}
          </li>
        </ul>

        <RouterLink to="/a-propos" class="about-link animate-fade-up animate-fade-up-delay-4">
          {{ $t('about.storyLink') }}
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
        </RouterLink>
      </div>

      <!-- Stats droite -->
      <div class="stats-grid">
        <div v-for="s in stats" :key="s.label" class="stat-card card">
          <div class="stat-value">{{ s.value }}</div>
          <div class="stat-label">{{ s.label }}</div>
        </div>
      </div>

    </div>
  </section>
</template>

<script setup>
import { RouterLink } from 'vue-router'

defineProps({
  shopName: { type: String, required: true },
  tagline:  { type: String, required: true },
  promises: { type: Array,  required: true },
  stats:    { type: Array,  required: true },
})
</script>

<style scoped>
.about-section {
  padding: var(--space-20) 0;
  background: var(--color-surface);
}

.about-inner {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-16);
  align-items: center;
}

.about-content {
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

.about-title em {
  font-style: italic;
  color: var(--color-primary);
}

.about-desc {
  color: var(--gray-500);
  line-height: 1.75;
}

.about-promises {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.promise-item {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  font-size: 0.875rem;
  color: var(--gray-700);
}

.promise-check {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: var(--rose-100);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--rose-600);
  flex-shrink: 0;
}

.stats-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-4);
}

.stat-card {
  padding: var(--space-6);
  text-align: center;
}

.stat-value {
  font-family: var(--font-display);
  font-size: 2rem;
  font-weight: 600;
  color: var(--color-primary);
  margin-bottom: var(--space-1);
}

.stat-label {
  font-size: 0.75rem;
  color: var(--gray-400);
}

.about-link {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--color-primary);
  text-decoration: none;
  margin-top: var(--space-2);
  transition: gap var(--transition-fast), opacity var(--transition-fast);
}
.about-link:hover {
  gap: var(--space-3);
  opacity: 0.8;
}

@media (max-width: 1024px) {
  .about-inner { grid-template-columns: 1fr; }
}

@media (max-width: 640px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
