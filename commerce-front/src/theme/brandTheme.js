/**
 * Moteur de thème marque blanche.
 *
 * Toute l'app utilise les variables CSS --rose-* (teinte principale) et
 * --gold-* (accent). En les surchargeant au runtime depuis les réglages BDD,
 * on re-thématise TOUTE l'application sans toucher un seul composant.
 *
 * Réglages admin attendus (clés settings) :
 *   brand_primary_color  → ex. "#e8336d"  (teinte principale, = --rose-500)
 *   brand_accent_color   → ex. "#d4905a"  (accent, = --gold-500)
 *
 * Les noms --rose-* / --gold-* sont historiques : ils désignent la couleur
 * principale et l'accent de la boutique, quelle que soit leur teinte réelle.
 */

// ── Manipulation couleur ────────────────────────────────────────────────────
function hexToRgb(hex) {
  const h = hex.replace('#', '').trim()
  const full = h.length === 3 ? h.split('').map(c => c + c).join('') : h
  const n = parseInt(full, 16)
  return { r: (n >> 16) & 255, g: (n >> 8) & 255, b: n & 255 }
}
function rgbToHex({ r, g, b }) {
  const to = (v) => Math.max(0, Math.min(255, Math.round(v))).toString(16).padStart(2, '0')
  return `#${to(r)}${to(g)}${to(b)}`
}
/** Mélange linéaire de deux couleurs. ratio=0 → a, ratio=1 → b */
function mix(a, b, ratio) {
  return {
    r: a.r + (b.r - a.r) * ratio,
    g: a.g + (b.g - a.g) * ratio,
    b: a.b + (b.b - a.b) * ratio,
  }
}

const WHITE = { r: 255, g: 255, b: 255 }
const BLACK = { r: 0, g: 0, b: 0 }

// Paliers de luminosité calés sur l'échelle d'origine (50…900).
const TINTS = { 50: 0.92, 100: 0.82, 200: 0.62, 300: 0.40, 400: 0.18 }
const SHADES = { 600: 0.16, 700: 0.32, 800: 0.48, 900: 0.64 }

/** Génère une échelle 50..900 à partir d'une teinte de base (le 500). */
function buildScale(baseHex) {
  const base = hexToRgb(baseHex)
  const scale = { 500: rgbToHex(base) }
  for (const [step, ratio] of Object.entries(TINTS))  scale[step] = rgbToHex(mix(base, WHITE, ratio))
  for (const [step, ratio] of Object.entries(SHADES)) scale[step] = rgbToHex(mix(base, BLACK, ratio))
  return scale
}

// ── Application ─────────────────────────────────────────────────────────────
// Palette par défaut d'une installation neuve, tant que la boutique n'a pas
// choisi ses couleurs. Identique aux valeurs de design-system.css.
const DEFAULT_PRIMARY = '#e8336d'
const DEFAULT_ACCENT  = '#d4905a'

function normalizeHex(v) {
  const h = v.trim()
  return h.startsWith('#') ? h : `#${h}`
}

/**
 * Applique le thème depuis les réglages. Surcharge --rose-* et --gold-*.
 * @param {{ brand_primary_color?: string, brand_accent_color?: string }} settings
 */
export function applyBrandTheme(settings = {}) {
  const root = document.documentElement
  const primary = isValidHex(settings.brand_primary_color) ? normalizeHex(settings.brand_primary_color) : DEFAULT_PRIMARY
  const accent  = isValidHex(settings.brand_accent_color)  ? normalizeHex(settings.brand_accent_color)  : DEFAULT_ACCENT

  const rose = buildScale(primary)
  for (const [step, hex] of Object.entries(rose)) {
    root.style.setProperty(`--rose-${step}`, hex)
  }

  const gold = buildScale(accent)
  for (const [step, hex] of Object.entries(gold)) {
    if (['100', '200', '300', '400', '500', '600'].includes(step)) {
      root.style.setProperty(`--gold-${step}`, hex)
    }
  }

  // Canaux RGB, pour les transparences : rgba(var(--rose-500-rgb), .2)
  const p = hexToRgb(primary)
  const a = hexToRgb(accent)
  root.style.setProperty('--rose-500-rgb', `${p.r}, ${p.g}, ${p.b}`)
  root.style.setProperty('--gold-500-rgb', `${a.r}, ${a.g}, ${a.b}`)

  // Couleur de la barre d'adresse mobile (PWA)
  document.querySelector('meta[name="theme-color"]')?.setAttribute('content', primary)

  // Icône d'onglet choisie par la boutique
  const favicon = (settings.shop_favicon_url || '').trim()
  if (favicon) {
    document.querySelectorAll('link[rel~="icon"], link[rel="apple-touch-icon"]')
      .forEach(link => link.setAttribute('href', favicon))
  }
}

/**
 * Valeur courante d'une variable du thème.
 *
 * Pour les rares endroits où var() ne s'applique pas : tracés Leaflet,
 * attributs SVG calculés en JS, bibliothèques qui attendent un hex.
 */
export function themeColor(name = '--rose-500', fallback = DEFAULT_PRIMARY) {
  if (typeof document === 'undefined') return fallback
  const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim()
  return v || fallback
}

function isValidHex(v) {
  return typeof v === 'string' && /^#?[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/.test(v.trim())
}

export { DEFAULT_PRIMARY, DEFAULT_ACCENT, buildScale, isValidHex }
