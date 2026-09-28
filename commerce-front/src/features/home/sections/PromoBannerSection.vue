<template>
  <section class="promo-banner" :aria-label="campaign.name">
    <div class="container promo-banner__inner">
      <!-- Colonne message : tout ce que la cliente doit savoir de l'opération -->
      <div class="promo-banner__content">
        <span class="promo-banner__badge">{{ badge }}</span>

        <h2 class="promo-banner__title">{{ campaign.name }}</h2>

        <p v-if="headline" class="promo-banner__headline">
          <span class="promo-banner__headline-label">{{ headlineLabel }}</span>
          <strong class="promo-banner__headline-value">{{ headline }}</strong>
        </p>

        <p class="promo-banner__scope">{{ scopeLabel }}</p>

        <p v-if="campaign.description" class="promo-banner__desc">
          {{ campaign.description }}
        </p>

        <!-- Compte à rebours : seulement si l'opération a une fin annoncée.
             Une promotion sans date de fin n'a aucune urgence à afficher. -->
        <div v-if="time" class="promo-banner__countdown">
          <span class="promo-banner__countdown-label">{{ $t('promoBanner.endsIn') }}</span>
          <div class="promo-banner__countdown-time">
            <div class="promo-cell"><strong>{{ time.d }}</strong><small>{{ $t('flashSale.dayShort') }}</small></div>
            <span>:</span>
            <div class="promo-cell"><strong>{{ time.h }}</strong><small>{{ $t('flashSale.hourShort') }}</small></div>
            <span>:</span>
            <div class="promo-cell"><strong>{{ time.m }}</strong><small>{{ $t('flashSale.minShort') }}</small></div>
            <span>:</span>
            <div class="promo-cell"><strong>{{ time.s }}</strong><small>{{ $t('flashSale.secShort') }}</small></div>
          </div>
        </div>

        <RouterLink :to="shopLink" class="btn btn-primary promo-banner__cta">
          {{ $t('promoBanner.cta') }}
        </RouterLink>
      </div>

      <!-- Colonne visuelle. Quand l'opération vise des articles précis, ce sont
           eux qui l'illustrent : la cliente voit ce qu'elle achète et le prix
           remisé, plutôt qu'une photo de marque déjà présente ailleurs sur la
           page. Notre image ne revient que sur une opération catalogue, où
           aucun article ne peut la représenter — et elle reste en dernier. -->
      <ul v-if="showcase.length" class="promo-banner__showcase" :data-count="showcase.length">
        <li v-for="product in showcase" :key="product.id">
          <RouterLink :to="`/products/${product.slug}`" class="promo-item">
            <span class="promo-item__media">
              <img
                v-if="product.images?.[0]?.url"
                :src="product.images[0].url"
                :alt="product.name"
                class="promo-item__img"
                loading="lazy"
              />
              <span v-if="discountBadge(product)" class="promo-item__badge">
                {{ discountBadge(product) }}
              </span>
            </span>
            <span class="promo-item__name">{{ product.name }}</span>
            <span class="promo-item__prices">
              <span class="promo-item__price">{{ formatPrice(sellingPrice(product)) }}</span>
              <span v-if="referencePrice(product)" class="promo-item__price-old">
                {{ formatPrice(referencePrice(product)) }}
              </span>
            </span>
          </RouterLink>
        </li>
      </ul>

      <figure v-else class="promo-banner__visual">
        <img
          :src="settings.seoOgImage || '/placeholders/photo-3.jpg'"
          :alt="$t('promoBanner.imageAlt')"
          class="promo-banner__image"
          loading="lazy"
          decoding="async"
        />
      </figure>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useSettingsStore } from '@/stores/settings'
import { sellingPrice, referencePrice, discountPercent } from '@/utils/pricing'

const props = defineProps({
  /** Charge utile `promo_campaign` de GET /home/stats. */
  campaign: { type: Object, required: true },
})

