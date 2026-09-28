<template>
  <section class="hc">
    <!-- Bannière image avec overlay -->
    <div class="hc__banner">
      <div class="hc__bg" aria-hidden="true">
        <TransitionGroup name="hc-fade">
          <img
            v-for="(src, i) in heroPhotos"
            v-show="i === currentPhoto"
            :key="src"
            :src="src"
            alt=""
            class="hc__bg-img"
            :loading="i === 0 ? 'eager' : 'lazy'"
            @error="onPhotoError(i)"
          />
        </TransitionGroup>
        <div class="hc__veil"></div>
      </div>

      <div class="container hc__inner">
        <div class="hc__content">
          <span v-if="eyebrow" class="hc__eyebrow">{{ eyebrow }}</span>
          <h1 class="hc__title">
            <template v-if="title">{{ title }}</template>
            <template v-else>{{ $t('hero.classicTitle') }} <em>{{ $t('hero.classicTitleEm') }}</em></template>
          </h1>
          <p v-if="subtitle" class="hc__subtitle">{{ subtitle }}</p>
          <div class="hc__ctas">
            <RouterLink to="/products" class="hc__cta-primary">{{ $t('hero.buyNow') }}</RouterLink>
            <RouterLink to="/products" class="hc__cta-ghost">{{ $t('common.viewAllProducts') }}</RouterLink>
          </div>
        </div>
      </div>
    </div>

    <!-- Bande de réassurance commerciale -->
    <div class="hc__strip">
      <div class="container hc__strip-inner">
        <div v-for="item in strip" :key="item.label" class="hc__strip-item">
          <span class="hc__strip-icon" v-html="item.icon"></span>
          <div class="hc__strip-text">
            <strong>{{ item.label }}</strong>
            <span>{{ item.sub }}</span>
          </div>
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
  eyebrow: { type: String, default: '' },
  title:   { type: String, default: '' },
  subtitle:{ type: String, default: '' },
})

const { t } = useI18n()
const { heroPhotos, currentPhoto, onPhotoError } = useHeroPhotos()

const strip = computed(() => [
  { label: t('hero.natural'), sub: t('hero.naturalSub'), icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10z"/><path d="M2 21c0-3 1.85-5.36 5.08-6"/></svg>' },
  { label: t('hero.fastDelivery'), sub: t('hero.fastDeliverySub'), icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>' },
  { label: t('common.securePay'), sub: t('hero.securePaySub'), icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/></svg>' },
  { label: t('hero.moneyBack'), sub: t('hero.moneyBackSub'), icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>' },
])
</script>

<style scoped>
.hc { display: flex; flex-direction: column; }

/* ── Bannière ── */
.hc__banner {
  position: relative; overflow: hidden;
  min-height: clamp(420px, 64vh, 600px);
  display: flex; align-items: center; isolation: isolate;
}
.hc__bg { position: absolute; inset: 0; z-index: -1; background: #2a161e; }
.hc__bg-img {
  position: absolute; inset: 0; width: 100%; height: 100%;
  object-fit: cover; object-position: center 30%;
}
.hc__veil {
  position: absolute; inset: 0;
  background: linear-gradient(180deg, rgba(42,22,30,0.45) 0%, rgba(42,22,30,0.25) 50%, rgba(42,22,30,0.55) 100%);
}
.hc-fade-enter-active, .hc-fade-leave-active { transition: opacity 1.4s ease; }
.hc-fade-enter-from, .hc-fade-leave-to { opacity: 0; }

.hc__inner { width: 100%; text-align: center; padding: clamp(40px, 6vw, 72px) 0; }
.hc__content {
  display: flex; flex-direction: column; align-items: center;
  gap: clamp(14px, 2vw, 22px); max-width: 680px; margin: 0 auto;
}
.hc__eyebrow {
  font-size: 0.75rem; font-weight: 600; letter-spacing: 0.24em; text-transform: uppercase;
  color: rgba(255,255,255,0.9);
}
.hc__title {
  font-family: var(--font-display); font-size: clamp(2.2rem, 5vw, 3.6rem);
  font-weight: 400; line-height: 1.12; color: #fff; margin: 0;
  text-shadow: 0 2px 24px rgba(42,22,30,0.4);
}
.hc__title em { font-style: italic; color: #ffd9e4; }
.hc__subtitle {
  font-size: 1.0625rem; line-height: 1.7; color: rgba(255,255,255,0.9); margin: 0;
  max-width: 520px; text-shadow: 0 1px 10px rgba(42,22,30,0.3);
}
.hc__ctas { display: flex; align-items: center; justify-content: center; gap: 14px; flex-wrap: wrap; margin-top: 6px; }
.hc__cta-primary {
  display: inline-flex; align-items: center; padding: 16px 40px;
  background: #fff; color: var(--rose-700, #a02858); font-size: 0.9375rem; font-weight: 600;
  letter-spacing: 0.04em; text-decoration: none; border-radius: 999px;
  transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
}
.hc__cta-primary:hover { background: var(--rose-500, var(--rose-500)); color: #fff; transform: translateY(-1px); }
.hc__cta-ghost {
  display: inline-flex; align-items: center; padding: 15px 34px;
  background: transparent; border: 1.5px solid rgba(255,255,255,0.7); color: #fff;
  font-size: 0.9375rem; font-weight: 500; text-decoration: none; border-radius: 999px;
  transition: background 0.2s ease, border-color 0.2s ease;
}
.hc__cta-ghost:hover { background: rgba(255,255,255,0.15); border-color: #fff; }

/* ── Bande de réassurance ── */
.hc__strip { background: var(--rose-500, var(--rose-500)); }
.hc__strip-inner {
  display: grid; grid-template-columns: repeat(4, 1fr);
  gap: var(--space-4); padding: 18px 0;
}
.hc__strip-item { display: flex; align-items: center; gap: 12px; justify-content: center; }
.hc__strip-icon {
  display: inline-flex; align-items: center; justify-content: center;
  width: 38px; height: 38px; border-radius: 50%;
  background: rgba(255,255,255,0.16); color: #fff; flex-shrink: 0;
}
.hc__strip-text { display: flex; flex-direction: column; line-height: 1.25; }
.hc__strip-text strong { font-size: 0.8125rem; font-weight: 600; color: #fff; }
.hc__strip-text span { font-size: 0.6875rem; color: rgba(255,255,255,0.8); }

@media (max-width: 900px) {
  .hc__strip-inner { grid-template-columns: repeat(2, 1fr); gap: var(--space-3); }
}
@media (max-width: 640px) {
  .hc__title { font-size: 2.1rem; }
  .hc__cta-primary, .hc__cta-ghost { width: 100%; justify-content: center; }
  .hc__strip-item { justify-content: flex-start; }
}
</style>
