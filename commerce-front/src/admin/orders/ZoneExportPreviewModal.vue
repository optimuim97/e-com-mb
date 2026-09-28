<template>
  <div class="zep-overlay" @click.self="$emit('close')">
    <div class="zep">
      <!-- En-tête -->
      <header class="zep__head">
        <div>
          <span class="eyebrow">Aperçu avant export</span>
          <h3>{{ label }}</h3>
        </div>
        <button class="zep__close" @click="$emit('close')" aria-label="Fermer">✕</button>
      </header>

      <!--
        Une fois la tournée créée, l'écran ne sert plus qu'à une chose : donner
        le code et la feuille. Laisser la sélection affichée inviterait à
        cliquer une seconde fois, et à créer une tournée en double.
      -->
      <section v-if="roundResult" class="zep__done">
        <span class="eyebrow">Tournée créée</span>
        <p class="zep__done-code">{{ roundResult.code }}</p>
        <p class="zep__done-meta">
          {{ roundResult.orders_count }} livraison(s) · {{ roundResult.items_count }} article(s) à emporter
          <span v-if="roundResult.expected_total > 0">
            · <strong>{{ formatPrice(roundResult.expected_total) }}</strong> à encaisser
          </span>
          <span v-else>· rien à encaisser (tout est déjà réglé)</span>
        </p>
        <p class="zep__done-hint">
          Les commandes sont passées en « expédiée ». Reportez ce code sur la
          feuille : c'est par lui qu'on retrouvera la tournée au retour.
        </p>

        <div v-if="roundRejected.length" class="zep__rejected">
          <p class="zep__rejected-title">
            {{ roundRejected.length }} commande(s) restée(s) à quai
          </p>
          <ul>
            <li v-for="r in roundRejected" :key="r.id">
              <strong>{{ r.number }}</strong> — {{ r.reasons.join(' ') }}
            </li>
          </ul>
        </div>

        <div class="zep__done-actions">
          <button class="btn btn-primary btn-sm" :disabled="busy" @click="downloadRoundSheet">
            {{ busy === 'sheet' ? '…' : 'Télécharger la feuille' }}
          </button>
          <button class="btn btn-outline btn-sm" @click="$emit('close')">Fermer</button>
        </div>
      </section>

      <!-- Options d'ajustement -->
      <div v-if="!roundResult" class="zep__options">
        <div class="zep__row">
          <label class="zep__field">
            <span>Titre du document</span>
            <input v-model="titleEdit" type="text" class="input" maxlength="120" />
          </label>
          <label class="zep__field zep__field--courier">
            <span>Livreur</span>
            <select v-model="courierId" class="input">
              <option :value="null">— à affecter plus tard —</option>
              <option v-for="c in couriers" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </label>
        </div>

        <div class="zep__toggles">
          <button type="button" class="zep__chip" :class="{ active: onlyPaid }" @click="onlyPaid = !onlyPaid">
            {{ onlyPaid ? '✓' : '' }} Payées uniquement
          </button>
          <button type="button" class="zep__chip" :class="{ active: hideOutOfZone }" @click="hideOutOfZone = !hideOutOfZone">
            {{ hideOutOfZone ? '✓' : '' }} Exclure hors zone
          </button>
          <span class="zep__spacer"></span>
          <button type="button" class="zep__link" @click="selectAll">Tout cocher</button>
          <button type="button" class="zep__link" @click="selectNone">Tout décocher</button>
        </div>

        <!-- Blocs récapitulatifs du document (PDF / TXT) -->
        <div class="zep__toggles">
          <span class="zep__toggles-label">À inclure dans le document</span>
          <label class="zep__check">
            <input type="checkbox" v-model="showProductTotals" />
            Total des prix par produit
          </label>
          <label class="zep__check">
            <input type="checkbox" v-model="showItemCounts" />
            Total du nombre d'articles
          </label>
        </div>

        <!--
          Décoché par défaut, à dessein : on ouvre souvent cet écran juste pour
          vérifier une sélection, et un aperçu ne doit rien engager.
        -->
        <div class="zep__toggles">
          <label class="zep__check">
            <input type="checkbox" v-model="marquerApresExport" />
            Marquer les commandes comme extraites après le téléchargement
          </label>
          <span v-if="marquage" class="zep__marked">{{ marquage }}</span>
        </div>
      </div>

      <!-- Tableau de prévisualisation -->
      <div v-if="!roundResult" class="zep__body">
        <table class="zep__table">
          <thead>
            <tr>
              <th class="zep__th-check">
                <input type="checkbox" :checked="allVisibleSelected" @change="toggleAllVisible" />
              </th>
              <th>N°</th>
              <th>Client</th>
              <th>Adresse</th>
              <th>Total</th>
              <th>Paiement</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="o in visibleOrders"
              :key="o.id"
              :class="{ 'zep__row--off': !selected.has(o.id) }"
              @click="toggle(o.id)"
            >
              <td class="zep__th-check">
                <input type="checkbox" :checked="selected.has(o.id)" @click.stop="toggle(o.id)" />
              </td>
              <td class="zep__mono">{{ o.number }}</td>
              <td>
                <div class="zep__client">{{ clientName(o) }}</div>
                <a v-if="o.shipping_address?.phone" :href="`tel:${o.shipping_address.phone}`" class="zep__phone" @click.stop>
                  {{ o.shipping_address.phone }}
                </a>
              </td>
              <td class="zep__addr">
                {{ o.shipping_address?.address_line1 || '—' }}
                <span v-if="o.shipping_unknown" class="zep__tag zep__tag--warn">hors zone</span>
                <!-- Déjà sortie sur une feuille précédente : de quoi la retirer
                     de la sélection plutôt que de l'imprimer deux fois. -->
                <span v-if="o.exported_at" class="zep__tag zep__tag--warn">déjà extraite</span>
              </td>
              <td class="zep__total">{{ formatPrice(o.total) }}</td>
              <td>
                <span class="zep__tag" :class="o.is_paid ? 'zep__tag--ok' : 'zep__tag--pending'">
                  {{ o.is_paid ? 'Payée' : 'À régler' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>

        <p v-if="!visibleOrders.length" class="zep__empty">
          Aucune commande ne correspond aux filtres choisis.
        </p>
      </div>

      <!-- Récapitulatif + actions -->
      <footer v-if="!roundResult" class="zep__foot">
        <!--
          Le refus s'affiche ici, avec le motif commande par commande : l'agent
          doit savoir quoi corriger. Un « échec » sec le laisserait recommencer
          la même sélection.
        -->
        <div v-if="roundError" class="zep__error">
          <p class="zep__error-msg">{{ roundError }}</p>
          <ul v-if="roundRejected.length">
            <li v-for="r in roundRejected" :key="r.id">
              <strong>{{ r.number }}</strong> — {{ r.reasons.join(' ') }}
            </li>
          </ul>
          <button v-if="roundForceable" type="button" class="zep__link" @click="createRound(true)">
            Expédier quand même
          </button>
        </div>

        <div class="zep__summary">
          <strong>{{ selectedOrders.length }}</strong> commande(s) sélectionnée(s)
          <span v-if="showItemCounts && selectedItems">{{ selectedItems }} article(s)</span>
          <span class="zep__summary-total">{{ formatPrice(selectedTotal) }}</span>
          <span v-if="unpaidCount" class="zep__warn">{{ unpaidCount }} non payée(s)</span>
        </div>
        <div class="zep__actions">
          <button class="btn btn-outline btn-sm" @click="$emit('close')">Annuler</button>
          <button v-if="has('csv')" class="btn btn-outline btn-sm" :disabled="!selectedOrders.length" @click="downloadCSV">
            CSV
          </button>
          <button
            v-if="has('xlsx')"
            class="btn btn-outline btn-sm"
            :disabled="!selectedOrders.length || busy"
            @click="downloadXlsx"
          >
            {{ busy === 'xlsx' ? '…' : 'Excel' }}
          </button>
          <button
            class="btn btn-outline btn-sm"
            :disabled="!selectedOrders.length || busy"
            title="Feuille de livraison en texte brut, avec une case à cocher « Livré » par commande"
            @click="downloadTxt"
          >
            {{ busy === 'txt' ? '…' : 'Imprimer (.txt)' }}
          </button>
          <button
            v-if="has('pdf')"
            class="btn btn-outline btn-sm"
            :disabled="!selectedOrders.length || busy"
            @click="downloadPDF"
          >
            {{ busy === 'pdf' ? 'Génération…' : 'PDF' }}
          </button>

          <button
            class="btn btn-outline btn-sm"
            :disabled="!selectedOrders.length || busy"
            title="Copie la feuille dans le presse-papiers, prête à coller dans WhatsApp"
            @click="copierPourWhatsapp"
          >
            {{ copie ? 'Copié !' : (busy === 'copie' ? '…' : 'Copier texte pour WhatsApp') }}
          </button>
          <!--
            L'action principale : elle enregistre la sortie, expédie les
            commandes et rend le code. Le simple téléchargement reste à côté
            pour les cas où l'on veut la feuille sans engager la tournée.
          -->
          <button
            class="btn btn-primary btn-sm"
            :disabled="!selectedOrders.length || busy"
            title="Enregistre la tournée, passe les commandes en « expédiée » et génère la feuille"
            @click="createRound(false)"
          >
            {{ busy === 'round' ? 'Création…' : 'Créer la tournée' }}
          </button>
        </div>
      </footer>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { exportStamp } from '@/admin/utils/exportStamp'
import api from '@/api'

const props = defineProps({
  label:  { type: String, required: true },
  orders: { type: Array,  required: true },
  // Formats proposés : 'xlsx' (Excel, backend) · 'pdf' (feuille de livraison) · 'csv' (client)
  formats: { type: Array, default: () => ['pdf', 'csv'] },
})
const emit = defineEmits(['close', 'created', 'refresh'])

const has = (f) => props.formats.includes(f)

const titleEdit     = ref(props.label)
const onlyPaid      = ref(false)
const hideOutOfZone = ref(false)
const busy          = ref(null)   // null | 'pdf' | 'xlsx' | 'txt' | 'copie' | 'round' | 'sheet'

// ── Tournée ─────────────────────────────────────────────────────────────────
// Le livreur reste facultatif : une tournée peut partir avant qu'on sache qui
// la prend, et l'affectation se corrige ensuite.
const couriers      = ref([])
const courierId     = ref(null)
const roundResult   = ref(null)   // { code, orders_count, expected_total, … }
const roundRejected = ref([])
const roundError    = ref('')

// « Expédier quand même » n'a de sens que sur les refus qui se lèvent : une
// commande annulée le resterait, et le bouton ne ferait que rejouer l'échec.
const roundForceable = computed(() => roundRejected.value.some(r => r.forceable))

onMounted(async () => {
  try {
    const { data } = await api.get('/admin/delivery-rounds/couriers')
    couriers.value = data.data ?? []
  } catch {
    // Liste indisponible : la tournée part sans livreur affecté plutôt que
    // de bloquer la préparation sur un détail rattrapable.
    couriers.value = []
  }
})

// Blocs récapitulatifs du document — cochés par défaut (même défaut côté backend)
const showProductTotals = ref(true)
const showItemCounts    = ref(true)

/* ── Marquage « extraite » ──────────────────────────────────────────────────
 *
 * Décoché par défaut : cet écran sert autant à vérifier une sélection qu'à
 * sortir une feuille, et un aperçu ne doit rien écrire.
 *
 * Le marquage suit le téléchargement au lieu de voyager dans son URL : les
 * exports sont des GET, qu'un navigateur peut rejouer, et le CSV est fabriqué
 * ici même — il n'atteint jamais le serveur. Un appel distinct couvre les cinq
 * formats de la même manière.
 */
const marquerApresExport = ref(false)
const marquage           = ref('')

async function marquerExtraites() {
  if (!marquerApresExport.value || !selectedOrders.value.length) return

  try {
    const { data } = await api.post('/admin/orders/bulk-mark-exported', {
      order_ids: selectedOrders.value.map(o => o.id),
    })
    marquage.value = data.message
    // La liste derrière le modal affiche le repère « extraite » : elle date
    // d'avant l'appel.
    emit('refresh')
  } catch (e) {
    // Le fichier est déjà chez l'agent : le marquage qui échoue ne doit pas
    // ressembler à un export raté.
    marquage.value = "Export téléchargé, mais le marquage a échoué."
    console.error('Marquage « extraite » échoué', e)
  }
}

// Toutes les commandes sont cochées par défaut
const selected = ref(new Set(props.orders.map(o => o.id)))

const visibleOrders = computed(() =>
  props.orders.filter(o => {
    if (onlyPaid.value && !o.is_paid) return false
    if (hideOutOfZone.value && o.shipping_unknown) return false
    return true
  })
)

// L'export ne prend que les commandes à la fois visibles ET cochées
const selectedOrders = computed(() => visibleOrders.value.filter(o => selected.value.has(o.id)))
const selectedTotal  = computed(() => selectedOrders.value.reduce((s, o) => s + (Number(o.total) || 0), 0))
const unpaidCount    = computed(() => selectedOrders.value.filter(o => !o.is_paid).length)
const selectedItems  = computed(() =>
  selectedOrders.value.reduce(
    (s, o) => s + (o.items ?? []).reduce((n, i) => n + (Number(i.quantity) || 0), 0),
    0,
  )
)

const allVisibleSelected = computed(() =>
  visibleOrders.value.length > 0 && visibleOrders.value.every(o => selected.value.has(o.id))
)

function toggle(id) {
  const s = new Set(selected.value)
  s.has(id) ? s.delete(id) : s.add(id)
  selected.value = s
}
function toggleAllVisible() {
  allVisibleSelected.value ? selectNone() : selectAll()
}
function selectAll() {
  const s = new Set(selected.value)
  visibleOrders.value.forEach(o => s.add(o.id))
  selected.value = s
}
function selectNone() {
  const s = new Set(selected.value)
  visibleOrders.value.forEach(o => s.delete(o.id))
  selected.value = s
}

function clientName(o) {
  const a = o.shipping_address || {}
  return o.user?.name || `${a.first_name ?? ''} ${a.last_name ?? ''}`.trim() || '—'
}
function formatPrice(v) {
  // Montant absent (jamais calculé) → tiret. Afficher « 0 XOF » laissait croire
  // à une commande gratuite et masquait le vrai problème.
  if (v === null || v === undefined || v === '') return '—'
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency', currency: 'XOF', maximumFractionDigits: 0,
  }).format(Number(v) || 0)
}
const safeName = (s) => String(s ?? '').replace(/[^a-zA-Z0-9\-_]+/g, '_').slice(0, 50)

