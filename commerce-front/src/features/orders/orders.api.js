import api from '@/api'

export const ordersApi = {
  list:         (params)  => api.get('/orders', { params }),
  get:          (number)  => api.get(`/orders/${number}`),
  cancel:       (number)  => api.post(`/orders/${number}/cancel`),
  sendWhatsApp: (number)  => api.post(`/orders/${number}/whatsapp`),
}
