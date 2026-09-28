<template>
  <div class="hero-dispatch">
    <!-- Variante affichée selon le réglage admin -->
    <component
      :is="variantComponent"
      :eyebrow="eyebrow"
      :title="title"
      :subtitle="subtitle"
      @add-to-cart="$emit('add-to-cart', $event)"
    />

    <!-- Switcher flottant — visible uniquement pour l'admin connecté -->
    <div v-if="auth.isAdmin" class="hero-switch" :class="{ 'hero-switch--saving': saving }">
      <span class="hero-switch__label">{{ $t('hero.adminStyleLabel') }}</span>
      <div class="hero-switch__btns">
        <button
          v-for="opt in variants"
          :key="opt.value"
          class="hero-switch__btn"
          :class="{ 'hero-switch__btn--active': currentVariant === opt.value }"
          :title="$t(opt.labelKey)"
          @click="selectVariant(opt.value)"
        >{{ opt.value }}</button>
      </div>
      <span v-if="saved" class="hero-switch__saved">✓</span>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useSettingsStore } from '@/stores/settings'
import { useAuthStore } from '@/features/auth/auth.store'
import api from '@/api'

import HeroVariantFullscreen  from './hero/HeroVariantFullscreen.vue'
import HeroVariantSplit       from './hero/HeroVariantSplit.vue'
import HeroVariantClassic     from './hero/HeroVariantClassic.vue'
import HeroVariantBestSellers from './hero/HeroVariantBestSellers.vue'
import HeroVariantShowcase    from './hero/HeroVariantShowcase.vue'

// Props héritées (HomeView passe les valeurs settings) — on les relaie aux variantes.
const props = defineProps({
  heroProduct:    { type: Object, default: null },
  flashProduct:   { type: Object, default: null },
  ratingAvg:      { type: Number, default: 0 },
  reviewsCount:   { type: Number, default: 0 },
  clientsCount:   { type: Number, default: 0 },
  featuredReview: { type: Object, default: null },
  heroEyebrow:    { type: String, default: '' },
  heroTitle:      { type: String, default: '' },
  heroSubtitle:   { type: String, default: '' },
})
defineEmits(['add-to-cart'])

const settings = useSettingsStore()
const auth     = useAuthStore()

const eyebrow  = computed(() => props.heroEyebrow)
const title    = computed(() => props.heroTitle)
const subtitle = computed(() => props.heroSubtitle)

const variants = [
  { value: '1', labelKey: 'hero.variantFullscreen',  component: HeroVariantFullscreen },
  { value: '2', labelKey: 'hero.variantSplit',       component: HeroVariantSplit },
  { value: '3', labelKey: 'hero.variantClassic',     component: HeroVariantClassic },
  { value: '4', labelKey: 'hero.variantBestSellers', component: HeroVariantBestSellers },
  { value: '5', labelKey: 'hero.variantShowcase',    component: HeroVariantShowcase },
]
const VALID = variants.map(v => v.value)

const currentVariant = computed(() => {
  const v = String(settings.homeHeroVariant ?? '1')
  return VALID.includes(v) ? v : '1'
})

const variantComponent = computed(() =>
  (variants.find(v => v.value === currentVariant.value) ?? variants[0]).component
)

// ── Switcher admin : applique en live + persiste ───────────────────────────
const saving = ref(false)
const saved  = ref(false)

async function selectVariant(value) {
  if (value === currentVariant.value || saving.value) return
  // Applique immédiatement (optimiste)
  settings.setLocal('home_hero_variant', value)
  saving.value = true
  saved.value = false
  try {
    await api.put('/admin/settings', { settings: { home_hero_variant: value } })
    saved.value = true
    setTimeout(() => { saved.value = false }, 2000)
  } catch {
    // En cas d'échec, on laisse la valeur optimiste — l'admin peut réessayer.
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.hero-dispatch { position: relative; }

/* ── Switcher admin flottant ── */
.hero-switch {
  position: absolute;
  bottom: 16px; right: 16px;
  z-index: 20;
  display: flex; align-items: center; gap: 10px;
  background: rgba(255, 255, 255, 0.92);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(168, 50, 80, 0.15);
  border-radius: 999px;
  padding: 6px 8px 6px 14px;
  box-shadow: 0 8px 28px rgba(0, 0, 0, 0.16);
}
.hero-switch--saving { opacity: 0.7; }
.hero-switch__label {
  font-size: 0.6875rem; font-weight: 600; letter-spacing: 0.06em;
  text-transform: uppercase; color: var(--gray-500, #78716c);
}
.hero-switch__btns { display: flex; gap: 4px; }
.hero-switch__btn {
  width: 28px; height: 28px; border-radius: 50%;
  border: 1.5px solid var(--cream-300, #e7e0db);
  background: #fff; color: var(--gray-600, #57534e);
  font-size: 0.8125rem; font-weight: 600; cursor: pointer;
  transition: all 0.15s ease;
}
.hero-switch__btn:hover { border-color: var(--rose-400, #f06292); color: var(--rose-600, #c0386b); }
.hero-switch__btn--active {
  background: var(--rose-500, var(--rose-500)); border-color: var(--rose-500, var(--rose-500)); color: #fff;
}
.hero-switch__saved { color: #15803d; font-weight: 700; font-size: 0.875rem; }

@media (max-width: 640px) {
  .hero-switch { bottom: 10px; right: 10px; padding: 5px 7px 5px 10px; gap: 7px; }
  .hero-switch__label { display: none; }
}
</style>
