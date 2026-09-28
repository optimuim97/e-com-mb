import api from '@/api'

export const blogApi = {
  list: (params) => api.get('/blog', { params }),
  get:  (slug)   => api.get(`/blog/${slug}`),
}
