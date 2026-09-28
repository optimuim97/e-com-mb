<template>
  <div class="ship">
    <header class="ship__head">
      <div>
        <h1 class="ship__title">Expéditions</h1>
        <p class="ship__sub">
          Les commandes qui partent hors de {{ settings.homeCity }}. Celles à tarifer d'abord :
          tant que leurs frais ne sont pas saisis, elles ne peuvent pas être expédiées.
        </p>
      </div>
      <div class="ship__head-actions">
        <button class="btn btn-outline btn-sm" :disabled="loading" @click="rafraichir">
          Actualiser
        </button>
        <button
          class="btn btn-primary btn-sm"
          :disabled="loading || !commandes.length || !!extraction"
          title="Extraire les commandes de cet onglet"
          @click="panneauExport = !panneauExport"
        >
          Exporter
        </button>
      </div>
    </header>

    <!--
      Extraction de l'onglet courant. Elle est propre aux expéditions : l'export
      général écarte les commandes non payées hors Abidjan, c'est-à-dire à peu
      près tout ce qui s'affiche ici.
    -->
    <div v-if="panneauExport" class="card ship__export">
      <p class="ship__export-title">
        Extraire « {{ ONGLETS.find(t => t.cle === onglet).label }} »
        <span class="ship__export-count">{{ commandes.length }} commande(s)</span>
      </p>

      <div class="ship__export-opts">
        <label class="ship__check">
          <input type="radio" value="full" v-model="exportColonnes" />
          Fiche complète
        </label>
        <label class="ship__check">
          <input type="radio" value="minimal" v-model="exportColonnes" />
          Nom du client et numéro de commande
        </label>
        <span class="ship__export-sep"></span>
        <label class="ship__check">
          <input type="radio" value="xlsx" v-model="exportFormat" />
          Excel
        </label>
        <label class="ship__check">
          <input type="radio" value="csv" v-model="exportFormat" />
          CSV
        </label>
        <label class="ship__check">
          <input type="radio" value="txt" v-model="exportFormat" />
          Texte (impression / WhatsApp)
        </label>
      </div>

      <p v-if="exportFormat === 'txt'" class="ship__export-note">
        Feuille de livraison sur 48 colonnes : nom, numéro de commande, adresse,
        téléphone et montant. Les colonnes ci-dessus ne s'y appliquent pas.
      </p>

      <div class="ship__export-opts">
        <!-- Même repère que sur l'écran des commandes, et décoché par défaut
             pour la même raison : une extraction de vérification n'engage rien. -->
        <label class="ship__check">
          <input type="checkbox" v-model="marquerApresExport" />
          Marquer les commandes comme extraites après le téléchargement
        </label>
        <span class="ship__export-sep"></span>
        <button class="btn btn-primary btn-sm" :disabled="!!extraction" @click="exporter">
          {{ extraction === 'fichier' ? 'Extraction…' : 'Télécharger' }}
        </button>
        <!--
          La copie sert le cas réel : la feuille part au livreur par WhatsApp,
          pas par pièce jointe. Elle n'a de sens que sur le format texte.
        -->
        <button
          class="btn btn-outline btn-sm"
          :disabled="!!extraction"
          title="Copie la feuille dans le presse-papiers, prête à coller dans WhatsApp"
          @click="copierPourWhatsapp"
        >
          {{ copie ? 'Copié !' : (extraction === 'copie' ? '…' : 'Copier pour WhatsApp') }}
        </button>
        <button class="btn btn-outline btn-sm" @click="panneauExport = false">Fermer</button>
      </div>

      <p v-if="messageExport" class="ship__export-msg">{{ messageExport }}</p>
    </div>

    <!-- Onglets : l'ordre suit l'urgence, pas la géographie. -->
    <nav class="ship__tabs" role="tablist">
      <button
        v-for="t in ONGLETS"
        :key="t.cle"
        type="button"
        role="tab"
        class="ship__tab"
        :class="{ 'ship__tab--active': onglet === t.cle, 'ship__tab--urgent': t.urgent }"
        :aria-selected="onglet === t.cle"
        @click="changerOnglet(t.cle)"
      >
        {{ t.label }}
        <span v-if="compteurs[t.cle] !== null" class="ship__count">{{ compteurs[t.cle] }}</span>
      </button>
    </nav>

    <p class="ship__hint">{{ ONGLETS.find(t => t.cle === onglet).aide }}</p>

    <div class="card table-scroll">
      <table class="admin-table">
        <thead>
          <tr>
            <th>N°</th>
            <th>Date</th>
            <th>Cliente</th>
            <th>Destination</th>
            <th>Statut</th>
            <th>Montant</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <template v-for="o in commandes" :key="o.id">
            <tr :class="{ 'ship__row--traitee': traitees.has(o.id), 'ship__row--ouverte': ouverte === o.id }">
              <td class="admin-table__mono">{{ o.number }}</td>
              <td>{{ formatDate(o.created_at) }}</td>
              <td>
                {{ o.shipping_address?.first_name }} {{ o.shipping_address?.last_name }}
                <span class="ship__phone">{{ o.shipping_address?.phone || '—' }}</span>
              </td>
              <td>
                {{ o.shipping_address?.city || '—' }}
                <span v-if="o.shipping_unknown" class="ship__flag">à tarifer</span>
              </td>
              <td>
                {{ o.status_label ?? STATUTS[o.status] ?? o.status }}
                <span v-if="traitees.has(o.id)" class="ship__done">traitée</span>
              </td>
              <td class="admin-table__total">{{ formatPrice(o.total) }}</td>
              <td>
                <div class="ship__actions">
                  <!--
                    Traitement sur place : l'agent enchaîne les commandes sans
                    quitter son onglet. Ouvrir la fiche complète le renvoyait
                    dans la liste générale, où il perdait à la fois sa place et
                    le filtre qui l'y avait amené.
                  -->
                  <button type="button" class="btn btn-xs btn-primary" @click="basculer(o)">
                    {{ ouverte === o.id ? 'Fermer' : 'Traiter' }}
                  </button>
                  <!--
                    Le geste qui manquait : la commande vient d'être tarifée à la
                    main, et la suivante vers la même commune le sera aussi tant
                    que la zone n'existe pas. Le bouton ouvre la zone
                    pré-remplie avec la destination et le montant qu'on vient de
                    saisir — plus rien à ressaisir, donc plus rien à se tromper.
                  -->
                  <button
                    v-if="zoneCreable(o)"
                    type="button"
                    class="ship__zone"
                    title="Créer la zone de livraison avec ces frais, pour tarifer automatiquement les prochaines commandes"
                    @click="ouvrirZone(o)"
                  >
                    Créer la zone
                  </button>
                  <RouterLink
                    class="ship__detail"
                    :to="{ name: 'admin.order', params: { id: o.id }, query: { retour: 'expeditions', onglet } }"
                  >
                    Fiche →
                  </RouterLink>
                </div>
              </td>
            </tr>
            <tr v-if="ouverte === o.id" class="admin-table__detail-row">
              <td :colspan="7">
                <OrderQuickActionModal
                  :order="o"
                  inline
                  @close="ouverte = null"
                  @updated="apresTraitement"
                />
              </td>
            </tr>
          </template>
        </tbody>
      </table>

      <p v-if="!loading && !commandes.length" class="ship__empty">
        Aucune commande dans cet onglet.
      </p>
      <p v-if="loading" class="ship__empty">Chargement…</p>
    </div>

    <DeliveryZoneFormModal
      v-if="zonePrefill"
      :zone="zonePrefill.zone"
      :origin="zonePrefill.origin"
      @saved="apresCreationZone"
      @close="zonePrefill = null"
    />
  </div>
