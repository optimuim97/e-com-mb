import api from '@/api'

export const productsApi = {
  list:        (params)  => api.get('/products', { params }),
  get:         (slug)    => api.get(`/products/${slug}`),
  getByLine:   (slug, p) => api.get(`/product-lines/${slug}`, { params: p }),
  categories:  ()        => api.get('/categories'),
  reviews:     (slug)    => api.get(`/products/${slug}/reviews`),
  submitReview:(slug, d) => api.post(`/products/${slug}/reviews`, d),
  wishlist:    {
    add:    (id) => api.post('/wishlist', { product_id: id }),
    remove: (id) => api.delete(`/wishlist/${id}`),
  },
}
