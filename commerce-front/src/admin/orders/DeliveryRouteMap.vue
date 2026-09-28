<template>
  <div class="route-overlay" @click.self="$emit('close')">
    <div class="route-modal">
      <header class="route-modal__head">
        <div>
          <span class="eyebrow">Itinéraire de tournée</span>
          <h3>{{ title }}</h3>
          <p class="route-modal__sub">{{ orders.length }} arrêt(s)</p>
        </div>
        <div class="route-modal__head-right">
          <div class="pickup-field" v-if="pickupEditable !== null">
            <label class="pickup-label">🏠 Départ</label>
            <input
              v-model="pickupEditable"
              class="pickup-input"
              placeholder="Adresse de départ…"
              @keydown.enter="resetAndRebuild"
            />
            <button class="btn btn-outline btn-sm" @click="resetAndRebuild" :disabled="rebuilding" title="Appliquer le point de départ">
              {{ rebuilding ? '…' : '✓' }}
            </button>
          </div>
          <button class="route-modal__close" @click="$emit('close')" aria-label="Fermer">✕</button>
        </div>
      </header>

      <div class="route-modal__body">
        <!-- Carte -->
        <div ref="mapEl" class="route-map"></div>

        <!-- Légende / liste des arrêts -->
        <aside class="route-side">
          <div v-if="loading" class="route-status">
            <span class="spinner"></span>
            Géocodage des adresses… ({{ geocoded }}/{{ orders.length }})
          </div>
          <div v-else-if="error" class="route-status route-status--error">{{ error }}</div>
          <div v-else class="route-summary">
            <div>
              <span class="route-summary__label">Distance</span>
              <strong>{{ formatKm(distance) }}</strong>
            </div>
            <div>
              <span class="route-summary__label">Durée estimée</span>
              <strong>{{ formatDuration(duration) }}</strong>
            </div>
          </div>

          <ol class="route-stops">
            <li v-for="(s, i) in stops" :key="s.id" class="route-stop"
              :class="{ 'route-stop--missing': !s.coords, 'route-stop--start': s.isStart }">
              <span class="route-stop__num" :class="{ 'route-stop__num--start': s.isStart }">
                {{ s.isStart ? '🏠' : i }}
              </span>
              <div class="route-stop__body">
                <strong>{{ s.client }}</strong>
                <span class="route-stop__addr">{{ s.address }}</span>
                <a v-if="s.phone" :href="`tel:${s.phone}`" class="route-stop__phone">📞 {{ s.phone }}</a>
              </div>
              <span class="route-stop__order">{{ s.number }}</span>
            </li>
          </ol>

          <div class="route-actions">
            <button
              v-if="canOptimize"
              type="button"
              class="btn btn-primary btn-sm"
              :disabled="optimizing"
              @click="optimizeOrder"
              :title="optimized ? 'Réinitialiser à l\'ordre initial' : 'Réordonner les arrêts pour minimiser la distance (TSP)'"
            >
              {{ optimizing ? '⏳ Calcul…' : optimized ? '↺ Ordre initial' : '🔄 Optimiser l\'ordre' }}
            </button>
            <a
              v-if="osmDirectionsUrl"
              :href="osmDirectionsUrl"
              target="_blank" rel="noopener"
              class="btn btn-outline btn-sm"
            >Ouvrir dans OSM ↗</a>
          </div>
          <p v-if="optimized && savings > 0" class="route-savings">
            ✓ Itinéraire optimisé : {{ formatKm(savings) }} économisés ({{ savingsPct }}%)
          </p>
        </aside>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { themeColor } from '@/theme/brandTheme';

const props = defineProps({
  title:        { type: String,  required: true },
  orders:       { type: Array,   required: true },
  startAddress: { type: String,  default: '' },
});
defineEmits(['close']);

const mapEl          = ref(null);
const loading        = ref(true);
const error          = ref('');
const geocoded       = ref(0);
const rebuilding     = ref(false);
const pickupEditable = ref(props.startAddress ?? null);
const stops    = ref([]);
const distance = ref(0); // mètres
const duration = ref(0); // secondes

