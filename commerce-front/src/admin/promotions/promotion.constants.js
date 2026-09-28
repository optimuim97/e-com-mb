/**
 * Vocabulaire des promotions, partagé par la liste et le formulaire.
 * Les valeurs doivent rester alignées sur Promotion::TYPES côté serveur.
 */

export const TYPES = [
  { value: 'standard',  label: 'Opération courante', hint: 'Remise commerciale ordinaire.' },
  { value: 'clearance', label: 'Déstockage',         hint: 'Écouler un stock qui dort ou approche de sa date.' },
  { value: 'seasonal',  label: 'Offre de saison',    hint: 'Soldes, fêtes, Ramadan, fête des mères…' },
  { value: 'launch',    label: 'Lancement',          hint: 'Faire découvrir un nouveau produit.' },
  { value: 'loyalty',   label: 'Fidélité',           hint: 'Geste envers les clientes régulières.' },
]

export const SCOPES = [
  { value: 'global',  label: 'Tout le catalogue', hint: 'S’applique à chaque article de la boutique.' },
  { value: 'product', label: 'Articles choisis',  hint: 'Ne concerne que les articles que vous sélectionnez.' },
]

export const DISCOUNT_TYPES = [
  { value: 'percent', label: 'Pourcentage (%)' },
  { value: 'fixed',   label: 'Montant fixe (F CFA)' },
]

export function typeLabel(value) {
  return TYPES.find((t) => t.value === value)?.label ?? value
}
