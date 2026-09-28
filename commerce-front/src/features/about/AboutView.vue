<template>
  <div class="ap">
    <!-- ── Hero ── -->
    <section class="ap-hero">
      <div class="container ap-hero__inner">
        <div class="ap-hero__text">
          <span class="eyebrow">{{ $t('aboutPage.eyebrow') }}</span>
          <h1 class="display-lg ap-hero__title">{{ title }}</h1>
          <p class="ap-hero__desc">{{ intro }}</p>
          <RouterLink to="/products" class="btn btn-primary">
            {{ $t('aboutPage.ctaProducts') }}
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
          </RouterLink>
        </div>
        <div class="ap-hero__img-wrap">
          <img :src="image" :alt="settings.shopName" class="ap-hero__img" v-img-fallback />
        </div>
      </div>
    </section>

    <!-- ── Histoire (+ vidéo facultative) ── -->
    <section v-if="storyParagraphs.length || videoUrl" class="ap-story">
      <div class="container ap-story__inner" :class="{ 'ap-story__inner--solo': !videoUrl }">
        <div v-if="videoUrl" class="ap-story__video">
          <VideoPlayer
            :src="videoUrl"
            :poster="videoPoster"
            aspect-ratio="4 / 5"
            max-height="540px"
          />
        </div>
        <div v-if="storyParagraphs.length" class="ap-story__text">
          <span class="eyebrow">{{ $t('aboutPage.storyEyebrow') }}</span>
          <p v-for="(p, i) in storyParagraphs" :key="i">{{ p }}</p>
        </div>
      </div>
    </section>

    <!-- ── Engagements ── -->
    <section class="ap-values">
      <div class="container">
        <header class="ap-values__head">
          <span class="eyebrow">{{ $t('aboutPage.valuesEyebrow') }}</span>
          <h2 class="display-md">{{ $t('aboutPage.valuesTitle') }}</h2>
        </header>
        <div class="ap-values__grid">
          <article v-for="v in values" :key="v.key" class="ap-value">
            <span class="ap-value__icon" v-html="v.icon"></span>
            <h3>{{ v.title }}</h3>
            <p>{{ v.desc }}</p>
          </article>
        </div>
      </div>
    </section>

    <!-- ── CTA ── -->
    <section class="ap-cta">
      <div class="container ap-cta__inner">
        <h2 class="display-md ap-cta__headline">{{ $t('aboutPage.ctaHeadline') }}</h2>
        <p>{{ $t('aboutPage.ctaDesc') }}</p>
        <div class="ap-cta__btns">
          <RouterLink to="/products" class="btn btn-primary">{{ $t('aboutPage.ctaProducts') }}</RouterLink>
          <RouterLink to="/contact"  class="btn btn-outline">{{ $t('contact.eyebrow') }}</RouterLink>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
/**
 * Page « À propos » — marque blanche.
 *
 * Tout le contenu vient des réglages de la boutique (onglet « À propos » de
 * l'administration) ; les textes traduits ne servent que de repli neutre tant
 * que le commerçant n'a rien saisi. Aucune affirmation produit (« 100 %
 * naturel », chiffres de ventes…) n'est écrite ici : elle serait fausse pour
 * la moitié des boutiques.
 */
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import VideoPlayer from '@/components/ui/VideoPlayer.vue'
import { useSettingsStore } from '@/stores/settings'
import { useSeo, breadcrumbJsonLd } from '@/composables/useSeo'

const { t } = useI18n()
const settings = useSettingsStore()

const setting = (key) => (settings.data[key] ?? '').toString().trim()

const title = computed(() => setting('about_title') || t('aboutPage.heroTitle'))
const intro = computed(() =>
  setting('about_intro') || settings.shopTagline || t('aboutPage.heroDesc', { shopName: settings.shopName }))
const image = computed(() => setting('about_image_url') || '/placeholders/photo-1.jpg')

const videoUrl    = computed(() => setting('about_video_url'))
const videoPoster = computed(() => setting('about_video_poster_url') || undefined)

// Paragraphes séparés par une ligne vide dans le champ d'administration
const storyParagraphs = computed(() =>
  setting('about_story').split(/\n\s*\n/).map(p => p.trim()).filter(Boolean))

