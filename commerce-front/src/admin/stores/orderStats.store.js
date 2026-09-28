/**
 * Admin order stats store
 *
 * Light polling of /admin/orders/stats to keep the sidebar badge
 * "Commandes (N)" up to date with the number of pending orders.
 *
 * Refresh strategy:
 *   - load() once when AdminLayout mounts (start())
 *   - re-poll every 60s (configurable)
 *   - also re-fetch on demand after an order action (refresh())
 */

import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/api';

const POLL_INTERVAL_MS = 60_000;

export const useOrderStatsStore = defineStore('adminOrderStats', () => {
    const pending     = ref(0);
    const confirmed   = ref(0);
    const processing  = ref(0);
    const shipped     = ref(0);
    const delivered   = ref(0);
    const cancelled   = ref(0);
    const refunded    = ref(0);
    const total       = ref(0);
    const today       = ref(0);
    const thisWeek    = ref(0);
    const revenue     = ref(0);
    const pendingRevenue = ref(0);
    const avgBasket   = ref(0);
    const loading     = ref(false);
    const lastError   = ref(null);

    let timer = null;

    async function load() {
        loading.value = true;
        lastError.value = null;
        try {
            const { data } = await api.get('/admin/orders/stats');
            pending.value    = data.pending    ?? 0;
            confirmed.value  = data.confirmed  ?? 0;
            processing.value = data.processing ?? 0;
            shipped.value    = data.shipped    ?? 0;
            delivered.value  = data.delivered  ?? 0;
            cancelled.value  = data.cancelled  ?? 0;
            refunded.value   = data.refunded   ?? 0;
            total.value      = data.total      ?? 0;
            today.value      = data.today      ?? 0;
            thisWeek.value   = data.this_week  ?? 0;
            revenue.value    = data.revenue    ?? 0;
            pendingRevenue.value = data.pending_revenue ?? 0;
            avgBasket.value  = data.avg_basket ?? 0;
        } catch (e) {
            lastError.value = e;
        } finally {
            loading.value = false;
        }
    }

    function start() {
        if (timer) return;
        load();
        timer = setInterval(load, POLL_INTERVAL_MS);
    }

    function stop() {
        if (timer) { clearInterval(timer); timer = null; }
    }

    return {
        pending, confirmed, processing, shipped, delivered, cancelled, refunded,
        total, today, thisWeek, revenue, pendingRevenue, avgBasket,
        loading, lastError,
        load, start, stop,
        refresh: load,
    };
});