const { t } = useI18n()
const settings = useSettingsStore()
const formatPrice = (value) => settings.formatPrice(value)

/* Le libellé saisi en administration prime ; sinon la nature de l'opération,
   traduite. Jamais un pourcentage : celui de la bannière est calculé sur les
   prix réels et contredirait le taux nominal de l'opération. */
const badge = computed(() =>
  props.campaign.banner_label || t(`promoBanner.type.${props.campaign.type || 'standard'}`)
)

/**
 * Remise mise en avant.
 *
 * `max_percent` est calculé côté serveur à partir des prix réels : sur une
 * opération ciblée, chaque article peut porter sa propre remise et le taux
 * saisi sur l'opération ne serait pas celui pratiqué. Il vaut null quand la
 * remise est un montant fixe appliqué à tout le catalogue — on annonce alors
 * ce montant.
 */
const headline = computed(() => {
  if (props.campaign.max_percent) return `−${props.campaign.max_percent} %`
  if (props.campaign.discount_type === 'fixed') return `−${formatPrice(props.campaign.value)}`
  return null
})

/* « Jusqu'à » n'a de sens que si les remises diffèrent d'un article à l'autre.
   Sur une opération globale, ou sur un article unique, le taux annoncé est
   exactement celui que la cliente aura. */
const headlineLabel = computed(() =>
  props.campaign.scope === 'product' && props.campaign.products_count > 1
    ? t('promoBanner.upTo')
    : t('promoBanner.discountOf')
)

const scopeLabel = computed(() =>
  props.campaign.scope === 'product'
    ? t('promoBanner.selection', { count: props.campaign.products_count }, props.campaign.products_count)
    : t('promoBanner.wholeCatalogue')
)

/* Articles illustrant l'opération : uniquement sur une opération ciblée. */
const showcase = computed(() =>
  props.campaign.scope === 'product' ? (props.campaign.products ?? []) : []
)

const discountBadge = (product) => {
  const percent = discountPercent(product)
  return percent ? `−${percent} %` : null
}

/* Une opération globale ne rattache aucun article : le filtre du catalogue ne
   remonterait rien, on renvoie donc vers la boutique entière. */
const shopLink = computed(() =>
  props.campaign.scope === 'product'
    ? { path: '/products', query: { promotion: props.campaign.id } }
    : { path: '/products' }
)

/* ── Compte à rebours ──────────────────────────────────────────────────── */
const endsAt = computed(() => {
  if (!props.campaign.ends_at) return null
  const date = new Date(props.campaign.ends_at)
  return Number.isNaN(date.getTime()) ? null : date
})

const time = ref(null)
let timer = null

function pad(value) {
  return String(Math.max(0, Math.floor(value))).padStart(2, '0')
}

function updateCountdown() {
  if (!endsAt.value) return

  const diff = endsAt.value - Date.now()
  if (diff <= 0) {
    // Opération terminée entre deux rafraîchissements du cache d'accueil :
    // on retire le décompte plutôt que d'afficher des zéros.
    time.value = null
    return
  }

  time.value = {
    d: pad(diff / 86400000),
    h: pad((diff % 86400000) / 3600000),
    m: pad((diff % 3600000) / 60000),
    s: pad((diff % 60000) / 1000),
  }
}

onMounted(() => {
  updateCountdown()
  if (endsAt.value) timer = setInterval(updateCountdown, 1000)
})
onBeforeUnmount(() => clearInterval(timer))
</script>

<style scoped>
.promo-banner {
  padding: var(--space-10) 0;
  position: relative;
  overflow: hidden;
  /* Rose poudré vers crème : la palette de la marque, pas le bleu criard des
     bannières promotionnelles génériques. */
  background: linear-gradient(135deg, var(--rose-50) 0%, var(--cream-50) 60%, var(--rose-100) 100%);
}

