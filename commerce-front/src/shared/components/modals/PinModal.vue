<template>
  <div class="pin-modal__overlay">
    <div class="pin-modal__card">
      <div class="pin-modal__icon">
        <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <path stroke-linecap="round" stroke-linejoin="round"
            d="M16.5 10.5V7.5a4.5 4.5 0 10-9 0v3M4.5 10.5h15A1.5 1.5 0 0121 12v7.5A1.5 1.5 0 0119.5 21h-15A1.5 1.5 0 013 19.5V12a1.5 1.5 0 011.5-1.5z" />
        </svg>
      </div>

      <span class="eyebrow">{{ $t('pin.security') }}</span>
      <h2 class="pin-modal__title">
        {{ hasPin ? $t('pin.enterPin') : $t('pin.setPin') }}
      </h2>
      <p class="pin-modal__desc">
        {{ hasPin ? $t('pin.enterPinDesc') : $t('pin.setPinDesc') }}
      </p>

      <div class="pin-modal__digits">
        <input
          v-for="i in 4"
          :key="i"
          :ref="el => { if (el) inputs[i - 1] = el }"
          v-model="digits[i - 1]"
          type="password"
          inputmode="numeric"
          maxlength="1"
          class="pin-modal__input"
          :class="{ 'pin-modal__input--error': error }"
          @input="onInput(i - 1, $event)"
          @keydown.backspace="onBackspace(i - 1)"
          @paste.prevent="onPaste"
        />
      </div>

      <p v-if="error" class="pin-modal__error">{{ error }}</p>

      <button @click="submit" :disabled="pin.length < 4 || loading"
        class="btn btn-primary btn-lg pin-modal__submit">
        <span v-if="loading" class="pin-modal__spinner"></span>
        <span v-else>{{ hasPin ? $t('pin.confirm') : $t('pin.createPin') }}</span>
      </button>

      <button v-if="hasPin" @click="$emit('change-pin')" class="pin-modal__change">
        {{ $t('pin.changePin') }}
      </button>
      <button v-if="hasPin" type="button" class="pin-modal__forgot" @click="showForgot = true">
        Code PIN oublié ?
      </button>
    </div>

    <ForgotSecretModal v-if="showForgot" mode="pin" @close="showForgot = false" @reset="onForgotReset" />
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePinStore } from '@/stores/pin'
import { useAuthStore } from '@/features/auth/auth.store'
import ForgotSecretModal from '@/features/auth/ForgotSecretModal.vue'

const { t } = useI18n()
const emit = defineEmits(['verified', 'change-pin'])

const pinStore  = usePinStore()
const authStore = useAuthStore()

const hasPin = computed(() => authStore.user?.has_pin ?? true)

const digits      = ref(['', '', '', ''])
const inputs      = ref([])
const loading     = ref(false)
const error       = ref('')
const showForgot  = ref(false)

function onForgotReset() {
  // PIN remis à zéro côté backend → reset l'input, l'utilisateur saisit son nouveau PIN
  digits.value = ['', '', '', '']
  error.value  = 'Code PIN réinitialisé. Saisissez le nouveau.'
  nextTick(() => inputs.value[0]?.focus())
}

const pin = computed(() => digits.value.join(''))

onMounted(() => nextTick(() => inputs.value[0]?.focus()))

function onInput(index, event) {
  const val = event.target.value.replace(/\D/g, '')
  digits.value[index] = val.slice(-1)
  error.value = ''
  if (val && index < 3) {
    nextTick(() => inputs.value[index + 1]?.focus())
  }
  if (pin.value.length === 4) submit()
}

function onBackspace(index) {
  if (!digits.value[index] && index > 0) {
    digits.value[index - 1] = ''
    nextTick(() => inputs.value[index - 1]?.focus())
  }
}

function onPaste(event) {
  const text = (event.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 4)
  text.split('').forEach((ch, i) => { digits.value[i] = ch })
  nextTick(() => inputs.value[Math.min(text.length, 3)]?.focus())
  if (text.length === 4) submit()
}

async function submit() {
  if (pin.value.length < 4 || loading.value) return
  loading.value = true
  error.value   = ''
  try {
    if (hasPin.value) {
      await pinStore.verify(pin.value)
    } else {
      await pinStore.setPin(pin.value)
      await authStore.fetchUser()
      pinStore.verified = true
      sessionStorage.setItem('pin_verified', '1')
    }
    emit('verified')
  } catch (e) {
    error.value = e.response?.data?.message ?? t('pin.incorrectCode')
    digits.value = ['', '', '', '']
    nextTick(() => inputs.value[0]?.focus())
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.pin-modal__overlay {
  position: fixed;
  inset: 0;
  z-index: var(--z-modal);
  background: rgba(20, 18, 19, 0.5);
  backdrop-filter: blur(6px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
  animation: fadeIn 0.2s ease;
}

.pin-modal__card {
  background: #fff;
  border-radius: var(--radius-xl);
  width: 100%;
  max-width: 400px;
  padding: var(--space-8);
  text-align: center;
  box-shadow: var(--shadow-lg);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-3);
  animation: scaleIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.pin-modal__icon {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: var(--rose-50);
  color: var(--rose-500);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: var(--space-2);
}

.pin-modal__title {
  font-family: var(--font-display);
  font-size: 1.375rem;
  font-weight: 500;
  color: var(--gray-800);
}

.pin-modal__desc {
  font-size: 0.875rem;
  color: var(--gray-500);
  line-height: 1.6;
  margin-bottom: var(--space-3);
}

.pin-modal__digits {
  display: flex;
  gap: var(--space-3);
  margin: var(--space-2) 0 var(--space-3);
}

.pin-modal__input {
  width: 56px;
  height: 56px;
  text-align: center;
  font-family: var(--font-display);
  font-size: 1.5rem;
  font-weight: 600;
  border-radius: var(--radius-md);
  border: 2px solid var(--cream-300);
  background: var(--cream-50);
  color: var(--gray-800);
  outline: none;
  transition: all var(--transition-fast);
}
.pin-modal__input:focus {
  border-color: var(--rose-500);
  box-shadow: 0 0 0 4px var(--rose-100);
}
.pin-modal__input--error {
  border-color: #fca5a5;
  background: #fee2e2;
}

.pin-modal__error {
  color: #ef4444;
  font-size: 0.8125rem;
  margin-bottom: var(--space-2);
}

.pin-modal__submit {
  width: 100%;
  justify-content: center;
  margin-top: var(--space-2);
}

.pin-modal__spinner {
  width: 16px; height: 16px;
  border: 2px solid rgba(255,255,255,0.4);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes fadeIn { from { opacity: 0 } to { opacity: 1 } }

.pin-modal__change {
  margin-top: var(--space-3);
  font-size: 0.75rem;
  color: var(--gray-400);
  transition: color var(--transition-fast);
}
.pin-modal__change:hover { color: var(--rose-500); }

.pin-modal__forgot {
  margin-top: var(--space-2);
  background: none;
  border: none;
  font-size: 0.75rem;
  color: var(--rose-600);
  cursor: pointer;
  padding: 0;
}
.pin-modal__forgot:hover { text-decoration: underline; }
</style>
