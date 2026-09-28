export default [
  // Page liste — stable key → keep-alive la préserve
  { path: 'products',       name: 'products',     component: () => import('./ProductsView.vue'),  meta: { stableKey: 'products' } },
  // Pages détail — key = chemin complet → remount si slug change (comportement voulu)
  { path: 'products/:slug', name: 'product',      component: () => import('./ProductView.vue') },
  // Liste de toutes les gammes de soins
  { path: 'gammes',         name: 'product-lines', component: () => import('./ProductLinesListView.vue'), meta: { stableKey: 'product-lines' } },
  { path: 'gammes/:slug',   name: 'product-line', component: () => import('./ProductLineView.vue') },
]
