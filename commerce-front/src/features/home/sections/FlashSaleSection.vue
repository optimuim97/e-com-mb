<template>
  <section class="flash-section">
    <div class="container">
      <header class="flash-header">
        <div class="flash-header__left">
          <span class="flash-eyebrow">{{ $t('flashSale.eyebrow') }}</span>
          <h2 class="display-md flash-title">{{ label }}</h2>
        </div>

        <!-- Countdown du premier produit avec flash_ends_at -->
        <div v-if="endsAt" class="flash-countdown" :title="`Fin : ${endsAt.toLocaleString('fr-FR')}`">
          <span class="flash-countdown__label">{{ $t('flashSale.endsIn') }}</span>
          <div class="flash-countdown__time">
            <div class="flash-cell"><strong>{{ time.d }}</strong><small>{{ $t('flashSale.dayShort') }}</small></div>
            <span>:</span>
            <div class="flash-cell"><strong>{{ time.h }}</strong><small>{{ $t('flashSale.hourShort') }}</small></div>
            <span>:</span>
            <div class="flash-cell"><strong>{{ time.m }}</strong><small>{{ $t('flashSale.minShort') }}</small></div>
            <span>:</span>
            <div class="flash-cell"><strong>{{ time.s }}</strong><small>{{ $t('flashSale.secShort') }}</small></div>
          </div>
        </div>
      </header>

      <div class="flash-grid">
        <article v-for="p in products" :key="p.id" class="flash-card">
          <RouterLink :to="`/products/${p.slug}`" class="flash-card__img-wrap">
            <img
              v-if="p.images?.[0]?.url"
              :src="p.images[0].url"
              :alt="p.name"
              class="flash-card__img"
              loading="lazy"
            />
            <div v-else class="flash-card__placeholder"><FlowerMark /></div>
            <span class="flash-card__badge">
              -{{ discountPct(p) }}%
            </span>
          </RouterLink>

          <div class="flash-card__body">
            <h3 class="flash-card__name">{{ p.name }}</h3>
            <div class="flash-card__prices">
              <span class="flash-card__price-new">{{ formatPrice(p.flash_price || p.effective_price) }}</span>
              <span class="flash-card__price-old">{{ formatPrice(p.price) }}</span>
            </div>
            <button class="btn btn-primary btn-sm flash-card__cta" @click="$emit('add-to-cart', p)">
              {{ $t('common.addToCart') }}
            </button>
          </div>
        </article>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { RouterLink } from 'vue-router'
import { useSettingsStore } from '@/stores/settings'

const props = defineProps({
  products: { type: Array, required: true },
  label:    { type: String, default: 'Ventes flash' },
})
defineEmits(['add-to-cart'])

const settings = useSettingsStore()
const formatPrice = (v) => settings.formatPrice(v)

const discountPct = (p) => {
  const ref = parseFloat(p.price)
  const flash = parseFloat(p.flash_price ?? p.effective_price)
  if (!ref || !flash || flash >= ref) return 0
  return Math.round((1 - flash / ref) * 100)
}

/* ── Countdown ─────────────────────────────────────────────────── */
const endsAt = computed(() => {
  const e = props.products.find(p => p.flash_ends_at)?.flash_ends_at
  return e ? new Date(e) : null
})

const time = ref({ d: '00', h: '00', m: '00', s: '00' })
let timer = null

function pad(n) { return String(Math.max(0, Math.floor(n))).padStart(2, '0') }

function updateCountdown() {
  if (!endsAt.value) return
  const diff = endsAt.value - Date.now()
  if (diff <= 0) {
    time.value = { d: '00', h: '00', m: '00', s: '00' }
    return
  }
  const d = Math.floor(diff / 86400000)
  const h = Math.floor((diff % 86400000) / 3600000)
  const m = Math.floor((diff % 3600000) / 60000)
  const s = Math.floor((diff % 60000) / 1000)
  time.value = { d: pad(d), h: pad(h), m: pad(m), s: pad(s) }
}

