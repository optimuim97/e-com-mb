export default [
  {
    path: 'checkout',
    name: 'checkout',
    component: () => import('./CheckoutView.vue'),
    // Pas de requiresAuth : la gate d'auth est gérée dans le composant lui-même
    // (login/inscription/commande rapide)
  },
]
