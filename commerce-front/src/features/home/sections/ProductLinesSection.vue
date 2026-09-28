<template>
  <section v-if="loading || lines.length" class="pl-section">
    <div class="container">
      <SectionHeader :eyebrow="$t('productLines.eyebrow')">
        {{ $t('productLines.sectionTitle') }}
      </SectionHeader>
      <p class="pl-section__sub">
        {{ $t('productLines.sectionDesc') }}
      </p>

      <!-- Skeleton -->
      <div v-if="loading" class="pl-section__grid">
        <div v-for="i in 2" :key="i" class="pl-section__skeleton"></div>
      </div>

      <template v-else>
        <div class="pl-section__grid">
          <ProductLineCard v-for="line in featured" :key="line.id" :line="line" />
        </div>

        <!-- Ouverture vers toutes les gammes -->
        <div class="pl-section__more">
          <RouterLink to="/gammes" class="btn btn-outline pl-section__more-btn">
            {{ $t('productLines.sectionSeeAll') }}
            <span v-if="lines.length > 2" class="pl-section__more-count">{{ lines.length }}</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </RouterLink>
        </div>
      </template>
    </div>
  </section>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/api'
import SectionHeader from '@/components/ui/SectionHeader.vue'
import ProductLineCard from '@/components/shop/ProductLineCard.vue'

const lines   = ref([])
const loading = ref(true)

// Les 2 premières gammes (déjà triées par sort_order côté API)
const featured = computed(() => lines.value.slice(0, 2))

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
.pl-section {
  padding: var(--space-16) 0;
  background: linear-gradient(180deg, #fff 0%, var(--cream-50) 100%);
  border-top: 1px solid var(--cream-100);
}

.pl-section__sub {
  text-align: center;
  color: var(--gray-500);
  font-size: 0.9375rem;
  margin: calc(-1 * var(--space-4)) auto var(--space-8);
  max-width: 520px;
}

.pl-section__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-6);
  max-width: 980px;
  margin: 0 auto;
}

.pl-section__skeleton {
  border-radius: var(--radius-xl);
  background: var(--cream-200);
  height: 380px;
  animation: pl-pulse 1.5s ease-in-out infinite;
}
@keyframes pl-pulse {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0.55; }
}

.pl-section__more {
  text-align: center;
  margin-top: var(--space-8);
}
.pl-section__more-btn {
  gap: 8px;
}
.pl-section__more-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 22px;
  height: 22px;
  padding: 0 6px;
  border-radius: 999px;
  background: var(--rose-100);
  color: var(--rose-700);
  font-size: 0.75rem;
  font-weight: 700;
}

@media (max-width: 768px) {
  .pl-section { padding: var(--space-12) 0; }
  .pl-section__grid {
    grid-template-columns: 1fr;
    gap: var(--space-4);
  }
  .pl-section__skeleton { height: 300px; }
}
</style>
