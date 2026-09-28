export default [
  {
    path:      'profil',
    name:      'profile',
    component: () => import('./ProfileView.vue'),
    meta:      { requiresAuth: true },
  },
]
