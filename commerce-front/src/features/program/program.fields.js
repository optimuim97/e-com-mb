/**
 * program.fields.js
 * Contrat champs pour la demande de consultation conseiller.
 */

export const CONSULTATION_FIELDS = {
  type:        { api: 'type',        labelKey: 'fields.consultationType', type: 'select',   required: true  },
  channel:     { api: 'channel',     labelKey: 'fields.contactChannel',   type: 'select',   required: true  },
  preferred_at:{ api: 'preferred_at',labelKey: 'fields.preferredSlot',    type: 'datetime', required: false },
  message:     { api: 'message',     labelKey: 'fields.yourRequest',      type: 'textarea', required: false },
  phone:       { api: 'phone',       labelKey: 'fields.phoneWhatsapp',    type: 'tel',      required: false },
}

export const CONSULTATION_TYPES = [
  { value: 'skincare',  labelKey: 'program.consultationTypes.skincare' },
  { value: 'product',   labelKey: 'program.consultationTypes.product' },
  { value: 'routine',   labelKey: 'program.consultationTypes.routine' },
  { value: 'gift',      labelKey: 'program.consultationTypes.gift' },
  { value: 'other',     labelKey: 'program.consultationTypes.other' },
]

export const CONSULTATION_CHANNELS = [
  { value: 'whatsapp', labelKey: 'program.consultationChannels.whatsapp' },
  { value: 'video',    labelKey: 'program.consultationChannels.video' },
  { value: 'email',    labelKey: 'program.consultationChannels.email' },
  { value: 'store',    labelKey: 'program.consultationChannels.store' },
]

export function makeConsultationForm() {
  return {
    type:         '',
    channel:      'whatsapp',
    preferred_at: '',
    message:      '',
    phone:        '',
  }
}

export function mapConsultationErrors(laravelErrors = {}) {
  const apiToForm = Object.fromEntries(
    Object.entries(CONSULTATION_FIELDS).map(([k, def]) => [def.api, k])
  )
  return Object.fromEntries(
    Object.entries(laravelErrors).map(([apiKey, msgs]) => [
      apiToForm[apiKey] ?? apiKey,
      Array.isArray(msgs) ? msgs[0] : msgs,
    ])
  )
}