</template>

<script setup>
import { useSettingsStore } from '@/stores/settings'

const settings = useSettingsStore()
import { ref, reactive, onMounted, nextTick } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { exportStamp } from '@/admin/utils/exportStamp'
import api from '@/api'
import OrderQuickActionModal from './OrderQuickActionModal.vue'
import DeliveryZoneFormModal from '@/admin/delivery-zones/DeliveryZoneFormModal.vue'
import { readPagination } from '@/admin/utils/pagination'
import { useAuthStore } from '@/features/auth/auth.store'

/*
 * Trois onglets, dans l'ordre où ils coûtent de l'argent : une commande à
 * tarifer bloque l'expédition, une commande hors Abidjan attend un prépaiement,
 * une commande internationale se négocie à la main.
 */
const ONGLETS = [
  { cle: 'a_tarifer',     label: 'À tarifer',     urgent: true,
    aide: "Zone non tarifée : saisir les frais de livraison, puis le mode de règlement, et rappeler la cliente pour confirmer le nouveau total." },
  { cle: 'interior',      label: `Hors ${settings.homeCity}`,  urgent: false,
    aide: "Intérieur du pays. Règlement d'avance par Wave ou Orange Money : ne rien expédier avant d'avoir vu le crédit sur le compte." },
  { cle: 'international', label: 'International', urgent: false,
    aide: "Hors Côte d'Ivoire. Aucun paiement en ligne : frais, délais et douane se négocient avec la cliente sur WhatsApp." },
]