// ── Optimisation TSP ─────────────────────────────────────────────────────────
const optimizing       = ref(false);
const optimized        = ref(false);
const initialDistance  = ref(0);
const initialOrderIds  = ref([]); // pour revenir à l'ordre alpha

const canOptimize = computed(() =>
  !loading.value && stops.value.filter(s => s.coords).length >= 3
);
const savings = computed(() =>
  optimized.value ? Math.max(0, initialDistance.value - distance.value) : 0
);
const savingsPct = computed(() => {
  if (!initialDistance.value || !savings.value) return 0;
  return Math.round((savings.value / initialDistance.value) * 100);
});

let leafletMap = null;
let routeLayer = null;
let markers    = [];

// Cache mémoire pour éviter de re-géocoder les mêmes adresses.
const geoCache = new Map();

const osmDirectionsUrl = computed(() => {
  const coords = stops.value.filter(s => s.coords).map(s => s.coords);
  if (coords.length < 2) return null;
  // OpenStreetMap "Directions" UI : un waypoint par paramètre route= séparé par ;
  const route = coords.map(c => `${c.lat}%2C${c.lng}`).join('%3B');
  return `https://www.openstreetmap.org/directions?engine=fossgis_osrm_car&route=${route}`;
});

onMounted(async () => {
  await initMap();
  await buildStops();
  await drawRoute();
});

async function resetAndRebuild() {
  if (rebuilding.value) return
  rebuilding.value = true
  // Clear map and rebuild from scratch with new pickup address
  markers.forEach(m => m.remove()); markers = []
  routeLayer?.remove(); routeLayer = null
  stops.value = []
  geocoded.value = 0
  loading.value = true
  distance.value = 0; duration.value = 0
  optimized.value = false
  await buildStops()
  await drawRoute()
  rebuilding.value = false
}

onBeforeUnmount(() => {
  leafletMap?.remove();
  leafletMap = null;
});

async function initMap() {
  const { default: L } = await import('leaflet');
  await import('leaflet/dist/leaflet.css');
  // Centre par défaut : Abidjan
  leafletMap = L.map(mapEl.value).setView([5.3485, -4.0137], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap',
    maxZoom: 19,
  }).addTo(leafletMap);
}

function buildAddressString(o) {
  const a = o.shipping_address || {};
  return [a.address_line1, a.commune, a.city, a.country || 'CI']
    .filter(Boolean).join(', ');
}

async function geocode(query) {
  if (geoCache.has(query)) return geoCache.get(query);
  // Nominatim : 1 req/sec max — respecter
  const url = `https://nominatim.openstreetmap.org/search?format=json&limit=1&accept-language=fr&q=${encodeURIComponent(query)}`;
  try {
    const res  = await fetch(url, { headers: { 'Accept': 'application/json' } });
    const data = await res.json();
    if (data[0]) {
      const coords = { lat: +data[0].lat, lng: +data[0].lon };
      geoCache.set(query, coords);
      return coords;
    }
  } catch { /* ignore */ }
  geoCache.set(query, null);
  return null;
}

