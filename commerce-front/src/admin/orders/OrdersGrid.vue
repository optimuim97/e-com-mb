<template>
  <div class="ogrid">
    <!-- Barre du tableau : disposition des colonnes -->
    <div class="ogrid__bar">
      <span class="ogrid__total">
        <strong>{{ total.toLocaleString('fr-FR') }}</strong> commande{{ total > 1 ? 's' : '' }}
      </span>

      <button type="button" class="ogrid__link" @click="selectionnerPage">Cocher la page</button>

      <span class="ogrid__spacer"></span>

      <div class="ogrid__cols" ref="menuColonnes">
        <button type="button" class="btn btn-sm btn-outline" @click="menuOuvert = !menuOuvert">
          Colonnes ▾
        </button>
        <div v-if="menuOuvert" class="ogrid__menu">
          <label v-for="c in colonnesChoisissables" :key="c.colId" class="ogrid__menu-item">
            <input type="checkbox" :checked="c.visible" @change="basculerColonne(c.colId, $event.target.checked)" />
            {{ c.label }}
          </label>
        </div>
      </div>

      <button
        type="button"
        class="btn btn-sm btn-outline"
        title="Remet les colonnes, les tris et les filtres de colonne dans leur état d'origine"
        @click="reinitialiser"
      >
        Réinitialiser
      </button>
    </div>

    <AgGridVue
      class="ogrid__grid"
      :theme="theme"
      :column-defs="colonnes"
      :default-col-def="defaultColDef"
      :locale-text="AG_GRID_LOCALE_FR"
      row-model-type="infinite"
      :pagination="true"
      :pagination-page-size="20"
      :pagination-page-size-selector="[20, 50, 100]"
      :cache-block-size="20"
      :max-concurrent-datasource-requests="1"
      :infinite-initial-row-count="1"
      :row-selection="rowSelection"
      :selection-column-def="selectionColumnDef"
      :get-row-id="getRowId"
      :get-row-class="getRowClass"
      :context="contexte"
      dom-layout="autoHeight"
      :suppress-cell-focus="true"
      @grid-ready="onGridReady"
      @row-selected="onRowSelected"
      @model-updated="restaurerSelection"
      @filter-changed="onFilterChanged"
      @sort-changed="memoriser"
      @column-moved="memoriser"
      @column-resized="onColumnResized"
      @column-visible="memoriser"
    />
  </div>
</template>

<script setup>
import { ref, computed, h, watch, onMounted, onBeforeUnmount } from 'vue'
import { RouterLink } from 'vue-router'
import { AgGridVue } from 'ag-grid-vue3'
import {
  CellStyleModule,
  ColumnApiModule,
  DateFilterModule,
  InfiniteRowModelModule,
  LocaleModule,
  ModuleRegistry,
  NumberFilterModule,
  PaginationModule,
  PaginationPageNumbersModule,
  RenderApiModule,
  RowApiModule,
  RowSelectionModule,
  RowStyleModule,
  TextFilterModule,
  ValidationModule,
  themeQuartz,
} from 'ag-grid-community'
import { AG_GRID_LOCALE_FR } from '@ag-grid-community/locale'
import api from '@/api'

/*
 * Seulement les modules dont l'écran se sert. L'ensemble complet pesait
 * 1,2 Mo : de quoi faire attendre une gérante qui ouvre ses commandes en 4G.
 * Le module de validation, en développement uniquement, signale dans la
 * console toute fonction utilisée dont le module aurait été oublié ici.
 */
ModuleRegistry.registerModules([
  InfiniteRowModelModule,
  PaginationModule,
  PaginationPageNumbersModule,
  TextFilterModule,
  NumberFilterModule,
  DateFilterModule,
  RowSelectionModule,
  RowStyleModule,
  CellStyleModule,
  LocaleModule,
  ColumnApiModule,
  RowApiModule,
  RenderApiModule,
  ...(import.meta.env.DEV ? [ValidationModule] : []),
])

