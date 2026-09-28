/**
 * Cities and communes offered in address forms.
 *
 * They now live in the database and are edited from the admin: a town the shop
 * actually delivers to no longer waits for a deployment to become orderable.
 *
 * The administrative split shipped in the bundle (cities-ci.js) stays as the
 * fallback — used until the call answers, and if it fails. An incomplete list
 * is better than an empty selector, which would push every customer into
 * free-text entry and out of the Abidjan pricing.
 */
import { ref } from 'vue'
import api from '@/api'
import { citiesCI } from '@/data/cities-ci.js'

/** Cities with their communes: [{ id, name, region, communes: [string] }] */
export const localities = ref(citiesCI)

let loading = null

/**
 * Loads the list from the admin. Idempotent: several screens may call it on
 * mount without firing several requests.
 */
export function chargerLocalites() {
  if (loading) return loading

  loading = api.get('/localities')
    .then(({ data }) => {
      const list = data?.data ?? []
      if (list.length) localities.value = list
      return localities.value
    })
    .catch(() => {
      // Keep the fallback, and allow a later retry.
      loading = null
      return localities.value
    })

  return loading
}