async function buildStops() {
  const { default: L } = await import('leaflet');
  const allStops = [];

  // Géocode le point de départ en premier si défini
  if (pickupEditable.value?.trim()) {
    const coords = await geocode(pickupEditable.value.trim());
    const startStop = {
      id:      'pickup',
      number:  '',
      client:  'Point de départ',
      phone:   null,
      address: pickupEditable.value.trim(),
      coords,
      isStart: true,
    };
    allStops.push(startStop);
    stops.value = [...allStops];

    if (coords) {
      const icon = L.divIcon({
        className: 'route-marker route-marker--start',
        html: `<span>🏠</span>`,
        iconSize:   [32, 32],
        iconAnchor: [16, 16],
      });
      const m = L.marker([coords.lat, coords.lng], { icon })
        .addTo(leafletMap)
        .bindPopup(`<strong>Point de départ</strong><br>${startStop.address}`);
      markers.push(m);
    }
    await new Promise(r => setTimeout(r, 1100));
  }

  for (const o of props.orders) {
    const a = o.shipping_address || {};
    const address = buildAddressString(o);
    // Géocodage progressif (1.1s entre requêtes pour respecter Nominatim)
    const coords = await geocode(address);
    geocoded.value++;

    const stop = {
      id:      o.id,
      number:  o.number,
      client:  o.user?.name || [a.first_name, a.last_name].filter(Boolean).join(' ') || '—',
      phone:   a.phone,
      address: a.address_line1 || a.commune || a.city || '—',
      coords,
      isStart: false,
    };
    allStops.push(stop);
    stops.value = [...allStops];

    if (coords) {
      // Marqueur numéroté (seulement les arrêts de livraison)
      const num = allStops.filter(s => s.coords && !s.isStart).length;
      const icon = L.divIcon({
        className: 'route-marker',
        html: `<span>${num}</span>`,
        iconSize:   [28, 28],
        iconAnchor: [14, 14],
      });
      const m = L.marker([coords.lat, coords.lng], { icon })
        .addTo(leafletMap)
        .bindPopup(`<strong>${stop.client}</strong><br>${stop.address}<br><a href="tel:${stop.phone}">${stop.phone || ''}</a>`);
      markers.push(m);
    }

    // Throttle Nominatim
    await new Promise(r => setTimeout(r, 1100));
  }

  loading.value = false;

  // Ajuste la vue à tous les markers
  const valid = allStops.filter(s => s.coords);
  if (valid.length) {
    const bounds = L.latLngBounds(valid.map(s => [s.coords.lat, s.coords.lng]));
    leafletMap.fitBounds(bounds, { padding: [40, 40] });
  }
}

async function drawRoute() {
  const valid = stops.value.filter(s => s.coords);
  if (valid.length < 2) return;

  const { default: L } = await import('leaflet');
  const coordStr = valid.map(s => `${s.coords.lng},${s.coords.lat}`).join(';');
  const url      = `https://router.project-osrm.org/route/v1/driving/${coordStr}?overview=full&geometries=geojson`;

  try {
    const res  = await fetch(url);
    const data = await res.json();
    const route = data.routes?.[0];
    if (!route) {
      error.value = 'Aucun itinéraire trouvé.';
      return;
    }
    distance.value = route.distance;
    duration.value = route.duration;
    initialDistance.value = route.distance;
    initialOrderIds.value = stops.value.map(s => s.id);

    routeLayer = L.geoJSON(route.geometry, {
      style: { color: themeColor(), weight: 4, opacity: 0.85 },
    }).addTo(leafletMap);
  } catch (e) {
    error.value = 'Calcul d\'itinéraire indisponible (OSRM injoignable).';
  }
}

// Optimisation TSP via OSRM /trip — nearest neighbor + 2-opt côté serveur.
async function optimizeOrder() {
  // Revenir à l'ordre initial si déjà optimisé
  if (optimized.value) {
    const byId = new Map(stops.value.map(s => [s.id, s]));
    stops.value = initialOrderIds.value.map(id => byId.get(id)).filter(Boolean);
    distance.value = initialDistance.value;
    optimized.value = false;
    await rebuildMap();
    return;
  }

  const valid = stops.value.filter(s => s.coords);
  if (valid.length < 3) return;

  optimizing.value = true;
  try {
    const coordStr = valid.map(s => `${s.coords.lng},${s.coords.lat}`).join(';');
    // source=first : on garde le premier arrêt comme départ (souvent point de retrait).
    // roundtrip=false : la tournée se termine au dernier arrêt (pas de retour).
    const url = `https://router.project-osrm.org/trip/v1/driving/${coordStr}?source=first&roundtrip=false&overview=full&geometries=geojson`;
    const res  = await fetch(url);
    const data = await res.json();
    const trip = data.trips?.[0];
    if (!trip) {
      error.value = 'OSRM n\'a pas trouvé d\'optimisation.';
      return;
    }

    // data.waypoints[i].waypoint_index = position du i-ème arrêt original dans l'itinéraire optimisé
    const newOrder = [...data.waypoints]
      .map((wp, originalIdx) => ({ originalIdx, tripIdx: wp.waypoint_index }))
      .sort((a, b) => a.tripIdx - b.tripIdx)
      .map(x => valid[x.originalIdx]);

    // Réinjecte les arrêts sans coords (non géocodés) à la fin
    const noCoords = stops.value.filter(s => !s.coords);
    stops.value = [...newOrder, ...noCoords];

    distance.value = trip.distance;
    duration.value = trip.duration;
    optimized.value = true;

    await rebuildMap(trip.geometry);
  } catch (e) {
    error.value = 'Optimisation indisponible (OSRM injoignable).';
  } finally {
    optimizing.value = false;
  }
}

