<template>
  <div class="admin-shell">
    <!-- Sidebar -->
    <aside class="admin-sidebar">
      <!-- Logo -->
      <RouterLink to="/" class="admin-sidebar__logo" :aria-label="settingsStore.shopName + ' — Accueil'">
        <BrandLogo img-class="admin-sidebar__logo-img" size="sm" />
        <span class="admin-sidebar__logo-badge">Administration</span>
      </RouterLink>

      <!-- Nav groupé -->
      <nav class="admin-sidebar__nav" aria-label="Navigation admin">
        <template v-for="group in visibleGroups" :key="group.key">
          <span class="nav-group__label">{{ group.label }}</span>
          <RouterLink
            v-for="item in group.items"
            :key="item.to"
            :to="item.to"
            class="admin-nav-link"
            :class="{ 'admin-nav-link--active': isActive(item.to) }"
          >
            <component :is="item.icon" class="admin-nav-link__icon" />
            <span class="admin-nav-link__text">{{ item.label }}</span>
            <span
              v-if="badgeFor(item.to)"
              class="admin-nav-link__badge"
              :title="`${badgeFor(item.to)} en attente`"
            >{{ badgeFor(item.to) }}</span>
          </RouterLink>
        </template>
      </nav>

      <!-- User -->
      <div class="admin-sidebar__user">
        <div class="admin-sidebar__avatar">
          {{ auth.user?.name?.[0]?.toUpperCase() }}
        </div>
        <div class="admin-sidebar__user-info">
          <p>{{ auth.user?.name }}</p>
          <span>{{ auth.user?.email }}</span>
        </div>
        <button @click="handleLogout" class="admin-sidebar__logout" title="Se déconnecter">
          <ArrowLeftOnRectangleIcon class="w-4 h-4" />
        </button>
      </div>
    </aside>

    <!-- Contenu principal -->
    <div class="admin-main">
      <!-- Topbar -->
      <header class="admin-topbar">
        <div class="admin-topbar__left">
          <h1 class="admin-topbar__title">{{ currentTitle }}</h1>
        </div>
        <div class="admin-topbar__right">
          <NotificationBell />
          <RouterLink to="/" class="admin-topbar__shop-link">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
              <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            Boutique
          </RouterLink>
        </div>
      </header>

      <main class="admin-content">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<script setup>
import BrandLogo from '@/components/ui/BrandLogo.vue'
import { computed, onMounted, onBeforeUnmount } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import {
  Squares2X2Icon,
  ShoppingBagIcon,
  TagIcon,
  TicketIcon,
  UsersIcon,
  ArrowLeftOnRectangleIcon,
  FolderIcon,
  Cog6ToothIcon,
  NewspaperIcon,
  StarIcon,
  ChatBubbleLeftRightIcon,
  EnvelopeIcon,
  TruckIcon,
  PaperAirplaneIcon,
  BellAlertIcon,
  CalculatorIcon,
  ShieldCheckIcon,
  KeyIcon,
  MapPinIcon,
  ClipboardDocumentListIcon,
  ClipboardDocumentCheckIcon,
  ShoppingCartIcon,
  SwatchIcon,
  PhotoIcon,
  ArrowsRightLeftIcon,
  CalendarDaysIcon,
  BuildingStorefrontIcon,
  ReceiptPercentIcon,
  ClockIcon,
} from '@heroicons/vue/24/outline';
import { useAuthStore } from '@/features/auth/auth.store';
import { useSettingsStore } from '@/stores/settings';
import { useAdminNotificationsStore } from '@/admin/stores/adminNotifications.store';
import { useOrderStatsStore } from '@/admin/stores/orderStats.store';
import NotificationBell from '@/admin/components/NotificationBell.vue';

const auth       = useAuthStore();
const route      = useRoute();
const router     = useRouter();
const notifStore = useAdminNotificationsStore();
const orderStats = useOrderStatsStore();

onMounted(() => {
  notifStore.subscribe();
  orderStats.start();
});
onBeforeUnmount(() => {
  notifStore.unsubscribe();
  orderStats.stop();
});

function badgeFor(to) {
  if (to === '/admin/orders') return orderStats.pending || null;
  return null;
}

