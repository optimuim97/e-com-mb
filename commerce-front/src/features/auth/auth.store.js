import { defineStore } from 'pinia';
import { ref, computed , nextTick} from 'vue';

import api from '@/api';
import { useCartStore } from '@/features/cart/cart.store';

export const useAuthStore = defineStore('auth', () => {
  const user    = ref(null);
  const checked = ref(false);

  const isAdmin    = computed(() => user.value?.roles?.includes('admin'));
  // Accès à l'espace admin : admin, manager ou staff (agent). Le backend
  // renvoie `is_staff` ; on garde un fallback sur les rôles par sécurité.
  const isStaff    = computed(() =>
    user.value?.is_staff
    ?? (isAdmin.value
      || user.value?.roles?.includes('staff')
      || user.value?.roles?.includes('manager'))
  );
  const isLoggedIn = computed(() => !!user.value);

  // Permissions granulaires (fournies par /auth/me, login, register).
  const permissions = computed(() => user.value?.permissions ?? []);

  /**
   * Préfixes de permission appartenant à un module éteint par la boutique.
   * Fournis par le serveur : c'est lui qui tient le registre.
   */
  const disabledModules = computed(() => user.value?.disabled_modules ?? []);

  /** La permission relève-t-elle d'un module qu'on a éteint ? */
  function moduleEteint(permission) {
    return disabledModules.value.some(prefixe => permission.startsWith(prefixe));
  }

  /**
   * Teste une permission. On accepte une chaîne ou un tableau (dans ce cas :
   * au moins une permission suffit).
   *
   * Un module éteint fait échouer le test avant tout le reste, l'admin
   * compris. C'est ce qui fait disparaître ses écrans du menu et ferme ses
   * routes : sans cette ligne, éteindre un module laisserait ses entrées en
   * place et l'agent découvrirait la coupure sur un 403.
   */
  function can(permission) {
    if (!permission) return true;

    const liste = Array.isArray(permission) ? permission : [permission];
    const ouvertes = liste.filter(p => !moduleEteint(p));

    if (ouvertes.length === 0) return false;
    if (isAdmin.value) return true;

    const acquises = permissions.value;
    return ouvertes.some(p => acquises.includes(p));
  }

  async function fetchUser() {
    try {
      const { data } = await api.get('/auth/me');
      user.value = data;
    } catch {
      user.value = null;
    } finally {
      checked.value = true;
    }
  }

  async function login(email, password) {
    const { data } = await api.post('/auth/login', { email, password });
    localStorage.setItem('auth_token', data.token);
    user.value    = data.user;
    checked.value = true;
    // Fusionner le panier invité dans le panier utilisateur
    try { await useCartStore().mergeLocalCart() } catch {}
    return data;
  }

  async function register(payload) {
    const { data } = await api.post('/auth/register', payload);
    localStorage.setItem('auth_token', data.token);
    user.value    = data.user;
    checked.value = true;
    // Fusionner le panier invité dans le panier utilisateur
    try { await useCartStore().mergeLocalCart() } catch {}
    return data;
  }

  async function logout() {
    try { await api.post('/auth/logout'); } catch { /* ignore */ }
    localStorage.removeItem('auth_token');
    user.value = null;
  }

  /** Mise à jour nom / téléphone */
  async function updateInfo(payload) {
    const { data } = await api.put('/profile/info', payload);
    user.value = data.user;
    return data;
  }

  /** Configurer email + mdp (compte commande rapide) */
  async function setupAccount(payload) {
    const { data } = await api.put('/profile/setup-account', payload);
    if (data.access_token) {
      localStorage.setItem('auth_token', data.access_token);
    }
    user.value = data.user;
    return data;
  }

  /** Changer le mot de passe (compte normal) */
  async function changePassword(payload) {
    const { data } = await api.put('/profile/password', payload);
    return data;
  }

  /** Établit la session depuis un token (auth sociale / popup OAuth) */
  async function setSession(token, userData = null) {
    localStorage.setItem('auth_token', token);
    if (userData) {
      user.value = userData;
      checked.value = true;
    }
    // Recharge le profil complet (rôles, is_generated_email, etc.)
    await fetchUser();
  }

  const isQuickOrderUser = computed(() => user.value?.is_generated_email === true);

  /**
   * Où envoyer quelqu'un qui vient de se connecter.
   *
   * Le livreur passe avant la boutique : il ouvre l'application pour faire sa
   * tournée, pas pour acheter. Le déposer sur la page d'accueil l'obligerait à
   * connaître une URL qu'on ne lui a jamais donnée.
   *
   * @param {object|null} compte  profil renvoyé par le login (le store n'est
   *                              pas encore à jour au moment de l'appel)
   */
  function landingPath(compte = null) {
    const u     = compte ?? user.value;
    const roles = u?.roles ?? [];

    // Même repli que `isStaff` : la réponse du login ne porte pas toujours
    // `is_staff`, et un agent atterrissait alors sur la boutique.
    const personnel = u?.is_staff
      ?? ['admin', 'manager', 'staff'].some((r) => roles.includes(r));

    if (personnel) return '/admin';
    if (roles.includes('livreur')) return '/livreur';
    return '/';
  }

  // Initialisation : si un token existe, récupérer l'utilisateur ; sinon checked = true immédiatement
  if (localStorage.getItem('auth_token')) {
    fetchUser();
  } else {
    checked.value = true;
  }

  return {
    user, checked, isAdmin, isStaff, isLoggedIn, isQuickOrderUser,
    permissions, can, landingPath, disabledModules,
    fetchUser, login, register, logout, setSession,
    updateInfo, setupAccount, changePassword,
  };
});
