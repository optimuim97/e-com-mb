/**
 * Destinations hors Abidjan, fusionnées avec nos zones de livraison.
 *
 * Deux sources décrivaient le même pays sans jamais se parler : le découpage
 * administratif (villes et communes de Côte d'Ivoire) alimentait le sélecteur,
 * et les zones de l'administration portaient les tarifs. Une cliente
 * choisissait donc « Bouaké » dans une liste qui ignorait tout du prix, et une
 * localité que nous livrons mais que le découpage ne connaît pas — un
 * lotissement, un quartier tarifé à part — restait introuvable.
 *
 * La fusion résout les deux : chaque destination sait si elle tombe dans une de
 * nos zones et à quel tarif, et une zone déjà présente dans le découpage
 * n'ajoute pas une seconde entrée du même nom.
 */
import { computed } from 'vue'
import { localities } from '@/data/localities.js'
import { zonesLivraison, normalizeLoc } from '@/data/abidjan-communes.js'

/** Zones ivoiriennes hors district d'Abidjan — celui-ci a son propre sélecteur. */
const zonesInterieur = computed(() =>
  zonesLivraison.value.filter(z => z.country === 'CI' && !z.is_abidjan),
)

/**
 * Un tarif n'a de sens affiché que s'il est ferme. Le prix au kilo dépend du
 * poids du colis, qui n'est pas connu à la commande : l'annoncer reviendrait à
 * promettre un montant qu'on ne tiendrait pas.
 */
function tarifFerme(zone) {
  return zone && zone.price_unit !== 'per_kg' ? Number(zone.price) || 0 : null
}

/**
 * Liste des destinations proposées, dans l'ordre alphabétique.
 *
 * Chaque entrée : { city, communes: [{ name, price }], price, fromZone }
 * `price` vaut null quand la destination ne tombe dans aucune de nos zones —
 * les frais partent alors en traitement manuel, et l'interface le dit.
 */
export const destinationsInterieur = computed(() => {
  const villes = new Map()

  for (const ville of localities.value) {
    if (normalizeLoc(ville.name) === 'abidjan') continue
    villes.set(normalizeLoc(ville.name), {
      city:     ville.name,
      communes: ville.communes.map(name => ({ name, price: null })),
      price:    null,
      fromZone: false,
    })
  }

  // Index des communes, pour rattacher une zone au bon endroit sans reparcourir
  // les 57 villes à chaque fois.
  const parCommune = new Map()
  for (const entree of villes.values()) {
    for (const commune of entree.communes) {
      parCommune.set(normalizeLoc(commune.name), { entree, commune })
    }
  }

  for (const zone of zonesInterieur.value) {
    const tarif = tarifFerme(zone)
    // Le nom de la zone et ses alias désignent la même destination : « Bouaké »
    // et « bouake centre » ne doivent pas produire deux lignes.
    const noms = [zone.name, ...(zone.cities ?? [])].filter(Boolean)

    let rattachee = false

    for (const nom of noms) {
      const cle = normalizeLoc(nom)

      const ville = villes.get(cle)
      if (ville) {
        if (ville.price === null) ville.price = tarif
        rattachee = true
        continue
      }

      const trouvee = parCommune.get(cle)
      if (trouvee) {
        if (trouvee.commune.price === null) trouvee.commune.price = tarif
        // La ville hérite du tarif de sa commune tant qu'elle n'en a pas :
        // sinon une ville dont on ne tarife que les quartiers s'afficherait
        // comme non desservie.
        if (trouvee.entree.price === null) trouvee.entree.price = tarif
        rattachee = true
      }
    }

    // Zone que le découpage administratif ignore : c'est une destination réelle,
    // elle rejoint la liste sous son propre nom.
    if (!rattachee) {
      const cle = normalizeLoc(zone.name)
      if (!villes.has(cle)) {
        villes.set(cle, { city: zone.name, communes: [], price: tarif, fromZone: true })
      }
    }
  }

  return [...villes.values()].sort((a, b) => a.city.localeCompare(b.city, 'fr'))
})

/**
 * Filtre la liste sur une saisie libre.
 *
 * La recherche porte sur la ville ET sur ses communes : taper le nom d'un
 * quartier doit remonter sa ville, sans quoi la cliente conclut qu'on ne livre
 * pas chez elle. La commune trouvée est retenue pour être sélectionnée en même
 * temps que la ville — elle change souvent le tarif.
 */
export function filtrerDestinations(saisie) {
  const q = normalizeLoc(saisie)
  if (!q) return destinationsInterieur.value

  return destinationsInterieur.value.flatMap((entree) => {
    if (normalizeLoc(entree.city).includes(q)) return [entree]

    const commune = entree.communes.find(c => normalizeLoc(c.name).includes(q))
    return commune ? [{ ...entree, matchedCommune: commune }] : []
  })
}
