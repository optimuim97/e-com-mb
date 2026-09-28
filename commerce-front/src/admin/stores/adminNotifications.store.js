/**
 * Admin real-time notifications store
 *
 * Subscribes to the private channel `admin.notifications`
 * via Laravel Reverb and keeps an in-memory list of events.
 *
 * Usage:
 *   import { useAdminNotificationsStore } from '@/admin/stores/adminNotifications.store'
 *   const notif = useAdminNotificationsStore()
 *   notif.subscribe()   // call once when AdminLayout mounts
 *   notif.unsubscribe() // call on layout unmount
 */

import { defineStore } from 'pinia';
import { jouerSignal } from './notificationSound';
import { ref, computed } from 'vue';
import { useEcho } from '@/plugins/echo';
import { useAuthStore } from '@/features/auth/auth.store';

const MAX_NOTIFICATIONS = 50;

export const useAdminNotificationsStore = defineStore('adminNotifications', () => {
    const notifications = ref([]);
    const isConnected   = ref(false);

    // ── Computed ──────────────────────────────────────────────────────────────
    const unreadCount = computed(() =>
        notifications.value.filter(n => !n.read).length
    );

    const hasUnread = computed(() => unreadCount.value > 0);

    // ── Helpers ───────────────────────────────────────────────────────────────
    function pushNotification(event, payload) {
        const item = {
            id:        `${Date.now()}-${Math.random().toString(36).slice(2)}`,
            event,
            payload,
            read:      false,
            timestamp: new Date().toISOString(),
        };

        notifications.value.unshift(item);

        // Keep list bounded
        if (notifications.value.length > MAX_NOTIFICATIONS) {
            notifications.value.splice(MAX_NOTIFICATIONS);
        }
    }

    // ── Channel subscription ──────────────────────────────────────────────────
    let channel = null;

    function subscribe() {
        const auth = useAuthStore();
        if (!auth.isLoggedIn) return;

        try {
            const echo = useEcho();

            if (!echo) {
                console.warn('[AdminNotifications] Echo not available — real-time disabled.');
                return;
            }

            channel = echo.private('admin.notifications');

            channel.listen('.AdminNotification', ({ event, payload }) => {
                pushNotification(event, payload);

                // Seules les nouvelles commandes sonnent. Une expédition ou une
                // annulation part d'un geste de l'agent : il sait déjà.
                if (event === 'order.placed') jouerSignal();
            });

            channel.subscribed(() => {
                isConnected.value = true;
            });

            channel.error(() => {
                isConnected.value = false;
            });
        } catch (err) {
            console.warn('[AdminNotifications] WebSocket connection failed:', err.message);
        }
    }

    function unsubscribe() {
        try {
            const echo = useEcho();
            if (echo) echo.leave('admin.notifications');
        } catch {
            // silently ignore if Echo not initialised
        }
        channel       = null;
        isConnected.value = false;
    }

    // ── Actions ───────────────────────────────────────────────────────────────
    function markAllRead() {
        notifications.value.forEach(n => { n.read = true; });
    }

    function markRead(id) {
        const n = notifications.value.find(n => n.id === id);
        if (n) n.read = true;
    }

    function clear() {
        notifications.value = [];
    }

    return {
        notifications,
        isConnected,
        unreadCount,
        hasUnread,
        subscribe,
        unsubscribe,
        markAllRead,
        markRead,
        clear,
        pushNotification, // exposed for dev/testing
    };
});