/*
 * Le tableau des commandes, à la manière d'un tableur.
 *
 * Il ne détient jamais toutes les commandes : il les demande page par page, et
 * chaque tri ou filtre de colonne part au serveur (OrderGridQuery). Charger la
 * liste entière aurait rendu les filtres instantanés, mais chaque ligne coûte
 * une résolution de zone et un calcul d'annulation : à quelques milliers de
 * commandes, l'écran ne s'ouvrirait plus.
 *
 * La disposition — ordre, largeur, colonnes affichées, tris, filtres de colonne —
 * est conservée dans le navigateur : chacune retrouve le tableau qu'elle s'est
 * composé, sans l'imposer à ses collègues.
 */

const props = defineProps({
  /** Filtres posés hors du tableau : statut, paiement, recherche, mises de côté… */
  externalFilters: { type: Object, default: () => ({}) },
  canFinance:      { type: Boolean, default: false },
  /** Commande dont on revient : surlignée pour retrouver sa place. */
  highlightId:     { type: Number, default: null },
})

const emit = defineEmits(['selection-changed', 'total-changed', 'date-filter-changed', 'search-report', 'process', 'aside'])

const CLE_DISPOSITION = 'shop.grid.orders.v1'

let gridApi = null
const total = ref(0)
const menuOuvert = ref(false)
const menuColonnes = ref(null)
const versionColonnes = ref(0)   // force le recalcul du menu après un changement

/** Sélection tenue ici : le tableau oublie les lignes des pages qu'il décharge. */
const selection = new Set()

/** Numéro de chaque commande déjà affichée : la sélection ne garde que des identifiants. */
const numerosVus = new Map()

/** Numéros des commandes cochées, y compris sur les pages déchargées. */
function selectedNumbers() {
  return [...selection].map((id) => numerosVus.get(id)).filter(Boolean)
}

// ── Apparence ────────────────────────────────────────────────────────────────

const theme = themeQuartz.withParams({
  accentColor: 'var(--rose-600)',
  fontFamily: 'inherit',
  fontSize: 13,
  foregroundColor: '#2b2126',
  headerTextColor: '#6b5b62',
  headerFontWeight: 600,
  borderColor: '#eee3e7',
  rowHoverColor: '#fdf1f5',
  selectedRowBackgroundColor: '#fce7ef',
  oddRowBackgroundColor: '#fffcfd',
  wrapperBorderRadius: 12,
  rowHeight: 52,
  headerHeight: 40,
  spacing: 6,
  browserColorScheme: 'light',
})

// ── Libellés ─────────────────────────────────────────────────────────────────

const STATUTS = {
  pending: ['En attente', 'badge badge-warning'],
  confirmed: ['Confirmée', 'badge badge-primary'],
  processing: ['En cours', 'badge badge-primary'],
  shipped: ['Expédiée', 'badge badge-primary'],
  delivered: ['Livrée', 'badge badge-success'],
  cancelled: ['Annulée', 'badge badge-danger'],
  refunded: ['Remboursée', 'badge badge-gray'],
}

const PAIEMENTS = {
  wave: 'Wave', orange_money: 'Orange Money', mtn: 'MTN', cash: 'Espèces',
  card: 'Carte', stripe: 'Carte', cinetpay: 'Carte', cod: 'À la livraison', delivery: 'À la livraison',
}

const DESTINATIONS = { abidjan: 'Abidjan', interior: 'Hors Abidjan', international: 'International' }

const dateHeure = (v) => v
  ? new Date(v).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })
  : ''

const prix = (v) => (v === null || v === undefined)
  ? '—'
  : new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', maximumFractionDigits: 0 }).format(v)

const nomClient = (o) => o?.user?.name
  || [o?.shipping_address?.first_name, o?.shipping_address?.last_name].filter(Boolean).join(' ')
  || '—'

// ── Cellules ─────────────────────────────────────────────────────────────────

