<template>
  <main class="auth-page">
    <!-- Retour accueil — visible pour tous les utilisateurs -->
    <RouterLink to="/" class="auth-back">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <path d="M19 12H5M12 5l-7 7 7 7"/>
      </svg>
      Retour à l'accueil
    </RouterLink>

    <!-- Background décoratif -->
    <div class="auth-bg" aria-hidden="true">
      <div class="auth-bg__blob auth-bg__blob--1"></div>
      <div class="auth-bg__blob auth-bg__blob--2"></div>
      <div class="auth-bg__petal auth-bg__petal--1"><FlowerMark /></div>
      <div class="auth-bg__petal auth-bg__petal--2"><FlowerMark /></div>
    </div>

    <div class="auth-inner">
      <header class="auth-header">
        <RouterLink to="/" class="auth-logo" :aria-label="settings.shopName + ' - Accueil'">
          <BrandLogo img-class="auth-logo__img" size="lg" width="200" height="200" />
        </RouterLink>

        <span class="eyebrow">{{ eyebrow }}</span>
        <h1 class="display-lg auth-title" v-html="title"></h1>
        <p class="auth-desc">{{ desc }}</p>
      </header>

      <div class="card auth-card">
        <slot />

        <p class="auth-footer">
          {{ footerText }}
          <RouterLink :to="footerLink">{{ footerLinkText }}</RouterLink>
        </p>
      </div>
    </div>
  </main>
</template>

<script setup>
import { useSettingsStore } from '@/stores/settings'
import BrandLogo from '@/components/ui/BrandLogo.vue'

const settings = useSettingsStore()
import { RouterLink } from 'vue-router';

defineProps({
  eyebrow:      { type: String, required: true },
  title:        { type: String, required: true },  // HTML autorisé pour les <em>
  desc:         { type: String, required: true },
  footerText:   { type: String, required: true },
  footerLink:   { type: String, required: true },
  footerLinkText: { type: String, required: true },
});
</script>

<style scoped>
/* ── Page : exactement 1 viewport, jamais de scroll ── */
.auth-page {
  height: 100vh;
  height: 100dvh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: clamp(8px, 2vh, 32px) var(--space-4);
  background: linear-gradient(160deg, var(--cream-50) 0%, var(--rose-50) 60%, var(--cream-100) 100%);
  position: relative;
}

/* Lien retour accueil */
.auth-back {
  position: absolute;
  top: clamp(8px, 1.5vh, 20px);
  left: var(--space-5);
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--gray-500);
  text-decoration: none;
  padding: 6px 12px;
  border-radius: var(--radius-full);
  border: 1.5px solid var(--cream-300);
  background: rgba(255,255,255,0.7);
  backdrop-filter: blur(8px);
  transition: all var(--transition-fast);
  z-index: 2;
}
.auth-back:hover {
  color: var(--rose-500);
  border-color: var(--rose-200);
  background: rgba(255,255,255,0.95);
  transform: translateX(-2px);
}

.auth-bg { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
.auth-bg__blob {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.4;
}
.auth-bg__blob--1 {
  width: 400px; height: 400px;
  background: radial-gradient(circle, var(--rose-200), transparent 70%);
  top: -100px; right: -80px;
  animation: floatY 8s ease-in-out infinite;
}
.auth-bg__blob--2 {
  width: 320px; height: 320px;
  background: radial-gradient(circle, var(--gold-200), transparent 70%);
  bottom: -60px; left: -60px;
  animation: floatY 10s ease-in-out infinite reverse;
}
.auth-bg__petal {
  position: absolute;
  font-size: 1.5rem;
  opacity: 0.18;
  animation: floatY 6s ease-in-out infinite;
}
.auth-bg__petal--1 { top: 15%; left: 8%; }
.auth-bg__petal--2 { bottom: 12%; right: 12%; font-size: 2rem; animation-delay: 2s; }

.auth-inner {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 460px;
}

.auth-header {
  text-align: center;
  margin-bottom: clamp(8px, 1.5vh, 24px);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: clamp(2px, 0.5vh, 8px);
}

/* Logo : scale entre 55px (très petit écran) et 120px (grand écran) */
.auth-logo__img {
  height: clamp(55px, 13vh, 120px);
  width: auto;
  object-fit: contain;
  display: block;
  margin: 0 auto;
}
.auth-logo {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: var(--space-3);
  margin-bottom: clamp(4px, 1vh, 16px);
}
.auth-logo__text {
  display: flex;
  flex-direction: column;
  line-height: 1.1;
  text-align: left;
}
.auth-logo__name {
  font-family: var(--font-display);
  font-size: 1.375rem;
  font-weight: 500;
  color: var(--gray-800);
}
.auth-logo__sub {
  font-size: 0.625rem;
  font-weight: 500;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--color-primary);
}

/* Titre : scale entre 1.25rem et 1.75rem selon hauteur */
.auth-title {
  color: var(--gray-800);
  font-size: clamp(1.125rem, 3vh, 1.75rem) !important;
  line-height: 1.2;
}
:deep(.auth-title em) { font-style: italic; color: var(--color-primary); }

.auth-desc {
  color: var(--gray-500);
  font-size: clamp(0.8125rem, 1.8vh, 0.9375rem);
  max-width: 380px;
  text-align: center;
}

/* Card : padding réduit sur petits écrans */
.auth-card {
  padding: clamp(16px, 3vh, 32px) clamp(16px, 4vw, 32px);
  background: rgba(255, 255, 255, 0.92);
  backdrop-filter: blur(8px);
  box-shadow: var(--shadow-md);
  border-color: var(--cream-200);
}

.auth-footer {
  text-align: center;
  font-size: 0.875rem;
  color: var(--gray-500);
  margin-top: clamp(8px, 1.5vh, 24px);
}
.auth-footer a {
  color: var(--rose-500);
  font-weight: 600;
  transition: color var(--transition-fast);
}
.auth-footer a:hover { color: var(--rose-700); }
</style>