// Chaque item porte la permission qui conditionne son affichage.
// Un item sans `permission` est visible par tout le staff.
const NAV_GROUPS = [
  {
    key: 'ops',
    label: 'Opérations',
    items: [
      { to: '/admin',        label: 'Dashboard',    icon: Squares2X2Icon,           permission: 'dashboard.view' },
      { to: '/admin/orders', label: 'Commandes',    icon: ClipboardDocumentListIcon, permission: 'orders.view'   },
      { to: '/admin/pos',    label: 'Caisse / POS', icon: CalculatorIcon,           permission: 'pos.use'        },
      { to: '/admin/whatsapp', label: 'Messages WhatsApp', icon: ChatBubbleLeftRightIcon, permission: 'whatsapp.view' },
    ],
  },
  {
    key: 'delivery',
    label: 'Livraison',
    items: [
      // Les commandes à expédier vivent sous « Livraison » et non sous
      // « Opérations » : ce n'est pas une seconde liste de commandes, c'est
      // le poste de travail de qui prépare les envois hors Abidjan.
      { to: '/admin/a-traiter',       label: 'À traiter',         icon: BellAlertIcon, permission: 'orders.view' },
      { to: '/admin/expeditions',     label: 'Expéditions',       icon: PaperAirplaneIcon, permission: 'orders.view' },
      { to: '/admin/tournees',        label: 'Tournées',          icon: ClipboardDocumentCheckIcon, permission: 'rounds.view' },
      { to: '/admin/deliveries',      label: 'Livraisons',        icon: TruckIcon,  permission: 'deliveries.view'     },
      { to: '/admin/delivery-zones',  label: 'Zones de livraison', icon: MapPinIcon, permission: 'delivery_zones.view' },
      { to: '/admin/localites',       label: 'Villes et communes', icon: MapPinIcon, permission: 'localities.view' },
    ],
  },
  {
    key: 'catalog',
    label: 'Catalogue',
    items: [
      { to: '/admin/products',      label: 'Produits',    icon: ShoppingBagIcon, permission: 'products.view'      },
      { to: '/admin/categories',    label: 'Catégories',  icon: FolderIcon,      permission: 'categories.view'    },
      { to: '/admin/product-lines', label: 'Gammes',      icon: SwatchIcon,      permission: 'product_lines.view' },
      { to: '/admin/stock-semaine',    label: 'Stock de la semaine', icon: CalendarDaysIcon,    permission: 'stock.view' },
      { to: '/admin/mouvements-stock', label: 'Mouvements de stock', icon: ArrowsRightLeftIcon, permission: 'stock.view' },
    ],
  },
  {
    // Groupe entier conditionné à l'interrupteur du module : afficher « Achats »
    // sur une boutique qui ne l'a pas activé mènerait à un 403.
    key: 'purchases',
    label: 'Achats',
    requiresSetting: 'module_purchases_enabled',
    items: [
      { to: '/admin/achats',        label: "Bons d'achat", icon: ReceiptPercentIcon,      permission: 'purchases.view' },
      { to: '/admin/fournisseurs',  label: 'Fournisseurs', icon: BuildingStorefrontIcon,  permission: 'suppliers.view' },
    ],
  },
  {
    key: 'marketing',
    label: 'Marketing',
    items: [
      { to: '/admin/promotions',      label: 'Promotions',         icon: TagIcon,                 permission: 'promotions.view'      },
      { to: '/admin/coupons',         label: 'Coupons',            icon: TicketIcon,              permission: 'coupons.view'         },
      { to: '/admin/programme',       label: 'Club fidélité',      icon: StarIcon,                permission: 'program.view'         },
      { to: '/admin/reviews',         label: 'Avis clients',       icon: ChatBubbleLeftRightIcon, permission: 'reviews.view'         },
      { to: '/admin/blog',            label: 'Blog',               icon: NewspaperIcon,           permission: 'blog.view'            },
      { to: '/admin/galerie',         label: 'Galerie photos',    icon: PhotoIcon,               permission: 'gallery.view'         },
      { to: '/admin/newsletter',      label: 'Newsletter',         icon: EnvelopeIcon,            permission: 'newsletter.view'      },
      { to: '/admin/abandoned-carts', label: 'Paniers abandonnés', icon: ShoppingCartIcon,        permission: 'abandoned_carts.view' },
    ],
  },
  {
    key: 'team',
    label: 'Équipe',
    items: [
      { to: '/admin/users',       label: 'Clients',      icon: UsersIcon,       permission: 'customers.view'   },
      { to: '/admin/roles',       label: 'Rôles',        icon: ShieldCheckIcon, permission: 'roles.view'       },
      { to: '/admin/permissions', label: 'Permissions',  icon: KeyIcon,         permission: 'permissions.view' },
      // Le journal dit qui a fait quoi : outil d'encadrement, d'où sa place
      // sous « Équipe » et non sous « Système ».
      { to: '/admin/journal',     label: 'Journal des actions', icon: ClockIcon, permission: 'activity.view'   },
    ],
  },
  {
    key: 'system',
    label: 'Système',
    items: [
      { to: '/admin/settings', label: 'Paramètres', icon: Cog6ToothIcon, permission: 'settings.view' },
    ],
  },
];

