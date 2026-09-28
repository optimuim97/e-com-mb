#!/bin/bash
# Commerce marque blanche — serveurs de dev (ports dédiés pour tourner à côté
# d'une instance client : front 5180, back 7010).

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRONT_PATH="$ROOT/commerce-front"
BACK_PATH="$ROOT/commerce"

export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
nvm use 22 2>/dev/null

# Frontend Vue (Vite) sur 5180 — proxy /api vers 7010 (voir vite.config.js)
cd "$FRONT_PATH" && npm run dev -- --port 5180 &

# Backend Laravel sur 7010
cd "$BACK_PATH" && php artisan serve --port 7010 &

echo "Marque blanche démarrée → http://localhost:5180  (API: 7010). Ctrl+C pour arrêter."
wait
