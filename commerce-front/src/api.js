import axios from 'axios';

const api = axios.create({
  baseURL:         import.meta.env.VITE_API_URL ?? '/api',
  withCredentials: true,
  headers: {
    'Accept':           'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    // Content-Type intentionnellement absent :
    // axios le gère automatiquement selon le payload
    //   → 'application/json'      pour les objets JS
    //   → 'multipart/form-data'   pour les FormData (uploads)
  },
});

// Injecter le token Bearer + la langue courante sur chaque requête
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;

  // Langue choisie côté SPA → messages de validation/erreurs traduits.
  // (Accept-Language est un en-tête interdit en XHR, on utilise X-Locale.)
  const locale = localStorage.getItem('locale');
  config.headers['X-Locale'] = ['fr', 'en'].includes(locale) ? locale : 'fr';

  return config;
});

// Gestion globale des erreurs HTTP
api.interceptors.response.use(
  (res) => res,
  (err) => {
    const status = err.response?.status;

    if (status === 401) {
      localStorage.removeItem('auth_token');
      window.location.href = '/login';
    } else if (status >= 500 || !err.response) {
      // Marquer l'erreur pour que les composants ne doublent pas le message
      err._serverError = true;
      // Notifier App.vue via un événement DOM (useToast non disponible hors composant)
      window.dispatchEvent(new CustomEvent('api:server-error', { detail: { status } }));
    }

    return Promise.reject(err);
  },
);

export default api;
