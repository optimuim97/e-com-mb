/**
 * Laravel Echo — Reverb WebSocket plugin
 *
 * Usage in main.js:
 *   import echoPlugin from '@/plugins/echo'
 *   app.use(echoPlugin)
 *
 * The Echo instance is then available via:
 *   import { useEcho } from '@/plugins/echo'
 *   const echo = useEcho()
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echoInstance = null;

export function createEcho() {
    if (echoInstance) return echoInstance;

    const appKey = import.meta.env.VITE_REVERB_APP_KEY;

    if (!appKey) {
        console.warn('[Echo] VITE_REVERB_APP_KEY is not set — WebSocket disabled.');
        return null;
    }

    window.Pusher = Pusher;

    // En production le WebSocket passe par le même domaine que la page
    // (nginx proxifie /app/ vers Reverb). On dérive donc l'hôte de
    // window.location au lieu de le figer à la compilation : la même image
    // Docker sert ainsi la recette et la production.
    // Les variables VITE_REVERB_* restent prioritaires pour le dev local,
    // où le serveur Reverb tourne sur un autre port que Vite.
    const isSecure  = window.location.protocol === 'https:';
    const wsHost    = import.meta.env.VITE_REVERB_HOST   || window.location.hostname;
    const wsScheme  = import.meta.env.VITE_REVERB_SCHEME || (isSecure ? 'https' : 'http');
    const wsPort    = Number(import.meta.env.VITE_REVERB_PORT) || (isSecure ? 443 : 8080);

    echoInstance = new Echo({
        broadcaster:     'reverb',
        key:             appKey,
        wsHost:          wsHost,
        wsPort:          wsPort,
        wssPort:         wsPort,
        scheme:          wsScheme,
        forceTLS:        wsScheme === 'https',
        enabledTransports: ['ws', 'wss'],
        // Authorizer custom : on (re-)lit le token à CHAQUE handshake d'auth,
        // pas seulement à l'init. Sinon, un login après chargement de la page
        // garde l'ancien token (souvent vide) → 401 sur /broadcasting/auth.
        authorizer: (channel) => ({
            authorize: (socketId, callback) => {
                const token = localStorage.getItem('auth_token') ?? '';
                fetch('/broadcasting/auth', {
                    method:  'POST',
                    headers: {
                        'Content-Type':  'application/json',
                        'Accept':        'application/json',
                        'Authorization': `Bearer ${token}`,
                    },
                    body: JSON.stringify({
                        socket_id:    socketId,
                        channel_name: channel.name,
                    }),
                })
                    .then(async (res) => {
                        if (!res.ok) {
                            const body = await res.text();
                            throw new Error(`broadcasting/auth ${res.status}: ${body}`);
                        }
                        return res.json();
                    })
                    .then((data) => callback(null, data))
                    .catch((err) => {
                        console.warn('[Echo] auth failed:', err.message);
                        callback(err, null);
                    });
            },
        }),
    });

    return echoInstance;
}

/** Retrieve the shared Echo instance — may return null if key is missing. */
export function useEcho() {
    if (!echoInstance) {
        createEcho();
    }
    return echoInstance;
}

/** Update the auth token after login / token refresh. */
export function updateEchoToken(token) {
    if (!echoInstance) return;
    echoInstance.options.auth.headers.Authorization = `Bearer ${token}`;
    echoInstance.connector.pusher.config.auth = echoInstance.options.auth;
}

/** Vue plugin */
export default {
    install(app) {
        // createEcho() may return null if VITE_REVERB_APP_KEY is unset
        const echo = createEcho();
        app.config.globalProperties.$echo = echo;
        app.provide('echo', echo);
    },
};
