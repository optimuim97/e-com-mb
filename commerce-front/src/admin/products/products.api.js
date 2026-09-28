import api from '@/api'

export const adminProductsApi = {
  // ── Liste & détail ───────────────────────────────────────────────────────
  list:   (params) => api.get('/admin/products', { params }),
  get:    (id)     => api.get(`/admin/edit-products/${id}`),

  // ── CRUD ─────────────────────────────────────────────────────────────────
  create: (payload)        => api.post('/admin/products', payload),
  update: (id, payload)    => api.patch(`/admin/products/${id}`, payload),
  delete: (id)             => api.delete(`/admin/products/${id}`),

  // ── Images ───────────────────────────────────────────────────────────────
  addImage:    (id, formData) => api.post(
    `/admin/products/${id}/images`,
    formData,
    { headers: { 'Content-Type': 'multipart/form-data' } },
  ),
  removeImage: (id, imgId)  => api.delete(`/admin/products/${id}/images/${imgId}`),
  setCover:    (id, imgId)  => api.patch(`/admin/products/${id}/images/${imgId}/cover`),
}
