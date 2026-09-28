<template>
  <section class="hs">
    <div class="container hs__inner">
      <!-- Texte -->
      <div class="hs__content">
        <span v-if="eyebrow" class="hs__eyebrow">{{ eyebrow }}</span>
        <h1 class="hs__title">
          <template v-if="title">{{ title }}</template>
          <template v-else>{{ $t('hero.splitTitle') }}<em>{{ $t('hero.splitTitleEm') }}</em>{{ $t('hero.splitTitleSuffix') }}</template>
        </h1>
        <p v-if="subtitle" class="hs__subtitle">{{ subtitle }}</p>

        <div class="hs__ctas">
          <RouterLink to="/products" class="hs__cta-primary">{{ $t('hero.discoverCare') }}</RouterLink>
          <RouterLink to="/a-propos" class="hs__cta-text">{{ $t('hero.ourStory') }} →</RouterLink>
        </div>

        <!-- Pastilles de réassurance -->
        <ul class="hs__pills">
          <li v-for="p in pills" :key="p.label" class="hs__pill">
            <span class="hs__pill-icon" v-html="p.icon"></span>
            <span>{{ p.label }}</span>
          </li>
        </ul>
      </div>

      <!-- Visuel en arche -->
      <div class="hs__visual">
        <div class="hs__arch">
          <TransitionGroup name="hs-fade">
            <img
              v-for="(src, i) in heroPhotos"
              v-show="i === currentPhoto"
              :key="src"
              :src="src"
              alt=""
              class="hs__img"
              :loading="i === 0 ? 'eager' : 'lazy'"
              @error="onPhotoError(i)"
            />
          </TransitionGroup>
        </div>
        <div class="hs__blob" aria-hidden="true"></div>
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
  eyebrow: { type: String, default: '' },
  title:   { type: String, default: '' },
  subtitle:{ type: String, default: '' },
})

const { t } = useI18n()
const { heroPhotos, currentPhoto, onPhotoError } = useHeroPhotos()

const pills = computed(() => [
  { label: t('hero.naturalIngredients'), icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10z"/><path d="M2 21c0-3 1.85-5.36 5.08-6"/></svg>' },
  { label: t('hero.dermTested'), icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>' },
  { label: t('hero.crueltyFree'), icon: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>' },
])
</script>

<style scoped>
.hs {
  background: linear-gradient(135deg, #fff6f1 0%, #fdeef2 55%, #f6ecfb 100%);
  padding: clamp(40px, 6vw, 80px) 0;
  overflow: hidden;
}
.hs__inner {
  display: grid;
  grid-template-columns: 1.05fr 0.95fr;
  gap: clamp(32px, 5vw, 72px);
  align-items: center;
}

/* ── Texte ── */
.hs__content { display: flex; flex-direction: column; gap: clamp(14px, 2vw, 22px); max-width: 540px; }
.hs__eyebrow {
  font-size: 0.75rem; font-weight: 600; letter-spacing: 0.18em; text-transform: uppercase;
  color: var(--rose-600, #c0386b);
}
.hs__title {
  font-family: var(--font-display);
  font-size: clamp(2.2rem, 4.6vw, 3.4rem);
  font-weight: 400; line-height: 1.1; color: #2a1f24; margin: 0;
}
.hs__title em { font-style: italic; color: var(--rose-500, var(--rose-500)); }
.hs__subtitle { font-size: 1.0625rem; line-height: 1.7; color: #6b5e60; margin: 0; max-width: 440px; }

.hs__ctas { display: flex; align-items: center; gap: 22px; flex-wrap: wrap; margin-top: 4px; }
.hs__cta-primary {
  display: inline-flex; align-items: center; padding: 16px 38px;
  background: var(--rose-500, var(--rose-500)); color: #fff; font-size: 0.9375rem; font-weight: 600;
  letter-spacing: 0.03em; text-decoration: none; border-radius: 999px;
  transition: background 0.2s ease, transform 0.2s ease;
}
.hs__cta-primary:hover { background: var(--rose-600, #c0386b); transform: translateY(-1px); }
.hs__cta-text { font-size: 0.875rem; font-weight: 500; color: var(--rose-700, #a02858); text-decoration: none; }
.hs__cta-text:hover { text-decoration: underline; }

.hs__pills {
  list-style: none; display: flex; flex-wrap: wrap; gap: 10px;
  margin: 8px 0 0; padding-top: 18px; border-top: 1px solid rgba(168,50,80,0.12);
}
.hs__pill {
  display: inline-flex; align-items: center; gap: 7px;
  font-size: 0.75rem; font-weight: 500; color: var(--gray-600, #57534e);
  background: rgba(255,255,255,0.7); padding: 7px 14px; border-radius: 999px;
  border: 1px solid rgba(255,255,255,0.9);
}
.hs__pill-icon { display: inline-flex; color: var(--rose-500, var(--rose-500)); }

/* ── Visuel ── */
.hs__visual { position: relative; display: flex; justify-content: center; }
.hs__arch {
  position: relative;
  width: 100%; max-width: 440px; aspect-ratio: 4 / 5;
  border-radius: 999px 999px 28px 28px; /* arche : arrondi haut */
  overflow: hidden;
  background: var(--cream-100, #fdf2ec);
  box-shadow: 0 30px 60px -24px rgba(168, 50, 80, 0.28);
  z-index: 1;
}
.hs__img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.hs-fade-enter-active, .hs-fade-leave-active { transition: opacity 1.2s ease; }
.hs-fade-enter-from, .hs-fade-leave-to { opacity: 0; }

/* Blob décoratif derrière l'arche */
.hs__blob {
  position: absolute; inset: -8% -6% -4% -6%;
  background: radial-gradient(circle at 70% 30%, rgba(248,168,184,0.5), transparent 60%);
  z-index: 0;
  filter: blur(8px);
}

@media (max-width: 900px) {
  .hs__inner { grid-template-columns: 1fr; gap: 40px; }
  .hs__content { max-width: 100%; }
  .hs__arch { max-width: 360px; margin: 0 auto; }
}
@media (max-width: 640px) {
  .hs__title { font-size: 2.1rem; }
  .hs__cta-primary { width: 100%; justify-content: center; }
}
</style>
