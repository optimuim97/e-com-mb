<template>
  <Teleport to="body">
  <div class="fs-overlay" @click.self="$emit('close')">
    <div class="fs-modal" role="dialog" aria-modal="true" :aria-label="title">
      <button class="fs-close" @click="$emit('close')" :aria-label="$t('common.close')">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M18 6 6 18M6 6l12 12" />
        </svg>
      </button>

      <header class="fs-head">
        <span class="fs-badge" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <rect x="4.5" y="10.5" width="15" height="10" rx="2.5" />
            <path d="M8 10.5V7a4 4 0 0 1 8 0v3.5" />
            <circle cx="12" cy="15.5" r="1.4" fill="currentColor" stroke="none" />
          </svg>
        </span>
        <h3>{{ title }}</h3>
        <p>{{ subtitle }}</p>
      </header>

      <!-- ── Étape 1 : identifiant + canal ── -->
      <form v-if="step === 'request'" @submit.prevent="askCode" class="fs-form">
        <div class="fs-field">
          <label class="fs-label" for="fs-identifier">{{ $t('forgot.identifier') }}</label>
          <div class="fs-input-wrap">
            <svg class="fs-input-ico" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 6h16v12H4z" />
              <path d="m4 7 8 6 8-6" />
            </svg>
            <input
              id="fs-identifier"
              v-model="identifier"
              type="text"
              class="input fs-input"
              :placeholder="$t('forgot.identifierPlaceholder')"
              autocomplete="username"
              required
              autofocus
            />
          </div>
        </div>

        <div class="fs-field">
          <span class="fs-label">{{ $t('forgot.channelLabel') }}</span>
          <div class="fs-channels" role="radiogroup" :aria-label="$t('forgot.channelGroupLabel')">
            <button
              type="button"
              class="fs-channel"
              :class="{ active: channel === 'email' }"
              role="radio"
              :aria-checked="channel === 'email'"
              @click="channel = 'email'"
            >
              <svg class="fs-channel__ico" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="5" width="18" height="14" rx="2" />
                <path d="m3 7 9 6 9-6" />
              </svg>
              <span class="fs-channel__label">Email</span>
              <svg class="fs-channel__check" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                <path d="m20 6-11 11-5-5" />
              </svg>
            </button>

            <button
              type="button"
              class="fs-channel"
              :class="{ active: channel === 'whatsapp' }"
              role="radio"
              :aria-checked="channel === 'whatsapp'"
              @click="channel = 'whatsapp'"
            >
              <svg class="fs-channel__ico" viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347M12.05 21.785h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413" />
              </svg>
              <span class="fs-channel__label">WhatsApp</span>
              <svg class="fs-channel__check" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                <path d="m20 6-11 11-5-5" />
              </svg>
            </button>
          </div>
        </div>

        <p v-if="error" class="fs-error">{{ error }}</p>

        <button type="submit" class="btn btn-primary fs-cta" :disabled="busy || !identifier">
          {{ busy ? $t('forgot.sending') : $t('forgot.getCode') }}
        </button>
      </form>

      <!-- ── Étape 2 : code + nouveau secret ── -->
      <form v-else @submit.prevent="resetSecret" class="fs-form">
        <p class="fs-info">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="9" /><path d="M12 8h.01M11 12h1v4h1" />
          </svg>
          <span>{{ $t('forgot.codeSentBefore') }} <strong>{{ channel === 'whatsapp' ? 'WhatsApp' : 'email' }}</strong>{{ $t('forgot.codeSentAfter') }}</span>
        </p>

        <div class="fs-field">
          <label class="fs-label" for="fs-code">{{ $t('forgot.codeLabel') }}</label>
          <input
            id="fs-code"
            v-model="code"
            type="text"
            inputmode="numeric"
            maxlength="6"
            pattern="\d{6}"
            class="input fs-code-input"
            placeholder="000000"
            autocomplete="one-time-code"
            required
            autofocus
          />
        </div>

        <div class="fs-field" v-if="mode === 'password'">
          <label class="fs-label" for="fs-pass">{{ $t('forgot.newPassword') }}</label>
          <input id="fs-pass" v-model="password" type="password" class="input" :placeholder="$t('auth.passwordMinPlaceholder')" minlength="8" autocomplete="new-password" required />
        </div>
        <div class="fs-field" v-if="mode === 'password'">
          <label class="fs-label" for="fs-pass2">{{ $t('auth.confirmPassword') }}</label>
          <input id="fs-pass2" v-model="passwordConfirm" type="password" class="input" :placeholder="$t('forgot.confirmPasswordPlaceholder')" minlength="8" autocomplete="new-password" required />
        </div>

        <div class="fs-field" v-if="mode !== 'password'">
          <label class="fs-label" for="fs-pin">{{ $t('forgot.newPin') }}</label>
          <input
            id="fs-pin"
            v-model="pin"
            type="text"
            inputmode="numeric"
            maxlength="4"
            pattern="\d{4}"
            class="input fs-code-input"
            placeholder="0000"
            required
          />
        </div>

        <p v-if="error" class="fs-error">{{ error }}</p>
        <p v-if="success" class="fs-success">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="m20 6-11 11-5-5" />
          </svg>
          <span>{{ success }}</span>
        </p>

        <div class="fs-actions">
          <button type="button" class="btn btn-outline" @click="step = 'request'">{{ $t('forgot.resend') }}</button>
          <button type="submit" class="btn btn-primary" :disabled="busy">
            {{ busy ? $t('forgot.validating') : $t('forgot.reset') }}
          </button>
        </div>
      </form>
    </div>
  </div>
  </Teleport>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/api'

