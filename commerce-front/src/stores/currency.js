import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useSettingsStore } from '@/stores/settings'

/**
 * Multi-devises XOF / EUR.
 *
 * Le franc CFA (XOF) a une PARITÉ FIXE LÉGALE avec l'euro :
 *   1 EUR = 655,957 XOF
 * → aucune API de taux nécessaire, aucune fluctuation.
 *
 * Principe : les prix sont stockés dans la devise de base de la boutique
 * (shop_currency, XOF par défaut). On convertit uniquement pour l'AFFICHAGE
 * selon la devise choisie par le visiteur. La commande reste enregistrée
 * dans la devise de base (canonique).
 */
const PARITY_EUR_XOF = 655.957
const SUPPORTED = ['XOF', 'EUR']
const STORAGE_KEY = 'display_currency'

const META = {
  XOF: { label: 'Franc CFA', symbol: 'FCFA', flag: '🇨🇮', decimals: 0 },
  EUR: { label: 'Euro',      symbol: '€',    flag: '🇪🇺', decimals: 2 },
}

export const useCurrencyStore = defineStore('currency', () => {
  const display = ref(localStorage.getItem(STORAGE_KEY) || '')

  const base = computed(() => {
    const s = useSettingsStore()
    const c = s.shopCurrency || 'XOF'
    return SUPPORTED.includes(c) ? c : 'XOF'
  })

  // Devise réellement affichée : choix visiteur sinon devise de base.
  const active = computed(() =>
    SUPPORTED.includes(display.value) ? display.value : base.value
  )

  const supported = computed(() =>
    SUPPORTED.map(code => ({ code, ...META[code] }))
  )

  function setCurrency(code) {
    if (!SUPPORTED.includes(code)) return
    display.value = code
    localStorage.setItem(STORAGE_KEY, code)
  }

  // ── Conversion via pivot XOF ──────────────────────────────────────────────
  const toXOF   = (amount, cur) => cur === 'EUR' ? amount * PARITY_EUR_XOF : amount
  const fromXOF = (xof, cur)    => cur === 'EUR' ? xof / PARITY_EUR_XOF    : xof

  /** Convertit un montant exprimé dans la devise de base → devise affichée. */
  function convert(amountInBase) {
    const xof = toXOF(Number(amountInBase) || 0, base.value)
    return fromXOF(xof, active.value)
  }

  /** Formate un montant (exprimé en devise de base) dans la devise affichée. */
  function format(amountInBase) {
    const cur = active.value
    const value = convert(amountInBase)
    return new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: cur,
      maximumFractionDigits: META[cur].decimals,
      minimumFractionDigits: 0,
    }).format(value)
  }

  return { display, base, active, supported, setCurrency, convert, format }
})

export { PARITY_EUR_XOF, SUPPORTED }
