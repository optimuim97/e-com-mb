<template>
  <div class="admin-page">
    <!-- Header -->
    <header class="page-header">
      <div>
        <span class="eyebrow">Boutique</span>
        <h1 class="page-header__title">Commandes</h1>
      </div>
      <span class="badge badge-gray">{{ orderStats.total }} commande(s) au total</span>
    </header>

    <!-- ── Vue d'ensemble : cartes stats ── -->
    <div class="orders-stats">
      <button
        class="stat-card stat-card--all"
        :class="{ 'stat-card--active': !filters.status.length }"
        @click="setStatusFilter('')"
      >
        <span class="stat-card__label">Total</span>
        <span class="stat-card__value">{{ orderStats.total }}</span>
        <span class="stat-card__hint">{{ orderStats.today }} aujourd'hui · {{ orderStats.thisWeek }} cette semaine</span>
      </button>

      <button
        class="stat-card stat-card--pending"
        :class="{ 'stat-card--active': estSeulStatut('pending') }"
        @click="setStatusFilter('pending')"
        :title="canFinance ? `${fmt(orderStats.pendingRevenue)} en attente de validation` : ''"
      >
        <span class="stat-card__label">En attente</span>
        <span class="stat-card__value">{{ orderStats.pending }}</span>
        <span class="stat-card__hint" v-if="canFinance">{{ fmt(orderStats.pendingRevenue) }}</span>
        <span class="stat-card__hint" v-else>À valider</span>
      </button>

      <button
        class="stat-card stat-card--processing"
        :class="{ 'stat-card--active': estSeulStatut('processing') }"
        @click="setStatusFilter('processing')"
      >
        <span class="stat-card__label">En traitement</span>
        <span class="stat-card__value">{{ orderStats.processing }}</span>
        <span class="stat-card__hint">À préparer</span>
      </button>

      <button
        class="stat-card stat-card--shipped"
        :class="{ 'stat-card--active': estSeulStatut('shipped') }"
        @click="setStatusFilter('shipped')"
      >
        <span class="stat-card__label">Expédiées</span>
        <span class="stat-card__value">{{ orderStats.shipped }}</span>
        <span class="stat-card__hint">En cours de livraison</span>
      </button>

      <button
        class="stat-card stat-card--delivered"
        :class="{ 'stat-card--active': estSeulStatut('delivered') }"
        @click="setStatusFilter('delivered')"
      >
        <span class="stat-card__label">Livrées</span>
        <span class="stat-card__value">{{ orderStats.delivered }}</span>
        <span class="stat-card__hint">Terminées</span>
      </button>

      <div class="stat-card stat-card--revenue" v-if="canFinance">
        <span class="stat-card__label">Chiffre d'affaires</span>
        <span class="stat-card__value">{{ fmtCompact(orderStats.revenue) }}</span>
        <span class="stat-card__hint">Panier moyen {{ fmt(orderStats.avgBasket) }}</span>
      </div>
    </div>

    <!-- Filters -->
    <div class="card filters-bar">
      <input
        v-if="!groupBy"
        v-model="filters.search"
        type="text"
        class="input filters-bar__search"
        placeholder="N° de commande(s), nom, téléphone, e-mail…"
        title="Plusieurs commandes d'un coup : collez leurs numéros séparés par des virgules (ORD-2026-00042, ORD-2026-00043)"
        @paste="collerRecherche"
      />

      <!--
        Raccourcis de période. Ils posent le filtre de la colonne Date du
        tableau plutôt qu'un filtre à part : la période choisie reste visible
        sous l'en-tête, et se précise là (« du 3 au 12 ») sans qu'un second
        filtre invisible vienne s'y ajouter.

        Axe distinct des statuts : « aujourd'hui » se combine avec « en
        attente », parce que la question du matin est justement « qu'est-ce
        qui est arrivé aujourd'hui et attend encore ».
      -->
      <div v-if="!groupBy" class="orders__presets" role="group" aria-label="Période">
        <button
          v-for="p in RACCOURCIS_DATE"
          :key="p.key"
          type="button"
          class="orders__chip"
          :class="{ 'orders__chip--on': raccourciActif === p.key }"
          @click="appliquerRaccourci(p.key)"
        >
          {{ p.label }}
        </button>
        <span v-if="raccourciActif === 'custom'" class="orders__chip orders__chip--on orders__chip--static">
          Période choisie dans la colonne
        </span>
      </div>

      <!-- Group-by selector -->
      <label class="group-by">
        <span>Grouper par</span>
        <select v-model="groupBy" @change="onGroupByChange" class="group-by__select">
          <option value="">Aucun (liste)</option>
          <option value="zone">Zone</option>
          <option value="commune">Commune</option>
          <option value="address">Adresse exacte</option>
          <option value="fuzzy_address">Adresse similaire</option>
          <option value="status">Statut</option>
        </select>
      </label>

      <!-- Threshold slider (visible only for fuzzy_address) -->
      <div v-if="groupBy === 'fuzzy_address'" class="fuzzy-control">
        <label class="fuzzy-control__label">
          Tolérance
          <span class="fuzzy-control__badge" :class="`fuzzy-control__badge--${fuzzyThresholdLabel.toLowerCase()}`">
            {{ fuzzyThresholdLabel }}
          </span>
        </label>
        <input
          v-model.number="fuzzyThreshold"
          type="range" min="0.50" max="0.95" step="0.05"
          class="fuzzy-control__slider"
        />
        <span class="fuzzy-control__val">{{ Math.round(fuzzyThreshold * 100) }}%</span>
      </div>

      <!-- Export button -->
      <button @click="showExport = !showExport" class="export-toggle-btn" :class="{ active: showExport }">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17v3a1 1 0 001 1h16a1 1 0 001-1v-3"/>
        </svg>
        Exporter
      </button>
    </div>

    <!--
      Filtres rapides : les valeurs d'une liste fermée (statut, paiement,
      destination) se cochent d'un geste ici plutôt que dans un menu de
      colonne. Plusieurs valeurs d'un même groupe se cumulent : « en attente
      ou en cours ».
    -->
    <div v-if="!groupBy" class="card orders__quick">
      <div class="orders__quick-group">
        <span class="orders__quick-label">Statut</span>
        <button
          v-for="s in STATUTS_RAPIDES"
          :key="s.value"
          type="button"
          class="orders__chip"
          :class="{ 'orders__chip--on': filters.status.includes(s.value) }"
          @click="basculerDans('status', s.value)"
        >
          {{ s.label }}
        </button>
      </div>

      <div class="orders__quick-group">
        <span class="orders__quick-label">Paiement</span>
        <button
          type="button"
          class="orders__chip"
          :class="{ 'orders__chip--on': filters.paid === '1' }"
          @click="basculerValeur('paid', '1')"
        >Payées</button>
        <button
          type="button"
          class="orders__chip"
          :class="{ 'orders__chip--on': filters.paid === '0' }"
          @click="basculerValeur('paid', '0')"
        >Non payées</button>
        <span class="orders__quick-sep"></span>
        <button
          v-for="m in MOYENS_RAPIDES"
          :key="m.key"
          type="button"
          class="orders__chip"
          :class="{ 'orders__chip--on': m.values.every(v => filters.payment_method.includes(v)) }"
          @click="basculerMoyen(m)"
        >
          {{ m.label }}
        </button>
      </div>

      <div class="orders__quick-group">
        <span class="orders__quick-label">Destination</span>
        <button
          v-for="d in DESTINATIONS_RAPIDES"
          :key="d.value"
          type="button"
          class="orders__chip"
          :class="{ 'orders__chip--on': filters.destination.includes(d.value) }"
          @click="basculerDans('destination', d.value)"
        >
          {{ d.label }}
        </button>
      </div>

      <div class="orders__quick-group">
        <span class="orders__quick-label">Extraction</span>
        <button
          type="button"
          class="orders__chip"
          :class="{ 'orders__chip--on': filters.exported === '0' }"
          @click="basculerValeur('exported', '0')"
        >Pas encore extraites</button>
        <button
          type="button"
          class="orders__chip"
          :class="{ 'orders__chip--on': filters.exported === '1' }"
          @click="basculerValeur('exported', '1')"
        >Déjà extraites</button>
      </div>

      <!--
        L'archive est une étagère, pas un filtre de plus : on y va, ou on n'y
        est pas. D'où un interrupteur unique plutôt qu'une paire de puces.
      -->
      <div class="orders__quick-group">
        <span class="orders__quick-label">Archive</span>
        <button
          type="button"
          class="orders__chip"
          :class="{ 'orders__chip--on': filters.archived === '1' }"
          @click="basculerValeur('archived', '1')"
        >Commandes archivées</button>
      </div>

      <button v-if="filtresRapidesActifs" type="button" class="orders__quick-reset" @click="effacerFiltresRapides">
        Effacer les filtres
      </button>
    </div>

    <!--
      Extractions par transporteur, toujours visibles : c'est le geste
      quotidien de l'agente, il n'a pas à être caché derrière « Exporter ».
    -->
    <ExtractionPanel @extracted="fetchOrders" />

    <!-- Export panel -->
    <div v-if="showExport" class="card export-panel">
      <h3 class="export-panel__title">
        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17v3a1 1 0 001 1h16a1 1 0 001-1v-3"/>
        </svg>
        Exporter les commandes
      </h3>

      <div class="export-filters">
        <!-- Plage : heure comprise, pour sortir une matinée ou une tournée -->
        <div class="export-field">
          <label class="export-label">Du</label>
          <input v-model="exportFilters.date_from" type="datetime-local" class="input export-input" />
        </div>
        <div class="export-field">
          <label class="export-label">Au</label>
          <input v-model="exportFilters.date_to" type="datetime-local" class="input export-input" />
        </div>

        <!-- Status -->
        <div class="export-field">
          <label class="export-label">Statut</label>
          <AppSelect v-model="exportFilters.status" :options="orderStatusOptions" placeholder="Ventes (hors annulées)" />
        </div>

        <!-- Country -->
        <div class="export-field">
          <label class="export-label">Pays</label>
          <AppSelect v-model="exportFilters.country" :options="exportCountryOptions" placeholder="Tous" />
        </div>
      </div>

      <!--
        Sans statut choisi, l'extraction porte sur les ventes : une commande
        annulée ou remboursée n'en est pas une et fausse les totaux. Choisir
        « Annulées » dans le statut reste possible et l'emporte.
      -->
      <label v-if="!exportFilters.status" class="export-toggle">
        <input v-model="exportFilters.include_cancelled" type="checkbox" />
        <span>Inclure les commandes annulées et remboursées</span>
      </label>

      <!-- Format + Download buttons -->
      <div class="export-actions">
        <p class="export-hint">
          <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01" stroke-linecap="round"/></svg>
          Les filtres actifs (statut, recherche) ne s'appliquent pas à l'export — utilisez les filtres ci-dessus.
        </p>
        <div class="export-btns">
          <button @click="openGlobalExportPreview" :disabled="loadingPreview" class="btn btn-primary btn-sm">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
            {{ loadingPreview ? 'Chargement…' : 'Aperçu et export' }}
          </button>
          <p v-if="previewError" class="export-error">{{ previewError }}</p>
        </div>
      </div>
    </div>

    <!-- Modal itinéraire de tournée -->
    <DeliveryRouteMap
      v-if="routeMap"
      :title="routeMap.title"
      :orders="routeMap.orders"
      :start-address="routeMap.startAddress"
      @close="routeMap = null"
    />

    <!-- Aperçu / ajustements avant export d'un groupe -->
    <ZoneExportPreviewModal
      v-if="exportPreview"
      :label="exportPreview.label"
      :orders="exportPreview.orders"
      :formats="exportPreview.formats"
      @created="apresTournee"
      @refresh="apresTournee"
      @close="exportPreview = null"
    />

    <!-- ── Vue groupée (groupBy ≠ '') ── -->
    <div v-if="groupBy" class="card">
      <div v-if="loading" class="loader-wrap"><div class="loader"></div></div>
      <div v-else-if="!groupedOrders.length" class="empty-state">Aucune commande à grouper.</div>
      <div v-else class="zone-groups">
        <div
          v-for="g in groupedOrders"
          :key="g.key"
          class="zone-group"
          :class="{ 'zone-group--off': g.isOther }"
        >
          <button class="zone-group__head" @click="toggleGroup(g.key)">
            <span class="zone-group__title">
              <strong>{{ groupIcon }} {{ g.label }}</strong>
              <span v-if="g.sublabel" class="zone-group__sub">{{ g.sublabel }}</span>
            </span>
            <span class="zone-group__meta">
              <span class="badge badge-gray">{{ g.orders.length }} cmd</span>
              <span class="zone-group__total">{{ formatPrice(g.total) }}</span>
              <button
                type="button"
                class="commune-group__export"
                @click.stop="openRouteMapForGroup(g)"
                title="Voir l'itinéraire de tournée"
              >
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                Carte
              </button>
              <button
                type="button"
                class="commune-group__export commune-group__export--pdf"
                @click.stop="openExportPreview(g)"
                title="Prévisualiser et ajuster avant export"
              >
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 10v6m0 0l-3-3m3 3l3-3M3 17v3a1 1 0 001 1h16a1 1 0 001-1v-3"/></svg>
                Exporter
              </button>
              <span class="zone-group__chevron" :class="{ open: openGroups.has(g.key) }">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
              </span>
            </span>
          </button>
          <div v-if="openGroups.has(g.key)" class="zone-group__body">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>N°</th>
                  <th>Client</th>
                  <th>Adresse</th>
                  <th>Date</th>
                  <th>Total</th>
                  <th>Statut</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="o in g.orders" :key="o.id">
                  <td class="admin-table__mono">{{ o.number }}</td>
                  <td>
                    <div class="admin-table__client">{{ o.user?.name ?? `${o.shipping_address?.first_name ?? ''} ${o.shipping_address?.last_name ?? ''}` }}</div>
                    <a
                      v-if="o.shipping_address?.phone"
                      :href="`tel:${o.shipping_address.phone}`"
                      class="admin-table__phone"
                    >{{ o.shipping_address.phone }}</a>
                  </td>
                  <td class="admin-table__sub">{{ o.shipping_address?.address_line1 ?? '—' }}</td>
                  <td>{{ formatDate(o.created_at) }}</td>
                  <td class="admin-table__total">{{ formatPrice(o.total) }}</td>
                  <td><span :class="statusBadge(o.status)">{{ statusLabel(o.status) }}</span></td>
                  <td class="admin-table__action">
                    <RouterLink :to="{ name: 'admin.order', params: { id: o.id } }">Détail →</RouterLink>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Vue Liste ── -->
    <div v-else class="card orders__list">
      <!--
        Bandeau permanent tant qu'une commande est masquée : sans lui, une
        mise de côté oubliée deviendrait une commande jamais traitée.
      -->
      <div v-if="decote.size" class="orders__aside-bar">
        <span>
          {{ decote.size }} commande{{ decote.size > 1 ? 's' : '' }} mise{{ decote.size > 1 ? 's' : '' }} de côté,
          visible{{ decote.size > 1 ? 's' : '' }} de vous seul.
        </span>
        <button type="button" class="btn btn-xs btn-outline" @click="toutReafficher">
          Tout réafficher
        </button>
      </div>

      <!-- Recherche de plusieurs numéros : ce qui manque, et pourquoi. -->
      <div v-if="bilanRecherche" class="orders__search-report">
        <span>
          <strong>{{ bilanRecherche.numbers.length }}</strong> numéros recherchés,
          <strong>{{ bilanRecherche.numbers.length - bilanRecherche.not_found.length - bilanRecherche.filtered_out.length }}</strong> affichés.
        </span>
        <span v-if="bilanRecherche.not_found.length" class="orders__search-missing">
          Introuvables : {{ bilanRecherche.not_found.join(', ') }}
        </span>
        <span v-if="bilanRecherche.filtered_out.length" class="orders__search-missing">
          Masqués par les filtres : {{ bilanRecherche.filtered_out.join(', ') }}
          <button type="button" class="orders__bulk-link" @click="afficherMasquees">Retirer les filtres</button>
        </span>
      </div>

      <!--
        Barre d'action de la sélection. Elle n'apparaît qu'une fois une case
        cochée : hors de ce moment-là, elle ne ferait qu'occuper la place
        au-dessus de la liste.
      -->
      <div v-if="cochees.size" class="orders__bulk">
        <span class="orders__bulk-count">
          <strong>{{ cochees.size }}</strong> commande(s) sélectionnée(s)
        </span>
        <button type="button" class="orders__bulk-link" @click="viderSelection">Tout décocher</button>
        <span class="orders__bulk-spacer"></span>

        <!-- Rien à marquer sur l'étagère d'archive : ce qui y est est réglé. -->
        <div v-if="canEdit && filters.archived !== '1'" class="orders__bulk-group">
          <span class="orders__bulk-label">Marquer comme :</span>
          <button
            v-for="a in ACTIONS_VISIBLES"
            :key="a"
            type="button"
            class="btn btn-sm"
            :class="a === 'ship' ? 'btn-primary' : 'btn-outline'"
            :disabled="!!enCours"
            :title="ACTIONS_LOT[a].title"
            @click="lancerLot(a)"
          >
            {{ enCours === a ? ACTIONS_LOT[a].busy : ACTIONS_LOT[a].label }}
          </button>
        </div>

        <button
          v-if="filters.archived !== '1'"
          type="button"
          class="btn btn-sm btn-outline"
          :disabled="!!enCours"
          title="Ouvre l'aperçu d'extraction des commandes cochées : Excel, feuille de livraison, création d'une tournée"
          @click="extraireSelection"
        >
          {{ enCours === 'extract' ? 'Chargement…' : 'Extraire / tournée' }}
        </button>

        <div ref="menuLot" class="orders__bulk-more">
          <button
            type="button"
            class="btn btn-sm btn-outline"
            :disabled="!!enCours"
            :aria-expanded="menuLotOuvert"
            @click="menuLotOuvert = !menuLotOuvert"
          >
            Plus ▾
          </button>
          <div v-if="menuLotOuvert" class="orders__bulk-menu" role="menu">
            <button type="button" role="menuitem" @click="copierNumeros">
              Copier les numéros
            </button>
            <button type="button" role="menuitem" @click="mettreSelectionDeCote">
              Mettre de côté
            </button>
            <button
              v-if="canEdit && filters.archived !== '1'"
              type="button"
              role="menuitem"
              :title="ACTIONS_LOT.archive.title"
              @click="lancerLot('archive')"
            >
              Archiver les commandes…
            </button>
            <button
              v-if="canEdit && filters.archived === '1'"
              type="button"
              role="menuitem"
              :title="ACTIONS_LOT.unarchive.title"
              @click="lancerLot('unarchive')"
            >
              Remettre dans la liste
            </button>
            <button
              v-if="canEdit"
              type="button"
              role="menuitem"
              class="orders__bulk-menu--danger"
              @click="lancerLot('cancel')"
            >
              Annuler les commandes…
            </button>
          </div>
        </div>
      </div>

      <!-- Compte rendu de l'action de masse -->
      <div v-if="bilanLot" class="orders__bulk-result">
        <p class="orders__bulk-msg">{{ bilanLot.message }}</p>

        <!-- Livrées mais pas encore réglées : la suite logique est de les
             passer « payées » une fois l'argent reçu. -->
        <p v-if="bilanLot.toCollect?.length" class="orders__bulk-collect">
          Dont <strong>{{ bilanLot.toCollect.length }}</strong> encore à encaisser.
          <button type="button" class="orders__bulk-link" @click="cocherAEncaisser">
            Les cocher pour les marquer payées
          </button>
        </p>

        <div v-if="bilanLot.rejected.length" class="orders__bulk-rejected">
          <p><strong>{{ bilanLot.rejected.length }}</strong> commande(s) écartée(s) :</p>
          <ul>
            <li v-for="r in bilanLot.rejected" :key="r.id">
              <strong>{{ r.number }}</strong> — {{ r.reasons.join(' ') }}
            </li>
          </ul>
          <!-- Le passage en force n'existe que là où un refus peut se lever :
               rien ne bloque une mise en préparation. -->
          <button
            v-if="bilanLot.forceable && ACTIONS_LOT[bilanLot.action]?.force"
            type="button"
            class="orders__bulk-link"
            @click="lancerLot(bilanLot.action, true)"
          >
            {{ ACTIONS_LOT[bilanLot.action].force }}
          </button>
        </div>
        <button type="button" class="orders__bulk-close" @click="bilanLot = null">✕</button>
      </div>

      <OrdersGrid
        ref="grille"
        :external-filters="filtresGrille"
        :can-finance="canFinance"
        :highlight-id="revue"
        @selection-changed="(ids) => (cochees = new Set(ids))"
        @search-report="(rapport) => (bilanRecherche = rapport)"
        @date-filter-changed="(model) => (filtreDate = model)"
        @process="(order) => (aTraiter = order)"
        @aside="mettreDeCote"
      />
    </div>

    <!--
      Traitement rapide dans une fenêtre : le tableau ne sait pas déplier une
      ligne sous une autre. Téléporté pour échapper au contexte d'empilement
      du tableau, qui la ferait passer sous les colonnes épinglées.
    -->
    <Teleport to="body">
      <OrderQuickActionModal
        v-if="aTraiter"
        :order="aTraiter"
        @close="aTraiter = null"
        @updated="onOrderUpdated"
      />
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import api from '@/api'
import OrderQuickActionModal from './OrderQuickActionModal.vue'
import DeliveryRouteMap from './DeliveryRouteMap.vue'
import ZoneExportPreviewModal from './ZoneExportPreviewModal.vue'
import ExtractionPanel from './ExtractionPanel.vue'
import OrdersGrid from './OrdersGrid.vue'
import { useOrderStatsStore } from '@/admin/stores/orderStats.store'
import { useSettingsStore } from '@/stores/settings'
import { useAuthStore } from '@/features/auth/auth.store'
import { usePersistedFilters } from '@/admin/utils/persistedFilters'