const props = defineProps({
  // 'password' | 'pin'
  mode: { type: String, default: 'password' },
})
const emit = defineEmits(['close', 'reset'])

const { t } = useI18n()

const title = computed(() =>
  props.mode === 'pin' ? t('forgot.titlePin') : t('forgot.titlePassword')
)
const subtitle = computed(() =>
  props.mode === 'pin' ? t('forgot.subtitlePin') : t('forgot.subtitlePassword')
)

const step      = ref('request') // 'request' | 'reset'
const identifier = ref('')
const channel   = ref('whatsapp')
const code      = ref('')
const password  = ref('')
const passwordConfirm = ref('')
const pin       = ref('')
const busy      = ref(false)
const error     = ref('')
const success   = ref('')

const endpoints = props.mode === 'pin'
  ? { ask: '/auth/pin/forgot',      reset: '/auth/pin/reset' }
  : { ask: '/auth/password/forgot', reset: '/auth/password/reset' }

async function askCode() {
  busy.value = true
  error.value = ''
  try {
    await api.post(endpoints.ask, {
      identifier: identifier.value.trim(),
      channel:    channel.value,
    })
    step.value = 'reset'
  } catch (e) {
    error.value = e.response?.data?.message
      || Object.values(e.response?.data?.errors ?? {})[0]?.[0]
      || t('forgot.errorRetry')
  } finally {
    busy.value = false
  }
}

async function resetSecret() {
  busy.value = true
  error.value = ''
  success.value = ''

  if (props.mode === 'password' && password.value !== passwordConfirm.value) {
    error.value = t('forgot.passwordMismatch')
    busy.value = false
    return
  }

  try {
    const body = {
      identifier: identifier.value.trim(),
      code:       code.value.trim(),
    }
    if (props.mode === 'pin') body.pin = pin.value
    else {
      body.password              = password.value
      body.password_confirmation = passwordConfirm.value
    }

    await api.post(endpoints.reset, body)
    success.value = props.mode === 'pin'
      ? t('forgot.pinResetDone')
      : t('forgot.passwordResetDone')
    emit('reset')
    setTimeout(() => emit('close'), 1500)
  } catch (e) {
    error.value = e.response?.data?.message
      || Object.values(e.response?.data?.errors ?? {})[0]?.[0]
      || t('forgot.errorCheckCode')
  } finally {
    busy.value = false
  }
}
</script>

<style scoped>
.fs-overlay {
  position: fixed; inset: 0; z-index: 250;
  background: rgba(28, 18, 24, 0.55);
  backdrop-filter: blur(4px);
  /* flex-start + margin auto : centre quand ça tient, scrolle quand c'est plus haut
     que l'écran (sinon le badge/les boutons se font couper). */
  display: flex; align-items: flex-start; justify-content: center;
  padding: 20px 16px;
  overflow-y: auto;
  animation: fs-fade 0.18s ease;
}
.fs-modal {
  position: relative;
  margin: auto;
  background: #fff;
  border-radius: 20px;
  width: 100%; max-width: 420px;
  padding: 30px 26px 26px;
  box-shadow: 0 24px 60px -12px rgba(28, 18, 24, 0.35);
  animation: fs-pop 0.22s cubic-bezier(0.22, 1, 0.36, 1);
}
@keyframes fs-fade { from { opacity: 0; } }
@keyframes fs-pop {
  from { opacity: 0; transform: translateY(10px) scale(0.98); }
}

