<template>
  <div class="nb" ref="rootRef">

    <!-- Bell button -->
    <button
      class="nb__btn"
      :class="{ 'nb__btn--active': open }"
      @click="toggle"
      aria-label="Notifications"
    >
      <!-- Bell icon -->
      <svg class="nb__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
      </svg>

      <!-- Unread badge -->
      <Transition name="badge-pop">
        <span v-if="store.hasUnread" class="nb__badge">
          {{ store.unreadCount > 9 ? '9+' : store.unreadCount }}
        </span>
      </Transition>
    </button>

    <!-- Dropdown panel -->
    <Transition name="nb-drop">
      <div v-if="open" class="nb__panel">

        <!-- Panel header -->
        <div class="nb__panel-header">
          <span class="nb__panel-title">Notifications</span>
          <div class="nb__panel-actions">
            <!--
              Interrupteur du son, ici plutôt que dans les réglages boutique :
              c'est une préférence de poste, pas un paramètre d'entreprise. Deux
              agents partageant le même compte n'ont pas le même bureau.
            -->
            <span
              class="nb__sound"
              :class="{ 'nb__sound--off': !son }"
              :title="son ? 'Couper le son des nouvelles commandes' : 'Activer le son des nouvelles commandes'"
              @click="son = basculerSon()"
            >{{ son ? '🔔 Son' : '🔕 Muet' }}</span>
            <span
              v-if="store.hasUnread"
              class="nb__mark-read"
              @click="store.markAllRead()"
            >Tout lire</span>
            <span
              v-if="store.notifications.length"
              class="nb__clear"
              @click="store.clear()"
            >Effacer</span>
          </div>
        </div>

        <!-- Status bar -->
        <div class="nb__status" :class="store.isConnected ? 'nb__status--online' : 'nb__status--offline'">
          <span class="nb__status-dot"></span>
          {{ store.isConnected ? 'Connecté en temps réel' : 'Hors connexion' }}
        </div>

        <!-- Notification list -->
        <div class="nb__list" v-if="store.notifications.length">
          <div
            v-for="notif in store.notifications"
            :key="notif.id"
            class="nb__item"
            :class="{ 'nb__item--unread': !notif.read }"
            @click="handleClick(notif)"
          >
            <div class="nb__item-icon">{{ eventIcon(notif.event) }}</div>
            <div class="nb__item-body">
              <p class="nb__item-title">{{ eventLabel(notif.event) }}</p>
              <p class="nb__item-sub">
                <strong>{{ notif.payload?.customer_name }}</strong>
                · {{ formatAmount(notif.payload?.total, notif.payload?.currency) }}
                <span v-if="notif.payload?.number"> · #{{ notif.payload.number }}</span>
              </p>
              <span class="nb__item-time">{{ timeAgo(notif.timestamp) }}</span>
            </div>
            <div v-if="!notif.read" class="nb__item-dot"></div>
          </div>
        </div>

        <!-- Empty state -->
        <div v-else class="nb__empty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="nb__empty-icon">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
          </svg>
          <p>Aucune notification</p>
        </div>

      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import { useAdminNotificationsStore } from '@/admin/stores/adminNotifications.store';
import { sonActif, basculerSon } from '@/admin/stores/notificationSound';

const store  = useAdminNotificationsStore();
const son    = ref(sonActif());
const router = useRouter();

const open    = ref(false);
const rootRef = ref(null);

// ── Toggle ────────────────────────────────────────────────────────────────────
function toggle() {
    open.value = !open.value;
    if (open.value && store.hasUnread) {
        // mark visible ones as read after a short delay
        setTimeout(() => store.markAllRead(), 1500);
    }
}

function close() { open.value = false; }

// Close on outside click
function onOutsideClick(e) {
    if (rootRef.value && !rootRef.value.contains(e.target)) close();
}

onMounted(() => document.addEventListener('click', onOutsideClick, true));
onBeforeUnmount(() => document.removeEventListener('click', onOutsideClick, true));

// ── Helpers ───────────────────────────────────────────────────────────────────
function eventIcon(event) {
    const icons = {
        'order.placed':    '🛒',
        'order.shipped':   '🚚',
        'order.cancelled': '❌',
    };
    return icons[event] ?? '🔔';
}

function eventLabel(event) {
    const labels = {
        'order.placed':    'Nouvelle commande',
        'order.shipped':   'Commande expédiée',
        'order.cancelled': 'Commande annulée',
    };
    return labels[event] ?? 'Notification';
}

function formatAmount(total, currency = 'XOF') {
    if (!total) return '';
    return new Intl.NumberFormat('fr-CI', {
        style: 'decimal',
        maximumFractionDigits: 0,
    }).format(total) + ' ' + currency;
}