const auth = useAuthStore()
// Finance privée : masque les montants agrégés pour les agents sans finance.view
const canFinance = computed(() => auth.can('finance.view'))
const orderStats = useOrderStatsStore()
const settings   = useSettingsStore()
const route      = useRoute()

// Adresse de départ par défaut pour l'itinéraire (adresse boutique)
const pickupAddress = computed(() => {
  const addr = settings.shopAddress
  const city = settings.shopCity
  return [addr, city].filter(Boolean).join(', ')
})

// Seuil de similarité pour le regroupement par adresse floue (0.50–0.95)
const fuzzyThreshold = ref(0.72)
const fuzzyThresholdLabel = computed(() => {
  if (fuzzyThreshold.value < 0.6)  return 'Permissive'
  if (fuzzyThreshold.value <= 0.8) return 'Équilibrée'
  return 'Stricte'
})

/** Le tableau : rechargé, re-sélectionné et filtré par date depuis cet écran. */
const grille = ref(null)

// ── Filtres rapides ─────────────────────────────────────────────────────────
//
// Conservés d'une visite à l'autre : la gérante revient dix fois par jour sur
// cet écran avec les mêmes critères.

const filters = usePersistedFilters('orders', {
  search:         '',
  status:         [],
  payment_method: [],
  destination:    [],
  paid:           '',
  exported:       '',
  // Vide = la liste de travail. « 1 » = l'archive, et elle seule.
  archived:       '',
})