.fs-close {
  position: absolute; top: 14px; right: 14px;
  width: 30px; height: 30px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  background: var(--cream-100, #f5f0ec); border: none; cursor: pointer;
  color: var(--gray-500, #8a807a);
  transition: background 0.15s, color 0.15s, transform 0.15s;
}
.fs-close:hover { background: var(--rose-500, var(--rose-500)); color: #fff; transform: rotate(90deg); }

/* ── En-tête ── */
.fs-head { text-align: center; margin-bottom: 22px; }
.fs-badge {
  display: inline-flex; align-items: center; justify-content: center;
  width: 52px; height: 52px; border-radius: 16px;
  margin-bottom: 12px;
  color: var(--rose-500, var(--rose-500));
  background: var(--rose-50, var(--rose-50));
  box-shadow: inset 0 0 0 1px var(--rose-100, #ffdce8);
}
.fs-head h3 {
  font-family: var(--font-display);
  font-size: 1.35rem;
  font-weight: 600;
  color: var(--gray-900, #1a1410);
  margin: 0 0 5px;
  letter-spacing: -0.01em;
}
.fs-head p {
  font-size: 0.85rem;
  line-height: 1.5;
  color: var(--gray-500, #8a807a);
  margin: 0 auto;
  max-width: 30ch;
}

/* ── Formulaire ── */
.fs-form { display: flex; flex-direction: column; gap: 16px; }
.fs-field { display: flex; flex-direction: column; gap: 7px; }
.fs-label {
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--gray-600, #6b6560);
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

/* Champ avec icône */
.fs-input-wrap { position: relative; }
.fs-input-ico {
  position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
  color: var(--gray-400, #b0a8a2); pointer-events: none;
}
.fs-input.input { padding-left: 42px; }

.input {
  width: 100%;
  height: 46px;
  border: 1.5px solid var(--cream-300, #e7ded7);
  border-radius: 12px;
  padding: 0 14px;
  font-size: 0.9375rem;
  color: var(--gray-800, #3d3230);
  background: var(--cream-50, #faf7f4);
  transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
}
.input::placeholder { color: var(--gray-400, #b0a8a2); }
.input:focus {
  outline: none;
  border-color: var(--rose-500, var(--rose-500));
  background: #fff;
  box-shadow: 0 0 0 3px var(--rose-100, #ffdce8);
}

/* ── Sélecteur de canal ── */
.fs-channels { display: flex; gap: 10px; }
.fs-channel {
  position: relative;
  flex: 1;
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 13px 14px;
  border: 1.5px solid var(--cream-300, #e7ded7);
  border-radius: 12px;
  cursor: pointer;
  background: #fff;
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--gray-600, #6b6560);
  transition: border-color 0.15s, background 0.15s, color 0.15s, box-shadow 0.15s;
}
.fs-channel__ico { flex-shrink: 0; color: var(--gray-400, #b0a8a2); transition: color 0.15s; }
.fs-channel__label { flex: 1; text-align: left; }
.fs-channel__check {
  color: var(--rose-500, var(--rose-500));
  opacity: 0; transform: scale(0.6);
  transition: opacity 0.15s, transform 0.15s;
}
.fs-channel:hover { border-color: var(--rose-200, #ffc4d9); background: var(--rose-50, var(--rose-50)); }
.fs-channel.active {
  border-color: var(--rose-500, var(--rose-500));
  background: var(--rose-50, var(--rose-50));
  color: var(--rose-700, #b01450);
  box-shadow: 0 0 0 3px var(--rose-100, #ffdce8);
}
.fs-channel.active .fs-channel__ico { color: var(--rose-500, var(--rose-500)); }
.fs-channel.active .fs-channel__check { opacity: 1; transform: scale(1); }

/* ── Code / infos ── */
.fs-code-input {
  font-family: ui-monospace, "SF Mono", monospace;
  font-size: 1.4rem;
  font-weight: 600;
  text-align: center;
  letter-spacing: 0.4em;
  text-indent: 0.4em;
}
.fs-info {
  display: flex; align-items: flex-start; gap: 9px;
  font-size: 0.82rem; line-height: 1.45;
  color: var(--gray-600, #6b6560);
  background: var(--cream-50, #faf7f4);
  border: 1px solid var(--cream-200, #efe7e0);
  padding: 11px 13px;
  border-radius: 12px;
  margin: 0;
}
.fs-info svg { flex-shrink: 0; margin-top: 1px; color: var(--rose-500, var(--rose-500)); }

/* ── Actions ── */
.fs-cta { margin-top: 6px; width: 100%; height: 48px; font-size: 0.95rem; }
.fs-actions { display: flex; gap: 10px; margin-top: 4px; }
.fs-actions .btn { flex: 1; height: 46px; }

/* ── Messages ── */
.fs-error, .fs-success {
  display: flex; align-items: center; gap: 7px;
  font-size: 0.83rem; font-weight: 500; margin: 0;
}
.fs-error { color: #c0264a; }
.fs-success { color: #15803d; }
.fs-success svg { flex-shrink: 0; }
</style>