const TITLES = {
  'admin.dashboard':           'Vue d\'ensemble',
  'admin.products':            'Produits',
  'admin.products.create':     'Nouveau produit',
  'admin.products.edit':       'Modifier le produit',
  'admin.categories':          'Catégories',
  'admin.orders':              'Commandes',
  'admin.order':               'Détail commande',
  'admin.pos':                 'Caisse / POS',
  'admin.whatsapp':            'Messages WhatsApp',
  'admin.expeditions':         'Expéditions',
  'admin.rounds':              'Tournées',
  'admin.deliveries':          'Livraisons',
  'admin.attention':           'À traiter',
  'admin.delivery-zones':      'Zones de livraison',
  'admin.localities':          'Villes et communes',
  'admin.weekly-stock':        'Stock de la semaine',
  'admin.stock-movements':     'Mouvements de stock',
  'admin.purchase-orders':     "Bons d'achat",
  'admin.suppliers':           'Fournisseurs',
  'admin.promotions':          'Opérations commerciales',
  'admin.coupons':             'Coupons & promotions',
  'admin.programme':           'Club fidélité',
  'admin.reviews':             'Avis clients',
  'admin.blog':                'Blog & Actualités',
  'admin.blog.create':         'Nouvel article',
  'admin.blog.edit':           'Modifier l\'article',
  'admin.newsletter':          'Newsletter',
  'admin.abandoned-carts':     'Paniers abandonnés',
  'admin.users':               'Utilisateurs',
  'admin.roles':               'Rôles',
  'admin.permissions':         'Permissions',
  'admin.activity':            'Journal des actions',
  'admin.settings':            'Paramètres boutique',
  'admin.product-lines':       'Gammes de produits',
  'admin.product-lines.create':'Nouvelle gamme',
  'admin.product-lines.edit':  'Modifier la gamme',
};

const settingsStore = useSettingsStore();
settingsStore.fetch();

const visibleGroups = computed(() =>
  NAV_GROUPS
    // Un groupe peut dépendre d'un module désactivé : l'afficher mènerait à un
    // écran qui refuse l'accès, ce qui se lit comme une panne.
    .filter(group => !group.requiresSetting || settingsStore.data[group.requiresSetting] === 'true')
    .map(group => ({
      ...group,
      items: group.items.filter(item => !item.permission || auth.can(item.permission)),
    }))
    .filter(group => group.items.length > 0)
);

const currentTitle = computed(() => TITLES[route.name] ?? 'Admin');
const isActive = (path) =>
  route.path === path || (path !== '/admin' && route.path.startsWith(path));

async function handleLogout() {
  await auth.logout();
  router.push('/login');
}
</script>

<style scoped>
.admin-shell {
  height: 100vh;
  display: flex;
  overflow: hidden;
  background: #f5f4f2;
}

/* ══ Sidebar ══ */
.admin-sidebar {
  width: 232px;
  flex-shrink: 0;
  background: #fff;
  border-right: 1px solid #ede9e4;
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden;
}

/* ── Logo ── */
.admin-sidebar__logo {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 5px;
  padding: 14px 16px 10px;
  border-bottom: 1px solid #ede9e4;
  text-decoration: none;
  transition: opacity 0.15s;
  flex-shrink: 0;
}
.admin-sidebar__logo:hover { opacity: 0.8; }

.admin-sidebar__logo-img {
  width: 100%;
  max-width: 130px;
  height: auto;
  display: block;
  object-fit: contain;
}
.admin-sidebar__logo-badge {
  font-size: 0.5rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--color-primary, var(--rose-500));
  font-weight: 700;
  background: var(--rose-50);
  border: 1px solid var(--rose-100);
  border-radius: 999px;
  padding: 2px 7px;
  line-height: 1.6;
}

/* ── Nav ── */
.admin-sidebar__nav {
  flex: 1;
  padding: 6px 8px 8px;
  display: flex;
  flex-direction: column;
  gap: 1px;
  overflow-y: auto;
  overflow-x: hidden;
  scrollbar-width: thin;
  scrollbar-color: #ede9e4 transparent;
}