const STATUTS_RAPIDES = [
  { value: 'pending',    label: 'En attente' },
  { value: 'processing', label: 'En cours' },
  { value: 'shipped',    label: 'Expédiées' },
  { value: 'delivered',  label: 'Livrées' },
  { value: 'cancelled',  label: 'Annulées' },
]

/** Un moyen affiché peut couvrir plusieurs codes : « à la livraison » en a deux. */
const MOYENS_RAPIDES = [
  { key: 'wave',     label: 'Wave',           values: ['wave'] },
  { key: 'om',       label: 'Orange Money',   values: ['orange_money'] },
  { key: 'livraison', label: 'À la livraison', values: ['cod', 'delivery'] },
  { key: 'cash',     label: 'Espèces',        values: ['cash'] },
  { key: 'carte',    label: 'Carte',          values: ['card', 'stripe', 'cinetpay'] },
]

const DESTINATIONS_RAPIDES = [
  { value: 'abidjan',       label: settings.homeCity },
  { value: 'interior',      label: `Hors ${settings.homeCity}` },
  { value: 'international', label: 'International' },
]

function basculerDans(cle, valeur) {
  const liste = filters[cle]
  filters[cle] = liste.includes(valeur) ? liste.filter(v => v !== valeur) : [...liste, valeur]
}

/** Deux choix exclusifs (payées / non payées) : un second clic retire le filtre. */
function basculerValeur(cle, valeur) {
  filters[cle] = filters[cle] === valeur ? '' : valeur
}

function basculerMoyen(moyen) {
  const actif = moyen.values.every(v => filters.payment_method.includes(v))
  filters.payment_method = actif
    ? filters.payment_method.filter(v => !moyen.values.includes(v))
    : [...new Set([...filters.payment_method, ...moyen.values])]
}

const filtresRapidesActifs = computed(() =>
  filters.status.length || filters.payment_method.length || filters.destination.length
  || filters.paid !== '' || filters.exported !== '' || filters.archived !== ''
  || filters.search !== ''
)

function effacerFiltresRapides() {
  filters.$reset()
}

/** Les cartes de statistiques ne filtrent que sur un statut à la fois. */
function estSeulStatut(statut) {
  return filters.status.length === 1 && filters.status[0] === statut
}

/**
 * Plusieurs numéros collés depuis un tableur ou WhatsApp arrivent une ligne
 * par numéro. Un champ texte supprime les retours à la ligne sans rien mettre
 * à la place : « ORD-1⏎ORD-2 » devenait « ORD-1ORD-2 », introuvable. On les
 * remplace donc par des virgules avant de coller.
 */