const CelluleNumero = {
  props: ['params'],
  setup(p) {
    return () => {
      const o = p.params.data
      if (!o) return null
      return h('div', { class: 'ogrid__stack' }, [
        h('span', { class: 'ogrid__mono' }, o.number),
        o.exported_at
          ? h('span', { class: 'ogrid__tag', title: `Extraite le ${dateHeure(o.exported_at)}` }, 'extraite')
          : (o.tracking_number ? h('span', { class: 'ogrid__sub' }, o.tracking_number) : null),
      ])
    }
  },
}

const CelluleClient = {
  props: ['params'],
  setup(p) {
    return () => {
      const o = p.params.data
      if (!o) return null
      return h('div', { class: 'ogrid__stack' }, [
        h('span', { class: 'ogrid__strong' }, nomClient(o)),
        o.user?.email ? h('span', { class: 'ogrid__sub' }, o.user.email) : null,
      ])
    }
  },
}

const CelluleStatut = {
  props: ['params'],
  setup(p) {
    return () => {
      const o = p.params.data
      if (!o) return null
      const [libelle, classe] = STATUTS[o.status] ?? [o.status, 'badge badge-gray']
      const texte = o.status_label || libelle
      // Enveloppe centrée : posé seul dans la cellule, le badge prenait sa
      // hauteur et tombait en pastille ovale décentrée.
      return h('div', { class: 'ogrid__center' }, [
        h('span', { class: `${classe} ogrid__badge`, title: texte }, texte),
      ])
    }
  },
}

const CellulePaiement = {
  props: ['params'],
  setup(p) {
    return () => {
      const o = p.params.data
      if (!o) return null
      return h('div', { class: 'ogrid__stack' }, [
        h('span', null, PAIEMENTS[o.payment_method] ?? o.payment_method ?? '—'),
        h('span', { class: o.is_paid ? 'ogrid__paid' : 'ogrid__unpaid' }, o.is_paid ? 'payée' : 'non payée'),
      ])
    }
  },
}

const CelluleActions = {
  props: ['params'],
  setup(p) {
    return () => {
      const o = p.params.data
      if (!o) return null
      const ctx = p.params.context
      return h('div', { class: 'ogrid__actions' }, [
        h('button', { type: 'button', class: 'btn btn-xs btn-primary', onClick: () => ctx.traiter(o) }, 'Traiter'),
        h(RouterLink, {
          to: { name: 'admin.order', params: { id: o.id }, query: { retour: 'commandes' } },
          class: 'ogrid__link',
        }, () => 'Détail →'),
        h('button', {
          type: 'button',
          class: 'ogrid__aside',
          title: `Retirer ${o.number} de ma liste de travail`,
          onClick: () => ctx.mettreDeCote(o.id),
        }, 'De côté'),
      ])
    }
  },
}

// ── Colonnes ─────────────────────────────────────────────────────────────────

/*
 * Filtres de date au jour près, bornes incluses : « du 12 au 13 » veut dire
 * les deux journées entières. Une seule condition : deux plages reliées par
 * « ou » ne servent à personne ici, et alourdissent le menu.
 */
const filtreDate = {
  filter: 'agDateColumnFilter',
  filterParams: {
    maxNumConditions: 1,
    inRangeInclusive: true,
    filterOptions: ['equals', 'inRange', 'greaterThan', 'lessThan', 'blank', 'notBlank'],
    buttons: ['reset'],
  },
}

const filtreTexte = {
  filter: 'agTextColumnFilter',
  filterParams: {
    filterOptions: ['contains', 'notContains', 'equals', 'notEqual', 'startsWith', 'endsWith', 'blank', 'notBlank'],
    debounceMs: 400,
    buttons: ['reset'],
  },
}

/*
 * Petit écran : rien n'est épinglé. Sur un téléphone, cases, numéro et
 * actions épinglés dépassaient à eux seuls la largeur disponible, et le cœur
 * du tableau n'avait plus de place pour s'afficher.
 */
