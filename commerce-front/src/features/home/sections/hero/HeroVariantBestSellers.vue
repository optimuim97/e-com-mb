<template>
  <section class="hb">
    <div class="container hb__inner">
      <!-- Texte -->
      <div class="hb__content">
        <span v-if="eyebrow" class="hb__eyebrow">{{ eyebrow }}</span>
        <h1 class="hb__title">
          <template v-if="title">{{ title }}</template>
          <template v-else>{{ $t('hero.bsTitle') }} <em>{{ $t('hero.bsTitleEm') }}</em></template>
        </h1>
        <p v-if="subtitle" class="hb__subtitle">{{ subtitle }}</p>
        <div class="hb__ctas">
          <RouterLink to="/products" class="hb__cta-primary">{{ $t('hero.buyNow') }}</RouterLink>
          <RouterLink to="/a-propos" class="hb__cta-text">{{ $t('hero.learnMore') }}</RouterLink>
        </div>
      </div>

      <!-- Visuel central -->
      <div class="hb__visual">
        <div class="hb__photo">
          <img v-if="heroPhotos[0]" :src="heroPhotos[0]" alt="" class="hb__photo-img" @error="onPhotoError(0)" />
        </div>
      </div>

      <!-- 2 best-sellers -->
      <div class="hb__cards">
        <article v-for="p in topProducts" :key="p.id" class="hb-card">
          <RouterLink :to="`/products/${p.slug}`" class="hb-card__img-wrap">
            <img :src="p.images?.[0]?.url || '/placeholder.png'" v-img-fallback :alt="p.name" class="hb-card__img" />
          </RouterLink>
          <div class="hb-card__body">
            <div class="hb-card__head">
              <strong class="hb-card__name">{{ p.name }}</strong>
              <button class="hb-card__buy" @click="$emit('add-to-cart', p)" :title="$t('common.addToCart')">{{ $t('hero.buyShort') }}</button>
            </div>
            <span class="hb-card__price">{{ fmt(p.price) }}</span>
          </div>
        </article>

        <!-- Skeletons si pas encore chargé -->
        <template v-if="!topProducts.length">
          <div class="hb-card hb-card--skeleton"></div>
          <div class="hb-card hb-card--skeleton"></div>
        </template>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useHeroPhotos } from './useHeroPhotos'
import { useSettingsStore } from '@/stores/settings'
import api from '@/api'

defineProps({
  eyebrow: { type: String, default: '' },
  title:   { type: String, default: '' },
  subtitle:{ type: String, default: '' },
})
defineEmits(['add-to-cart'])

const settings = useSettingsStore()
const { heroPhotos, onPhotoError } = useHeroPhotos()
const fmt = (v) => settings.formatPrice(v)

// 2 best-sellers (featured) chargés directement par la variante
const topProducts = ref([])
onMounted(async () => {
  try {
    const { data } = await api.get('/products', { params: { featured: true, per_page: 2 } })
    topProducts.value = (data.data ?? []).slice(0, 2)
    if (topProducts.value.length < 2) {
      const { data: more } = await api.get('/products', { params: { per_page: 2 } })
      const ids = new Set(topProducts.value.map(p => p.id))
      for (const p of more.data ?? []) {
        if (topProducts.value.length >= 2) break
        if (!ids.has(p.id)) topProducts.value.push(p)
      }
    }
  } catch { topProducts.value = [] }
})
</script>

<style scoped>
.hb {
  background: linear-gradient(135deg, #fdf6ee 0%, #fdeef2 100%);
  padding: clamp(40px, 6vw, 72px) 0;
  overflow: hidden;
}
.hb__inner {
  display: grid;
  grid-template-columns: 1fr 0.9fr 280px;
  gap: clamp(20px, 3vw, 44px);
  align-items: center;
}

.hb__content { display: flex; flex-direction: column; gap: clamp(12px, 1.8vw, 20px); }
.hb__eyebrow {
  align-self: flex-start;
  font-size: 0.6875rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase;
  color: var(--rose-700, #a02858); background: var(--rose-100, var(--rose-100));
  padding: 5px 12px; border-radius: 999px;
}
.hb__title {
  font-family: var(--font-display);
  font-size: clamp(2rem, 4.4vw, 3.2rem);
  font-weight: 400; line-height: 1.1; color: #2a1f24; margin: 0;
}
.hb__title em { font-style: italic; color: var(--rose-500, var(--rose-500)); }
.hb__subtitle { font-size: 1rem; line-height: 1.65; color: #6b5e60; margin: 0; max-width: 380px; }
.hb__ctas { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; margin-top: 4px; }
.hb__cta-primary {
  display: inline-flex; align-items: center; padding: 14px 32px;
  background: var(--rose-500, var(--rose-500)); color: #fff; font-size: 0.9375rem; font-weight: 600;
  text-decoration: none; border-radius: 999px; transition: background 0.2s, transform 0.2s;
}
.hb__cta-primary:hover { background: var(--rose-600, #c0386b); transform: translateY(-1px); }
.hb__cta-text { font-size: 0.875rem; font-weight: 500; color: var(--rose-700, #a02858); text-decoration: none; }
.hb__cta-text:hover { text-decoration: underline; }

/* Visuel central */
.hb__visual { display: flex; justify-content: center; }
.hb__photo {
  width: 100%; max-width: 320px; aspect-ratio: 3 / 4;
  border-radius: 999px 999px 24px 24px; overflow: hidden;
  background: var(--cream-100, #fdf2ec);
  box-shadow: 0 24px 48px -20px rgba(168,50,80,0.25);
}
.hb__photo-img { width: 100%; height: 100%; object-fit: cover; }

/* Cartes best-sellers */
.hb__cards { display: flex; flex-direction: column; gap: var(--space-3); }
.hb-card {
  display: flex; align-items: center; gap: 12px;
  background: #fff; border-radius: var(--radius-lg);
  padding: 10px; box-shadow: 0 8px 28px -12px rgba(0,0,0,0.18);
  transition: transform 0.2s ease;
}
.hb-card:hover { transform: translateY(-2px); }
.hb-card__img-wrap { width: 64px; height: 64px; border-radius: var(--radius-md); overflow: hidden; flex-shrink: 0; background: var(--cream-100); }
.hb-card__img { width: 100%; height: 100%; object-fit: cover; }
.hb-card__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
.hb-card__head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.hb-card__name { font-size: 0.875rem; color: var(--gray-800); line-height: 1.2; }
.hb-card__buy {
  flex-shrink: 0; font-size: 0.6875rem; font-weight: 600; color: #fff;
  background: var(--rose-500, var(--rose-500)); border: none; border-radius: 999px;
  padding: 5px 12px; cursor: pointer; white-space: nowrap; transition: background 0.2s;
}
.hb-card__buy:hover { background: var(--rose-600, #c0386b); }
.hb-card__price { font-size: 0.9375rem; font-weight: 700; color: var(--rose-600, #c0386b); }
.hb-card--skeleton { height: 84px; background: #fff; opacity: 0.6; animation: pulse 1.5s ease-in-out infinite; }
@keyframes pulse { 0%,100% { opacity: 0.6; } 50% { opacity: 0.3; } }

@media (max-width: 1100px) {
  .hb__inner { grid-template-columns: 1fr 1fr; }
  .hb__cards { grid-column: 1 / -1; flex-direction: row; }
  .hb-card { flex: 1; }
}
@media (max-width: 720px) {
  .hb__inner { grid-template-columns: 1fr; }
  .hb__visual { order: -1; }
  .hb__photo { max-width: 240px; }
  .hb__cards { flex-direction: column; }
}
</style>