function collerRecherche(e) {
  const texte = e.clipboardData?.getData('text') ?? ''
  if (!/[\r\n]/.test(texte.trim())) return

  e.preventDefault()
  const propre = texte.split(/[\r\n]+/).map(l => l.trim()).filter(Boolean).join(', ')
  const champ = e.target
  const debut = champ.selectionStart ?? filters.search.length
  const fin   = champ.selectionEnd ?? filters.search.length
  filters.search = filters.search.slice(0, debut) + propre + filters.search.slice(fin)
}

/** Bilan renvoyé par le serveur quand la recherche porte sur plusieurs numéros. */
const bilanRecherche = ref(null)

/** Garde la recherche, retire tout ce qui peut cacher une commande cherchée. */
function afficherMasquees() {
  const recherche = filters.search
  filters.$reset()
  filters.search = recherche
  grille.value?.clearColumnFilters()
  toutReafficher()
}

// La recherche part au serveur après une pause de frappe, pas à chaque lettre.
const rechercheAppliquee = ref(filters.search)
let minuteurRecherche = null
watch(() => filters.search, (valeur) => {
  clearTimeout(minuteurRecherche)
  minuteurRecherche = setTimeout(() => { rechercheAppliquee.value = valeur }, 400)
})

// ── Raccourcis de période ───────────────────────────────────────────────────

const RACCOURCIS_DATE = [
  { key: 'all',       label: 'Toutes les dates' },
  { key: 'today',     label: "Aujourd'hui" },
  { key: 'yesterday', label: 'Hier' },
  { key: 'last7',     label: '7 derniers jours' },
  { key: 'month',     label: 'Ce mois-ci' },
]

/** Modèle du filtre de la colonne Date, tel que le tableau le rapporte. */
const filtreDate = ref(null)

/**
 * Date locale au format du tableau. La boutique est à Abidjan (GMT+0) : pas
 * de décalage entre l'heure du navigateur, celle du serveur et celle de la
 * boutique.
 */
function jour(decalage = 0, base = new Date()) {
  const d = new Date(base)
  d.setDate(d.getDate() + decalage)
  const p = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} 00:00:00`
}

function modeleRaccourci(cle) {
  const aujourdhui = jour()
  switch (cle) {
    case 'today':     return { filterType: 'date', type: 'equals', dateFrom: aujourdhui, dateTo: null }
    case 'yesterday': return { filterType: 'date', type: 'equals', dateFrom: jour(-1), dateTo: null }
    case 'last7':     return { filterType: 'date', type: 'inRange', dateFrom: jour(-6), dateTo: aujourdhui }
    case 'month': {
      const premier = new Date()
      premier.setDate(1)
      return { filterType: 'date', type: 'inRange', dateFrom: jour(0, premier), dateTo: aujourdhui }
    }
    default: return null
  }
}

/** Raccourci qui correspond au filtre posé, ou « custom » si la date a été saisie dans la colonne. */
const raccourciActif = computed(() => {
  const m = filtreDate.value
  if (!m) return 'all'
  const cle = RACCOURCIS_DATE.map(r => r.key).find((k) => {
    const attendu = modeleRaccourci(k)
    return attendu
      && attendu.type === m.type
      && attendu.dateFrom === m.dateFrom
      && (attendu.dateTo ?? null) === (m.dateTo ?? null)
  })
  return cle ?? 'custom'
})

function appliquerRaccourci(cle) {
  grille.value?.setDateFilter(modeleRaccourci(cle))
}

// ── Traitement rapide ───────────────────────────────────────────────────────

const aTraiter = ref(null)

function onOrderUpdated(updated) {
  // La fenêtre reste ouverte : elle doit refléter ce qui vient d'être enregistré.
  if (aTraiter.value?.id === updated.id) aTraiter.value = { ...aTraiter.value, ...updated }
  // La page courante se recharge sans revenir à la première.
  grille.value?.reload()
  orderStats.refresh()
}

/* ── Mise de côté et retour surligné ──────────────────────────────────────────
 *
 * Deux conforts de travail, tous deux volontairement locaux.
 *
 * La mise de côté n'est PAS un état de la commande : elle n'est pas enregistrée
 * en base et reste invisible des collègues. Masquer une commande pour toute
 * l'équipe reviendrait à cacher du travail à faire, et une commande archivée
 * par erreur ne se retrouverait plus. Elle vit donc dans le stockage de session
 * du navigateur : elle disparaît à la fermeture de l'onglet, ce qui garantit
 * qu'aucune commande ne reste masquée d'un jour sur l'autre.
 */
const CLE_DECOTE = 'admin:commandes:de-cote'

const decote = ref(new Set(JSON.parse(sessionStorage.getItem(CLE_DECOTE) ?? '[]')))

/**
 * Commande dont on revient, surlignée : sans ce repère, revenir d'une fiche
 * renvoyait sur une liste de lignes identiques, et il fallait relire les
 * numéros pour retrouver où l'on en était.
 */
const revue = ref(Number(route.query.commande) || null)

/** Ce que le tableau transmet au serveur, en plus de ses propres filtres de colonne. */
const filtresGrille = computed(() => ({
  status:         filters.status,
  payment_method: filters.payment_method,
  destination:    filters.destination,
  paid:           filters.paid,
  exported:       filters.exported,
  archived:       filters.archived,
  search:         rechercheAppliquee.value,
  // Écartées côté serveur, pour que le total et les pages ne comptent pas
  // des lignes qu'on ne voit pas.
  exclude:        [...decote.value],
}))

// ── Sélection multiple & expédition en masse ────────────────────────────────
// Le pendant de la tournée pour les envois qui n'en forment pas une : un dépôt
// chez un transporteur, une série de colis pour l'intérieur. Le backend
// applique exactement les garde-fous de l'expédition à l'unité.
const cochees  = ref(new Set())
const enCours  = ref(null)   // null | clé de ACTIONS_LOT | 'extract'
const bilanLot = ref(null)   // { action, message, rejected, forceable, toCollect }

const canEdit = computed(() => auth.can('orders.edit'))

/**
 * Actions de masse sur la sélection. Chaque action serveur applique les
 * garde-fous du geste unitaire et renvoie, commande par commande, ce qu'elle
 * a écarté et si ce refus peut être levé (`force`).
 */
const ACTIONS_LOT = {
  process: {
    url:   '/admin/orders/bulk-process',
    label: 'Traitée',
    busy:  'Traitement…',
    title: 'Passe les commandes cochées en préparation — le geste du matin sur ce qui est arrivé la veille',
    fail:  'La mise en préparation a échoué.',
  },
  ship: {
    url:   '/admin/orders/bulk-ship',
    label: 'Expédiée',
    busy:  'Envoi…',
    title: "Passe les commandes cochées en « expédiée », avec les mêmes garde-fous qu'à l'unité",
    fail:  "L'expédition en masse a échoué.",
    force: 'Expédier quand même',
  },
  deliver: {
    url:   '/admin/orders/bulk-deliver',
    label: 'Livrée',
    busy:  'Enregistrement…',
    title: "Pour les colis remis hors tournée (transporteur, retrait en boutique). Les commandes d'une tournée se pointent depuis la tournée.",
    fail:  'Le marquage « livrée » a échoué.',
    force: 'Marquer livrées quand même',
  },
  paid: {
    url:   '/admin/orders/bulk-mark-paid',
    label: 'Payée',
    busy:  'Enregistrement…',
    title: "Enregistre le règlement des commandes cochées (relevé Wave, caisse du livreur…). Une expédition en attente passe confirmée, comme à l'unité.",
    fail:  'Le marquage « payée » a échoué.',
    force: 'Marquer payées quand même',
  },
  archive: {
    url:     '/admin/orders/bulk-archive',
    label:   'Archiver',
    busy:    'Archivage…',
    title:   "Range les commandes réglées : elles quittent la liste sans quitter la base, et se retrouvent par « Commandes archivées »",
    fail:    "L'archivage a échoué.",
    confirm: (n) => `Archiver ${n} commande(s) ? Elles quittent la liste de travail ; rien n'est supprimé.`,
  },
  unarchive: {
    url:   '/admin/orders/bulk-unarchive',
    label: 'Désarchiver',
    busy:  'Remise en liste…',
    title: 'Remet les commandes cochées dans la liste de travail',
    fail:  'La remise en liste a échoué.',
  },
  cancel: {
    url:     '/admin/orders/bulk-cancel',
    label:   'Annuler les commandes',
    busy:    'Annulation…',
    fail:    "L'annulation a échoué.",
    force:   'Annuler quand même',
    confirm: (n) => `Annuler ${n} commande(s) ? Les articles seront remis en stock.`,
    confirmForce: (n) => `${n} commande(s) déjà payée(s) : un remboursement sera à faire. Les annuler quand même ?`,
  },
}

