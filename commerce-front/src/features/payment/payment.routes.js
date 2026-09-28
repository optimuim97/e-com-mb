export default [
  {
    path: '/payment/return',
    name: 'payment-return',
    component: () => import('./PaymentReturnView.vue'),
    meta: { title: 'Confirmation de paiement' },
  },
]
