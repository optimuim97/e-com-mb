<template>
  <button
    @click="copy"
    :class="['copy-btn', size && `copy-btn--${size}`, variant && `copy-btn--${variant}`]"
    :title="copied ? copiedText : title"
    :disabled="disabled"
  >
    <svg v-if="!copied" :width="iconSize" :height="iconSize" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
      <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
    </svg>
    <svg v-else :width="iconSize" :height="iconSize" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <polyline points="20 6 9 17 4 12"></polyline>
    </svg>
    <span v-if="label" class="copy-btn__label">{{ copied && copiedLabel ? copiedLabel : label }}</span>
  </button>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  text: {
    type: String,
    required: true
  },
  title: {
    type: String,
    default: 'Copier'
  },
  copiedText: {
    type: String,
    default: 'Copié !'
  },
  label: {
    type: String,
    default: ''
  },
  copiedLabel: {
    type: String,
    default: ''
  },
  size: {
    type: String,
    default: 'md', // sm, md, lg
    validator: (val) => ['sm', 'md', 'lg'].includes(val)
  },
  variant: {
    type: String,
    default: 'default', // default, primary, ghost
    validator: (val) => ['default', 'primary', 'ghost'].includes(val)
  },
  duration: {
    type: Number,
    default: 2000
  },
  disabled: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['copied', 'error'])

const copied = ref(false)

const iconSize = computed(() => {
  const sizes = { sm: 14, md: 16, lg: 18 }
  return sizes[props.size] || 16
})

async function copy() {
  if (!props.text || props.disabled) return
  try {
    await navigator.clipboard.writeText(props.text)
    copied.value = true
    emit('copied', props.text)
    setTimeout(() => { copied.value = false }, props.duration)
  } catch (error) {
    emit('error', error)
  }
}
</script>

<style scoped>
.copy-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-1);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: all var(--transition-fast);
  flex-shrink: 0;
  font-weight: 500;
}

.copy-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Sizes */
.copy-btn--sm {
  padding: 4px;
  font-size: 0.75rem;
}

.copy-btn--md {
  padding: 6px;
  font-size: 0.8125rem;
}

.copy-btn--lg {
  padding: 8px;
  font-size: 0.875rem;
}

.copy-btn--sm.copy-btn__label {
  padding: 4px 8px;
}

.copy-btn--md.copy-btn__label {
  padding: 6px 12px;
}

.copy-btn--lg.copy-btn__label {
  padding: 8px 16px;
}

/* Variants */
.copy-btn--default {
  background: #fff;
  color: #1e40af;
  border: 1.5px solid #bfdbfe;
}

.copy-btn--default:hover:not(:disabled) {
  background: #eff6ff;
  border-color: #93c5fd;
}

.copy-btn--primary {
  background: var(--rose-500);
  color: #fff;
  border: 1.5px solid var(--rose-500);
}

.copy-btn--primary:hover:not(:disabled) {
  background: var(--rose-600);
  border-color: var(--rose-600);
}

.copy-btn--ghost {
  background: transparent;
  color: var(--gray-500);
  border: 1.5px solid transparent;
}

.copy-btn--ghost:hover:not(:disabled) {
  background: var(--gray-100);
  color: var(--gray-700);
}
</style>