const STATUTS = {
  pending: 'En attente', confirmed: 'Confirmée', processing: 'En préparation',
  shipped: 'Expédiée', delivered: 'Livrée', cancelled: 'Annulée', refunded: 'Remboursée',
}

const route = useRoute()
const auth  = useAuthStore()

const onglet = ref('a_tarifer')
const commandes = ref([])
const loading = ref(false)
const compteurs = reactive({ a_tarifer: null, interior: null, international: null })

/** Fiche dépliée sous sa ligne. Une seule à la fois. */
const ouverte = ref(null)

/*
 * Commandes traitées pendant cette session d'écran.
 *
 * Une commande dont on vient de saisir les frais quitte l'onglet « À tarifer »
 * au prochain chargement. La faire disparaître sous le curseur donne
 * l'impression d'avoir perdu quelque chose : on la garde affichée, marquée
 * « traitée », jusqu'à ce que l'agent change d'onglet ou actualise lui-même.
 */
const traitees = ref(new Set())

/** Traduit un onglet en paramètres de l'API des commandes. */
function parametres(cle) {
  return cle === 'a_tarifer' ? { a_tarifer: 1 } : { destination: cle }
}

async function charger() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/orders', {
      params: { ...parametres(onglet.value), per_page: 100 },
    })
    commandes.value = data.data
    // Les compteurs vivent sous `meta` : lus à la racine, ils restaient
    // indéfinis et aucun onglet n'affichait son nombre.
    compteurs[onglet.value] = readPagination(data).total
  } finally {
    loading.value = false
  }
}

/**
 * Compteurs des onglets inactifs.
 *
 * Une requête par onglet, en ne demandant qu'une ligne : seul le total compte,
 * et l'API des commandes le renvoie déjà. Ajouter un point d'entrée dédié pour
 * trois nombres n'en valait pas la peine.
 */
async function chargerCompteurs() {
  await Promise.all(ONGLETS.map(async (t) => {
    if (t.cle === onglet.value) return
    try {
      const { data } = await api.get('/admin/orders', {
        params: { ...parametres(t.cle), per_page: 5 },
      })
      compteurs[t.cle] = readPagination(data).total
    } catch {
      compteurs[t.cle] = null
    }
  }))
}

function changerOnglet(cle) {
  if (cle === onglet.value) return
  onglet.value = cle
  ouverte.value = null
  traitees.value = new Set()   // le marquage ne vaut que pour l'onglet en cours
  charger()
}

function basculer(commande) {
  ouverte.value = ouverte.value === commande.id ? null : commande.id
}

/**
 * Une action vient d'aboutir sur la commande dépliée.
 *
 * La ligne est mise à jour sur place et marquée, plutôt que rechargée : le
 * rechargement la ferait sortir de l'onglet et refermerait la fiche, alors que
 * l'agent a souvent deux ou trois gestes à enchaîner dessus.
 */