// Redessine markers + polyline après réordonnancement.
async function rebuildMap(geometry = null) {
  const { default: L } = await import('leaflet');
  markers.forEach(m => m.remove());
  markers = [];
  routeLayer?.remove();
  routeLayer = null;

  let num = 0;
  for (const s of stops.value) {
    if (!s.coords) continue;
    let icon;
    if (s.isStart) {
      icon = L.divIcon({
        className: 'route-marker route-marker--start',
        html: `<span>🏠</span>`,
        iconSize:   [32, 32],
        iconAnchor: [16, 16],
      });
    } else {
      num++;
      icon = L.divIcon({
        className: 'route-marker',
        html: `<span>${num}</span>`,
        iconSize:   [28, 28],
        iconAnchor: [14, 14],
      });
    }
    const m = L.marker([s.coords.lat, s.coords.lng], { icon })
      .addTo(leafletMap)
      .bindPopup(`<strong>${s.client}</strong><br>${s.address}<br><a href="tel:${s.phone}">${s.phone || ''}</a>`);
    markers.push(m);
  }

  if (geometry) {
    routeLayer = L.geoJSON(geometry, {
      style: { color: themeColor(), weight: 4, opacity: 0.85 },
    }).addTo(leafletMap);
  } else {
    // Pas de geometry fournie : retracer via /route classique
    await drawRouteClassic();
  }
}

async function drawRouteClassic() {
  const valid = stops.value.filter(s => s.coords);
  if (valid.length < 2) return;
  const { default: L } = await import('leaflet');
  const coordStr = valid.map(s => `${s.coords.lng},${s.coords.lat}`).join(';');
  try {
    const res  = await fetch(`https://router.project-osrm.org/route/v1/driving/${coordStr}?overview=full&geometries=geojson`);
    const data = await res.json();
    const route = data.routes?.[0];
    if (!route) return;
    routeLayer = L.geoJSON(route.geometry, {
      style: { color: themeColor(), weight: 4, opacity: 0.85 },
    }).addTo(leafletMap);
  } catch { /* silent */ }
}

function formatKm(m) {
  if (!m) return '—';
  return (m / 1000).toFixed(1) + ' km';
}
function formatDuration(s) {
  if (!s) return '—';
  const min = Math.round(s / 60);
  if (min < 60) return `${min} min`;
  const h = Math.floor(min / 60), r = min % 60;
  return `${h}h${r.toString().padStart(2, '0')}`;
}
</script>

