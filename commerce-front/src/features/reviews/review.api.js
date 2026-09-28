import api from '@/api'

export const reviewApi = {
  /**
   * List approved reviews for a product (public).
   * @param {string} slug
   * @param {object} params - page, per_page
   */
  list: (slug, params = {}) =>
    api.get(`/products/${slug}/reviews`, { params }),

  /**
   * Submit a review for a product (auth required).
   * @param {string} slug
   * @param {{ rating: number, title?: string, comment?: string }} payload
   */
  store: (slug, payload) =>
    api.post(`/products/${slug}/reviews`, payload),

  /**
   * Get authenticated user's own reviews.
   */
  mine: (params = {}) =>
    api.get('/reviews/mine', { params }),

  /**
   * Delete own pending review.
   * @param {number} id
   */
  destroy: (id) =>
    api.delete(`/reviews/${id}`),

  // ─── Admin ──────────────────────────────────────────────────────────────

  adminList: (params = {}) =>
    api.get('/admin/reviews', { params }),

  adminUpdate: (id, payload) =>
    api.patch(`/admin/reviews/${id}`, payload),

  adminDelete: (id) =>
    api.delete(`/admin/reviews/${id}`),
}
