export default [
  {
    path: '',
    name: 'home',
    component: () => import('./HomeView.vue'),
    meta: { stableKey: 'home' },
  },
]