/** Télécharge un blob renvoyé par le backend pour les ids sélectionnés. */
async function downloadBlob({ kind, url, mime, ext, prefix = 'commandes' }) {
  if (busy.value || !selectedOrders.value.length) return
  busy.value = kind
  try {
    /*
     * La sélection part dans le corps, jamais dans l'URL.
     *
     * Répétés en `order_ids[]=…`, sept cents identifiants font une ligne de
     * requête de 14 Ko — nginx en accepte 8 et renvoyait un 414 : la feuille
     * de livraison devenait impossible à sortir précisément sur les gros lots,
     * ceux qui en ont le plus besoin.
     */
    const payload = { order_ids: selectedOrders.value.map(o => o.id) }
    if (kind === 'xlsx') payload.format = 'xlsx'

    // Feuilles de livraison (PDF et TXT) : titre + blocs récapitulatifs choisis
    if (kind === 'pdf' || kind === 'txt') {
      payload.label               = titleEdit.value || props.label
      payload.show_product_totals = showProductTotals.value ? 1 : 0
      payload.show_item_counts    = showItemCounts.value    ? 1 : 0
    }

    const res  = await api.post(url, payload, { responseType: 'blob' })
    const href = URL.createObjectURL(new Blob([res.data], { type: mime }))
    const a    = document.createElement('a')
    a.href     = href
    a.download = `${prefix}_${safeName(titleEdit.value)}_${exportStamp()}.${ext}`
    document.body.appendChild(a); a.click(); a.remove()
    URL.revokeObjectURL(href)

    await marquerExtraites()
  } catch (e) {
    console.error(`Export ${kind} échoué`, e)
  } finally {
    busy.value = null
  }
}

