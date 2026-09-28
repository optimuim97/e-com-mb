# Commerce — Marque blanche · Guide de duplication

Boutique e-commerce **duplicable** par client. Chaque client = une copie de ce
dossier, déployée séparément, **configurée depuis le back-office** (nom,
couleurs, logo, textes, modules, zone de livraison) — sans toucher au code.

Base fonctionnelle : le projet ECOM (Rosa Beauty) au dernier commit
(27/09/2026), débarrassé de tout ce qui était propre à Rosa Beauty.

---

## 0. Ce que contient la boutique

**Vitrine** : catalogue (catégories, variantes, gammes), recherche et filtres,
panier + panier flottant, tunnel de commande complet **et** commande rapide
(sans compte), paiement à la livraison / Wave / Orange Money / MTN / carte
(Stripe) / GeniusPay, suivi et annulation de commande, compte client (PIN,
OTP, connexion Google), liste de souhaits, avis, club fidélité, blog,
newsletter, galerie, page « À propos » configurable, contact, SEO (sitemap,
robots, balises par page), PWA installable, français / anglais, XOF / EUR.

**Back-office** : tableau de bord et chiffre d'affaires, commandes (grille,
filtres mémorisés, actions groupées, commandes en double, archivage, relances
de paiement, commandes « à surveiller »), expéditions et extractions (XLSX /
CSV / TXT, feuille de livraison par e-mail), tournées et espace livreur, zones
de livraison et localités, stock (mouvements tracés, inventaire CSV, stock
hebdo, disponibilité en ligne), achats et fournisseurs (coût moyen pondéré),
caisse (POS), promotions automatiques et coupons, file WhatsApp (double
saisie), clients (import CSV), utilisateurs, rôles et permissions fines,
journal d'activité, paramètres.

---

## 1. Créer une instance pour un nouveau client

```bash
# Copier le modèle (sans dépendances ni fichiers d'environnement)
robocopy ECOM-MARQUE-BLANCHE client-<nom> /E /XD node_modules vendor .git _old /XF .env
cd client-<nom>
```

**Backend** (`commerce/`) :

```bash
cp .env.example .env
php artisan key:generate
# Clés Reverb propres à l'instance (REVERB_APP_ID / _KEY / _SECRET) :
php -r 'foreach (["ID"=>6,"KEY"=>20,"SECRET"=>20] as $k=>$n) echo "REVERB_APP_$k=".bin2hex(random_bytes($n/2)).PHP_EOL;'
# Renseigner les sections ★ du .env : APP_NAME, APP_URL, APP_FRONTEND_URL,
# OWNER_EMAIL / OWNER_NAME, base de données, SMTP.
composer install
php artisan migrate --seed        # rôles, permissions, compte propriétaire, zones
```

Le mot de passe du propriétaire est affiché **une seule fois** par le seeder
(ou fixé par `OWNER_PASSWORD`). Aucun compte n'est écrit dans le code.

Instance de **démo commerciale** : `SEED_DEMO=true` ajoute 8 articles neutres.

**Frontend** (`commerce-front/`) :

```bash
cp .env.example .env              # VITE_API_URL, VITE_REVERB_*
npm install
npm run build                     # Node 22
```

En local : `./run-server.sh` (front 5180, API 7010).

---

## 2. Configurer la boutique (100 % depuis l'admin)

Connecté en admin → **Paramètres** :

| Onglet | Réglages |
|---|---|
| **Boutique** | Nom, slogan, e-mail, téléphone, adresse, ville, pays, logo, devise |
| **Marque** | Couleur principale, couleur d'accent (aperçu en direct), favicon |
| **Livraison** | **Ville de la zone locale**, tarif de base, délais, frais |
| **Paiement** | Moyens actifs, numéros Wave / Orange Money / MTN, lien marchand |
| **Accueil** | Variante du hero, textes, photos du carrousel, bandeau |
| **À propos** | Titre, introduction, histoire, photo, vidéo, 3 engagements |
| **SEO** | Titre, description, image de partage |
| **Modules** | Allumer / éteindre les modules (voir §4) |
| **WhatsApp** | Numéro, notifications, bouton d'assistance |

Sans logo, le **nom de la boutique** sert de logo (vitrine, admin, factures).
Tant qu'un texte n'est pas saisi, un texte **neutre** s'affiche : aucune
affirmation produit (« 100 % naturel »…) n'est écrite dans le code.

---

## 3. Thème dynamique

Toute l'app utilise les variables CSS `--rose-*` (couleur principale) et
`--gold-*` (accent) — noms historiques, la teinte réelle est celle de la
boutique. `src/theme/brandTheme.js` lit `brand_primary_color` /
`brand_accent_color`, **génère l'échelle 50→900** et surcharge ces variables
sur `:root`, avec `--rose-500-rgb` pour les transparences. Changer une couleur
re-thématise toute l'app sans modifier un composant.

Là où `var()` ne s'applique pas (tracés Leaflet, attributs SVG calculés), utiliser
`themeColor()` de `brandTheme.js`. Les e-mails utilisent
`App\Support\Shop::primaryColor()`.

**Règle** : ne jamais écrire une couleur de marque en dur (`#e8336d`) dans un
composant — toujours `var(--rose-500)`.

---

## 4. Modules activables

Source unique : `commerce/app/Support/ModuleRegistry.php`.

