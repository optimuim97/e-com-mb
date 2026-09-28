<template>
  <main class="pll-page">
    <!-- Hero éditorial -->
    <header class="pll-hero">
      <div class="container pll-hero__inner">
        <span class="eyebrow">{{ $t('productLines.eyebrow') }}</span>
        <h1 class="display-md">{{ $t('productLines.sectionTitle') }}</h1>
        <p class="pll-hero__desc">
          {{ $t('productLines.listDesc') }}
        </p>
      </div>
    </header>

    <div class="container pll-body">
      <!-- Skeleton -->
      <div v-if="loading" class="pll-grid">
        <div v-for="i in 4" :key="i" class="pll-skeleton"></div>
      </div>

      <!-- Vide -->
      <div v-else-if="lines.length === 0" class="pll-empty">
        <p>{{ $t('productLines.listEmpty') }}</p>
        <RouterLink :to="{ name: 'products' }" class="btn btn-primary">{{ $t('common.discoverProducts') }}</RouterLink>
      </div>

      <!-- Grille -->
      <div v-else class="pll-grid">
        <ProductLineCard v-for="line in lines" :key="line.id" :line="line" />
      </div>
    </div>
  </main>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/api'
import ProductLineCard from '@/components/shop/ProductLineCard.vue'

const lines   = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await api.get('/product-lines')
    lines.value = data.data ?? data
  } catch {
    lines.value = []
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.pll-page {
  min-height: 60vh;
  background: var(--color-bg);
}

.pll-hero {
  background: linear-gradient(180deg, var(--rose-50) 0%, var(--color-bg) 100%);
  border-bottom: 1px solid var(--cream-100);
}
.pll-hero__inner {
  padding: var(--space-12) var(--space-6) var(--space-10);
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-3);
}
.pll-hero__desc {
  max-width: 560px;
  color: var(--gray-500);
  font-size: 0.9375rem;
  line-height: 1.7;
}

.pll-body {
  padding: var(--space-10) var(--space-6) var(--space-16);
}

.pll-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: var(--space-6);
  max-width: 980px;
  margin: 0 auto;
}

.pll-skeleton {
  border-radius: var(--radius-xl);
  background: var(--cream-200);
  height: 380px;
  animation: pll-pulse 1.5s ease-in-out infinite;
}
@keyframes pll-pulse {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0.55; }
}

.pll-empty {
  text-align: center;
  padding: var(--space-16) 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-4);
  color: var(--gray-500);
}

@media (max-width: 768px) {
  .pll-hero__inner { padding: var(--space-8) var(--space-4) var(--space-6); }
  .pll-body { padding: var(--space-6) var(--space-4) var(--space-12); }
  .pll-grid {
    grid-template-columns: 1fr;
    gap: var(--space-4);
  }
  .pll-skeleton { height: 300px; }
}
</style>