// PDF : feuille de livraison groupée par zone
const downloadPDF = () => downloadBlob({
  kind:   'pdf',
  url:    '/admin/orders/export-by-zone',
  mime:   'application/pdf',
  ext:    'pdf',
  prefix: 'livraison',
})

const copie = ref(false)

/**
 * Copie la feuille dans le presse-papiers, prête à coller dans WhatsApp.
 *
 * Le texte est encadré de trois accents graves : c'est ce qui fait passer
 * WhatsApp en police monospace. Sans eux, il affiche le message dans sa police
 * proportionnelle et les colonnes de montants, calées au caractère près, se
 * décalent toutes — la feuille devient illisible à l'arrivée.
 *
 * Le BOM est retiré : invisible dans un fichier, il apparaît comme un
 * caractère parasite en tête de message.
 */
async function copierPourWhatsapp() {
  if (busy.value || !selectedOrders.value.length) return
  busy.value = 'copie'
  try {
    const res = await api.post('/admin/orders/export-by-zone-txt', {
      order_ids:           selectedOrders.value.map(o => o.id),
      label:               titleEdit.value || props.label,
      show_product_totals: showProductTotals.value ? 1 : 0,
      show_item_counts:    showItemCounts.value    ? 1 : 0,
    }, { responseType: 'text' })

    const feuille = String(res.data).replace(/^\uFEFF/, '').trimEnd()
    await ecrireDansPressePapiers('```\n' + feuille + '\n```')

    copie.value = true
    setTimeout(() => { copie.value = false }, 2500)

    // La feuille collée dans WhatsApp est une sortie comme une autre : c'est
    // même celle qui part le plus souvent chez le livreur.
    await marquerExtraites()
  } catch (e) {
    console.error('Copie WhatsApp échouée', e)
  } finally {
    busy.value = null
  }
}

