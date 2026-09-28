import { onBeforeUnmount, ref } from 'vue'

/**
 * Bascule vers WhatsApp après une commande à frais négociés.
 *
 * Hors Abidjan et à l'international, la commande n'est pas finie quand elle
 * est enregistrée : ni les frais ni le moyen de règlement ne sont arrêtés. La
 * cliente doit parler à un agent, et la laisser chercher le bouton, c'est la
 * perdre.
 *
 * Deux précautions, pour deux raisons différentes :
 *
 *  · Toujours un nouvel onglet, jamais l'onglet courant — la page de
 *    confirmation porte le numéro de commande, elle doit rester ouverte
 *    derrière. Une fenêtre ouverte hors d'un clic peut être bloquée sur
 *    mobile ; dans ce cas on ne bascule pas sur place, on le signale et le
 *    bouton du même bloc prend le relais. Un clic est un geste utilisateur :
 *    il n'est jamais bloqué, et ouvre bien dans un autre onglet.
 *
 *  · Un court délai visible, annulable — la cliente doit avoir eu le temps de
 *    lire son numéro de commande. Si la bascule échoue, ou si elle revient en
 *    arrière, c'est tout ce qui lui reste pour nous retrouver.
 */
export function useAgentRedirect(delaiSecondes = 4) {
  const secondes = ref(delaiSecondes)
  const active   = ref(false)
  // Le navigateur a refusé le nouvel onglet : l'écran doit inviter au clic
  // plutôt que laisser croire qu'il ne s'est rien passé.
  const bloque   = ref(false)

  let minuteur = null

  function stop() {
    clearInterval(minuteur)
    minuteur = null
    active.value = false
  }

  /** La cliente préfère rester : on n'insiste pas. */
  function cancel() {
    stop()
    bloque.value = false
  }

  /**
   * Ouvre WhatsApp dans un nouvel onglet, la page de confirmation reste
   * derrière — c'est là que se trouve le numéro de commande.
   *
   * `noopener` est posé après coup et non via les options : passé en
   * paramètre, il fait renvoyer `null` à `window.open()` même quand
   * l'ouverture réussit, et on ne saurait plus distinguer un blocage.
   */
  function ouvrir(lien) {
    const fenetre = window.open(lien, '_blank')

    if (fenetre) {
      fenetre.opener = null
      bloque.value = false
      return
    }

    // Refusé. On ne remplace surtout pas la page courante : on affiche
    // l'invitation à cliquer, et le bouton fait le reste.
    bloque.value = true
  }

  /** Bascule immédiate — le bouton du même bloc. */
  function go(lien) {
    if (!lien) return
    stop()
    ouvrir(lien)
  }

  /**
   * Démarre le compte à rebours.
   * @param {() => string|null} obtenirLien  évalué au dernier moment : le lien
   *        dépend de la commande, qui peut n'être connue qu'après coup.
   */
  function start(obtenirLien) {
    if (active.value) return
    if (!obtenirLien()) return   // pas de numéro WhatsApp configuré : rien à faire

    secondes.value = delaiSecondes
    active.value   = true

    minuteur = setInterval(() => {
      secondes.value -= 1
      if (secondes.value <= 0) {
        const lien = obtenirLien()
        stop()
        if (lien) ouvrir(lien)
      }
    }, 1000)
  }

  onBeforeUnmount(stop)

  return { secondes, active, bloque, start, cancel, go }
}
