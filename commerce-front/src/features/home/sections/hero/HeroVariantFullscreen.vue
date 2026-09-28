<template>
  <section class="hero">
    <div class="hero__bg" aria-hidden="true">
      <TransitionGroup name="hero-fade">
        <img
          v-for="(src, i) in heroPhotos"
          v-show="i === currentPhoto"
          :key="src"
          :src="src"
          alt=""
          class="hero__bg-img"
          :class="{ 'hero__bg-img--active': i === currentPhoto }"
          :loading="i === 0 ? 'eager' : 'lazy'"
          @error="onPhotoError(i)"
        />
      </TransitionGroup>
      <div class="hero__veil"></div>
    </div>

    <div class="container hero__inner">
      <div class="hero__content">
        <span v-if="eyebrow" class="hero__eyebrow">{{ eyebrow }}</span>
        <h1 class="hero__title">
          <template v-if="title">{{ title }}</template>
          <template v-else>{{ $t('hero.fullscreenTitle') }}<br>
            <em>{{ $t('hero.fullscreenTitleEm') }}</em>
          </template>
        </h1>
        <div class="hero__ctas">
          <RouterLink to="/products" class="hero__cta-primary">{{ $t('hero.discoverCare') }}</RouterLink>
          <RouterLink to="/a-propos" class="hero__cta-secondary">{{ $t('hero.ourStory') }}</RouterLink>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { RouterLink } from 'vue-router'
import { useHeroPhotos } from './useHeroPhotos'

defineProps({
  eyebrow: { type: String, default: '' },
  title:   { type: String, default: '' },
  subtitle:{ type: String, default: '' },
})

const { heroPhotos, currentPhoto, onPhotoError } = useHeroPhotos()
</script>

<style scoped>
.hero {
  position: relative; overflow: hidden;
  min-height: clamp(520px, 82vh, 760px);
  display: flex; align-items: center; isolation: isolate;
}
.hero__bg { position: absolute; inset: 0; z-index: -1; background: #2a161e; }
.hero__bg-img {
  position: absolute; inset: 0; width: 100%; height: 100%;
  object-fit: cover; object-position: center 35%;
}
.hero__bg-img--active { animation: heroZoom 14s ease-out forwards; }
@keyframes heroZoom { from { transform: scale(1); } to { transform: scale(1.06); } }
.hero__veil {
  position: absolute; inset: 0;
  background:
    linear-gradient(180deg, rgba(42,22,30,0.25) 0%, rgba(42,22,30,0.05) 35%, rgba(42,22,30,0.45) 100%),
    linear-gradient(95deg, rgba(42,22,30,0.38) 0%, rgba(42,22,30,0.12) 55%, transparent 100%);
}
.hero-fade-enter-active, .hero-fade-leave-active { transition: opacity 1.6s ease; }
.hero-fade-enter-from, .hero-fade-leave-to { opacity: 0; }

.hero__inner {
  position: relative; width: 100%;
  padding-top: clamp(48px, 7vw, 80px); padding-bottom: clamp(48px, 7vw, 80px);
}
.hero__content { display: flex; flex-direction: column; gap: clamp(16px, 2.2vw, 26px); max-width: 560px; }
.hero__eyebrow {
  font-size: 0.75rem; font-weight: 600; letter-spacing: 0.22em; text-transform: uppercase;
  color: rgba(255,255,255,0.85);
}
.hero__title {
  font-family: var(--font-display); font-size: clamp(2.3rem, 5.2vw, 3.8rem);
  font-weight: 400; line-height: 1.12; color: #fff; margin: 0;
  text-shadow: 0 2px 24px rgba(42,22,30,0.35);
}
.hero__title em { font-style: italic; color: #ffd9e4; }
.hero__ctas { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.hero__cta-primary {
  display: inline-flex; align-items: center; padding: 17px 42px;
  background: var(--rose-500, var(--rose-500)); color: #fff; font-size: 0.9375rem; font-weight: 600;
  letter-spacing: 0.05em; text-decoration: none; border-radius: 999px;
  transition: background 0.2s ease, transform 0.2s ease;
}
.hero__cta-primary:hover { background: var(--rose-600, #c0386b); transform: translateY(-1px); }
.hero__cta-secondary {
  display: inline-flex; align-items: center; padding: 16px 36px;
  background: transparent; border: 1.5px solid rgba(255,255,255,0.75); color: #fff;
  font-size: 0.9375rem; font-weight: 500; letter-spacing: 0.04em; text-decoration: none;
  border-radius: 999px; transition: background 0.2s ease, border-color 0.2s ease;
}
.hero__cta-secondary:hover { background: rgba(255,255,255,0.15); border-color: #fff; }

@media (max-width: 640px) {
  .hero { min-height: 76vh; }
  .hero__title { font-size: 2.2rem; }
  .hero__ctas { flex-direction: column; align-items: stretch; }
  .hero__cta-primary, .hero__cta-secondary { width: 100%; justify-content: center; }
  .hero__veil {
    background: linear-gradient(180deg, rgba(42,22,30,0.3) 0%, rgba(42,22,30,0.15) 40%, rgba(42,22,30,0.6) 100%);
  }
}
</style>
