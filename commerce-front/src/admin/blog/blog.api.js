import api from '@/api'

export const blogApi = {
  // ── Posts ─────────────────────────────────────────────────────────────────
  list:   (params)          => api.get('/admin/blog/posts', { params }),
  get:    (id)              => api.get(`/admin/blog/posts/${id}`),
  create: (payload)         => api.post('/admin/blog/posts', payload),
  update: (id, payload)     => api.patch(`/admin/blog/posts/${id}`, payload),
  delete: (id)              => api.delete(`/admin/blog/posts/${id}`),

  // ── Cover image ───────────────────────────────────────────────────────────
  uploadCover: (id, formData) => api.post(
    `/admin/blog/posts/${id}/cover`,
    formData,
    { headers: { 'Content-Type': 'multipart/form-data' } },
  ),

  // ── Catégories ────────────────────────────────────────────────────────────
  listCategories:   (params)  => api.get('/admin/blog/categories', { params }),
  createCategory:   (payload) => api.post('/admin/blog/categories', payload),
  deleteCategory:   (id)      => api.delete(`/admin/blog/categories/${id}`),
}
