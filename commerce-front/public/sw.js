/* ═══════════════════════════════════════════════════════════════════════════
   Boutique — Service Worker

   Objectif : consultation utilisable en connexion faible ou intermittente, et
   installation sur l'écran d'accueil. Pas de mode hors-ligne complet.

   Écrit à la main plutôt qu'avec Workbox : les règles ci-dessous tiennent en
   une page, et un service worker est le seul fichier capable de rendre un site
   inutilisable pour un visiteur déjà venu — mieux vaut pouvoir le relire en
   entier que de dépendre d'une configuration générée.

   ── Règles de prudence appliquées ─────────────────────────────────────────
   1. L'API n'est JAMAIS mise en cache. Prix, stocks, commandes et session
      doivent toujours venir du serveur : servir un stock périmé fait vendre
      un produit épuisé.
   2. index.html est servi en « réseau d'abord ». En cache d'abord, une
      cliente resterait bloquée sur une version périmée après chaque
      déploiement.
   3. Seuls les assets à nom haché par Vite sont servis « cache d'abord » :
      leur URL change à chaque build, ils ne peuvent pas devenir périmés.
   4. Aucune activation silencieuse : la nouvelle version attend que la page
      la réclame (voir src/pwa.js). Sinon un onglet ouvert mélangerait
      l'ancien HTML et les nouveaux assets.
   ═════════════════════════════════════════════════════════════════════════ */

// ⚠ Incrémenter à chaque changement de ce fichier : c'est ce qui déclenche le
// nettoyage des anciens caches à l'activation.
const VERSION      = 'v1';
const CACHE_SHELL  = `shop-shell-${VERSION}`;
const CACHE_ASSETS = `shop-assets-${VERSION}`;
const CACHE_IMAGES = `shop-images-${VERSION}`;

const SHELL_URL   = '/index.html';
const OFFLINE_URL = '/offline.html';

// Plafond du cache images : au-delà, on retire les plus anciennes. Le catalogue
// contient des photos lourdes, sans plafond le quota du navigateur saute.
const MAX_IMAGES = 60;

/** Chemins qui ne doivent jamais passer par le cache. */
const NEVER_CACHE = [
  '/api/',
  '/sanctum/',
  '/broadcasting/',
  '/app/',          // WebSocket Reverb
  '/admin',         // back-office : toujours des données fraîches
  '/checkout',
  '/payment/',
];

const isNeverCached = (url) =>
  NEVER_CACHE.some((prefix) => url.pathname.startsWith(prefix));

/** Assets produits par Vite : nom haché, donc immuables. */
const isHashedAsset = (url) => url.pathname.startsWith('/assets/');

const isImage = (url) =>
  /\.(png|jpe?g|gif|webp|avif|svg|ico)$/i.test(url.pathname);

/* ── Installation ───────────────────────────────────────────────────────── */

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_SHELL).then((cache) =>
      // `reload` force le réseau : sans ça, on précacherait la copie que le
      // navigateur a déjà en cache HTTP, c'est-à-dire potentiellement l'ancienne.
      cache.addAll([
        new Request(SHELL_URL,   { cache: 'reload' }),
        new Request(OFFLINE_URL, { cache: 'reload' }),
      ]).catch(() => {
        // Un échec de précache ne doit pas empêcher l'installation : le site
        // reste parfaitement fonctionnel en ligne sans cache.
      })
    )
  );
  // Pas de skipWaiting() ici — l'activation est déclenchée par la page.
});

/* ── Activation : purge des caches d'une version précédente ─────────────── */

self.addEventListener('activate', (event) => {
  const valides = [CACHE_SHELL, CACHE_ASSETS, CACHE_IMAGES];

  event.waitUntil(
    caches.keys()
      .then((noms) => Promise.all(
        noms.filter((nom) => nom.startsWith('shop-') && !valides.includes(nom))
            .map((nom) => caches.delete(nom))
      ))
      .then(() => self.clients.claim())
  );
});

/* ── Mise à jour pilotée par la page ────────────────────────────────────── */

self.addEventListener('message', (event) => {
  if (event.data === 'SKIP_WAITING') self.skipWaiting();
});

/* ── Interception des requêtes ──────────────────────────────────────────── */

self.addEventListener('fetch', (event) => {
  const { request } = event;

  // POST/PUT/DELETE et requêtes vers d'autres origines : on ne touche à rien.
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;
  if (isNeverCached(url)) return;

  // Navigation (barre d'adresse, lien entrant) → réseau d'abord.
  if (request.mode === 'navigate') {
    event.respondWith(navigationStrategy(request));
    return;
  }

  if (isHashedAsset(url)) {
    event.respondWith(cacheFirst(request, CACHE_ASSETS));
    return;
  }

  if (isImage(url)) {
    event.respondWith(staleWhileRevalidate(request, CACHE_IMAGES));
  }
});

/* ── Stratégies ─────────────────────────────────────────────────────────── */

/**
 * Réseau d'abord, repli sur la coquille en cache, puis page hors-ligne.
 * Garantit qu'un déploiement est pris en compte dès la visite suivante.
 */
async function navigationStrategy(request) {
  try {
    const reponse = await fetch(request);
    if (reponse.ok) {
      const copie = reponse.clone();
      caches.open(CACHE_SHELL).then((cache) => cache.put(SHELL_URL, copie));
    }
    return reponse;
  } catch {
    return (await caches.match(SHELL_URL))
        ?? (await caches.match(OFFLINE_URL))
        ?? Response.error();
  }
}

/** Cache d'abord — réservé aux URL immuables (assets hachés). */
async function cacheFirst(request, nomCache) {
  const enCache = await caches.match(request);
  if (enCache) return enCache;

  const reponse = await fetch(request);
  if (reponse.ok) {
    const cache = await caches.open(nomCache);
    cache.put(request, reponse.clone());
  }
  return reponse;
}

/** Cache immédiat + rafraîchissement en arrière-plan (images). */
async function staleWhileRevalidate(request, nomCache) {
  const cache   = await caches.open(nomCache);
  const enCache = await cache.match(request);

  const reseau = fetch(request)
    .then((reponse) => {
      if (reponse.ok) {
        cache.put(request, reponse.clone()).then(() => trim(nomCache, MAX_IMAGES));
      }
      return reponse;
    })
    .catch(() => enCache);

  return enCache ?? reseau;
}

/** Limite un cache à N entrées, en retirant les plus anciennes. */
async function trim(nomCache, max) {
  const cache = await caches.open(nomCache);
  const cles  = await cache.keys();
  if (cles.length <= max) return;

  for (const cle of cles.slice(0, cles.length - max)) {
    await cache.delete(cle);
  }
}
