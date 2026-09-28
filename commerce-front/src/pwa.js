/**
 * Enregistrement du service worker et cycle de mise à jour.
 *
 * Le service worker vit dans public/sw.js : il doit être servi à la racine
 * pour contrôler tout le site (un worker sous /assets/ ne verrait que /assets/).
 */

/** État partagé, consommé par le composant d'installation / mise à jour. */
import { ref } from 'vue'

/** Une nouvelle version est prête et attend le feu vert de la page. */
export const updateReady = ref(false)

/** Événement d'installation capturé, si le navigateur l'a proposé. */
export const installPrompt = ref(null)

/** L'application tourne déjà en mode installé. */
export const isStandalone = () =>
  window.matchMedia?.('(display-mode: standalone)').matches
  || window.navigator.standalone === true

let registration = null

export function registerServiceWorker() {
  if (!('serviceWorker' in navigator)) return

  // En développement, le service worker masquerait le rechargement à chaud
  // de Vite : la page servirait la version en cache au lieu du code modifié.
  if (import.meta.env.DEV) return

  window.addEventListener('load', async () => {
    try {
      registration = await navigator.serviceWorker.register('/sw.js', {
        scope: '/',
        // Le script du worker ne doit jamais venir du cache HTTP : nginx pose
        // « immutable, 1 an » sur les .js, ce qui gèlerait les mises à jour.
        updateViaCache: 'none',
      })

      // Un worker déjà en attente = mise à jour prête depuis une visite passée.
      if (registration.waiting) updateReady.value = true

      registration.addEventListener('updatefound', () => {
        const nouveau = registration.installing
        if (!nouveau) return

        nouveau.addEventListener('statechange', () => {
          // « installed » avec un contrôleur actif = il y a bien une version
          // précédente, donc c'est une mise à jour et non la première visite.
          if (nouveau.state === 'installed' && navigator.serviceWorker.controller) {
            updateReady.value = true
          }
        })
      })

      // Vérifie les mises à jour au retour sur l'onglet : une cliente qui garde
      // la boutique ouverte pendant des jours ne les verrait jamais autrement.
      document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') registration?.update()
      })
    } catch {
      // Un enregistrement raté ne doit rien casser : le site fonctionne
      // exactement comme avant, simplement sans cache hors-ligne.
    }
  })

  // Rechargement unique quand le nouveau worker prend la main.
  let rechargement = false
  navigator.serviceWorker.addEventListener('controllerchange', () => {
    if (rechargement) return
    rechargement = true
    window.location.reload()
  })
}

/** Applique la mise à jour en attente (déclenché par l'utilisateur). */
export function applyUpdate() {
  updateReady.value = false
  registration?.waiting?.postMessage('SKIP_WAITING')
}

/**
 * Capture l'invite d'installation Android/Chrome.
 *
 * Le navigateur ne la propose qu'une fois et au moment de son choix : sans
 * cette interception, elle est perdue et on ne peut plus proposer
 * l'installation depuis notre propre interface.
 */
export function captureInstallPrompt() {
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault()
    installPrompt.value = e
  })

  window.addEventListener('appinstalled', () => {
    installPrompt.value = null
  })
}

/** Déclenche l'invite native. Retourne le choix de l'utilisateur. */
export async function promptInstall() {
  const invite = installPrompt.value
  if (!invite) return null

  invite.prompt()
  const { outcome } = await invite.userChoice
  installPrompt.value = null

  return outcome // 'accepted' | 'dismissed'
}
