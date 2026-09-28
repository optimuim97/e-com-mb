<template>
  <section class="explore">
    <div class="container">
      <header class="explore__head">
        <span class="explore__eyebrow">
          <span class="explore__eyebrow-line"></span>
          {{ $t('explore.eyebrow') }}
        </span>
        <h2 class="explore__title">{{ $t('explore.title') }}</h2>
      </header>

      <div class="explore__grid">
        <RouterLink
          v-for="card in cards"
          :key="card.key"
          :to="card.to"
          class="explore-card"
          :class="`explore-card--${card.key}`"
        >
          <div class="explore-card__media">
            <img
              v-if="card.image"
              :src="card.image"
              :alt="card.title"
              class="explore-card__img"
              loading="lazy"
              @error="$event.target.style.display='none'"
            />
            <span class="explore-card__icon" v-html="card.icon"></span>
            <div class="explore-card__overlay"></div>
          </div>
          <div class="explore-card__body">
            <span class="explore-card__kicker">{{ card.kicker }}</span>
            <h3 class="explore-card__title">{{ card.title }}</h3>
            <p class="explore-card__desc">{{ card.desc }}</p>
            <span class="explore-card__cta">
              {{ card.cta }}
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </span>
          </div>
        </RouterLink>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useSettingsStore } from '@/stores/settings'

const { t }    = useI18n()
const settings = useSettingsStore()

const cards = computed(() => ALL_CARDS.value.filter(card => !card.module || settings.moduleEnabled(card.module)))

const ALL_CARDS = computed(() => [
  {
    key: 'about',
    to: { name: 'about' },
    kicker: t('explore.about.kicker'),
    title: t('explore.about.title'),
    desc: t('explore.about.desc'),
    cta: t('explore.about.cta'),
    image: settings.data.about_image_url || '/placeholders/photo-4.jpg',
    icon: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
  },
  {
    key: 'club',
    module: 'program',
    to: { name: 'program' },
    kicker: t('explore.club.kicker'),
    title: t('explore.club.title'),
    desc: t('explore.club.desc'),
    cta: t('explore.club.cta'),
    image: '/placeholders/photo-5.jpg',
    icon: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
  },
  {
    key: 'blog',
    module: 'blog',
    to: { name: 'blog' },
    kicker: t('explore.blog.kicker'),
    title: t('explore.blog.title'),
    desc: t('explore.blog.desc'),
    cta: t('explore.blog.cta'),
    image: '/placeholders/photo-6.jpg',
    icon: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
  },
])
</script>

<style scoped>
.explore {
  padding: clamp(48px, 7vw, 88px) 0;
  background: linear-gradient(180deg, #fff 0%, #fdf6f3 100%);
}

.explore__head { text-align: center; margin-bottom: clamp(28px, 4vw, 48px); }
.explore__eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.22em;
  color: var(--rose-600, #c0386b);
  text-transform: uppercase;
}
.explore__eyebrow-line { width: 28px; height: 1px; background: currentColor; opacity: 0.6; }
.explore__title {
  font-family: var(--font-display);
  font-size: clamp(1.6rem, 3.5vw, 2.4rem);
  font-weight: 400;
  color: #2a1f24;
  margin: 12px 0 0;
}

.explore__grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: clamp(16px, 2.5vw, 28px);
}

.explore-card {
  display: flex;
  flex-direction: column;
  background: #fff;
  border-radius: var(--radius-xl, 20px);
  overflow: hidden;
  text-decoration: none;
  box-shadow: 0 4px 18px rgba(168, 50, 80, 0.07);
  border: 1px solid var(--cream-200, #f1e4e8);
  transition: transform 0.25s ease, box-shadow 0.25s ease;
}
.explore-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 18px 40px -12px rgba(168, 50, 80, 0.22);
}

.explore-card__media {
  position: relative;
  aspect-ratio: 16 / 10;
  overflow: hidden;
  background: linear-gradient(145deg, var(--rose-50, #fdeef2), var(--cream-100, #fdf2ec));
}
.explore-card__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.5s ease;
}
.explore-card:hover .explore-card__img { transform: scale(1.06); }
.explore-card__icon {
  position: absolute;
  top: 14px;
  left: 14px;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: rgba(255,255,255,0.92);
  color: var(--rose-500, var(--rose-500));
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.explore-card__overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to bottom, transparent 55%, rgba(0,0,0,0.04) 100%);
}

.explore-card__body {
  padding: clamp(16px, 2vw, 24px);
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
}
.explore-card__kicker {
  font-size: 0.625rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--rose-500, var(--rose-500));
}
.explore-card__title {
  font-family: var(--font-display);
  font-size: 1.25rem;
  font-weight: 500;
  color: #2a1f24;
  line-height: 1.25;
  margin: 0;
}
.explore-card__desc {
  font-size: 0.875rem;
  color: #6b5e60;
  line-height: 1.55;
  margin: 0 0 4px;
}
.explore-card__cta {
  margin-top: auto;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 0.8125rem;
  font-weight: 600;
  color: #2a1f24;
}
.explore-card__cta svg { transition: transform 0.2s ease; }
.explore-card:hover .explore-card__cta { color: var(--rose-600, #c0386b); }
.explore-card:hover .explore-card__cta svg { transform: translateX(4px); }

@media (max-width: 900px) {
  .explore__grid { grid-template-columns: 1fr; max-width: 460px; margin: 0 auto; }
  .explore-card__media { aspect-ratio: 16 / 7; }
}
</style>