| Clé | Module | Défaut |
|---|---|---|
| `purchases` | Achats, fournisseurs | éteint |
| `rounds` | Tournées et espace livreur | allumé |
| `blog` | Blog | allumé |
| `newsletter` | Newsletter | allumé |
| `program` | Club fidélité | allumé |
| `reviews` | Avis clients | allumé |
| `abandoned_carts` | Relances de paniers | allumé |
| `gallery` | Galerie de l'accueil | allumé |
| `promotions` | Promotions automatiques | allumé |
| `coupons` | Codes promo | allumé |
| `wishlist` | Liste de souhaits | allumé |
| `whatsapp` | File WhatsApp | allumé |

Un module éteint est **fermé partout** :
- routes API (admin **et** boutique) → middleware `module:<clé>` (403 `module_disabled`) ;
- menu admin → masqué via les préfixes de permission ;
- boutique → `GET /api/settings` expose `module_<clé>_enabled` résolu par le
  serveur ; le front masque liens, pages (`meta: { module: 'blog' }` dans les
  routes) et composants (`settings.moduleEnabled('wishlist')`).
- coupons : `Coupon::isValid()` refuse tout code, quel que soit le tunnel.

Commandes, catalogue, caisse et clients ne sont pas éteignables : ce serait
fermer la boutique.

**Ajouter un module** : une entrée dans `ModuleRegistry::MODULES`, le
middleware sur ses routes, `moduleEnabled()` côté front. L'écran Modules
l'affiche tout seul.

---

## 5. Zone de livraison locale

La règle métier d'origine (« à Abidjan, paiement à la livraison ; ailleurs,
prépaiement ») vaut pour **la ville réglée** dans Paramètres → Livraison
(`delivery_home_city`, défaut **Abidjan**). Ses communes sont les zones de
livraison dont le **groupe contient ce nom** (« Grand Bouaké »…).

Côté code : `Modules\Order\Support\HomeZone`. L'identifiant interne
`abidjan` (colonne `orders.destination`, `PaymentAvailability::ABIDJAN`) et les
noms `AbidjanZone` / `is_abidjan` sont **historiques** : ils désignent la zone
locale, quelle que soit la ville. Ne pas les renommer (données en base).

---

## 6. À faire une fois par client (hors admin)

- `commerce-front/public/manifest.webmanifest` → `name`, `short_name` (PWA)
- `commerce-front/public/logos/favicon-*.png` → icônes PWA (le favicon d'onglet,
  lui, se règle dans l'admin)
- `commerce/.env` → `SEO_INDEXABLE_HOST` (domaine de production)
- CI/CD : variables du dépôt `IMAGE_NAME`, `APP_DIR`, `PRODUCTION_URL`
  (voir l'en-tête de `commerce-front/.github/workflows/deploy.yml`)
- Pages légales : relire `resources/views/legal/` (nom, e-mail et URL sont
  dynamiques, le texte juridique est générique)

---

## 7. Commandes d'exploitation

Préfixe `shop:` (anciennement `rosa:`) :

```bash
php artisan shop:whatsapp-doctor          # diagnostic WhatsApp Cloud API
php artisan shop:export-stock             # inventaire → CSV
php artisan shop:import-stock <csv> --dry-run
php artisan shop:reset-stock --dry-run
php artisan shop:reset-launch --dry-run   # purge des données de test avant ouverture
php artisan shop:import-customers <csv> --dry-run
php artisan shop:demo-data [--purge]      # commandes fictives (démo)
php artisan logo:cache                    # logo en cache pour les factures PDF
```

---

## 8. Propager un correctif à tous les clients ⚠️

Coût du modèle dupliqué : un correctif du modèle doit être reporté sur chaque
copie client. Garder le modèle sous git, et chaque client comme un dépôt
ayant le modèle en remote `upstream` :

```bash
cd client-<nom>
git remote add upstream <url-du-modele>   # une seule fois
git fetch upstream
git merge upstream/main
```

Garder les personnalisations client **dans la base** (réglages) plutôt que
dans le code minimise les conflits.

---

## 9. Limites connues

- **Marché ivoirien** : moyens de paiement (Wave, Orange Money, MTN), devise
  XOF, référentiel des villes et localités de Côte d'Ivoire. Un autre pays
  demande d'adapter ces trois points.
- Quelques fonds très pâles des e-mails restent rosés (seul l'accent suit la
  couleur de la marque).
- Les noms `--rose-*`, `AbidjanZone`, `is_abidjan` sont historiques (voir §3, §5).

---

## 10. Checklist de mise en ligne d'un client

- [ ] `.env` backend (★) + frontend configurés, `APP_KEY` et clés Reverb générées
- [ ] `php artisan migrate --seed` exécuté, mot de passe propriétaire transmis
- [ ] Nom, logo, couleurs, favicon réglés (Paramètres → Boutique / Marque)
- [ ] Ville de la zone locale et zones de livraison vérifiées
- [ ] Modules inutiles éteints
- [ ] Textes Accueil / À propos / SEO saisis
- [ ] Paiements (numéros, clés) et WhatsApp configurés et testés
- [ ] SMTP testé (`GET /api/admin/test/smtp`)
- [ ] Manifest PWA, `SEO_INDEXABLE_HOST`, variables CI/CD
- [ ] DNS + reverse proxy pointant sur la bonne instance
