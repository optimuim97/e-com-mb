<template>
  <section class="hv">
    <div class="container hv__inner">
      <!-- Texte -->
      <div class="hv__content">
        <span v-if="eyebrow" class="hv__eyebrow">{{ eyebrow }}</span>
        <h1 class="hv__title">
          <template v-if="title">{{ title }}</template>
          <template v-else>{{ $t('hero.showcaseTitle') }} <em>{{ $t('hero.showcaseTitleEm') }}</em> {{ $t('hero.showcaseTitleSuffix') }}</template>
        </h1>
        <p v-if="subtitle" class="hv__subtitle">{{ subtitle }}</p>

        <!-- Icônes de réassurance -->
        <ul class="hv__features">
          <li v-for="f in features" :key="f.label" class="hv__feature">
            <span class="hv__feature-icon" v-html="f.icon"></span>
            <span class="hv__feature-label">{{ f.label }}</span>
          </li>
        </ul>

        <RouterLink to="/products" class="hv__cta">{{ ctaLabel || $t('hero.discoverShop') }}</RouterLink>
      </div>

      <!-- Visuel -->
      <div class="hv__visual">
        <div class="hv__photo">
          <TransitionGroup name="hv-fade">
            <img
              v-for="(src, i) in heroPhotos"
              v-show="i === currentPhoto"
              :key="src"
              :src="src"
              alt=""
              class="hv__photo-img"
              :loading="i === 0 ? 'eager' : 'lazy'"
              @error="onPhotoError(i)"
            />
          </TransitionGroup>
        </div>
      </div>
    </div>

    <!-- Bande de réassurance -->
    <div class="hv__strip">
      <div class="container hv__strip-inner">
        <div v-for="s in strip" :key="s.label" class="hv__strip-item">
          <span class="hv__strip-icon" v-html="s.icon"></span>
          <span>{{ s.label }}</span>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useHeroPhotos } from './useHeroPhotos'

defineProps({
  eyebrow:  { type: String, default: '' },
  title:    { type: String, default: '' },
  subtitle: { type: String, default: '' },
  ctaLabel: { type: String, default: '' },
})

const { t } = useI18n()
const { heroPhotos, currentPhoto, onPhotoError } = useHeroPhotos()

const leaf = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10z"/><path d="M2 21c0-3 1.85-5.36 5.08-6"/></svg>'
const flask = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M9 3h6M10 3v6l-5.5 9.5A2 2 0 006.2 21h11.6a2 2 0 001.7-2.5L14 9V3"/></svg>'
const check = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>'
const heart = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>'

const features = computed(() => [
  { label: t('hero.natural'),           icon: leaf },
  { label: t('hero.chosenIngredients'), icon: flask },
  { label: t('hero.safeEffective'),     icon: check },
  { label: t('hero.noHarmful'),         icon: heart },
])
const strip = computed(() => [
  { label: t('hero.natureInspired'),       icon: leaf },
  { label: t('hero.selectedIngredients'),  icon: flask },
  { label: t('hero.holisticWellbeing'),    icon: heart },
  { label: t('hero.approvedByThousands'),  icon: check },
])
</script>

<style scoped>
.hv { display: flex; flex-direction: column; }

.hv__inner {
  display: grid;
  grid-template-columns: 1fr 1.05fr;
  gap: clamp(28px, 4vw, 64px);
  align-items: center;
  padding: clamp(36px, 5vw, 64px) 0;
}

.hv__content { display: flex; flex-direction: column; gap: clamp(14px, 2vw, 22px); max-width: 520px; }
.hv__eyebrow {
  font-size: 0.6875rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase;
  color: var(--rose-600, #c0386b);
}
.hv__title {
  font-family: var(--font-display);
  font-size: clamp(2.2rem, 5vw, 3.6rem);
  font-weight: 400; line-height: 1.08; color: #2a1f24; margin: 0;
}
.hv__title em { font-style: italic; color: var(--rose-500, var(--rose-500)); }
.hv__subtitle { font-size: 1rem; line-height: 1.65; color: #6b5e60; margin: 0; }

.hv__features {
  list-style: none; display: flex; flex-wrap: wrap; gap: 18px 24px;
  margin: 4px 0; padding: 0;
}
.hv__feature { display: flex; flex-direction: column; align-items: center; gap: 6px; width: 72px; text-align: center; }
.hv__feature-icon {
  width: 44px; height: 44px; border-radius: 50%;
  background: var(--rose-50, var(--rose-50)); color: var(--rose-500, var(--rose-500));
  display: flex; align-items: center; justify-content: center;
}
.hv__feature-label { font-size: 0.6875rem; color: var(--gray-600, #57534e); line-height: 1.2; }

.hv__cta {
  align-self: flex-start;
  display: inline-flex; align-items: center; padding: 15px 36px;
  background: var(--rose-500, var(--rose-500)); color: #fff; font-size: 0.9375rem; font-weight: 600;
  text-decoration: none; border-radius: 999px; transition: background 0.2s, transform 0.2s;
}
.hv__cta:hover { background: var(--rose-600, #c0386b); transform: translateY(-1px); }

/* Visuel */
.hv__visual { position: relative; }
.hv__photo {
  position: relative; aspect-ratio: 5 / 4; width: 100%;
  border-radius: var(--radius-xl); overflow: hidden;
  background: var(--cream-100, #fdf2ec);
  box-shadow: 0 30px 60px -24px rgba(168,50,80,0.25);
}
.hv__photo-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.hv-fade-enter-active, .hv-fade-leave-active { transition: opacity 1.2s ease; }
.hv-fade-enter-from, .hv-fade-leave-to { opacity: 0; }

/* Bande réassurance */
.hv__strip { background: var(--rose-50, var(--rose-50)); border-top: 1px solid var(--rose-100, var(--rose-100)); }
.hv__strip-inner {
  display: grid; grid-template-columns: repeat(4, 1fr);
  gap: var(--space-4); padding: 16px 0;
}
.hv__strip-item {
  display: flex; align-items: center; gap: 10px; justify-content: center;
  font-size: 0.8125rem; font-weight: 500; color: var(--gray-700, #363333);
}
.hv__strip-icon { display: inline-flex; color: var(--rose-500, var(--rose-500)); }

@media (max-width: 900px) {
  .hv__inner { grid-template-columns: 1fr; }
  .hv__visual { order: -1; }
  .hv__strip-inner { grid-template-columns: repeat(2, 1fr); gap: var(--space-3); }
}
@media (max-width: 480px) {
  .hv__feature { width: 64px; }
  .hv__cta { align-self: stretch; justify-content: center; }
}
</style>