/**
 * `navigator.clipboard` exige un contexte sécurisé : sur une recette servie en
 * HTTP, ou sur d'anciens navigateurs, il faut ce repli.
 */
async function ecrireDansPressePapiers(texte) {
  try {
    await navigator.clipboard.writeText(texte)
    return
  } catch {
    // On tente la méthode historique.
  }

  const zone = document.createElement('textarea')
  zone.value = texte
  zone.style.position = 'fixed'
  zone.style.opacity  = '0'
  document.body.appendChild(zone)
  zone.select()
  try { document.execCommand('copy') } catch { /* rien de plus à tenter */ }
  document.body.removeChild(zone)
}

// TXT : même feuille, en texte brut annotable (case « [ ] LIVRÉ » par commande)
const downloadTxt = () => downloadBlob({
  kind:   'txt',
  url:    '/admin/orders/export-by-zone-txt',
  mime:   'text/plain;charset=utf-8',
  ext:    'txt',
  prefix: 'livraison',
})

// Excel : tableau complet des commandes (même générateur que l'export global)
const downloadXlsx = () => downloadBlob({
  kind: 'xlsx',
  url:  '/admin/orders/export',
  mime: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  ext:  'xlsx',
})

// ── Tournée ─────────────────────────────────────────────────────────────────

