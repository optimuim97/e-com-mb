<template>
  <main class="notfound-page">
    <!-- Background -->
    <div class="notfound-bg" aria-hidden="true">
      <div class="notfound-bg__blob notfound-bg__blob--1"></div>
      <div class="notfound-bg__blob notfound-bg__blob--2"></div>
      <div class="notfound-bg__petal notfound-bg__petal--1"><FlowerMark /></div>
      <div class="notfound-bg__petal notfound-bg__petal--2"><FlowerMark /></div>
      <div class="notfound-bg__petal notfound-bg__petal--3"><FlowerMark /></div>
    </div>

    <div class="notfound-inner">
      <div class="notfound-number">
        <span>4</span>
        <div class="notfound-flower"><FlowerMark /></div>
        <span>4</span>
      </div>

      <span class="eyebrow">{{ $t('notFound.eyebrow') }}</span>
      <h1 class="display-lg notfound-title">
        {{ $t('notFound.title') }} <em>{{ $t('notFound.titleEm') }}</em>
      </h1>
      <p class="notfound-desc">
        {{ $t('notFound.desc') }}
        <br />
        {{ $t('notFound.descExtra') }}
      </p>

      <div class="notfound-actions">
        <RouterLink to="/" class="btn btn-primary btn-lg">
          {{ $t('notFound.backHome') }}
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
        </RouterLink>
        <RouterLink to="/products" class="btn btn-outline btn-lg">
          {{ $t('notFound.viewShop') }}
        </RouterLink>
      </div>
    </div>
  </main>
</template>

<script setup>
import { RouterLink } from 'vue-router'
</script>

<style scoped>
.notfound-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-8) var(--space-4);
  background: linear-gradient(160deg, var(--cream-50) 0%, var(--rose-50) 60%, var(--cream-100) 100%);
  position: relative;
  overflow: hidden;
}

.notfound-bg { position: absolute; inset: 0; pointer-events: none; }
.notfound-bg__blob {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.4;
}
.notfound-bg__blob--1 {
  width: 500px; height: 500px;
  background: radial-gradient(circle, var(--rose-200), transparent 70%);
  top: -100px; right: -100px;
  animation: floatY 8s ease-in-out infinite;
}
.notfound-bg__blob--2 {
  width: 350px; height: 350px;
  background: radial-gradient(circle, var(--gold-200), transparent 70%);
  bottom: -80px; left: -60px;
  animation: floatY 10s ease-in-out infinite reverse;
}
.notfound-bg__petal {
  position: absolute;
  font-size: 1.5rem;
  opacity: 0.18;
  animation: floatY 6s ease-in-out infinite;
}
.notfound-bg__petal--1 { top: 15%; left: 8%; }
.notfound-bg__petal--2 { top: 60%; right: 8%; font-size: 2rem; animation-delay: 2s; }
.notfound-bg__petal--3 { bottom: 18%; left: 40%; animation-delay: 4s; }

.notfound-inner {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-3);
  max-width: 500px;
  text-align: center;
}

.notfound-number {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  margin-bottom: var(--space-4);
  font-family: var(--font-display);
  font-size: clamp(5rem, 12vw, 8rem);
  font-weight: 300;
  color: var(--rose-200);
  line-height: 1;
}

.notfound-flower {
  font-size: clamp(3rem, 8vw, 5rem);
  animation: floatY 3s ease-in-out infinite;
}

.notfound-title { color: var(--gray-800); }
.notfound-title em { font-style: italic; color: var(--color-primary); }

.notfound-desc {
  color: var(--gray-500);
  font-size: 1rem;
  line-height: 1.7;
  margin-top: var(--space-2);
  max-width: 420px;
}

.notfound-actions {
  display: flex;
  gap: var(--space-3);
  margin-top: var(--space-4);
  flex-wrap: wrap;
  justify-content: center;
}

@media (max-width: 540px) {
  .notfound-actions .btn { flex: 1 1 auto; justify-content: center; }
}
</style>