/** Dans la barre ; l'annulation, rare et lourde, reste dans le menu « Plus ». */
const ACTIONS_VISIBLES = ['process', 'ship', 'deliver', 'paid']

function viderSelection() {
  grille.value?.setSelection([])
}

/**
 * Applique une action de masse et rend compte commande par commande.
 *
 * Le compte rendu importe autant que l'action : sur trente commandes, un
 * « 27 traitées » sans dire lesquelles des trois autres ont résisté, ni
 * pourquoi, oblige à tout reprendre à la main.
 *
 * En force, seules les commandes dont le refus pouvait être levé repartent :
 * la sélection a pu changer entre-temps, et l'accord de l'agent ne portait
 * que sur les commandes listées.
 */
async function lancerLot(action, force = false) {
  const def = ACTIONS_LOT[action]
  menuLotOuvert.value = false

  const ids = force
    ? (bilanLot.value?.rejected ?? []).filter(r => r.forceable).map(r => r.id)
    : [...cochees.value]
  if (enCours.value || !ids.length) return

  const question = force ? def.confirmForce : def.confirm
  if (question && !window.confirm(question(ids.length))) return

  enCours.value = action

  try {
    const { data } = await api.post(def.url, { order_ids: ids, ...(force ? { force: true } : {}) })

    const refuses = data.rejected ?? []
    bilanLot.value = {
      action,
      message:   data.message,
      rejected:  refuses,
      // « … quand même » n'a de sens que sur les refus qui se lèvent : une
      // commande annulée le resterait.
      forceable: refuses.some(r => r.forceable),
      toCollect: data.to_collect ?? [],
    }

    // Ne restent cochées que les commandes refusées : l'agent voit sa
    // sélection se réduire à ce qui demande encore une décision.
    grille.value?.setSelection(refuses.map(r => r.id))
    grille.value?.reload()
    orderStats.refresh()   // les compteurs de l'écran et du menu ont bougé
  } catch (e) {
    bilanLot.value = {
      action,
      message:   e.response?.data?.message ?? def.fail,
      rejected:  [],
      forceable: false,
      toCollect: [],
    }
  } finally {
    enCours.value = null
  }
}

/** Enchaîne « livrée » puis « payée » sans rechercher les commandes une à une. */
function cocherAEncaisser() {
  grille.value?.setSelection(bilanLot.value.toCollect.map(o => o.id))
  bilanLot.value = null
}

/**
 * Ouvre l'aperçu d'extraction sur la sélection : Excel, feuille de livraison
 * et création de tournée, sans repasser par les filtres d'export.
 *
 * L'aperçu applique les règles de l'export réel — ni annulées, ni commandes
 * hors Abidjan impayées —, d'où l'annonce de ce qu'il a laissé de côté.
 */
async function extraireSelection() {
  if (enCours.value || !cochees.value.size) return
  menuLotOuvert.value = false
  enCours.value = 'extract'

  try {
    const { data } = await api.post('/admin/orders/export-preview', { order_ids: [...cochees.value] })
    const orders = data.data ?? []
    const ecartees = cochees.value.size - orders.length

    bilanLot.value = ecartees > 0
      ? {
          action:    'extract',
          message:   orders.length
            ? `${ecartees} commande(s) cochée(s) laissée(s) hors de l'extraction : annulées, ou hors ${settings.homeCity} et pas encore payées.`
            : `Aucune commande cochée n'est extractible : annulées, ou hors ${settings.homeCity} et pas encore payées.`,
          rejected:  [],
          forceable: false,
          toCollect: [],
        }
      : null

    if (!orders.length) return

    exportPreview.value = {
      label:   `Sélection du ${new Date().toLocaleString('fr-FR', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
      })}`,
      orders,
      formats: ['xlsx', 'pdf', 'csv'],
    }
  } catch (e) {
    bilanLot.value = {
      action:    'extract',
      message:   e.response?.data?.message ?? "Impossible de charger l'aperçu d'extraction.",
      rejected:  [],
      forceable: false,
      toCollect: [],
    }
  } finally {
    enCours.value = null
  }
}

/** Numéros séparés par des retours à la ligne : prêts à coller dans WhatsApp ou un tableur. */
async function copierNumeros() {
  menuLotOuvert.value = false
  const numeros = grille.value?.selectedNumbers?.() ?? []
  const texte = numeros.join('\n')

  try {
    await navigator.clipboard.writeText(texte)
    bilanLot.value = {
      action: 'copy',
      message: numeros.length === cochees.value.size
        ? `${numeros.length} numéro(s) copié(s).`
        : `${numeros.length} numéro(s) copié(s) sur ${cochees.value.size} : les autres sont sur des pages non chargées.`,
      rejected: [], forceable: false, toCollect: [],
    }
  } catch {
    bilanLot.value = {
      action: 'copy', message: `Copie refusée par le navigateur. Numéros : ${numeros.join(', ')}`,
      rejected: [], forceable: false, toCollect: [],
    }
  }
}

function mettreSelectionDeCote() {
  menuLotOuvert.value = false
  const ids = [...cochees.value]
  decote.value = new Set([...decote.value, ...ids])
  if (aTraiter.value && ids.includes(aTraiter.value.id)) aTraiter.value = null
  grille.value?.setSelection([])
  persisterDecote()
}

// Menu « Plus » : se referme au clic ailleurs.
const menuLot = ref(null)
const menuLotOuvert = ref(false)

function fermerMenuLot(e) {
  if (menuLotOuvert.value && menuLot.value && !menuLot.value.contains(e.target)) {
    menuLotOuvert.value = false
  }
}
onMounted(() => document.addEventListener('click', fermerMenuLot))
onBeforeUnmount(() => document.removeEventListener('click', fermerMenuLot))

function persisterDecote() {
  sessionStorage.setItem(CLE_DECOTE, JSON.stringify([...decote.value]))
}

function mettreDeCote(id) {
  decote.value = new Set(decote.value).add(id)
  if (aTraiter.value?.id === id) aTraiter.value = null
  // Une commande qu'on ne voit plus ne doit pas partir dans une action de masse.
  if (cochees.value.has(id)) grille.value?.setSelection([...cochees.value].filter(c => c !== id))
  persisterDecote()
}

function toutReafficher() {
  decote.value = new Set()
  persisterDecote()
}

const loading = ref(true)

// ── Mode d'affichage : liste paginée ou groupée selon une dimension ──
// groupBy: '' = liste paginée | 'zone' | 'commune' | 'address' | 'status'
const groupBy    = ref('')             // défaut : aucun groupement (liste paginée)
const allOrders  = ref([])             // commandes brutes pour le regroupement client-side
const openGroups = ref(new Set())

const groupIcon = computed(() => '')

// Calcule les groupes selon la dimension choisie. Dérivé de allOrders.
const groupedOrders = computed(() => {
  if (!groupBy.value) return []

  // Regroupement par adresse similaire (Levenshtein)
  if (groupBy.value === 'fuzzy_address') {
    return buildFuzzyGroups(allOrders.value, fuzzyThreshold.value)
  }

  const buckets = new Map()

  for (const o of allOrders.value) {
    let key, label, sublabel = '', isOther = false
    const a = o.shipping_address || {}

    switch (groupBy.value) {
      case 'zone': {
        const z = o.shipping_zone
        key      = z?.name || '__off__'
        label    = z?.name || 'Hors zone'
        sublabel = z?.group || ''
        isOther  = !z
        break
      }
      case 'commune': {
        const c = (a.commune || a.city || '').trim()
        key      = c || '__none__'
        label    = c || 'Sans commune'
        sublabel = a.country || ''
        isOther  = !c
        break
      }
      case 'address': {
        // Normalisation : lowercase, accents retirés, espaces collapsés.
        const raw  = (a.address_line1 || '').trim()
        const norm = raw.toLowerCase()
          .normalize('NFD').replace(/\p{Diacritic}/gu, '')
          .replace(/\s+/g, ' ')
        key      = norm || '__none__'
        label    = raw || 'Sans adresse'
        sublabel = [a.commune, a.city].filter(Boolean).join(', ')
        isOther  = !raw
        break
      }
      case 'status': {
        key      = o.status
        label    = statusLabel(o.status)
        break
      }
    }

    if (!buckets.has(key)) {
      buckets.set(key, { key, label, sublabel, isOther, orders: [], total: 0 })
    }
    const b = buckets.get(key)
    b.orders.push(o)
    b.total += Number(o.total) || 0
  }

  // Tri : groupes "Autre" (hors zone, sans commune, sans adresse) en dernier.
  return [...buckets.values()].sort((a, b) => {
    if (a.isOther && !b.isOther) return 1
    if (b.isOther && !a.isOther) return -1
    return a.label.localeCompare(b.label, 'fr')
  })
})

