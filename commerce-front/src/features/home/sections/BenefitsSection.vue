<template>
  <section class="benefits-section">
    <div class="container benefits-grid">
      <div v-for="b in items" :key="b.title" class="benefit-card">
        <div class="benefit-icon" v-html="b.icon"></div>
        <h3 class="benefit-title">{{ b.title }}</h3>
        <p class="benefit-desc">{{ b.desc }}</p>
      </div>
    </div>
  </section>
</template>

<script setup>
defineProps({
  items: { type: Array, required: true },
});
</script>

<style scoped>
.benefits-section {
  background: var(--color-surface);
  border-bottom: 1px solid var(--color-border-light);
  padding: var(--space-16) 0;
}

.benefits-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--space-8);
}

.benefit-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: var(--space-3);
}

.benefit-icon {
  width: 56px;
  height: 56px;
  border-radius: var(--radius-lg);
  background: var(--rose-50);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-primary);
  flex-shrink: 0;
}

.benefit-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--gray-800);
}

.benefit-desc {
  font-size: 0.8125rem;
  color: var(--gray-400);
  line-height: 1.6;
}

@media (max-width: 1024px) {
  .benefits-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
