export default [
  { path: '/login',    name: 'login',    component: () => import('./LoginView.vue'),    meta: { guest: true } },
  { path: '/register', name: 'register', component: () => import('./RegisterView.vue'), meta: { guest: true } },
  // Fallback OAuth social (cas sans popup / mobile redirect)
  { path: '/auth/social/callback', name: 'social.callback', component: () => import('./SocialCallbackView.vue') },
]