const requeteEtroite = window.matchMedia('(max-width: 767px)')
const etroit = ref(requeteEtroite.matches)
const suivreLargeur = (e) => { etroit.value = e.matches }

/*
 * Ordre des colonnes : ce qu'on lit pour décider d'abord — quand, qui, où en
 * est la commande, est-elle payée, combien —, puis ce qui sert à la livrer.
 * Sur un écran de bureau, le statut et le total restent visibles sans faire
 * défiler.
 */
const colonnes = computed(() => [
  {
    colId: 'number', headerName: 'N° commande', field: 'number',
    pinned: etroit.value ? null : 'left', width: 150, cellRenderer: CelluleNumero, ...filtreTexte,
  },
  {
    colId: 'created_at', headerName: 'Date', field: 'created_at', width: 140,
    sort: 'desc', valueFormatter: (p) => dateHeure(p.value), ...filtreDate,
  },
  { colId: 'customer', headerName: 'Client', width: 170, sortable: false, cellRenderer: CelluleClient, ...filtreTexte },
  // Assez large pour « En attente de paiement », le plus long des libellés.
  { colId: 'status', headerName: 'Statut', field: 'status', width: 175, filter: false, cellRenderer: CelluleStatut },
  { colId: 'payment_method', headerName: 'Paiement', field: 'payment_method', width: 130, filter: false, cellRenderer: CellulePaiement },
  ...(props.canFinance ? [{
    colId: 'total', headerName: 'Total', field: 'total', width: 120, type: 'rightAligned',
    valueFormatter: (p) => prix(p.value),
    filter: 'agNumberColumnFilter',
    filterParams: { maxNumConditions: 1, buttons: ['reset'], debounceMs: 400 },
  }] : []),
  {
    colId: 'locality', headerName: 'Commune / ville', width: 140, sortable: false,
    valueGetter: (p) => p.data?.shipping_address?.commune || p.data?.shipping_address?.city || '', ...filtreTexte,
  },
  { colId: 'phone', headerName: 'Téléphone', width: 125, sortable: false, valueGetter: (p) => p.data?.shipping_address?.phone ?? '', ...filtreTexte },
  { colId: 'address', headerName: 'Adresse', width: 220, hide: true, sortable: false, valueGetter: (p) => p.data?.shipping_address?.address_line1 ?? '', ...filtreTexte },
  // Zone résolue à l'affichage : ni triable ni filtrable côté serveur. La
  // commune, elle, l'est — c'est elle qu'on filtre.
  { colId: 'zone', headerName: 'Zone', width: 150, hide: true, sortable: false, filter: false, valueGetter: (p) => p.data?.shipping_zone?.name ?? 'Hors zone' },
  { colId: 'destination', headerName: 'Destination', width: 130, hide: true, sortable: false, filter: false, valueGetter: (p) => DESTINATIONS[p.data?.destination] ?? '' },
  { colId: 'paid_at', headerName: 'Payée le', field: 'paid_at', width: 150, hide: true, valueFormatter: (p) => dateHeure(p.value), ...filtreDate },
  { colId: 'shipped_at', headerName: 'Expédiée le', field: 'shipped_at', width: 150, hide: true, valueFormatter: (p) => dateHeure(p.value), ...filtreDate },
  { colId: 'delivered_at', headerName: 'Livrée le', field: 'delivered_at', width: 150, hide: true, valueFormatter: (p) => dateHeure(p.value), ...filtreDate },
  { colId: 'tracking_number', headerName: 'N° de suivi', field: 'tracking_number', width: 150, hide: true, ...filtreTexte },
  {
    colId: 'actions', headerName: '', pinned: etroit.value ? null : 'right', width: 200,
    sortable: false, filter: false, resizable: false, suppressMovable: true,
    lockVisible: true, cellRenderer: CelluleActions,
  },
])