function apresTraitement(majOrder, options = {}) {
  const i = commandes.value.findIndex((o) => o.id === majOrder.id)
  if (i >= 0) commandes.value[i] = { ...commandes.value[i], ...majOrder }

  // Une mise à jour partielle — saisir les frais, par exemple — rafraîchit la
  // ligne sans la déclarer traitée : il reste à notifier ou à expédier.
  if (options.partial) return

  traitees.value = new Set(traitees.value).add(majOrder.id)
}

/* ── Extraction de l'onglet courant ──────────────────────────────────────────
 *
 * Endpoint distinct de l'export général : celui-ci écarte les commandes non
 * payées hors Abidjan — la règle qui décide de ce qui peut partir, pas de ce
 * que l'agent a besoin de voir. Appliquée ici, elle vide les trois onglets.
 */
const panneauExport      = ref(false)
const exportColonnes     = ref('full')     // 'full' | 'minimal'
const exportFormat       = ref('xlsx')     // 'xlsx' | 'csv'
const marquerApresExport = ref(false)
const extraction         = ref(null)   // null | 'fichier' | 'copie'
const messageExport      = ref('')
const copie              = ref(false)

const libelleOnglet = () => ONGLETS.find(t => t.cle === onglet.value).label

/** Charge utile commune au téléchargement et à la copie. */
function chargeExport(format) {
  return {
    tab:     onglet.value,
    columns: exportColonnes.value,
    format,
    label:   libelleOnglet(),
  }
}

async function exporter() {
  if (extraction.value || !commandes.value.length) return
  extraction.value    = 'fichier'
  messageExport.value = ''

  const label = libelleOnglet()

  try {
    // POST comme les autres extractions : l'endpoint accepte aussi une
    // sélection d'identifiants, qui ne tiendrait pas dans une URL.
    const res = await api.post('/admin/orders/export-shipments', chargeExport(exportFormat.value), {
      responseType: 'blob',
    })

    const href = URL.createObjectURL(new Blob([res.data]))
    const a    = document.createElement('a')
    a.href     = href
    a.download = `expeditions_${label.replace(/[^a-zA-Z0-9]+/g, '_')}_${exportStamp()}.${exportFormat.value}`
    document.body.appendChild(a); a.click(); a.remove()
    URL.revokeObjectURL(href)

    await marquerExtraites()
  } catch (e) {
    messageExport.value = e.response?.data?.message ?? "L'extraction a échoué."
    console.error('Extraction des expéditions échouée', e)
  } finally {
    extraction.value = null
  }
}

/**
 * Copie la feuille dans le presse-papiers, prête à coller dans WhatsApp.
 *
 * Encadrée de trois accents graves : c'est ce qui fait passer WhatsApp en
 * police à chasse fixe. Sans eux, les colonnes de montants — calées au
 * caractère près sur 48 colonnes — se décalent toutes et la feuille devient
 * illisible à l'arrivée. Le BOM est retiré : invisible dans un fichier, il
 * apparaît comme un caractère parasite en tête de message.
 */
