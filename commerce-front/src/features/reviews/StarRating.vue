<template>
  <div class="star-rating" :class="{ 'star-rating--interactive': interactive }">
    <button
      v-for="i in 5"
      :key="i"
      type="button"
      class="star-rating__star"
      :class="{
        'star-rating__star--filled': i <= displayValue,
        'star-rating__star--hover':  interactive && i <= hovered,
      }"
      :disabled="!interactive"
      @mouseenter="interactive && (hovered = i)"
      @mouseleave="interactive && (hovered = 0)"
      @click="interactive && emit('update:modelValue', i)"
      :aria-label="$t('reviews.starLabel', { count: i }, i)"
    >★</button>

    <span v-if="showCount && count !== undefined" class="star-rating__count">
      ({{ count }})
    </span>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  modelValue: { type: Number, default: 0 },
  count:      { type: Number, default: undefined },
  interactive:{ type: Boolean, default: false },
  showCount:  { type: Boolean, default: false },
  size:       { type: String, default: 'md' }, // sm | md | lg
})

const emit = defineEmits(['update:modelValue'])

const hovered = ref(0)

const displayValue = computed(() =>
  props.interactive && hovered.value > 0 ? hovered.value : props.modelValue
)
</script>

<style scoped>
.star-rating {
  display: inline-flex;
  align-items: center;
  gap: 2px;
}

.star-rating__star {
  background: none;
  border: none;
  padding: 0;
  font-size: 1.1rem;
  color: #d1d5db;
  cursor: default;
  transition: color 0.15s, transform 0.1s;
  line-height: 1;
}

.star-rating--interactive .star-rating__star {
  cursor: pointer;
}

.star-rating__star--filled {
  color: #f59e0b;
}

.star-rating__star--hover {
  color: #fbbf24;
  transform: scale(1.15);
}

.star-rating--interactive .star-rating__star:disabled {
  cursor: default;
}

.star-rating__count {
  font-size: 0.8rem;
  color: #6b7280;
  margin-left: 4px;
}
</style>