onMounted(() => {
  updateCountdown()
  timer = setInterval(updateCountdown, 1000)
})
onBeforeUnmount(() => clearInterval(timer))
</script>

<style scoped>
.flash-section {
  padding: var(--space-12) 0;
  /* Rose poudré doux, cohérent avec l'identité — fini le dégradé orange criard */
  background: linear-gradient(180deg, var(--rose-50, #fdeef2) 0%, var(--cream-50, #fdf8f4) 100%);
  position: relative;
  overflow: hidden;
}

.flash-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--space-6);
  margin-bottom: var(--space-8);
  flex-wrap: wrap;
}

.flash-header__left { flex: 1; min-width: 250px; }

.flash-eyebrow {
  display: inline-block;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--rose-600, #c0386b);
  margin-bottom: var(--space-2);
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.flash-title {
  font-family: var(--font-display);
  font-size: clamp(1.75rem, 4vw, 2.5rem);
  color: var(--gray-800);
  font-weight: 600;
  line-height: 1.1;
}

/* Countdown */
.flash-countdown {
  background: white;
  border: 1px solid var(--rose-200, #fbd7e1);
  border-radius: var(--radius-lg);
  padding: var(--space-3) var(--space-5);
  box-shadow: 0 6px 20px rgba(var(--rose-500-rgb), 0.08);
  text-align: center;
}
.flash-countdown__label {
  display: block;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--gray-500);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-bottom: 4px;
}
.flash-countdown__time {
  display: flex;
  align-items: baseline;
  justify-content: center;
  gap: 4px;
  font-family: var(--font-display);
  color: var(--rose-600, #c0386b);
}
.flash-cell {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  min-width: 32px;
}
.flash-cell strong {
  font-size: 1.5rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  line-height: 1;
}
.flash-cell small {
  font-size: 0.625rem;
  color: var(--gray-400);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-top: 2px;
}

/* Grid */
.flash-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: var(--space-5);
}

.flash-card {
  background: white;
  border-radius: var(--radius-xl);
  overflow: hidden;
  border: 1px solid var(--cream-200);
  transition: transform var(--transition-normal), box-shadow var(--transition-normal);
  display: flex;
  flex-direction: column;
}
.flash-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 32px rgba(var(--rose-500-rgb), 0.15);
}

.flash-card__img-wrap {
  position: relative;
  display: block;
  aspect-ratio: 1 / 1;
  background: var(--cream-50);
  overflow: hidden;
}
.flash-card__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform var(--transition-slow);
}
.flash-card:hover .flash-card__img { transform: scale(1.06); }

.flash-card__placeholder {
  width: 100%; height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3rem;
  background: linear-gradient(135deg, #fce8f0, #fdf0f5);
}

.flash-card__badge {
  position: absolute;
  top: 12px; left: 12px;
  background: var(--rose-500, var(--rose-500));
  color: white;
  font-size: 0.8125rem;
  font-weight: 600;
  padding: 5px 12px;
  border-radius: var(--radius-full);
  letter-spacing: 0.02em;
  box-shadow: 0 4px 12px rgba(var(--rose-500-rgb), 0.25);
}

.flash-card__body { padding: var(--space-4); display: flex; flex-direction: column; gap: var(--space-2); flex: 1; }

.flash-card__name {
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

.flash-card__prices {
  display: flex;
  align-items: baseline;
  gap: var(--space-2);
  flex-wrap: wrap;
}
.flash-card__price-new {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--rose-600);
}
.flash-card__price-old {
  font-size: 0.875rem;
  color: var(--gray-400);
  text-decoration: line-through;
}

.flash-card__cta {
  margin-top: auto;
  width: 100%;
}

@media (max-width: 540px) {
  .flash-grid { grid-template-columns: repeat(2, 1fr); gap: var(--space-3); }
  .flash-cell strong { font-size: 1.125rem; }
  .flash-card__name { font-size: 0.875rem; min-height: 2.3em; }
  .flash-card__price-new { font-size: 1rem; }
}
</style>