function timeAgo(timestamp) {
    if (!timestamp) return '';
    const diff = Math.floor((Date.now() - new Date(timestamp).getTime()) / 1000);
    if (diff < 60)   return 'À l\'instant';
    if (diff < 3600) return `Il y a ${Math.floor(diff / 60)} min`;
    if (diff < 86400) return `Il y a ${Math.floor(diff / 3600)} h`;
    return new Date(timestamp).toLocaleDateString('fr-CI', { day: 'numeric', month: 'short' });
}

function handleClick(notif) {
    store.markRead(notif.id);

    // La route admin est `orders/:id` : elle attend la clé primaire, pas le
    // numéro affiché. Pousser « ORD-2026-00074 » menait à « Commande
    // introuvable ». Le payload porte les deux, `id` sert à naviguer et
    // `number` à afficher.
    const id = notif.payload?.id;
    if (!id) return;

    router.push(`/admin/orders/${id}`);
    close();
}
</script>

<style scoped>
.nb {
    position: relative;
}

/* ── Bell button ── */
.nb__btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: transparent;
    color: var(--gray-500);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    transition: all var(--transition-fast);
    border: 1.5px solid transparent;
}
.nb__btn:hover,
.nb__btn--active {
    background: var(--rose-50);
    color: var(--rose-500);
    border-color: var(--rose-100);
}
.nb__icon {
    width: 20px;
    height: 20px;
}

/* ── Badge ── */
.nb__badge {
    position: absolute;
    top: -2px;
    right: -2px;
    min-width: 18px;
    height: 18px;
    padding: 0 4px;
    border-radius: 999px;
    background: #ef4444;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #fff;
    line-height: 1;
}
.badge-pop-enter-active { animation: badge-pop 0.25s ease; }
.badge-pop-leave-active { animation: badge-pop 0.2s ease reverse; }
@keyframes badge-pop {
    0%   { transform: scale(0); opacity: 0; }
    60%  { transform: scale(1.2); }
    100% { transform: scale(1); opacity: 1; }
}

/* ── Panel ── */
.nb__panel {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    width: 340px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.12), 0 1px 4px rgba(0,0,0,0.06);
    border: 1px solid var(--cream-200);
    overflow: hidden;
    z-index: 100;
    transform-origin: top right;
}
.nb-drop-enter-active { animation: nb-drop 0.2s ease; }
.nb-drop-leave-active { animation: nb-drop 0.18s ease reverse; }
@keyframes nb-drop {
    from { opacity: 0; transform: scale(0.92) translateY(-6px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}

/* Panel header */
.nb__panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px 10px;
    border-bottom: 1px solid var(--cream-100);
}
.nb__panel-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--gray-800);
    letter-spacing: 0.3px;
}
.nb__panel-actions {
    display: flex;
    gap: 10px;
}
.nb__mark-read,
.nb__clear {
    font-size: 11px;
    color: var(--gray-400);
    cursor: pointer;
    transition: color var(--transition-fast);
}
.nb__mark-read:hover { color: var(--rose-500); }
.nb__clear:hover     { color: #ef4444; }

.nb__sound {
    font-size: 11px;
    font-weight: 600;
    color: var(--rose-500);
    cursor: pointer;
    user-select: none;
    white-space: nowrap;
}
.nb__sound--off { color: var(--gray-400); }
.nb__sound:hover { text-decoration: underline; text-underline-offset: 3px; }

/* Status bar */
.nb__status {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 16px;
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 0.3px;
    background: var(--cream-50);
    border-bottom: 1px solid var(--cream-100);
}
.nb__status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}
.nb__status--online  { color: #16a34a; }
.nb__status--online  .nb__status-dot { background: #16a34a; }
.nb__status--offline { color: var(--gray-400); }
.nb__status--offline .nb__status-dot { background: var(--gray-300); }

/* List */
.nb__list {
    max-height: 380px;
    overflow-y: auto;
    overscroll-behavior: contain;
}

/* Item */
.nb__item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 16px;
    cursor: pointer;
    transition: background var(--transition-fast);
    border-bottom: 1px solid var(--cream-50);
    position: relative;
}
.nb__item:last-child  { border-bottom: none; }
.nb__item:hover       { background: var(--cream-50); }
.nb__item--unread     { background: #fef9f5; }

.nb__item-icon {
    font-size: 20px;
    line-height: 1;
    flex-shrink: 0;
    margin-top: 2px;
}
.nb__item-body {
    flex: 1;
    min-width: 0;
}
.nb__item-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 2px;
}
.nb__item-sub {
    font-size: 12px;
    color: var(--gray-500);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 4px;
}
.nb__item-time {
    font-size: 10px;
    color: var(--gray-400);
}
.nb__item-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--rose-500);
    flex-shrink: 0;
    margin-top: 6px;
}

/* Empty */
.nb__empty {
    padding: 40px 16px;
    text-align: center;
    color: var(--gray-300);
}
.nb__empty-icon {
    width: 32px;
    height: 32px;
    margin: 0 auto 10px;
}
.nb__empty p {
    font-size: 13px;
}
</style>
