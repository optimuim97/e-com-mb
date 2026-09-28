# ══════════════════════════════════════════════════════════════════════
# Boutique — image web (SPA Vue compilée + Nginx)
#
# L'image ne contient aucune référence au domaine : l'API est appelée en
# relatif (/api) et le WebSocket dérive son hôte de window.location.
# La même image sert donc la recette et la production.
# ══════════════════════════════════════════════════════════════════════

# ── Étape 1 — compilation du front ────────────────────────────────────
FROM node:20-alpine AS build

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY . .

# Identifiant public Reverb : visible dans le navigateur par conception,
# ce n'est pas un secret (le secret reste côté serveur).
ARG VITE_REVERB_APP_KEY=""
ENV VITE_API_URL="/api" \
    VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY

RUN npm run build

# ── Étape 2 — service des fichiers ────────────────────────────────────
FROM nginx:1.27-alpine

COPY --from=build /app/dist /var/www/frontend
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