// ── Fuzzy address matching ────────────────────────────────────────────────────

function normalizeForFuzzy(s) {
  return String(s ?? '')
    .toLowerCase()
    .normalize('NFD').replace(/\p{Diacritic}/gu, '')
    .replace(/[-,.']/g, ' ')
    .replace(/\b(rue|av|avenue|bd|boulevard|cite|quartier|lot|batiment|bat|immeuble|appt|appartement|villa|n°|no|bp)\b/g, '')
    .replace(/\s+/g, ' ')
    .trim()
}

function levenshtein(a, b) {
  const m = a.length, n = b.length
  const dp = Array.from({ length: m + 1 }, (_, i) => Array.from({ length: n + 1 }, (_, j) => i === 0 ? j : j === 0 ? i : 0))
  for (let i = 1; i <= m; i++)
    for (let j = 1; j <= n; j++)
      dp[i][j] = a[i-1] === b[j-1] ? dp[i-1][j-1] : 1 + Math.min(dp[i-1][j], dp[i][j-1], dp[i-1][j-1])
  return dp[m][n]
}

function addrSimilarity(a, b) {
  if (!a && !b) return 1
  if (!a || !b) return 0
  const na = normalizeForFuzzy(a), nb = normalizeForFuzzy(b)
  if (na === nb) return 1
  const maxLen = Math.max(na.length, nb.length)
  if (maxLen === 0) return 1
  return 1 - levenshtein(na, nb) / maxLen
}

function buildFuzzyGroups(orders, threshold) {
  const groups  = []   // [{ label, orders, total, representative }]
  const assigned = new Set()

  for (let i = 0; i < orders.length; i++) {
    if (assigned.has(i)) continue
    const o = orders[i]
    const a = o.shipping_address || {}
    const rawAddr = (a.address_line1 || '').trim()
    const rawCity = (a.commune || a.city || '').trim()
    const repr = [rawAddr, rawCity].filter(Boolean).join(', ') || 'Sans adresse'

    const cluster = [o]
    assigned.add(i)

    for (let j = i + 1; j < orders.length; j++) {
      if (assigned.has(j)) continue
      const oa = orders[j]
      const aa = oa.shipping_address || {}
      const cmpAddr = (aa.address_line1 || '').trim()
      const cmpCity = (aa.commune || aa.city || '').trim()

      // Comparer adresse_line1 + commune vs référence
      const simAddr = addrSimilarity(rawAddr, cmpAddr)
      const simCity = addrSimilarity(rawCity, cmpCity)
      // La ville / commune compte autant que l'adresse, moyenne pondérée
      const sim = rawAddr ? (simAddr * 0.7 + simCity * 0.3) : simCity

      if (sim >= threshold) {
        cluster.push(oa)
        assigned.add(j)
      }
    }

    groups.push({
      key:     `fuzzy_${i}`,
      label:   repr,
      sublabel: cluster.length > 1 ? `${cluster.length} adresses similaires` : '',
      isOther: !rawAddr,
      orders:  cluster,
      total:   cluster.reduce((s, x) => s + (Number(x.total) || 0), 0),
    })
  }

  return groups.sort((a, b) => {
    if (a.isOther && !b.isOther) return 1
    if (b.isOther && !a.isOther) return -1
    return b.orders.length - a.orders.length || a.label.localeCompare(b.label, 'fr')
  })
}

function onGroupByChange() {
  // Retour à la liste : le tableau se charge lui-même en se montant.
  if (groupBy.value) fetchAllForGrouping()
}

function toggleGroup(key) {
  if (openGroups.value.has(key)) openGroups.value.delete(key)
  else openGroups.value.add(key)
  openGroups.value = new Set(openGroups.value)
}

// Récupère toutes les commandes (limit 500) pour regroupement client-side.
async function fetchAllForGrouping() {
  loading.value = true
  try {
    const params = { limit: 500 }
    // La vue groupée ne connaît qu'un statut à la fois.
    if (filters.status.length === 1) params.status = filters.status[0]
    const { data } = await api.get('/admin/orders/by-zone', { params })
    // Aplatir les zones du backend en liste plate de commandes (shipping_zone est dans chaque commande)
    allOrders.value = (data.data ?? []).flatMap(g =>
      g.orders.map(o => ({
        ...o,
        // Le backend ne réinjecte pas shipping_zone dans chaque commande individuelle :
        // on le reconstruit à partir du groupe parent.
        shipping_zone: g.zone === 'Hors zone' ? null : { name: g.zone, group: g.group },
      }))
    )
    openGroups.value = new Set()
    if (groupedOrders.value.length) openGroups.value = new Set([groupedOrders.value[0].key])
  } finally {
    loading.value = false
  }
}

// ── Aperçu avant export d'un groupe (ajustements : titre, sélection, filtres) ─
const exportPreview = ref(null) // { label, orders } | null

function openExportPreview(group) {
  exportPreview.value = { label: group.label, orders: group.orders }
}

/**
 * Une tournée vient de partir : les commandes qu'elle emporte sont passées en
 * « expédiée ». La liste affichée date d'avant, et laisserait croire qu'elles
 * sont encore à préparer — de quoi les embarquer une seconde fois.
 */
async function apresTournee() {
  if (groupBy.value) await fetchAllForGrouping()
  else grille.value?.reload()
}

// ── Modal itinéraire ─────────────────────────────────────────────────────────
const routeMap = ref(null) // { title, orders, startAddress } | null

function openRouteMapForGroup(group) {
  routeMap.value = { title: group.label, orders: group.orders, startAddress: pickupAddress.value }
}


const orderStatusOptions = [
  { value: 'pending',    label: 'En attente' },
  { value: 'processing', label: 'En cours' },
  { value: 'shipped',    label: 'Expédiées' },
  { value: 'delivered',  label: 'Livrées' },
  { value: 'cancelled',  label: 'Annulées' },
  { value: 'refunded',   label: 'Remboursées' },
]

const exportCountryOptions = [
  { value: 'CI', label: "Côte d'Ivoire" },
  { value: 'SN', label: 'Sénégal' },
  { value: 'ML', label: 'Mali' },
  { value: 'BF', label: 'Burkina Faso' },
  { value: 'GN', label: 'Guinée' },
  { value: 'CM', label: 'Cameroun' },
  { value: 'FR', label: 'France' },
]

/* ── Export ── */
const showExport     = ref(false)
const loadingPreview = ref(false)
const previewError   = ref('')
const exportFilters = reactive({
  date_from: '',
  date_to:   '',
  status:    '',
  country:   '',
  // Les annulées et remboursées sortent de l'extraction par défaut : ce ne
  // sont pas des ventes. Décision réversible, d'où la case.
  include_cancelled: false,
})

/**
 * Charge les commandes correspondant aux filtres d'export puis ouvre l'aperçu.
 * Le backend applique exactement les mêmes règles que l'export réel, donc ce
 * qui est affiché ici est bien ce qui sera exporté.
 */
async function openGlobalExportPreview() {
  loadingPreview.value = true
  previewError.value   = ''
  try {
    const params = {}
    if (exportFilters.date_from) params.date_from = exportFilters.date_from
    if (exportFilters.date_to)   params.date_to   = exportFilters.date_to
    if (exportFilters.status)    params.status    = exportFilters.status
    if (exportFilters.country)   params.country   = exportFilters.country
    if (exportFilters.include_cancelled) params.include_cancelled = 1

    const { data } = await api.get('/admin/orders/export-preview', { params })
    const orders = data.data ?? []

    if (!orders.length) {
      previewError.value = 'Aucune commande ne correspond à ces filtres.'
      return
    }

    exportPreview.value = {
      // Heure comprise : deux extractions du même jour doivent se distinguer,
      // dans le titre de la feuille comme dans le nom du fichier.
      label:   `Commandes ${new Date().toLocaleString('fr-FR', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
      })}`,
      orders,
      formats: ['xlsx', 'pdf', 'csv'],
    }
  } catch (e) {
    previewError.value = e.response?.data?.message ?? "Impossible de charger l'aperçu."
  } finally {
    loadingPreview.value = false
  }
}



/**
 * Carte de statistique cliquée : filtre sur ce seul statut, ou retire le
 * filtre si c'était déjà lui. « Total » efface le filtre de statut.
 */
function setStatusFilter(status) {
  filters.status = !status || estSeulStatut(status) ? [] : [status]
  if (groupBy.value) fetchAllForGrouping()
}

function fmt(v) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', minimumFractionDigits: 0 }).format(Number(v ?? 0))
}

function fmtCompact(v) {
  const n = Number(v ?? 0)
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace('.0', '') + 'M FCFA'
  if (n >= 1_000)     return (n / 1_000).toFixed(0) + 'k FCFA'
  return n.toLocaleString('fr-FR') + ' FCFA'
}

function formatDate(val) {
  if (!val) return '—'
  return new Date(val).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatPrice(val) {
  // Montant masqué (finance privée) → tiret
  if (val === null || val === undefined) return '—'
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', maximumFractionDigits: 0 }).format(val)
}

function statusLabel(status) {
  const map = {
    pending: 'En attente', processing: 'En cours', shipped: 'Expédiée',
    delivered: 'Livrée', cancelled: 'Annulée', refunded: 'Remboursée',
  }
  return map[status] ?? status
}

function statusBadge(status) {
  const map = {
    pending: 'badge badge-warning', processing: 'badge badge-primary',
    shipped: 'badge badge-primary', delivered: 'badge badge-success',
    cancelled: 'badge badge-danger', refunded: 'badge badge-gray',
  }
  return map[status] ?? 'badge badge-gray'
}

onMounted(async () => {
  settings.fetch()

  // Lien depuis le tableau de bord (« 12 en attente ») : le statut demandé
  // prime sur celui conservé de la dernière visite.
  if (route.query.status) filters.status = [String(route.query.status)]

  if (groupBy.value) await fetchAllForGrouping()
})
</script>

<style scoped>
.admin-page { display: flex; flex-direction: column; gap: var(--space-5); }

/* ── Raccourcis de période et filtres rapides ── */
.orders__presets { display: flex; flex-wrap: wrap; gap: 6px; }

.orders__chip {
  display: inline-flex;
  align-items: center;
  padding: 6px 12px;
  border: 1.5px solid var(--cream-300);
  border-radius: var(--radius-full);
  background: #fff;
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--gray-600);
  cursor: pointer;
  transition: all var(--transition-fast);
  white-space: nowrap;
}
.orders__chip:hover { border-color: var(--rose-300); color: var(--rose-600); }
.orders__chip--on {
  border-color: var(--rose-500);
  background: var(--rose-50);
  color: var(--rose-600);
  font-weight: 600;
}
.orders__chip--static { cursor: default; }

.orders__quick {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-3) var(--space-5);
  padding: var(--space-3) var(--space-4);
}
.orders__quick-group { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.orders__quick-label {
  margin-right: 2px;
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--gray-400);
}
.orders__quick-sep { width: 1px; height: 18px; margin: 0 4px; background: var(--cream-300); }
.orders__quick-reset {
  margin-left: auto;
  padding: 0;
  border: none;
  background: none;
  font-size: 0.8125rem;
  color: var(--rose-600);
  cursor: pointer;
}
.orders__quick-reset:hover { text-decoration: underline; }