/**
 * Enregistre la tournée et expédie les commandes cochées.
 *
 * Les trois effets tombent ensemble côté serveur : le code, les lignes de
 * tournée et le passage en « expédiée ». Le téléchargement de la feuille suit,
 * et peut être rejoué autant de fois qu'il le faut à partir du code.
 *
 * @param force lève les garde-fous d'expédition, après que l'agent a vu ce
 *              qu'ils reprochaient à chaque commande.
 */
async function createRound(force = false) {
  if (busy.value || !selectedOrders.value.length) return
  busy.value    = 'round'
  roundError.value = ''

  try {
    const { data } = await api.post('/admin/delivery-rounds', {
      order_ids:  selectedOrders.value.map(o => o.id),
      label:      titleEdit.value || props.label,
      courier_id: courierId.value,
      force,
    })

    roundResult.value   = data.data
    roundRejected.value = data.rejected ?? []

    // La feuille part sans second clic : c'est elle qu'on emporte, et l'agent
    // n'a aucune raison de créer une tournée pour ne pas l'imprimer.
    await downloadRoundSheet()

    // Les commandes viennent de changer de statut : la liste derrière le modal
    // ne le sait pas encore.
    emit('created', roundResult.value)
  } catch (e) {
    const payload = e.response?.data ?? {}
    roundRejected.value = payload.rejected ?? []
    roundError.value    = payload.message ?? "La tournée n'a pas pu être créée."
  } finally {
    busy.value = null
  }
}

/** Feuille de route de la tournée créée — réimprimable par son code. */
async function downloadRoundSheet() {
  if (!roundResult.value) return
  const precedent = busy.value
  busy.value = 'sheet'
  try {
    const res  = await api.get(
      `/admin/delivery-rounds/${encodeURIComponent(roundResult.value.code)}/sheet`,
      { responseType: 'blob' },
    )
    const href = URL.createObjectURL(new Blob([res.data], { type: 'text/plain;charset=utf-8' }))
    const a    = document.createElement('a')
    a.href     = href
    a.download = `tournee_${roundResult.value.code}.txt`
    document.body.appendChild(a); a.click(); a.remove()
    URL.revokeObjectURL(href)
  } catch (e) {
    console.error('Feuille de tournée indisponible', e)
    roundError.value = "La tournée est bien créée, mais la feuille n'a pas pu être téléchargée. Réessayez depuis son code."
  } finally {
    busy.value = precedent === 'round' ? 'round' : null
  }
}