const defaultColDef = {
  sortable: true,
  resizable: true,
  floatingFilter: true,
  suppressHeaderMenuButton: false,
}

const rowSelection = {
  mode: 'multiRow',
  checkboxes: true,
  // La case d'en-tête ne sait pas « tout cocher » quand les lignes arrivent
  // page par page : on la remplace par « Cocher la page ».
  headerCheckbox: false,
  enableClickSelection: false,
}

/*
 * Cases à cocher épinglées à gauche, avant le numéro : sans cela le tableau
 * les place dans la première colonne libre, entre le numéro et la date, et
 * elles défilent hors de vue avec le reste.
 */
const selectionColumnDef = computed(() => ({
  pinned: etroit.value ? null : 'left', width: 44, resizable: false, suppressHeaderMenuButton: true,
}))

const getRowId = (p) => String(p.data.id)

const getRowClass = (p) => (p.data && p.data.id === props.highlightId ? 'ogrid__row--revue' : '')

/** Ce que les cellules appellent : elles vivent hors de ce composant. */
const contexte = {
  traiter: (order) => emit('process', order),
  mettreDeCote: (id) => emit('aside', id),
}

// ── Données ──────────────────────────────────────────────────────────────────

function parametresExternes() {
  const f = props.externalFilters
  const p = {}
  if (f.status?.length)         p.status = f.status.join(',')
  if (f.payment_method?.length) p.payment_method = f.payment_method.join(',')
  if (f.destination?.length)    p.destination = f.destination.join(',')
  if (f.paid !== '' && f.paid !== null && f.paid !== undefined)         p.paid = f.paid
  if (f.exported !== '' && f.exported !== null && f.exported !== undefined) p.exported = f.exported
  if (f.search)                 p.search = f.search
  if (f.exclude?.length)        p.exclude = f.exclude
  return p
}

const datasource = {
  getRows(params) {
    const parPage = params.endRow - params.startRow

    api.get('/admin/orders', {
      params: {
        page: Math.floor(params.startRow / parPage) + 1,
        per_page: parPage,
        sort: JSON.stringify(params.sortModel ?? []),
        filter: JSON.stringify(params.filterModel ?? {}),
        ...parametresExternes(),
      },
    })
      .then(({ data }) => {
        const n = Number(data.meta?.total ?? data.total ?? 0)
        total.value = n
        emit('total-changed', n)
        ;(data.data ?? []).forEach((o) => numerosVus.set(o.id, o.number))
        emit('search-report', data.search ?? null)
        params.successCallback(data.data ?? [], n)
      })
      .catch((e) => {
        console.error('Commandes indisponibles', e)
        params.failCallback()
      })
  },
}

/** Recharge depuis la première page : les filtres externes ont changé. */
function recharger() {
  gridApi?.setGridOption('datasource', datasource)
}

/**
 * Relit la page affichée, sans revenir à la première : une commande vient
 * d'être traitée, et l'agent doit retrouver sa ligne là où il l'a laissée.
 */
function relirePage() {
  gridApi?.refreshInfiniteCache()
}

// Les filtres externes changent souvent par petites touches (une pastille, une
// lettre tapée) : un seul rechargement pour une rafale.
let minuteurRechargement = null
watch(() => props.externalFilters, () => {
  clearTimeout(minuteurRechargement)
  minuteurRechargement = setTimeout(recharger, 150)
}, { deep: true })

watch(() => props.highlightId, () => gridApi?.redrawRows())

// ── Sélection ────────────────────────────────────────────────────────────────

function onRowSelected(e) {
  // Les resélections faites en restaurant une page ne sont pas des gestes.
  if (!e.data || e.source === 'api') return
  e.node.isSelected() ? selection.add(e.data.id) : selection.delete(e.data.id)
  emit('selection-changed', [...selection])
}

/** À chaque page chargée, recoche ce qui l'était. */
function restaurerSelection() {
  gridApi?.forEachNode((n) => {
    if (!n.data) return
    const doit = selection.has(n.data.id)
    if (n.isSelected() !== doit) n.setSelected(doit, false, 'api')
  })
}

