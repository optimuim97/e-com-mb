import { createI18n } from 'vue-i18n'
import fr from './fr.json'
import en from './en.json'

/* ── Langue sauvegardée dans localStorage, fallback navigateur puis FR ── */
function getDefaultLocale() {
  const saved = localStorage.getItem('locale')
  if (saved && ['fr', 'en'].includes(saved)) return saved

  const browserLang = navigator.language?.slice(0, 2)
  if (browserLang === 'en') return 'en'
  return 'fr'
}

const i18n = createI18n({
  legacy: false,          // Composition API mode
  locale: getDefaultLocale(),
  fallbackLocale: 'fr',
  messages: { fr, en },
  // Supprime les warnings pour les clés manquantes en dev
  missingWarn: false,
  fallbackWarn: false,
})

export default i18n

/**
 * Change la langue et persiste le choix
 */
export function setLocale(locale) {
  i18n.global.locale.value = locale
  localStorage.setItem('locale', locale)
  document.documentElement.lang = locale
}

/**
 * Langue courante
 */
export function getLocale() {
  return i18n.global.locale.value
}
