<template>
  <div class="pin-reveal__overlay">
    <div class="pin-reveal__card">
      <div class="pin-reveal__icon">
        <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <path stroke-linecap="round" stroke-linejoin="round"
            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
        </svg>
      </div>

      <span class="eyebrow">{{ $t('pin.revealEyebrow') }}</span>
      <h2 class="pin-reveal__title">{{ $t('pin.revealTitle') }}</h2>
      <p class="pin-reveal__desc">
        {{ $t('pin.revealDesc') }}
        <br />
        <strong>{{ $t('pin.revealOnce') }}</strong>
      </p>

      <div class="pin-reveal__digits">
        <span
          v-for="(digit, i) in pin.split('')"
          :key="i"
          class="pin-reveal__digit"
        >{{ digit }}</span>
      </div>

      <p class="pin-reveal__hint">
        {{ $t('pin.revealHint') }}
      </p>

      <button @click="$emit('close')" class="btn btn-primary btn-lg pin-reveal__btn">
        {{ $t('pin.revealConfirm') }}
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({ pin: { type: String, required: true } })
defineEmits(['close'])
</script>

<style scoped>
.pin-reveal__overlay {
  position: fixed;
  inset: 0;
  z-index: var(--z-modal);
  background: rgba(20, 18, 19, 0.55);
  backdrop-filter: blur(6px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
}

.pin-reveal__card {
  background: #fff;
  border-radius: var(--radius-xl);
  width: 100%;
  max-width: 420px;
  padding: var(--space-8);
  text-align: center;
  box-shadow: var(--shadow-lg);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-3);
  animation: scaleIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.pin-reveal__icon {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: var(--gold-200);
  color: var(--gold-600);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: var(--space-2);
}

.pin-reveal__title {
  font-family: var(--font-display);
  font-size: 1.375rem;
  font-weight: 500;
  color: var(--gray-800);
}

.pin-reveal__desc {
  font-size: 0.875rem;
  color: var(--gray-500);
  line-height: 1.7;
  margin-bottom: var(--space-3);
}
.pin-reveal__desc strong { color: var(--gray-800); }

.pin-reveal__digits {
  display: flex;
  gap: var(--space-2);
  margin: var(--space-2) 0;
}
.pin-reveal__digit {
  width: 56px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: var(--font-display);
  font-size: 1.75rem;
  font-weight: 600;
  color: var(--rose-700);
  background: var(--rose-50);
  border: 2px solid var(--rose-200);
  border-radius: var(--radius-md);
}

.pin-reveal__hint {
  font-size: 0.75rem;
  color: var(--gray-400);
  margin-bottom: var(--space-3);
}

.pin-reveal__btn {
  width: 100%;
  justify-content: center;
}
</style>