const SVG = (p) => `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`
const ICONS = [
  SVG('<path d="M12 2l3 7h7l-5.5 4.5L18.5 21 12 16.5 5.5 21l2-7.5L2 9h7z"/>'),
  SVG('<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'),
  SVG('<rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 5v4h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>'),
]

const values = computed(() => [1, 2, 3].map((n, i) => ({
  key: n,
  icon: ICONS[i],
  title: setting(`about_value${n}_title`) || t(`aboutPage.value${n}Title`),
  desc:  setting(`about_value${n}_desc`)  || t(`aboutPage.value${n}Desc`),
})))

useSeo(() => ({
  title: title.value,
  description: intro.value,
  canonical: '/a-propos',
  image: setting('about_image_url') || undefined,
  jsonLd: breadcrumbJsonLd([
    { name: t('common.home'), path: '/' },
    { name: t('aboutPage.eyebrow'), path: '/a-propos' },
  ]),
}))
</script>

<style scoped>
.ap { background: var(--color-bg); }

/* ── Hero ── */
.ap-hero { padding: clamp(40px, 7vw, 96px) 0; }
.ap-hero__inner {
  display: grid;
  grid-template-columns: 1.1fr 1fr;
  gap: clamp(28px, 5vw, 72px);
  align-items: center;
}
.ap-hero__title { margin: 12px 0 18px; color: var(--color-text); }
.ap-hero__desc {
  font-size: 1.05rem;
  line-height: 1.75;
  color: var(--color-text-muted);
  margin-bottom: 28px;
  white-space: pre-line;
}
.ap-hero__text .btn { display: inline-flex; align-items: center; gap: 8px; }
.ap-hero__img-wrap {
  border-radius: 28px;
  overflow: hidden;
  aspect-ratio: 4 / 5;
  background: var(--cream-100);
  box-shadow: 0 24px 60px rgba(var(--rose-500-rgb), 0.12);
}
.ap-hero__img { width: 100%; height: 100%; object-fit: cover; display: block; }

/* ── Histoire ── */
.ap-story { padding: clamp(40px, 6vw, 80px) 0; background: #fff; }
.ap-story__inner {
  display: grid;
  grid-template-columns: minmax(0, 420px) 1fr;
  gap: clamp(28px, 5vw, 64px);
  align-items: center;
}
.ap-story__inner--solo { grid-template-columns: 1fr; max-width: 760px; }
.ap-story__text p {
  font-size: 1.02rem;
  line-height: 1.8;
  color: var(--color-text-muted);
  margin-top: 16px;
}

/* ── Engagements ── */
.ap-values { padding: clamp(48px, 7vw, 96px) 0; }
.ap-values__head { text-align: center; margin-bottom: clamp(28px, 4vw, 48px); }
.ap-values__head h2 { margin-top: 10px; }
.ap-values__grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
}
.ap-value {
  background: #fff;
  border: 1px solid var(--gray-100);
  border-radius: 20px;
  padding: 28px 24px;
}
.ap-value__icon {
  display: inline-flex;
  width: 48px;
  height: 48px;
  align-items: center;
  justify-content: center;
  border-radius: 14px;
  background: var(--rose-50);
  color: var(--rose-600);
  margin-bottom: 16px;
}
.ap-value h3 { font-size: 1.1rem; margin-bottom: 8px; color: var(--color-text); }
.ap-value p { color: var(--color-text-muted); line-height: 1.65; font-size: 0.95rem; }

/* ── CTA ── */
.ap-cta {
  padding: clamp(48px, 7vw, 96px) 0;
  background: linear-gradient(135deg, var(--rose-50), var(--cream-100));
  text-align: center;
}
.ap-cta__inner { max-width: 680px; }
.ap-cta__inner p { color: var(--color-text-muted); margin: 14px 0 28px; line-height: 1.7; }
.ap-cta__btns { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }

@media (max-width: 860px) {
  .ap-hero__inner,
  .ap-story__inner { grid-template-columns: 1fr; }
  .ap-hero__img-wrap { max-width: 460px; }
  .ap-values__grid { grid-template-columns: 1fr; }
}
</style>
