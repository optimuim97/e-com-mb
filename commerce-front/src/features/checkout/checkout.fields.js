/**
 * checkout.fields.js
 * Source de vérité pour tous les champs du formulaire de commande.
 *
 * - Clé (gauche)  : nom du champ dans le formulaire Vue (v-model)
 * - api           : nom exact attendu par Laravel dans le payload
 * - labelKey      : clé i18n du texte affiché dans les <label>
 * - type          : type d'input HTML
 * - required      : validation minimale
 *
 * Usage :
 *   import { makeForm, toPayload, mapErrors } from './checkout.fields'
 */

export const FIELDS = {
  // ── Informations personnelles ──────────────────────────────────────────
  first_name:             { api: 'first_name',             labelKey: 'fields.firstName',    type: 'text',   required: true  },
  last_name:              { api: 'last_name',              labelKey: 'fields.lastName',     type: 'text',   required: false },
  email:                  { api: 'email',                  labelKey: 'fields.email',        type: 'email',  required: true  },
  phone:                  { api: 'phone',                  labelKey: 'fields.phone',        type: 'tel',    required: true  },

  // ── Adresse de livraison ───────────────────────────────────────────────
  shipping_address_line1: { api: 'shipping_address_line1', labelKey: 'fields.addressLine1', type: 'text',   required: true  },
  shipping_address_line2: { api: 'shipping_address_line2', labelKey: 'fields.addressLine2', type: 'text',   required: false },
  shipping_city:          { api: 'shipping_city',          labelKey: 'fields.city',         type: 'text',   required: true  },
  shipping_commune:       { api: 'shipping_commune',       labelKey: 'fields.commune',      type: 'text',   required: false },
  shipping_region:        { api: 'shipping_region',        labelKey: 'fields.region',       type: 'text',   required: false },
  shipping_postal_code:   { api: 'shipping_postal_code',   labelKey: 'fields.postalCode',   type: 'text',   required: false },
  shipping_country:       { api: 'shipping_country',       labelKey: 'fields.country',      type: 'select', required: true  },
  shipping_phone:         { api: 'shipping_phone',         labelKey: 'fields.shippingPhone', type: 'tel',   required: false },

  // ── Adresse de facturation ─────────────────────────────────────────────
  billing_address_line1:  { api: 'billing_address_line1',  labelKey: 'fields.addressLine1', type: 'text',   required: false },
  billing_address_line2:  { api: 'billing_address_line2',  labelKey: 'fields.addressLine2', type: 'text',   required: false },
  billing_city:           { api: 'billing_city',           labelKey: 'fields.city',         type: 'text',   required: false },
  billing_postal_code:    { api: 'billing_postal_code',    labelKey: 'fields.postalCode',   type: 'text',   required: false },
  billing_country:        { api: 'billing_country',        labelKey: 'fields.country',      type: 'select', required: false },

  // ── Livraison : infos complémentaires ────────────────────────────────
  landmark:               { api: 'landmark',               labelKey: 'fields.landmark',     type: 'textarea', required: false },
  receiver_phone:         { api: 'receiver_phone',         labelKey: 'fields.receiverPhone', type: 'tel',    required: false },

  // ── Paiement & livraison ───────────────────────────────────────────────
  payment_method:         { api: 'payment_method',         labelKey: 'fields.paymentMethod', type: 'radio',  required: true  },
  shipping_method:        { api: 'shipping_method',        labelKey: 'fields.shippingMethod', type: 'radio', required: true  },
  coupon_code:            { api: 'coupon_code',            labelKey: 'fields.couponCode',   type: 'text',   required: false },
  customer_note:          { api: 'customer_note',          labelKey: 'fields.customerNote', type: 'textarea',required: false },
}

/** Génère le formulaire vide avec les valeurs par défaut */
export function makeForm(overrides = {}) {
  const defaults = {
    first_name: '', last_name: '', email: '', phone: '',
    shipping_address_line1: '', shipping_address_line2: '',
    shipping_city: '', shipping_commune: '', shipping_region: '',
    shipping_postal_code: '', shipping_country: 'CI', shipping_phone: '',
    billing_address_line1: '', billing_address_line2: '',
    billing_city: '', billing_postal_code: '', billing_country: 'CI',
    payment_method: 'wave', shipping_method: 'standard',
    coupon_code: null, customer_note: '',
    landmark: '', receiver_phone: '', receiver_different: false,
  }
  return { ...defaults, ...overrides }
}

/**
 * Convertit les erreurs de validation Laravel vers les clés du formulaire Vue.
 * Laravel renvoie : { "shipping_address_line1": ["Ce champ est requis."] }
 * → Vue reçoit   : { "shipping_address_line1": "Ce champ est requis." }
 */
export function mapErrors(laravelErrors = {}) {
  const apiToForm = Object.fromEntries(
    Object.entries(FIELDS).map(([formKey, def]) => [def.api, formKey])
  )
  return Object.fromEntries(
    Object.entries(laravelErrors).map(([apiKey, msgs]) => [
      apiToForm[apiKey] ?? apiKey,
      Array.isArray(msgs) ? msgs[0] : msgs,
    ])
  )
}
