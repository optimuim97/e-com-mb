/**
 * Signal sonore des nouvelles commandes, pour l'administration.
 *
 * Synthétisé plutôt que chargé depuis un fichier : deux notes suffisent, et un
 * MP3 aurait ajouté une requête, un asset à déployer et un risque de 404
 * silencieuse le jour où le nom haché change. L'API Web Audio est présente
 * partout où tourne cette administration.
 *
 * Les navigateurs refusent tout son tant que la personne n'a pas interagi avec
 * la page. On ne peut donc pas garantir le premier bip : l'échec est avalé, il
 * ne doit jamais empêcher la notification visuelle de s'afficher.
 */

const CLE_PREFERENCE = 'admin:notifications:son'

let contexte = null

/** Le son est actif par défaut : une commande manquée coûte plus qu'un bip de trop. */
export function sonActif() {
  return localStorage.getItem(CLE_PREFERENCE) !== 'off'
}

export function basculerSon() {
  const actif = !sonActif()
  localStorage.setItem(CLE_PREFERENCE, actif ? 'on' : 'off')

  // Retour immédiat : sans lui, on ne sait pas si on vient d'activer ou de couper.
  if (actif) jouerSignal()

  return actif
}

/**
 * Deux notes montantes, brèves.
 *
 * Montantes parce qu'une nouvelle commande est une bonne nouvelle, et brèves
 * parce que le signal se répète : une boutique qui reçoit trente commandes
 * dans la matinée ne doit pas devenir insupportable.
 */
export function jouerSignal() {
  if (!sonActif()) return

  try {
    contexte ??= new (window.AudioContext || window.webkitAudioContext)()

    // Le contexte démarre suspendu tant qu'aucune interaction n'a eu lieu.
    if (contexte.state === 'suspended') contexte.resume()

    const debut = contexte.currentTime

    ;[[880, 0], [1174.7, 0.13]].forEach(([frequence, decalage]) => {
      const oscillateur = contexte.createOscillator()
      const volume = contexte.createGain()

      oscillateur.type = 'sine'
      oscillateur.frequency.value = frequence

      // Attaque et extinction douces : un créneau brut produit un clic sec.
      volume.gain.setValueAtTime(0, debut + decalage)
      volume.gain.linearRampToValueAtTime(0.18, debut + decalage + 0.01)
      volume.gain.exponentialRampToValueAtTime(0.001, debut + decalage + 0.22)

      oscillateur.connect(volume).connect(contexte.destination)
      oscillateur.start(debut + decalage)
      oscillateur.stop(debut + decalage + 0.24)
    })
  } catch {
    // Autoplay bloqué, ou pas de sortie audio : la notification visuelle reste.
  }
}