// ── Export CSV (client-side) ────────────────────────────────────────────────
async function downloadCSV() {
  const esc = (v) => {
    const s = String(v ?? '').replace(/"/g, '""')
    return /[",;\n]/.test(s) ? `"${s}"` : s
  }
  const header = ['N°', 'Client', 'Téléphone', 'Adresse', 'Commune', 'Total', 'Paiement', 'Statut']
  const rows = selectedOrders.value.map(o => {
    const a = o.shipping_address || {}
    return [
      o.number,
      clientName(o),
      a.phone ?? '',
      a.address_line1 ?? '',
      a.commune ?? a.city ?? '',
      o.total ?? '',
      o.is_paid ? 'Payée' : 'À régler',
      o.status ?? '',
    ]
  })
  const csv  = '﻿' + [header, ...rows].map(r => r.map(esc).join(';')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a')
  a.href     = url
  a.download = `tournee_${safeName(titleEdit.value)}_${exportStamp()}.csv`
  document.body.appendChild(a); a.click(); a.remove()
  URL.revokeObjectURL(url)

  await marquerExtraites()
}
</script>

<style scoped>
.zep-overlay {
  position: fixed; inset: 0; z-index: 300;
  background: rgba(0,0,0,0.5);
  display: flex; align-items: center; justify-content: center;
  padding: 16px;
}
.zep {
  background: #fff;
  border-radius: var(--radius-lg);
  width: 100%; max-width: 900px; max-height: 90vh;
  display: flex; flex-direction: column; overflow: hidden;
}

.zep__head {
  display: flex; justify-content: space-between; align-items: flex-start;
  padding: var(--space-4) var(--space-5);
  border-bottom: 1px solid var(--cream-200);
}
.zep__head h3 { font-family: var(--font-display); font-size: 1.125rem; margin: 2px 0 0; }
.zep__close {
  width: 30px; height: 30px; border-radius: 50%;
  border: 1px solid var(--cream-300); background: #fff; cursor: pointer;
}
.zep__close:hover { background: var(--rose-500); color: #fff; border-color: var(--rose-500); }

/* Options */
.zep__options {
  padding: var(--space-3) var(--space-5);
  border-bottom: 1px solid var(--cream-100);
  background: var(--cream-50);
  display: flex; flex-direction: column; gap: var(--space-3);
}
.zep__field { display: flex; flex-direction: column; gap: 4px; }
.zep__field span {
  font-size: 0.6875rem; font-weight: 600; letter-spacing: 0.06em;
  text-transform: uppercase; color: var(--gray-500);
}
.zep__toggles { display: flex; align-items: center; gap: var(--space-2); flex-wrap: wrap; }
.zep__spacer { flex: 1; }
.zep__chip {
  padding: 6px 14px; border-radius: 999px;
  border: 1.5px solid var(--cream-300); background: #fff;
  font-size: 0.75rem; font-weight: 500; color: var(--gray-600); cursor: pointer;
  transition: all var(--transition-fast);
}
.zep__chip:hover { border-color: var(--rose-300); }
.zep__chip.active {
  background: var(--rose-500); border-color: var(--rose-500); color: #fff;
}
.zep__link {
  background: none; border: none; cursor: pointer;
  font-size: 0.75rem; font-weight: 500; color: var(--rose-600);
}
.zep__link:hover { text-decoration: underline; }

.zep__toggles-label {
  font-size: 0.6875rem; font-weight: 600; letter-spacing: 0.06em;
  text-transform: uppercase; color: var(--gray-500);
}
.zep__check {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: 0.75rem; color: var(--gray-700); cursor: pointer;
}
.zep__check input { cursor: pointer; accent-color: var(--rose-500); }
.zep__marked { font-size: 0.75rem; font-weight: 600; color: var(--rose-600); }

/* Tableau */
.zep__body { flex: 1; overflow-y: auto; padding: 0 var(--space-5); }
.zep__table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
.zep__table thead th {
  position: sticky; top: 0; background: #fff; z-index: 1;
  text-align: left; padding: 10px 8px;
  font-size: 0.6875rem; font-weight: 600; letter-spacing: 0.04em;
  text-transform: uppercase; color: var(--gray-500);
  border-bottom: 1px solid var(--cream-200);
}
.zep__table tbody tr {
  border-bottom: 1px solid var(--cream-100); cursor: pointer;
}
.zep__table tbody tr:hover { background: var(--rose-50); }
.zep__table td { padding: 10px 8px; vertical-align: top; }
.zep__row--off { opacity: 0.4; }
.zep__th-check { width: 34px; }
.zep__mono { font-family: ui-monospace, monospace; font-weight: 500; }
.zep__client { font-weight: 500; color: var(--gray-800); }
.zep__phone { font-size: 0.75rem; color: var(--rose-600); text-decoration: none; }
.zep__addr { color: var(--gray-600); max-width: 260px; }
.zep__total { font-weight: 600; color: var(--rose-600); white-space: nowrap; }
.zep__empty { text-align: center; color: var(--gray-400); padding: var(--space-8) 0; }

.zep__tag {
  display: inline-block; font-size: 0.625rem; font-weight: 600;
  padding: 2px 8px; border-radius: 999px; white-space: nowrap;
}
.zep__tag--ok      { background: #dcfce7; color: #15803d; }
.zep__tag--pending { background: #fef3c7; color: #92400e; }
.zep__tag--warn    { background: var(--cream-200); color: var(--gray-600); margin-left: 6px; }

/* Pied */
.zep__foot {
  display: flex; align-items: center; justify-content: space-between;
  gap: var(--space-3); flex-wrap: wrap;
  padding: var(--space-4) var(--space-5);
  border-top: 1px solid var(--cream-200); background: var(--cream-50);
}
.zep__summary { font-size: 0.8125rem; color: var(--gray-600); display: flex; align-items: center; gap: var(--space-3); }
.zep__summary-total { font-weight: 700; color: var(--rose-600); }
.zep__warn { color: #92400e; font-weight: 500; }
.zep__actions { display: flex; gap: var(--space-2); }

/* Ligne d'en-tête des options : titre + livreur côte à côte */
.zep__row { display: flex; gap: var(--space-3); flex-wrap: wrap; }
.zep__row .zep__field { flex: 1 1 240px; }
.zep__field--courier { flex: 0 1 220px; }

/* Tournée créée */
.zep__done {
  flex: 1; overflow-y: auto;
  padding: var(--space-6) var(--space-5);
  text-align: center;
}
.zep__done-code {
  font-family: ui-monospace, monospace;
  font-size: 2rem; font-weight: 700; letter-spacing: 0.08em;
  color: var(--rose-600);
  margin: var(--space-2) 0 var(--space-1);
}
.zep__done-meta { font-size: 0.875rem; color: var(--gray-700); margin: 0 0 var(--space-3); }
.zep__done-hint {
  font-size: 0.8125rem; color: var(--gray-500);
  max-width: 46ch; margin: 0 auto var(--space-4);
}
.zep__done-actions {
  display: flex; gap: var(--space-2); justify-content: center; flex-wrap: wrap;
}

/* Commandes restées à quai */
.zep__rejected {
  text-align: left; max-width: 52ch; margin: 0 auto var(--space-4);
  padding: var(--space-3);
  background: #fef3c7; border-radius: var(--radius-md);
  font-size: 0.8125rem; color: #92400e;
}
.zep__rejected-title { font-weight: 600; margin: 0 0 6px; }
.zep__rejected ul { margin: 0; padding-left: 18px; }
.zep__rejected li { margin-bottom: 2px; }

/* Refus au moment de créer la tournée */
.zep__error {
  flex-basis: 100%;
  padding: var(--space-3);
  background: #fee2e2; border-radius: var(--radius-md);
  font-size: 0.8125rem; color: #991b1b;
}
.zep__error-msg { font-weight: 600; margin: 0 0 6px; }
.zep__error ul { margin: 0 0 6px; padding-left: 18px; }

@media (max-width: 640px) {
  .zep__addr { display: none; }
  .zep__foot { flex-direction: column; align-items: stretch; }
  .zep__actions .btn { flex: 1; }
}
</style>