.orders__list { display: flex; flex-direction: column; gap: var(--space-3); padding: var(--space-4); }

/* Commande que l'on vient de quitter : repère éphémère, pas un état. */
.orders__row--revue > td {
  background: var(--rose-50);
  box-shadow: inset 3px 0 0 var(--rose-500);
}

.orders__aside {
  background: none;
  border: none;
  padding: 0;
  font-size: 0.75rem;
  color: var(--gray-400);
  text-decoration: underline;
  text-underline-offset: 3px;
  cursor: pointer;
}
.orders__aside:hover { color: var(--gray-700); }

.orders__aside-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  flex-wrap: wrap;
  padding: var(--space-3);
  margin-bottom: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--cream-100);
  font-size: 0.875rem;
  color: var(--gray-600);
}

.filters-bar {
  padding: var(--space-4);
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
  align-items: center;
}
.filters-bar__search { flex: 1; min-width: 220px; }

.status-tabs { display: flex; flex-wrap: wrap; gap: 2px; }
.status-tab {
  padding: 6px 14px;
  border-radius: var(--radius-full);
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--gray-500);
  background: transparent;
  transition: all var(--transition-fast);
}
.status-tab:hover { background: var(--cream-200); color: var(--gray-700); }
.status-tab--active {
  background: var(--rose-500);
  color: #fff;
  box-shadow: var(--shadow-rose);
}

/* ── Export toggle ── */
.export-toggle-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: 8px 16px;
  border-radius: var(--radius-full);
  border: 1.5px solid var(--cream-300);
  background: #fff;
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--gray-600);
  transition: all var(--transition-fast);
  white-space: nowrap;
}
.export-toggle-btn:hover,
.export-toggle-btn.active {
  border-color: var(--rose-400);
  color: var(--rose-600);
  background: var(--rose-50);
}

/* ── Sélecteur "Grouper par" ── */
.group-by {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--gray-500);
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.group-by__select {
  padding: 6px 28px 6px 12px;
  border-radius: var(--radius-full);
  border: 1.5px solid var(--cream-300);
  background: #fff;
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--gray-700);
  cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6' fill='none' stroke='%23999' stroke-width='1.8' stroke-linecap='round'%3E%3Cpolyline points='1 1 5 5 9 1'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
  transition: all var(--transition-fast);
  letter-spacing: normal;
  text-transform: none;
}
.group-by__select:hover,
.group-by__select:focus {
  border-color: var(--rose-400);
  color: var(--rose-600);
  outline: none;
}

/* ── Groupes par zone ── */
.zone-groups {
  display: flex;
  flex-direction: column;
}
.zone-group {
  border-bottom: 1px solid var(--cream-200);
}
.zone-group:last-child { border-bottom: none; }
.zone-group--off .zone-group__title strong { color: var(--gold-600, #b45309); }

.zone-group__head {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  background: var(--cream-50);
  border: none;
  cursor: pointer;
  font-family: inherit;
  transition: background var(--transition-fast);
}
.zone-group__head:hover { background: var(--cream-100); }
.zone-group__title {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
}
.zone-group__title strong {
  font-family: var(--font-display);
  font-size: 0.9375rem;
  color: var(--gray-800);
}
.zone-group__sub {
  font-size: 0.6875rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--gray-400);
}
.zone-group__meta {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}
.zone-group__total {
  font-weight: 700;
  color: var(--rose-600);
  font-size: 0.9375rem;
}
.zone-group__chevron {
  display: inline-block;
  transition: transform 0.2s ease;
  color: var(--gray-400);
}
.zone-group__chevron.open { transform: rotate(180deg); }

.zone-group__body {
  padding: 0 var(--space-4) var(--space-3);
  background: #fff;
}
.zone-group__body .admin-table { width: 100%; }

.commune-group {
  border-top: 1px dashed var(--cream-200);
  padding: var(--space-2) 0;
}
.commune-group:first-child { border-top: none; }
.commune-group__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: 6px var(--space-2);
  margin-bottom: 4px;
}
.commune-group__name {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--gray-700);
}
.commune-group__count {
  font-size: 0.6875rem;
  color: var(--gray-500);
  background: var(--cream-100);
  padding: 2px 8px;
  border-radius: var(--radius-full);
}
.commune-group__actions {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}
.commune-group__export {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--rose-600);
  background: #fff;
  border: 1px solid var(--rose-200);
  border-radius: var(--radius-full);
  padding: 2px 10px;
  cursor: pointer;
  transition: all var(--transition-fast);
}
.commune-group__export:hover {
  background: var(--rose-50);
  border-color: var(--rose-400);
}
.commune-group__export--pdf {
  background: var(--rose-50);
  border-color: var(--rose-300);
  color: var(--rose-700);
}
.commune-group__export--pdf:hover:not(:disabled) {
  background: var(--rose-500);
  border-color: var(--rose-500);
  color: #fff;
}
.commune-group__export--pdf:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* ── Fuzzy threshold control ── */
.fuzzy-control {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  background: #fff;
  border: 1.5px solid var(--cream-300);
  border-radius: var(--radius-full);
  padding: 5px 14px;
}
.fuzzy-control__label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--gray-500);
  letter-spacing: 0.04em;
  text-transform: uppercase;
  display: flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
}
.fuzzy-control__badge {
  font-size: 0.6875rem;
  font-weight: 600;
  border-radius: var(--radius-full);
  padding: 1px 7px;
  letter-spacing: 0;
  text-transform: none;
}
.fuzzy-control__badge--permissive { background: #fef9c3; color: #854d0e; }
.fuzzy-control__badge--équilibrée { background: #dcfce7; color: #166534; }
.fuzzy-control__badge--stricte    { background: #fee2e2; color: #991b1b; }
.fuzzy-control__slider {
  -webkit-appearance: none;
  width: 90px;
  height: 4px;
  border-radius: 2px;
  background: linear-gradient(to right, var(--rose-400) 0%, var(--rose-400) calc((var(--val, 0.72) - 0.50) / 0.45 * 100%), var(--cream-200) calc((var(--val, 0.72) - 0.50) / 0.45 * 100%));
  outline: none;
  cursor: pointer;
}
.fuzzy-control__slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  width: 14px; height: 14px;
  border-radius: 50%;
  background: var(--rose-500);
  border: 2px solid #fff;
  box-shadow: 0 1px 4px rgba(0,0,0,0.2);
}
.fuzzy-control__val {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--gray-600);
  min-width: 32px;
  text-align: right;
}

.admin-table__phone {
  font-size: 0.75rem;
  color: var(--rose-600);
  font-weight: 500;
  text-decoration: none;
  display: inline-block;
  margin-top: 2px;
}
.admin-table__phone:hover { text-decoration: underline; }

/* ── Export panel ── */
.export-panel {
  padding: var(--space-5);
  border: 1.5px solid var(--rose-100);
  background: var(--rose-50);
}
.export-panel__title {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--gray-800);
  margin-bottom: var(--space-4);
}
.export-filters {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
  margin-bottom: var(--space-4);
}
.export-field { display: flex; flex-direction: column; gap: 4px; }
.export-label {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--gray-500);
  letter-spacing: 0.04em;
}
.export-input {
  min-width: 160px;
  padding: 8px 12px;
  font-size: 0.8125rem;
  background: #fff;
}
/* Le champ datetime-local est plus large que le champ date : sans ce minimum,
   l'heure était rognée sur les écrans étroits. */