.nav-group__label {
  display: block;
  font-size: 0.5rem;
  font-weight: 700;
  letter-spacing: 0.13em;
  text-transform: uppercase;
  color: #c0b8b2;
  padding: 10px 8px 3px;
  user-select: none;
  white-space: nowrap;
}
.nav-group__label:first-child { padding-top: 2px; }

.admin-nav-link {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 7px 9px;
  border-radius: 7px;
  font-size: 0.8rem;
  font-weight: 500;
  color: #6b6560;
  transition: background 0.12s, color 0.12s;
  position: relative;
  text-decoration: none;
  white-space: nowrap;
}
.admin-nav-link__icon {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
  opacity: 0.75;
}
.admin-nav-link__text { flex: 1; min-width: 0; }

.admin-nav-link__badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 18px;
  padding: 0 5px;
  border-radius: 999px;
  background: var(--rose-500, var(--rose-500));
  color: #fff;
  font-size: 0.625rem;
  font-weight: 700;
  line-height: 1;
  flex-shrink: 0;
}

.admin-nav-link:hover {
  background: #fff5f8;
  color: var(--rose-600);
}
.admin-nav-link:hover .admin-nav-link__icon { opacity: 1; }

.admin-nav-link--active {
  background: var(--rose-50);
  color: var(--rose-600);
  font-weight: 600;
}
.admin-nav-link--active .admin-nav-link__icon { opacity: 1; }
.admin-nav-link--active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 6px;
  bottom: 6px;
  width: 3px;
  background: var(--rose-500);
  border-radius: 0 3px 3px 0;
}
.admin-nav-link--active .admin-nav-link__badge {
  background: var(--rose-600);
}

/* ── User ── */
.admin-sidebar__user {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 9px 12px;
  border-top: 1px solid #ede9e4;
  flex-shrink: 0;
}
.admin-sidebar__avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--rose-400), var(--rose-600));
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.75rem;
  flex-shrink: 0;
}
.admin-sidebar__user-info {
  flex: 1;
  min-width: 0;
}
.admin-sidebar__user-info p {
  font-size: 0.75rem;
  font-weight: 600;
  color: #3d3230;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin: 0;
}
.admin-sidebar__user-info span {
  font-size: 0.6875rem;
  color: #a09490;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  display: block;
}
.admin-sidebar__logout {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: transparent;
  color: #b0a8a2;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s;
  flex-shrink: 0;
}
.admin-sidebar__logout:hover {
  background: #fee2e2;
  color: #ef4444;
}

/* ══ Main ══ */
.admin-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  height: 100%;
  overflow: hidden;
}

/* ── Topbar ── */
.admin-topbar {
  height: 52px;
  background: rgba(255,255,255,0.97);
  border-bottom: 1px solid #ede9e4;
  padding: 0 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
  z-index: 20;
}
.admin-topbar__title {
  font-family: var(--font-display);
  font-size: 1.125rem;
  font-weight: 500;
  color: #1a1410;
  letter-spacing: -0.01em;
  margin: 0;
}
.admin-topbar__right {
  display: flex;
  align-items: center;
  gap: 10px;
}
.admin-topbar__shop-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 999px;
  border: 1px solid #ede9e4;
  background: #faf9f7;
  font-size: 0.75rem;
  font-weight: 500;
  color: #6b6560;
  transition: all 0.12s;
  text-decoration: none;
}
.admin-topbar__shop-link:hover {
  border-color: var(--rose-100);
  background: #fff5f8;
  color: var(--rose-600);
}

/* ── Content ── */
.admin-content {
  flex: 1;
  padding: 20px 24px;
  overflow-y: auto;
  overflow-x: hidden;
}

/* ══ Responsive ══ */
@media (max-width: 960px) {
  .admin-sidebar { width: 60px; }
  .admin-sidebar__logo-img { max-width: 36px; }
  .admin-sidebar__logo-badge,
  .admin-sidebar__user-info,
  .admin-nav-link__text,
  .admin-nav-link__badge,
  .nav-group__label { display: none; }
  .admin-nav-link { justify-content: center; padding: 10px; }
  .admin-nav-link__icon { width: 20px; height: 20px; opacity: 1; }
  .admin-sidebar__logo { padding: 12px 8px; }
  .admin-sidebar__user { justify-content: center; padding: 10px; }
  .admin-topbar { padding: 0 16px; }
  .admin-content { padding: 16px; }
}
</style>