function selectionnerPage() {
  if (!gridApi) return
  const taille = gridApi.paginationGetPageSize()
  const debut = gridApi.paginationGetCurrentPage() * taille
  gridApi.forEachNode((n) => {
    if (n.data && n.rowIndex >= debut && n.rowIndex < debut + taille) selection.add(n.data.id)
  })
  restaurerSelection()
  emit('selection-changed', [...selection])
}

function setSelection(ids) {
  selection.clear()
  ids.forEach((id) => selection.add(id))
  restaurerSelection()
  emit('selection-changed', [...selection])
}

// ── Filtre de date piloté de l'extérieur ─────────────────────────────────────

/**
 * Pose un filtre sur la colonne Date. Les raccourcis « Aujourd'hui », « Hier »…
 * passent par ici : le filtre reste visible et modifiable dans la colonne, au
 * lieu d'un second filtre invisible qui s'ajouterait au premier.
 */
async function setDateFilter(model) {
  if (!gridApi) return
  await gridApi.setColumnFilterModel('created_at', model)
  gridApi.onFilterChanged()
}

/** Retire les filtres de toutes les colonnes, date comprise. */
function clearColumnFilters() {
  gridApi?.setFilterModel(null)
}

function onFilterChanged() {
  emit('date-filter-changed', gridApi?.getColumnFilterModel('created_at') ?? null)
  memoriser()
}

// ── Disposition conservée ────────────────────────────────────────────────────

let minuteurMemoire = null
function memoriser() {
  if (!gridApi) return
  versionColonnes.value++
  clearTimeout(minuteurMemoire)
  minuteurMemoire = setTimeout(() => {
    try {
      localStorage.setItem(CLE_DISPOSITION, JSON.stringify({
        // L'épinglage suit la largeur de l'écran, pas une préférence : le
        // conserver ramènerait sur le téléphone les colonnes épinglées du bureau.
        columns: gridApi.getColumnState().map(({ pinned, ...colonne }) => colonne),
        filters: gridApi.getFilterModel(),
      }))
    } catch {
      // Navigation privée ou quota plein : perdre sa disposition est sans
      // gravité, planter l'écran ne l'est pas.
    }
  }, 300)
}

function onColumnResized(e) {
  if (e.finished) memoriser()
}

function relireDisposition() {
  try {
    const brut = localStorage.getItem(CLE_DISPOSITION)
    return brut ? JSON.parse(brut) : null
  } catch {
    return null
  }
}

function reinitialiser() {
  if (!gridApi) return
  try { localStorage.removeItem(CLE_DISPOSITION) } catch { /* rien */ }
  gridApi.resetColumnState()
  gridApi.setFilterModel(null)
  versionColonnes.value++
}

const colonnesChoisissables = computed(() => {
  // Dépendance explicite : l'état des colonnes vit dans le tableau, pas dans Vue.
  void versionColonnes.value
  const etat = new Map((gridApi?.getColumnState() ?? []).map((c) => [c.colId, c]))
  return colonnes.value
    .filter((c) => c.colId !== 'actions')
    .map((c) => ({ colId: c.colId, label: c.headerName, visible: !(etat.get(c.colId)?.hide ?? c.hide) }))
})

function basculerColonne(colId, visible) {
  gridApi?.setColumnsVisible([colId], visible)
  memoriser()
}

function fermerMenu(e) {
  if (menuOuvert.value && menuColonnes.value && !menuColonnes.value.contains(e.target)) {
    menuOuvert.value = false
  }
}

// ── Cycle de vie ─────────────────────────────────────────────────────────────

