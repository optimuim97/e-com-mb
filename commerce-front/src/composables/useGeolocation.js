/**
 * Géolocalisation inverse → matching zones de livraison CI
 * Utilise Nominatim (OpenStreetMap) — gratuit, sans clé API.
 */
import { localities } from '@/data/localities.js'

/** Supprime accents, tirets et espaces + minuscules — pour comparaison floue */
function norm(s) {
  return (s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[-\s]/g, '').trim()
}

/** Cherche dans une liste avec correspondance exacte puis floue */
function fuzzyFind(list, key, candidate) {
  const n = norm(candidate)
  if (!n) return null
  return (
    list.find(item => norm(key(item)) === n) ??
    list.find(item => {
      const k = norm(key(item))
      return k.includes(n) || n.includes(k)
    }) ??
    null
  )
}

/**
 * Géocode inverse via Nominatim.
 * @returns {Promise<{
 *   inCI: boolean,
 *   city: object|null,       // entrée localities complète
 *   commune: string|null,    // nom de commune matched
 *   road: string,            // adresse rue (peut être vide)
 *   cityName?: string,       // si hors CI
 *   region?: string,         // si hors CI
 * }>}
 */
export async function reverseGeocodeCI(lat, lng) {
  const url = `https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json&accept-language=fr`
  const resp = await fetch(url, {
    headers: { 'User-Agent': 'ShopCheckout/1.0 checkout-localisation' },
  })
  if (!resp.ok) throw new Error(`Nominatim error ${resp.status}`)
  const data = await resp.json()

  const addr = data.address ?? {}
  const cc = (addr.country_code ?? '').toLowerCase()
  const road = [addr.house_number, addr.road ?? addr.pedestrian ?? addr.footway ?? '']
    .filter(Boolean)
    .join(' ')
    .trim()

  if (cc !== 'ci') {
    return {
      inCI:     false,
      city:     null,
      commune:  null,
      road,
      cityName: addr.city ?? addr.town ?? addr.village ?? '',
      region:   addr.state ?? addr.county ?? '',
    }
  }

  // Candidats ville CI (en ordre de précision)
  const cityCandidates = [
    addr.city, addr.county, addr.state_district, addr.municipality, addr.town, addr.village,
  ].filter(Boolean)

  // Candidats commune (quartier / arrondissement)
  const communeCandidates = [
    addr.suburb, addr.city_district, addr.neighbourhood, addr.quarter, addr.district, addr.borough,
  ].filter(Boolean)

  let foundCity    = null
  let foundCommune = null

  // 1. Match ville directement
  for (const cand of cityCandidates) {
    foundCity = fuzzyFind(localities.value, c => c.name, cand)
    if (foundCity) break
  }

  // 2. Match commune dans la ville trouvée
  if (foundCity) {
    for (const cand of communeCandidates) {
      const matched = foundCity.communes.find(c => norm(c) === norm(cand))
        ?? foundCity.communes.find(c => norm(c).includes(norm(cand)) || norm(cand).includes(norm(c)))
      if (matched) { foundCommune = matched; break }
    }
  }

  // 3. Fallback : chercher la commune dans TOUTES les villes
  //    (utile si Nominatim retourne "Cocody" sans "Abidjan")
  if (!foundCity || (!foundCommune && communeCandidates.length)) {
    for (const cand of communeCandidates) {
      const n = norm(cand)
      for (const city of localities.value) {
        const match = city.communes.find(c => norm(c) === n)
          ?? city.communes.find(c => norm(c).includes(n) || n.includes(norm(c)))
        if (match) {
          foundCity    = city
          foundCommune = match
          break
        }
      }
      if (foundCommune) break
    }
  }

  return {
    inCI:    true,
    city:    foundCity,
    commune: foundCommune,
    road,
  }
}

/**
 * Wraps navigator.geolocation.getCurrentPosition en Promise.
 * Rejet avec code 'UNSUPPORTED' | 'PERMISSION_DENIED' | 'POSITION_UNAVAILABLE' | 'TIMEOUT'
 */
export function getCurrentPosition() {
  return new Promise((resolve, reject) => {
    if (!navigator?.geolocation) {
      const err = new Error('Géolocalisation non disponible sur ce navigateur')
      err.code = 'UNSUPPORTED'
      reject(err)
      return
    }
    navigator.geolocation.getCurrentPosition(resolve, (posErr) => {
      const codeMap = { 1: 'PERMISSION_DENIED', 2: 'POSITION_UNAVAILABLE', 3: 'TIMEOUT' }
      const err = new Error(posErr.message)
      err.code = codeMap[posErr.code] ?? 'UNKNOWN'
      reject(err)
    }, {
      enableHighAccuracy: true,
      timeout:     10_000,
      maximumAge:  60_000,
    })
  })
}

/** Traduit un code d'erreur en message utilisateur */
export function geoErrorMessage(code) {
  return {
    UNSUPPORTED:          'La géolocalisation n\'est pas disponible sur ce navigateur.',
    PERMISSION_DENIED:    'Autorisation refusée. Activez la localisation dans vos paramètres.',
    POSITION_UNAVAILABLE: 'Position indisponible. Vérifiez votre connexion GPS.',
    TIMEOUT:              'Délai dépassé. Réessayez dans un endroit dégagé.',
  }[code] ?? 'Erreur de localisation. Réessayez.'
}