/* Confettis : quelques points rose et doré, dessinés en CSS pour ne pas
   ajouter une image de fond à charger. Discrets — le fond ne doit pas
   concurrencer le prix. */
.promo-banner::before {
  content: '';
  position: absolute;
  inset: 0;
  pointer-events: none;
  opacity: 0.35;
  background-image:
    radial-gradient(circle, var(--rose-300) 1.5px, transparent 1.5px),
    radial-gradient(circle, var(--gold-400) 1.5px, transparent 1.5px);
  background-size: 130px 130px, 190px 190px;
  background-position: 0 0, 65px 45px;
}

.promo-banner__inner {
  position: relative;
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 0.85fr);
  align-items: center;
  gap: var(--space-8);
}

.promo-banner__content {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--space-3);
}

.promo-banner__badge {
  background: var(--rose-500);
  color: white;
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  padding: 5px 12px;
  border-radius: var(--radius-full);
}

.promo-banner__title {
  font-family: var(--font-display);
  font-size: clamp(1.5rem, 3vw, 2.125rem);
  font-weight: 600;
  line-height: 1.15;
  color: var(--gray-800);
  margin: 0;
}

/* Le taux et son intitulé sur une même ligne de base, l'intitulé aligné en
   haut du chiffre : côte à côte au milieu, ils flottaient. */
.promo-banner__headline {
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
  gap: var(--space-2);
  margin: 0;
}
.promo-banner__headline-label {
  font-size: 0.8125rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--gray-500);
}
.promo-banner__headline-value {
  /* Police sans fantaisie pour les chiffres : ils doivent se lire, pas se
     déchiffrer. */
  font-size: clamp(2rem, 4.5vw, 3rem);
  font-weight: 800;
  line-height: 1;
  color: var(--rose-600);
  font-variant-numeric: tabular-nums;
}

.promo-banner__scope {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--rose-600);
  margin: 0;
}

.promo-banner__desc {
  font-size: 0.9375rem;
  line-height: 1.6;
  color: var(--gray-600);
  max-width: 44ch;
  margin: 0;
}

/* Compte à rebours */
.promo-banner__countdown {
  background: white;
  border: 1px solid var(--rose-200);
  border-radius: var(--radius-lg);
  padding: var(--space-2) var(--space-4);
  box-shadow: 0 4px 14px rgba(var(--rose-500-rgb), 0.08);
  text-align: center;
}
.promo-banner__countdown-label {
  display: block;
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--gray-500);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-bottom: 2px;
}
.promo-banner__countdown-time {
  display: flex;
  align-items: baseline;
  justify-content: center;
  gap: 4px;
  color: var(--rose-600);
}
.promo-cell {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  min-width: 28px;
}
.promo-cell strong {
  font-size: 1.25rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  line-height: 1;
}
.promo-cell small {
  font-size: 0.5625rem;
  color: var(--gray-400);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-top: 2px;
}

.promo-banner__cta {
  margin-top: var(--space-1);
  text-decoration: none;
}

/* ── Articles de l'opération ───────────────────────────────────────────── */
.promo-banner__showcase {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(0, 1fr));
  gap: var(--space-4);
  list-style: none;
  padding: 0;
  margin: 0 0 0 auto;
  width: 100%;
}
/* Un article seul n'a pas à s'étaler sur toute la colonne. */
.promo-banner__showcase[data-count='1'] { max-width: 260px; }
.promo-banner__showcase[data-count='2'] { max-width: 380px; }

.promo-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
  text-decoration: none;
  transition: transform var(--transition-normal);
}
.promo-item:hover { transform: translateY(-3px); }

