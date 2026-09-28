export default [
  { path: 'blog',       name: 'blog',      component: () => import('./BlogView.vue'),     meta: { stableKey: 'blog', module: 'blog' } },
  { path: 'blog/:slug', name: 'blog.post', component: () => import('./BlogPostView.vue'), meta: { module: 'blog' } },
]
