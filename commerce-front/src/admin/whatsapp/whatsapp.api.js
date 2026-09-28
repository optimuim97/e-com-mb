import api from '@/api'

export const adminWhatsAppApi = {
  // ── File des messages entrants ──────────────────────────────────────────────
  list:   (params) => api.get('/admin/whatsapp/messages', { params }),
  get:    (id)     => api.get(`/admin/whatsapp/messages/${id}`),

  // ── Traitement ──────────────────────────────────────────────────────────────
  // parse : pré-remplit la caisse à partir du texte, sans rien écrire.
  parse:  (id)              => api.post(`/admin/whatsapp/messages/${id}/parse`),
  link:   (id, orderId)     => api.post(`/admin/whatsapp/messages/${id}/link`, { order_id: orderId }),
  ignore: (id, reason)      => api.post(`/admin/whatsapp/messages/${id}/ignore`, { reason }),
  reopen: (id)              => api.post(`/admin/whatsapp/messages/${id}/reopen`),

  // ── Contrôle de la double saisie ────────────────────────────────────────────
  reconciliation: (params) => api.get('/admin/whatsapp/reconciliation', { params }),
}