function onGridReady(e) {
  gridApi = e.api

  const disposition = relireDisposition()
  if (disposition?.columns) {
    // Seules les colonnes qui existent encore : une colonne retirée du code
    // depuis la dernière visite ne doit pas faire échouer la restauration.
    const connues = new Set(colonnes.value.map((c) => c.colId))
    gridApi.applyColumnState({
      state: disposition.columns
        .filter((c) => connues.has(c.colId))
        .map(({ pinned, ...colonne }) => colonne),
      applyOrder: true,
    })
  }
  if (disposition?.filters) {
    gridApi.setFilterModel(disposition.filters)
  }

  versionColonnes.value++
  gridApi.setGridOption('datasource', datasource)
  emit('date-filter-changed', gridApi.getColumnFilterModel('created_at') ?? null)
}

onMounted(() => {
  document.addEventListener('click', fermerMenu)
  requeteEtroite.addEventListener('change', suivreLargeur)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', fermerMenu)
  requeteEtroite.removeEventListener('change', suivreLargeur)
  clearTimeout(minuteurRechargement)
  clearTimeout(minuteurMemoire)
})

defineExpose({ refresh: recharger, reload: relirePage, setSelection, setDateFilter, clearColumnFilters, selectedNumbers })
</script>

<style scoped>
.ogrid { display: flex; flex-direction: column; gap: var(--space-3); }

.ogrid__bar {
  display: flex; align-items: center; gap: var(--space-3); flex-wrap: wrap;
}
.ogrid__total { font-size: 0.8125rem; color: var(--gray-600); }
.ogrid__spacer { flex: 1; }
.ogrid__link {
  background: none; border: none; padding: 0; cursor: pointer;
  font-size: 0.8125rem; color: var(--rose-600); text-decoration: none;
}
.ogrid__link:hover { text-decoration: underline; }

.ogrid__cols { position: relative; }
.ogrid__menu {
  position: absolute; right: 0; top: calc(100% + 6px); z-index: 30;
  min-width: 220px; max-height: 360px; overflow-y: auto;
  padding: var(--space-2);
  background: #fff; border: 1px solid var(--cream-200);
  border-radius: var(--radius-md); box-shadow: var(--shadow-lg);
}
.ogrid__menu-item {
  display: flex; align-items: center; gap: 8px;
  padding: 6px 8px; border-radius: var(--radius-sm);
  font-size: 0.8125rem; color: var(--gray-700); cursor: pointer;
}
.ogrid__menu-item:hover { background: var(--cream-100); }

.ogrid__grid { width: 100%; }

/* Contenu des cellules : rendu hors du scope du composant par AG Grid. */
:deep(.ogrid__stack) { display: flex; flex-direction: column; justify-content: center; line-height: 1.3; height: 100%; }
:deep(.ogrid__center) { display: flex; align-items: center; height: 100%; min-width: 0; }
:deep(.ogrid__badge) {
  max-width: 100%;
  line-height: 1.5;
  padding: 2px 8px;
  font-size: 0.625rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
:deep(.ogrid__mono) { font-family: ui-monospace, monospace; font-weight: 600; }
:deep(.ogrid__strong) { font-weight: 600; }
:deep(.ogrid__sub) { font-size: 0.6875rem; color: var(--gray-400); overflow: hidden; text-overflow: ellipsis; }
:deep(.ogrid__tag) {
  align-self: flex-start; padding: 0 6px; border-radius: 999px;
  font-size: 0.625rem; font-weight: 600; background: var(--cream-200); color: var(--gray-600);
}
:deep(.ogrid__paid) { font-size: 0.6875rem; color: #15803d; }
:deep(.ogrid__unpaid) { font-size: 0.6875rem; color: #b45309; }
:deep(.ogrid__actions) { display: flex; align-items: center; gap: 10px; height: 100%; }
:deep(.ogrid__aside) {
  background: none; border: none; padding: 0; cursor: pointer;
  font-size: 0.75rem; color: var(--gray-400);
}
:deep(.ogrid__aside:hover) { color: var(--gray-700); text-decoration: underline; }
:deep(.ogrid__row--revue) { background: #fff4d6 !important; }
</style>
