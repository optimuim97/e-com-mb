<template>
  <button
    type="button"
    class="fb-btn"
    :class="{ 'fb-btn--loading': loading }"
    :disabled="loading"
    @click="onClick"
  >
    <svg class="fb-btn__logo" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07z"/>
    </svg>
    <span>{{ loading ? $t('auth.connecting') : (label || $t('authGate.continueWithFacebook')) }}</span>
  </button>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { useSocialAuth } from '@/features/auth/social-auth'

defineProps({
  label: { type: String, default: '' },
})
const emit = defineEmits(['success', 'error'])

const { t } = useI18n()
const { loginWith, loading, error } = useSocialAuth()

async function onClick() {
  try {
    const data = await loginWith('facebook')
    emit('success', data)
  } catch (e) {
    // popup fermée par l'utilisateur → pas une vraie erreur à signaler
    if (e?.message !== 'popup_closed') emit('error', error.value || t('auth.socialFailed'))
  }
}
</script>

<style scoped>
.fb-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
  padding: clamp(9px, 1.4vh, 12px) 16px;
  border-radius: var(--radius-md, 10px);
  background: #1877f2;
  color: #fff;
  font-size: 0.9375rem;
  font-weight: 600;
  border: none;
  cursor: pointer;
  transition: background 0.18s ease, transform 0.18s ease, opacity 0.18s ease;
}
.fb-btn:hover:not(:disabled) { background: #166fe5; transform: translateY(-1px); }
.fb-btn:disabled { opacity: 0.7; cursor: default; }
.fb-btn__logo { width: 20px; height: 20px; flex-shrink: 0; }
</style>