<style scoped>
.route-overlay {
  position: fixed; inset: 0; z-index: 300;
  background: rgba(0,0,0,0.55);
  display: flex; align-items: center; justify-content: center;
  padding: 16px;
}
.route-modal {
  background: #fff;
  border-radius: var(--radius-lg);
  width: 100%;
  max-width: 1100px;
  height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.route-modal__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: var(--space-4) var(--space-5);
  border-bottom: 1px solid var(--cream-200);
  gap: var(--space-4);
}
.route-modal__head-right {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  flex-shrink: 0;
}
.pickup-field {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  background: var(--cream-50);
  border: 1px solid var(--cream-300);
  border-radius: var(--radius-md);
  padding: 4px 8px;
}
.pickup-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--gray-600);
  white-space: nowrap;
}
.pickup-input {
  border: none;
  background: transparent;
  font-size: 0.8125rem;
  color: var(--gray-800);
  width: 220px;
  outline: none;
}
.pickup-input::placeholder { color: var(--gray-400); }
.route-modal__head h3 {
  font-family: var(--font-display);
  font-size: 1.125rem;
  margin: 2px 0 0;
}
.route-modal__sub { font-size: 0.75rem; color: var(--gray-500); margin-top: 2px; }
.route-modal__close {
  width: 32px; height: 32px; border-radius: 50%;
  border: 1px solid var(--cream-300);
  background: #fff; cursor: pointer;
}
.route-modal__close:hover { background: var(--rose-500); color: #fff; border-color: var(--rose-500); }

.route-modal__body {
  flex: 1;
  display: grid;
  grid-template-columns: 1fr 340px;
  min-height: 0;
}
.route-map {
  height: 100%;
  background: var(--cream-100);
}
.route-side {
  border-left: 1px solid var(--cream-200);
  background: var(--cream-50);
  overflow-y: auto;
  padding: var(--space-4);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.route-status {
  display: flex; align-items: center; gap: var(--space-2);
  font-size: 0.875rem; color: var(--gray-600);
  padding: var(--space-3);
  background: #fff;
  border-radius: var(--radius-md);
  border: 1px solid var(--cream-200);
}
.route-status--error { color: #b91c1c; }
.spinner {
  width: 14px; height: 14px;
  border: 2px solid var(--rose-200);
  border-top-color: var(--rose-500);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.route-summary {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-3);
  padding: var(--space-3);
  background: #fff;
  border-radius: var(--radius-md);
  border: 1px solid var(--cream-200);
}
.route-summary__label {
  display: block;
  font-size: 0.625rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--gray-400);
  font-weight: 600;
}
.route-summary strong {
  font-family: var(--font-display);
  font-size: 1.125rem;
  color: var(--gray-800);
}

.route-stops {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}
.route-stop {
  display: grid;
  grid-template-columns: 28px 1fr auto;
  gap: var(--space-2);
  align-items: flex-start;
  padding: var(--space-2);
  background: #fff;
  border-radius: var(--radius-sm);
  border: 1px solid var(--cream-200);
}
.route-stop--missing { background: var(--cream-100); opacity: 0.7; }
.route-stop--start {
  background: #f0fdf4;
  border-color: #86efac;
}
.route-stop__num--start {
  background: #16a34a !important;
  font-size: 0.875rem;
}
.route-stop__num {
  width: 24px; height: 24px;
  border-radius: 50%;
  background: var(--rose-500);
  color: #fff;
  font-weight: 700;
  font-size: 0.75rem;
  display: flex; align-items: center; justify-content: center;
  margin-top: 2px;
}
.route-stop--missing .route-stop__num { background: var(--gray-400); }
.route-stop__body { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.route-stop__body strong { font-size: 0.8125rem; color: var(--gray-800); }
.route-stop__addr { font-size: 0.75rem; color: var(--gray-500); }
.route-stop__phone { font-size: 0.75rem; color: var(--rose-600); text-decoration: none; }
.route-stop__phone:hover { text-decoration: underline; }
.route-stop__order {
  font-family: ui-monospace, monospace;
  font-size: 0.6875rem;
  color: var(--gray-400);
}

.route-actions {
  padding-top: var(--space-3);
  border-top: 1px solid var(--cream-200);
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}
.route-savings {
  font-size: 0.8125rem;
  color: #15803d;
  font-weight: 500;
  padding: var(--space-2) var(--space-3);
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: var(--radius-sm);
}

@media (max-width: 768px) {
  .route-modal__body { grid-template-columns: 1fr; grid-template-rows: 50% 50%; }
  .route-side { border-left: none; border-top: 1px solid var(--cream-200); }
}
</style>

<style>
/* Styles globaux pour les marqueurs (Leaflet attache le HTML hors scope) */
.route-marker {
  background: var(--rose-500, var(--rose-500));
  color: #fff;
  border-radius: 50%;
  border: 2px solid #fff;
  box-shadow: 0 2px 6px rgba(0,0,0,0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.75rem;
}
.route-marker--start {
  background: #16a34a;
  font-size: 1rem;
}
</style>
