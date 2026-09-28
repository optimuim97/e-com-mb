<template>
  <button
    type="button"
    class="g-btn"
    :class="{ 'g-btn--loading': loading }"
    :disabled="loading"
    @click="onClick"
  >
    <svg class="g-btn__logo" viewBox="0 0 24 24" aria-hidden="true">
      <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
      <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
      <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
      <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
    </svg>
    <span>{{ loading ? $t('auth.connecting') : (label || $t('authGate.continueWithGoogle')) }}</span>
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
    const data = await loginWith('google')
    emit('success', data)
  } catch (e) {
    // popup fermée par l'utilisateur → pas une vraie erreur à signaler
    if (e?.message !== 'popup_closed') emit('error', error.value || t('auth.socialFailed'))
  }
}
</script>

<style scoped>
.g-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
  padding: clamp(9px, 1.4vh, 12px) 16px;
  border-radius: var(--radius-md, 10px);
  background: #fff;
  color: #3c4043;
  font-size: 0.9375rem;
  font-weight: 600;
  border: 1.5px solid #dadce0;
  cursor: pointer;
  transition: background 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
}
.g-btn:hover:not(:disabled) { background: #f8f9fa; box-shadow: 0 1px 3px rgba(0,0,0,.12); transform: translateY(-1px); }
.g-btn:disabled { opacity: 0.7; cursor: default; }
.g-btn__logo { width: 20px; height: 20px; flex-shrink: 0; }
</style>
