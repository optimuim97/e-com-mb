/**
 * L'espace livreur, hors du back-office et hors de la boutique.
 *
 * Pas de layout : le livreur est sur un téléphone, dans la rue. Ni la barre
 * boutique ni le menu admin ne lui servent, et les deux lui prendraient la
 * moitié de l'écran.
 */
export default [
  {
    path: '/livreur',
    name: 'courier.rounds',
    component: () => import('./CourierRoundsView.vue'),
    meta: { requiresAuth: true, requiresCourier: true },
  },
  {
    // Le code dans l'URL : le livreur garde l'onglet ouvert sur sa tournée et
    // le retrouve après une coupure réseau ou un écran verrouillé.
    path: '/livreur/tournees/:code',
    name: 'courier.round',
    component: () => import('./CourierRoundsView.vue'),
    meta: { requiresAuth: true, requiresCourier: true },
  },
]
