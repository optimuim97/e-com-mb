<template>
  <section class="testimonials-section">
    <div class="container">
      <SectionHeader
        :eyebrow="$t('testimonials.eyebrow')"
        :desc="$t('testimonials.desc')"
      >
        {{ $t('testimonials.title') }}
      </SectionHeader>

      <div class="testimonials-grid">
        <div v-for="(t, idx) in items.slice(0, 3)" :key="t.id ?? idx" class="testimonial-card card">
          <div class="testimonial-stars">
            <span v-for="i in 5" :key="i" class="star" :class="{ 'star--off': i > (t.rating || 5) }">★</span>
          </div>
          <p class="testimonial-quote">"{{ t.quote }}"</p>
          <div class="testimonial-author">
            <div class="testimonial-avatar">{{ t.name?.[0] ?? '?' }}</div>
            <div>
              <p class="testimonial-name">{{ t.name }}</p>
              <p class="testimonial-location">
                {{ t.location }}
                <span v-if="t.product" class="testimonial-product"> · {{ t.product }}</span>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import SectionHeader from '@/components/ui/SectionHeader.vue'

defineProps({
  items: { type: Array, required: true },
})
</script>

<style scoped>
.testimonials-section {
  padding: var(--space-16) 0;
  background: linear-gradient(160deg, var(--rose-50) 0%, var(--cream-100) 100%);
}

.testimonials-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-6);
}

.testimonial-card {
  padding: var(--space-6);
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.testimonial-stars { display: flex; gap: 2px; }
.star { color: var(--gold-400); font-size: 0.875rem; }
.star--off { color: var(--gray-200); }
.testimonial-product { color: var(--rose-500); font-weight: 500; }

.testimonial-quote {
  font-size: 0.875rem;
  color: var(--gray-600);
  font-style: italic;
  line-height: 1.7;
  flex: 1;
}

.testimonial-author {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding-top: var(--space-3);
  border-top: 1px solid var(--cream-200);
}

.testimonial-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--rose-400), var(--rose-600));
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 0.875rem;
  flex-shrink: 0;
}

.testimonial-name     { font-size: 0.875rem; font-weight: 600; color: var(--gray-800); }
.testimonial-location { font-size: 0.75rem;  color: var(--gray-400); }

@media (max-width: 1024px) { .testimonials-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px)  { .testimonials-grid { grid-template-columns: 1fr; } }
</style>