async function copierPourWhatsapp() {
  if (extraction.value || !commandes.value.length) return
  extraction.value    = 'copie'
  messageExport.value = ''

  try {
    const res = await api.post('/admin/orders/export-shipments', chargeExport('txt'), {
      responseType: 'text',
    })

    const feuille = String(res.data).replace(/^\uFEFF/, '').trimEnd()
    await ecrireDansPressePapiers('```\n' + feuille + '\n```')

    copie.value = true
    setTimeout(() => { copie.value = false }, 2500)

    await marquerExtraites()
  } catch (e) {
    messageExport.value = 'La copie a échoué.'
    console.error('Copie WhatsApp échouée', e)
  } finally {
    extraction.value = null
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

/*
 * Le marquage suit le téléchargement plutôt que de voyager dans son URL :
 * l'export est un GET, qu'un navigateur peut rejouer, et une écriture ne doit
 * pas dépendre d'un téléchargement.
 */
async function marquerExtraites() {
  if (!marquerApresExport.value) return

  try {
    const { data } = await api.post('/admin/orders/bulk-mark-exported', {
      order_ids: commandes.value.map(o => o.id),
    })
    messageExport.value = data.message
    await charger()
  } catch (e) {
    // Le fichier est déjà chez l'agent : un marquage raté ne doit pas
    // ressembler à une extraction ratée.
    messageExport.value = 'Extraction téléchargée, mais le marquage a échoué.'
    console.error('Marquage « extraite » échoué', e)
  }
}

/* ── Création de zone depuis une commande tarifée à la main ──────────────────
 *
 * Une commande « à tarifer » signale une destination que nos zones ne couvrent
 * pas. L'agent saisit les frais, la commande part — et la suivante vers la même
 * commune revient au même point. Créer la zone dans la foulée est le seul geste
 * qui arrête la répétition, encore fallait-il qu'il tienne en un clic.
 */
const zonePrefill = ref(null)

/**
 * Proposable dès que les frais sont connus : c'est eux qui font le tarif.
 *
 * Le montant est masqué aux agents sans `finance.view`, et la création de zone
 * relève d'une autre habilitation : proposer le bouton sans l'une ou l'autre
 * mènerait à un refus du serveur, une fois la saisie faite.
 */
function zoneCreable(o) {
  if (!auth.can('delivery_zones.create')) return false
  return Number(o.shipping_cost) > 0 && !!(o.shipping_address?.commune || o.shipping_address?.city)
}

function ouvrirZone(o) {
  const adresse = o.shipping_address ?? {}
  const pays    = (adresse.country || 'CI').toUpperCase()

  // La commune nomme la zone quand elle existe : c'est le niveau auquel le
  // tarif se décide. La ville ne sert que de repli, et d'alias dans tous les cas.
  const nom = (adresse.commune || adresse.city || '').trim()

  const alias = [...new Set(
    [adresse.commune, adresse.city].map(v => (v || '').trim()).filter(Boolean),
  )]

  zonePrefill.value = {
    zone: {
      group:      pays === 'CI' ? 'Intérieur CI' : 'International',
      name:       nom,
      country:    pays,
      price:      Number(o.shipping_cost) || 0,
      price_unit: 'flat',
      cities:     alias,
      active:     true,
    },
    origin: `Pré-rempli depuis la commande ${o.number} — ${alias.join(', ') || 'destination inconnue'}.`,
  }
}

async function apresCreationZone() {
  zonePrefill.value = null
  // La commande cesse d'être « à tarifer » : elle sort de l'onglet au prochain
  // chargement, et les compteurs bougent.
  await rafraichir()
}

async function rafraichir() {
  ouverte.value = null
  traitees.value = new Set()
  await charger()
  await chargerCompteurs()
}

onMounted(async () => {
  // Retour depuis la fiche complète : on rouvre l'onglet quitté et on surligne
  // la commande, pour que l'agent retrouve sa place au lieu de repartir du haut.
  if (route.query.onglet && ONGLETS.some((t) => t.cle === route.query.onglet)) {
    onglet.value = route.query.onglet
  }

  await rafraichir()

  const retour = Number(route.query.commande)
  if (retour) {
    traitees.value = new Set([retour])
    await nextTick()
    document.querySelector('.ship__row--traitee')?.scrollIntoView({ block: 'center', behavior: 'smooth' })
  }
})

function formatDate(val) {
  if (!val) return '—'
  return new Date(val).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatPrice(val) {
  if (val === null || val === undefined) return '—'
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', maximumFractionDigits: 0 }).format(val)
}
</script>

<style scoped>
.ship { display: flex; flex-direction: column; gap: var(--space-4); }

.ship__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-4);
  flex-wrap: wrap;
}
.ship__title { margin: 0; font-size: 1.5rem; font-weight: 700; }
.ship__head-actions { display: flex; gap: var(--space-2); flex-wrap: wrap; }

/* Panneau d'extraction */
.ship__export {
  padding: var(--space-4);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  border: 1.5px solid var(--rose-100);
  background: var(--rose-50);
}
.ship__export-title {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--gray-800);
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex-wrap: wrap;
}
.ship__export-count {
  font-weight: 500;
  font-size: 0.75rem;
  color: var(--gray-500);
}
.ship__export-opts {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  flex-wrap: wrap;
}
.ship__export-sep { flex: 1; min-width: var(--space-2); }
.ship__check {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8125rem;
  color: var(--gray-700);
  cursor: pointer;
}
.ship__check input { cursor: pointer; accent-color: var(--rose-500); }
.ship__export-note {
  margin: 0;
  font-size: 0.75rem;
  color: var(--gray-500);
  line-height: 1.5;
}
.ship__export-msg {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--rose-600);
}
.ship__sub {
  margin: var(--space-1) 0 0;
  font-size: 0.875rem;
  color: var(--gray-500);
  max-width: 62ch;
}

