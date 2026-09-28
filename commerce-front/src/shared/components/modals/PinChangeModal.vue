<template>
  <div class="pin-modal__overlay">
    <div class="pin-modal__card">
      <header class="pin-modal__header">
        <div>
          <span class="eyebrow">{{ $t('pin.security') }}</span>
          <h2 class="pin-modal__title">{{ $t('pin.changePin') }}</h2>
        </div>
        <button @click="$emit('close')" class="pin-modal__close" :aria-label="$t('common.close')">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </header>

      <div class="pin-modal__body">
        <div class="pin-modal__group">
          <label class="label">{{ $t('pin.currentPin') }}</label>
          <div class="pin-modal__digits">
            <input
              v-for="i in 4" :key="'c'+i"
              :ref="el => { if (el) currentInputs[i-1] = el }"
              v-model="currentDigits[i-1]"
              type="password" inputmode="numeric" maxlength="1"
              class="pin-modal__input"
              @input="onCurrentInput(i-1, $event)"
              @keydown.backspace="onCurrentBackspace(i-1)"
            />
          </div>
        </div>

        <div class="pin-modal__group">
          <label class="label">{{ $t('pin.newPin') }}</label>
          <div class="pin-modal__digits">
            <input
              v-for="i in 4" :key="'n'+i"
              :ref="el => { if (el) newInputs[i-1] = el }"
              v-model="newDigits[i-1]"
              type="password" inputmode="numeric" maxlength="1"
              class="pin-modal__input"
              @input="onNewInput(i-1, $event)"
              @keydown.backspace="onNewBackspace(i-1)"
            />
          </div>
        </div>
      </div>

      <p v-if="error" class="pin-modal__msg pin-modal__msg--error">{{ error }}</p>
      <p v-if="success" class="pin-modal__msg pin-modal__msg--success">{{ $t('pin.changeSuccess') }}</p>

      <button @click="submit" :disabled="currentPin.length < 4 || newPin.length < 4 || loading"
        class="btn btn-primary btn-lg pin-modal__submit">
        <span v-if="loading" class="pin-modal__spinner"></span>
        <span v-else>{{ $t('pin.saveBtn') }}</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePinStore } from '@/stores/pin'

const { t } = useI18n()
const emit = defineEmits(['close'])

const pinStore = usePinStore()

const currentDigits = ref(['', '', '', ''])
const newDigits     = ref(['', '', '', ''])
const currentInputs = ref([])
const newInputs     = ref([])
const loading = ref(false)
const error   = ref('')
const success = ref(false)

const currentPin = computed(() => currentDigits.value.join(''))
const newPin     = computed(() => newDigits.value.join(''))

onMounted(() => nextTick(() => currentInputs.value[0]?.focus()))

function makeInputHandler(digits, inputs, nextRowInputs) {
  return function (index, event) {
    const val = event.target.value.replace(/\D/g, '')
    digits[index] = val.slice(-1)
    error.value = ''
    if (val && index < 3) nextTick(() => inputs[index + 1]?.focus())
    else if (val && index === 3 && nextRowInputs) nextTick(() => nextRowInputs[0]?.focus())
  }
}

function makeBackspaceHandler(digits, inputs) {
  return function (index) {
    if (!digits[index] && index > 0) {
      digits[index - 1] = ''
      nextTick(() => inputs[index - 1]?.focus())
    }
  }
}

function onCurrentInput(i, e)   { makeInputHandler(currentDigits.value, currentInputs.value, newInputs.value)(i, e) }
function onCurrentBackspace(i)  { makeBackspaceHandler(currentDigits.value, currentInputs.value)(i) }
function onNewInput(i, e)        { makeInputHandler(newDigits.value, newInputs.value, null)(i, e) }
function onNewBackspace(i)       { makeBackspaceHandler(newDigits.value, newInputs.value)(i) }

async function submit() {
  if (loading.value) return
  loading.value = true
  error.value   = ''
  success.value = false
  try {
    await pinStore.setPin(newPin.value, currentPin.value)
    success.value = true
    setTimeout(() => emit('close'), 1200)
  } catch (e) {
    error.value = e.response?.data?.message ?? t('common.error')
    currentDigits.value = ['', '', '', '']
    nextTick(() => currentInputs.value[0]?.focus())
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
}
.pin-modal__card {
  background: #fff;
  border-radius: var(--radius-xl);
  width: 100%;
  max-width: 420px;
  padding: var(--space-7);
  box-shadow: var(--shadow-lg);
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  animation: scaleIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.pin-modal__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: var(--space-3);
}
.pin-modal__title {
  font-family: var(--font-display);
  font-size: 1.25rem;
  font-weight: 500;
  color: var(--gray-800);
  margin-top: 4px;
}
.pin-modal__close {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  color: var(--gray-400);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all var(--transition-fast);
}
.pin-modal__close:hover { background: var(--cream-200); color: var(--gray-700); }

.pin-modal__body {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}
.pin-modal__group { display: flex; flex-direction: column; gap: var(--space-2); }

.pin-modal__digits {
  display: flex;
  gap: var(--space-2);
}
.pin-modal__input {
  width: 48px;
  height: 48px;
  text-align: center;
  font-family: var(--font-display);
  font-size: 1.25rem;
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
  box-shadow: 0 0 0 3px var(--rose-100);
}

.pin-modal__msg { font-size: 0.8125rem; text-align: center; }
.pin-modal__msg--error { color: #ef4444; }
.pin-modal__msg--success { color: #15803d; font-weight: 500; }

.pin-modal__submit {
  width: 100%;
  justify-content: center;
}

.pin-modal__spinner {
  width: 16px; height: 16px;
  border: 2px solid rgba(255,255,255,0.4);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
