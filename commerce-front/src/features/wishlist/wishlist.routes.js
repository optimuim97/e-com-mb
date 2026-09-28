export default [
  {
    path: 'wishlist',
    name: 'wishlist',
    component: () => import('./WishlistView.vue'),
    meta: { requiresAuth: true, module: 'wishlist' },
  },
]
