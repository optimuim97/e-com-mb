import api from '@/api'

export const cartApi = {
  get:          ()                => api.get('/cart'),
  addItem:      (productId, qty, variantId = null)  => api.post('/cart/items', { product_id: productId, variant_id: variantId, quantity: qty }),
  updateItem:   (itemId, qty)     => api.put(`/cart/items/${itemId}`, { quantity: qty }),
  removeItem:   (itemId)          => api.delete(`/cart/items/${itemId}`),
  applyCoupon:  (code)            => api.post('/cart/coupon', { code }),
  removeCoupon: ()                => api.delete('/cart/coupon'),
  merge:        (items)           => api.post('/cart/merge', { items }),
}