.export-input[type='datetime-local'] { min-width: 205px; }

.export-toggle {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  margin-bottom: var(--space-4);
  font-size: 0.8125rem;
  color: var(--gray-600);
  cursor: pointer;
  user-select: none;
}
.export-toggle input { cursor: pointer; }
.export-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: var(--space-3);
  padding-top: var(--space-4);
  border-top: 1px solid var(--rose-100);
}
.export-hint {
  display: flex;
  align-items: flex-start;
  gap: var(--space-1);
  font-size: 0.75rem;
  color: var(--gray-400);
  max-width: 400px;
  line-height: 1.4;
}
.export-btns {
  display: flex;
  gap: var(--space-2);
  flex-wrap: wrap;
  align-items: center;
}

.export-error {
  font-size: 0.8125rem;
  color: #b91c1c;
}

.export-zone-btn {
  border-color: var(--rose-500);
  color: var(--rose-500);
}
.export-zone-btn:hover:not(:disabled) {
  background: #fff5f8;
  border-color: #c4205a;
  color: #c4205a;
}

.table-scroll { overflow-x: auto; }

/* ── Table modifiers ── */
.admin-table__mono {
  font-family: ui-monospace, monospace;
  font-weight: 500;
  color: var(--gray-800) !important;
}
.admin-table__client {
  font-weight: 500;
  color: var(--gray-800);
}
.admin-table__sub {
  font-size: 0.75rem;
  color: var(--gray-400);
}
.admin-table__tracking {
  font-family: ui-monospace, monospace;
  font-size: 0.6875rem;
  color: var(--gray-500);
  font-weight: 400;
  margin-top: 2px;
}
.admin-table__total {
  font-weight: 600;
  color: var(--rose-600) !important;
}
.orders__exported {
  display: inline-block;
  margin-top: 3px;
  padding: 1px 7px;
  border-radius: var(--radius-full);
  background: var(--cream-200);
  font-family: var(--font-sans, inherit);
  font-size: 0.625rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  color: var(--gray-600);
}
.admin-table__action a {
  color: var(--rose-500);
  font-size: 0.8125rem;
  font-weight: 500;
  transition: color var(--transition-fast);
}
.admin-table__action a:hover { color: var(--rose-700); }
.admin-table__action-row {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  justify-content: flex-end;
}
.btn-xs {
  padding: 4px 10px !important;
  font-size: 0.6875rem !important;
  height: auto !important;
  border-radius: var(--radius-sm);
}

/* ── Cartes stats vue d'ensemble ── */
.orders-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: var(--space-2);
  margin-bottom: var(--space-3);
}
.stat-card {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: 10px 14px;
  background: #fff;
  border: 1px solid var(--cream-200);
  border-radius: var(--radius-md);
  text-align: left;
  cursor: pointer;
  transition: all 0.15s ease;
  position: relative;
}
.stat-card:hover {
  border-color: var(--rose-300);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(168, 50, 80, 0.08);
}
.stat-card--active {
  border-color: var(--rose-500);
  background: var(--rose-50);
  box-shadow: inset 3px 0 0 var(--rose-500);
}
.stat-card__label {
  font-size: 0.625rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--gray-500);
  font-weight: 600;
}
.stat-card__value {
  font-family: var(--font-display);
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--gray-800);
  line-height: 1;
  margin-top: 2px;
}
.stat-card__hint {
  font-size: 0.6875rem;
  color: var(--gray-400);
  margin-top: 4px;
}

/* Accents par statut */
.stat-card--pending .stat-card__value    { color: var(--gold-600, #b45309); }
.stat-card--processing .stat-card__value { color: #2563eb; }
.stat-card--shipped .stat-card__value    { color: #7c3aed; }
.stat-card--delivered .stat-card__value  { color: #15803d; }
.stat-card--revenue {
  background: linear-gradient(135deg, var(--rose-500), #f06292);
  border-color: transparent;
  color: #fff;
  cursor: default;
}
.stat-card--revenue:hover {
  transform: none;
  box-shadow: none;
}
.stat-card--revenue .stat-card__label,
.stat-card--revenue .stat-card__hint  { color: rgba(255,255,255,0.85); }
.stat-card--revenue .stat-card__value { color: #fff; }

.pagination {
  padding: var(--space-3) var(--space-4);
  border-top: 1px solid var(--cream-200);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.pagination p { font-size: 0.8125rem; color: var(--gray-400); }
.pagination__actions { display: flex; gap: var(--space-2); }
.pagination__actions .btn { padding: 6px 12px; }
/* ── Sélection multiple ── */
.orders__th-check { width: 34px; }
.orders__th-check input { cursor: pointer; accent-color: var(--rose-500); }

.orders__bulk {
  display: flex; align-items: center; gap: var(--space-3); flex-wrap: wrap;
  padding: var(--space-3) var(--space-4);
  background: var(--rose-50); border-radius: var(--radius-md);
  margin-bottom: var(--space-3);
}
.orders__bulk-count { font-size: 0.8125rem; color: var(--gray-700); }
.orders__bulk-spacer { flex: 1; }
.orders__bulk-link {
  background: none; border: none; cursor: pointer;
  font-size: 0.75rem; font-weight: 500; color: var(--rose-600);
}
.orders__bulk-link:hover { text-decoration: underline; }

.orders__search-report {
  display: flex; flex-wrap: wrap; align-items: center; gap: 4px var(--space-4);
  padding: var(--space-2) var(--space-4); margin-bottom: var(--space-3);
  background: var(--cream-50); border-radius: var(--radius-md);
  font-size: 0.8125rem; color: var(--gray-700);
}
.orders__search-missing { color: #92400e; }
.orders__bulk-group { display: flex; align-items: center; gap: var(--space-2); flex-wrap: wrap; }
.orders__bulk-label { font-size: 0.75rem; color: var(--gray-500); }
.orders__bulk-more { position: relative; }
.orders__bulk-menu {
  position: absolute; right: 0; top: calc(100% + 4px); z-index: 30;
  min-width: 210px; padding: 4px;
  background: #fff; border: 1px solid var(--gray-200); border-radius: var(--radius-md);
  box-shadow: 0 8px 24px rgba(43, 33, 38, 0.12);
}
.orders__bulk-menu button {
  display: block; width: 100%; text-align: left;
  padding: 8px 10px; border: none; background: none; border-radius: 6px;
  font-size: 0.8125rem; color: var(--gray-700); cursor: pointer;
}
.orders__bulk-menu button:hover { background: var(--rose-50); }
.orders__bulk-menu .orders__bulk-menu--danger { color: #b91c1c; }
.orders__bulk-menu .orders__bulk-menu--danger:hover { background: #fef2f2; }
.orders__bulk-collect { margin: 6px 0 0; color: var(--gray-700); }

.orders__bulk-result {
  position: relative;
  padding: var(--space-3) var(--space-4);
  background: var(--cream-50); border-radius: var(--radius-md);
  margin-bottom: var(--space-3); font-size: 0.8125rem;
}
.orders__bulk-msg { margin: 0; font-weight: 600; color: var(--gray-800); }
.orders__bulk-rejected { margin-top: var(--space-2); color: #92400e; }
.orders__bulk-rejected p { margin: 0 0 4px; }
.orders__bulk-rejected ul { margin: 0 0 6px; padding-left: 18px; }
.orders__bulk-close {
  position: absolute; top: 8px; right: 10px;
  background: none; border: none; cursor: pointer; color: var(--gray-400);
}

</style>
