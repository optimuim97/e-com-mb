/**
 * program.fields.js  (admin)
 * Contrat champs pour la gestion du programme fidélité côté admin.
 */

// ── Avantage (benefit) ─────────────────────────────────────────────────────
export const BENEFIT_FIELDS = {
  title:       { api: 'title',       label: 'Titre de l\'avantage',  type: 'text',     required: true  },
  description: { api: 'description', label: 'Description',           type: 'textarea', required: false },
  tier:        { api: 'tier',        label: 'Niveau',                type: 'select',   required: true  },
  icon:        { api: 'icon',        label: 'Icône (emoji)',          type: 'text',     required: false },
  is_active:   { api: 'is_active',   label: 'Actif',                 type: 'toggle',   required: false },
}

export function makeBenefitForm() {
  return { title: '', description: '', tier: 'bronze', icon: '✨', is_active: true }
}

// ── Niveau (tier) ──────────────────────────────────────────────────────────
export const TIER_FIELDS = {
  name:          { api: 'name',          label: 'Nom du niveau',         type: 'text',   required: true  },
  points_min:    { api: 'points_min',    label: 'Points minimum',        type: 'number', required: true  },
  points_max:    { api: 'points_max',    label: 'Points maximum',        type: 'number', required: false },
  discount_pct:  { api: 'discount_pct', label: 'Réduction (%)',          type: 'number', required: false },
  color:         { api: 'color',         label: 'Couleur (hex)',          type: 'text',   required: false },
  icon:          { api: 'icon',          label: 'Icône',                 type: 'text',   required: false },
}

// ── Consultation status ────────────────────────────────────────────────────
export const CONSULTATION_STATUS_OPTIONS = [
  { value: 'pending',   label: '⏳ En attente' },
  { value: 'confirmed', label: '✅ Confirmée' },
  { value: 'done',      label: '✓ Terminée' },
  { value: 'cancelled', label: '✕ Annulée' },
]

export const TIER_OPTIONS = [
  { value: 'bronze', label: '🥉 Bronze' },
  { value: 'silver', label: '🥈 Argent' },
  { value: 'gold',   label: '🥇 Or' },
]

export function mapErrors(fields, laravelErrors = {}) {
  const apiToForm = Object.fromEntries(
    Object.entries(fields).map(([k, def]) => [def.api, k])
  )
  return Object.fromEntries(
    Object.entries(laravelErrors).map(([apiKey, msgs]) => [
      apiToForm[apiKey] ?? apiKey,
      Array.isArray(msgs) ? msgs[0] : msgs,
    ])
  )
}
