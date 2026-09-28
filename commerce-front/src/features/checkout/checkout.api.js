import api from '@/api'

export const checkoutApi = {
  /** Soumet la commande complète */
  placeOrder: (payload) => api.post('/orders', payload),

  /** Valide un code promo et retourne { discount, type, value } */
  // Les lignes accompagnent le code : le serveur chiffre lui-même la remise.
  // Un coupon ciblé, ou un panier contenant des articles déjà remisés, ne se
  // calcule pas sans savoir ce qui est acheté.
  validateCoupon: (code, items = []) => api.post('/coupons/validate', { code, items }),
}
