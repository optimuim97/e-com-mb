export default [
  { path: 'orders',         name: 'orders', component: () => import('./OrdersView.vue'), meta: { requiresAuth: true } },
  { path: 'orders/:number', name: 'order',  component: () => import('./OrderView.vue'),  meta: { requiresAuth: true } },
]
