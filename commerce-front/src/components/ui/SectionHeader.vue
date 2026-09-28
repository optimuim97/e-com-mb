<template>
  <div class="section-header" :class="{ 'section-header--left': align === 'left' }">
    <span v-if="eyebrow" class="eyebrow animate-fade-up">{{ eyebrow }}</span>
    <h2 class="display-md section-header__title animate-fade-up animate-fade-up-delay-1">
      <!-- slot pour permettre du contenu riche (em, br…) -->
      <slot>{{ title }}</slot>
    </h2>
    <p v-if="desc" class="section-header__desc animate-fade-up animate-fade-up-delay-2">{{ desc }}</p>
  </div>
</template>

<script setup>
defineProps({
  eyebrow: { type: String, default: '' },
  title:   { type: String, default: '' },
  desc:    { type: String, default: '' },
  align:   { type: String, default: 'center' }, // 'center' | 'left'
})
</script>

<style scoped>
.section-header {
  text-align: center;
  margin-bottom: var(--space-10);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  align-items: center;
}

.section-header--left {
  text-align: left;
  align-items: flex-start;
}

.section-header__title :deep(em) {
  font-style: italic;
  color: var(--color-primary);
}

.section-header__desc {
  font-size: 0.9375rem;
  color: var(--gray-400);
}
</style>