.ship__tabs {
  display: flex;
  gap: var(--space-2);
  flex-wrap: wrap;
  border-bottom: 1px solid var(--cream-200);
  padding-bottom: var(--space-2);
}
.ship__tab {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: 8px 14px;
  border: 1.5px solid var(--cream-300);
  border-radius: var(--radius-full);
  background: #fff;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--gray-600);
  cursor: pointer;
  transition: all var(--transition-fast);
}
.ship__tab:hover { border-color: var(--rose-300); color: var(--rose-600); }
.ship__tab--active {
  border-color: var(--rose-500);
  background: var(--rose-50);
  color: var(--rose-600);
  font-weight: 600;
}
/* L'onglet des commandes bloquées reste repérable même inactif. */
.ship__tab--urgent:not(.ship__tab--active) { border-color: #e8b4ae; color: #a3221b; }

.ship__count {
  min-width: 1.4rem;
  padding: 0 6px;
  border-radius: var(--radius-full);
  background: var(--cream-200);
  font-size: 0.75rem;
  font-weight: 700;
  text-align: center;
}
.ship__tab--active .ship__count { background: var(--rose-200); }

.ship__hint {
  margin: 0;
  font-size: 0.875rem;
  color: var(--gray-600);
  background: var(--cream-50);
  border-radius: var(--radius-md);
  padding: var(--space-3);
}

.ship__phone {
  display: block;
  font-size: 0.75rem;
  color: var(--gray-400);
}

.ship__flag {
  display: inline-block;
  margin-left: 6px;
  padding: 1px 7px;
  border-radius: var(--radius-full);
  background: #fbeeec;
  color: #a3221b;
  font-size: 0.6875rem;
  font-weight: 700;
}

.ship__actions {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  white-space: nowrap;
}
.ship__detail { font-size: 0.8125rem; color: var(--gray-500); }

.ship__zone {
  padding: 3px 10px;
  border: 1px solid var(--rose-200);
  border-radius: var(--radius-full);
  background: var(--rose-50);
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--rose-600);
  cursor: pointer;
  transition: all var(--transition-fast);
}
.ship__zone:hover { background: var(--rose-500); border-color: var(--rose-500); color: #fff; }
.ship__detail:hover { color: var(--rose-600); }

/* Ligne dépliée : rattachée visuellement à sa fiche, sinon les deux flottent. */
.ship__row--ouverte > td { background: var(--cream-50); }

/* Traitée pendant cette session : la ligne reste, mais se distingue. */
.ship__row--traitee > td {
  background: #f2f8f4;
  box-shadow: inset 3px 0 0 #2f6b46;
}

.ship__done {
  display: inline-block;
  margin-left: 6px;
  padding: 1px 7px;
  border-radius: var(--radius-full);
  background: #dcece2;
  color: #2f6b46;
  font-size: 0.6875rem;
  font-weight: 700;
}

.ship__empty {
  padding: var(--space-6);
  text-align: center;
  color: var(--gray-400);
  font-size: 0.9375rem;
}
</style>
