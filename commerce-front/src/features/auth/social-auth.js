/**
 * Authentification sociale par popup OAuth — module réutilisable.
 *
 * Ouvre une popup vers le backend ({backend}/auth/{provider}/redirect),
 * écoute le postMessage de retour (token + user) et établit la session.
 *
 * Usage :
 *   import { useSocialAuth } from '@/features/auth/social-auth'
 *   const { loginWith, loading, error } = useSocialAuth()
 *   loginWith('facebook').then(() => router.push('/'))
 */
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/features/auth/auth.store'

/**
 * Base API atteignable depuis le navigateur (passe par le proxy → Laravel).
 * On réutilise VITE_API_URL (même valeur que axios), sinon "/api" same-origin.
 * Les routes social sont exposées sous /api/auth/{provider}/...
 */
function apiBase() {
  const apiUrl = import.meta.env.VITE_API_URL || '/api'
  if (/^https?:\/\//.test(apiUrl)) return apiUrl.replace(/\/$/, '')
  // Relatif → préfixer par l'origine courante
  return window.location.origin + (apiUrl.startsWith('/') ? apiUrl : '/' + apiUrl)
}

export function useSocialAuth() {
  const { t }   = useI18n()
  const auth    = useAuthStore()
  const loading = ref(false)
  const error   = ref('')

  function loginWith(provider = 'facebook') {
    return new Promise((resolve, reject) => {
      loading.value = true
      error.value = ''

      const url = `${apiBase()}/auth/${provider}/redirect`
      const w = 600, h = 720
      const left = window.screenX + (window.outerWidth - w) / 2
      const top  = window.screenY + (window.outerHeight - h) / 2
      const popup = window.open(
        url, 'social-auth',
        `width=${w},height=${h},left=${left},top=${top},menubar=no,toolbar=no`
      )

      if (!popup) {
        loading.value = false
        error.value = t('auth.popupBlocked')
        return reject(new Error('popup_blocked'))
      }

      let settled = false

      async function onMessage(event) {
        const data = event.data
        if (!data || data.source !== 'social-auth') return
        cleanup()

        if (data.error) {
          error.value = friendly(data.error)
          loading.value = false
          return reject(new Error(data.error))
        }
        try {
          await auth.setSession(data.token, data.user)
          loading.value = false
          resolve(data)
        } catch (e) {
          error.value = t('auth.socialFailed')
          loading.value = false
          reject(e)
        }
      }

      // Si l'utilisateur ferme la popup sans finir
      const poll = setInterval(() => {
        if (popup.closed && !settled) {
          cleanup()
          loading.value = false
          reject(new Error('popup_closed'))
        }
      }, 600)

      function cleanup() {
        settled = true
        clearInterval(poll)
        window.removeEventListener('message', onMessage)
        try { if (!popup.closed) popup.close() } catch { /* ignore */ }
      }

      window.addEventListener('message', onMessage)
    })
  }

  function friendly(code) {
    const map = {
      access_denied:  t('auth.socialCancelled'),
      invalid_state:  t('auth.sessionExpired'),
      auth_failed:    t('auth.socialUnavailable'),
      unsupported_provider: t('auth.providerUnsupported'),
    }
    return map[code] ?? t('auth.socialFailed')
  }

  return { loginWith, loading, error }
}