.promo-item__media {
  position: relative;
  display: block;
  aspect-ratio: 1 / 1;
  border-radius: var(--radius-lg);
  overflow: hidden;
  background: white;
  border: 1px solid var(--cream-200);
  box-shadow: 0 10px 26px rgba(var(--rose-500-rgb), 0.12);
}
.promo-item__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.promo-item__badge {
  position: absolute;
  top: 8px;
  left: 8px;
  background: var(--rose-500);
  color: white;
  font-size: 0.6875rem;
  font-weight: 700;
  padding: 3px 9px;
  border-radius: var(--radius-full);
}

.promo-item__name {
  font-size: 0.8125rem;
  color: var(--gray-700);
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.promo-item__prices {
  display: flex;
  align-items: baseline;
  gap: 6px;
  flex-wrap: wrap;
}
.promo-item__price {
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--rose-600);
}
.promo-item__price-old {
  font-size: 0.75rem;
  color: var(--gray-400);
  text-decoration: line-through;
}

/* ── Notre image (opération catalogue) ─────────────────────────────────── */
.promo-banner__visual {
  margin: 0 0 0 auto;
  width: 100%;
  max-width: 420px;
  border-radius: var(--radius-xl);
  overflow: hidden;
  box-shadow: 0 14px 36px rgba(var(--rose-500-rgb), 0.15);
}
.promo-banner__image {
  display: block;
  width: 100%;
  aspect-ratio: 4 / 3;
  object-fit: cover;
}

@media (max-width: 900px) {
  .promo-banner__inner {
    grid-template-columns: 1fr;
    gap: var(--space-6);
  }
  .promo-banner__showcase,
  .promo-banner__visual { margin: 0; }
  .promo-banner__showcase[data-count='1'] { max-width: 220px; }
}

/* ── Mobile ────────────────────────────────────────────────────────────────
   Le bandeau tombe juste sous l'accroche : chaque pixel qu'il prend repousse
   le catalogue. On resserre la colonne, on donne au taux la place qu'il
   mérite, et les articles défilent au doigt plutôt que de se serrer à trois
   sur 340 px. */
@media (max-width: 640px) {
  .promo-banner { padding: var(--space-8) 0; }

  .promo-banner__inner { gap: var(--space-5); }
  .promo-banner__content { gap: var(--space-2); }

  /* Le chiffre est l'information : il gagne ce que les marges perdent. */
  .promo-banner__headline-value { font-size: 2.5rem; }
  .promo-banner__desc { font-size: 0.875rem; }

  /* Décompte sur une seule ligne, bord à bord : deux lignes centrées
     coûtaient une hauteur d'écran pour quatre nombres. */
  .promo-banner__countdown {
    align-self: stretch;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-3);
    text-align: left;
    padding: var(--space-2) var(--space-4);
  }
  .promo-banner__countdown-label { margin-bottom: 0; }
  .promo-banner__countdown-time { justify-content: flex-end; }
  .promo-cell { min-width: 26px; }
  .promo-cell strong { font-size: 1.125rem; }

  .promo-banner__cta { width: 100%; text-align: center; }

  /* Défilement horizontal aimanté, débordant jusqu'aux bords de l'écran :
     l'article amorcé à droite dit qu'il y en a d'autres, ce qu'une grille
     tronquée ne dit pas. La gouttière rendue ici est celle de `.container`
     en dessous de 768 px. */
  .promo-banner__showcase {
    display: flex;
    gap: var(--space-3);
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    margin-inline: calc(var(--space-4) * -1);
    padding-inline: var(--space-4);
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
  }
  .promo-banner__showcase::-webkit-scrollbar { display: none; }
  .promo-banner__showcase > li {
    flex: 0 0 44%;
    scroll-snap-align: start;
  }
  .promo-banner__showcase[data-count='2'] { max-width: none; }
  /* Un article seul n'a rien à amorcer : il s'affiche en grand. */
  .promo-banner__showcase[data-count='1'] {
    max-width: none;
    overflow-x: visible;
  }
  .promo-banner__showcase[data-count='1'] > li { flex: 0 0 60%; }

  .promo-item__name { font-size: 0.75rem; }
}
</style>
