<template>
  <section v-if="posts.length" class="blog-stories">
    <div class="container">
      <div class="blog-stories__head">
        <h2 class="blog-stories__title">{{ $t('blogStories.title') }}</h2>
        <RouterLink to="/blog" class="blog-stories__see-all">
          {{ $t('common.seeAll') }}
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </RouterLink>
      </div>

      <!-- Carrousel horizontal stories -->
      <div class="stories-track" ref="trackRef">
        <!-- Bouton "Ajouter" — admins et staff uniquement -->
        <button
          v-if="authStore.isAdmin || authStore.isStaff"
          class="story-bubble story-bubble--add"
          @click="router.push({ name: 'admin.blog.create' })"
          :aria-label="$t('blogStories.addStory')"
        >
          <span class="story-bubble__ring story-bubble__ring--add">
            <span class="story-bubble__add-inner">
              <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
            </span>
          </span>
          <span class="story-bubble__label">{{ $t('common.add') }}</span>
        </button>

        <!-- Story skeleton -->
        <template v-if="loading">
          <div v-for="i in 6" :key="i" class="story-bubble story-bubble--skeleton">
            <div class="story-bubble__ring"></div>
            <div class="story-bubble__label-ph"></div>
          </div>
        </template>

        <button
          v-for="(post, idx) in posts"
          :key="post.id"
          class="story-bubble"
          :class="{ 'story-bubble--video': !!post.video_url, 'story-bubble--seen': seen.has(post.id) }"
          @click="openStory(idx)"
          :aria-label="post.title"
        >
          <!-- Anneau gradient -->
          <span class="story-bubble__ring">
            <img
              :src="post.cover_image || PLACEHOLDER"
              :alt="post.title"
              class="story-bubble__img"
              loading="lazy"
            />
            <!-- Icône vidéo -->
            <span v-if="post.video_url" class="story-bubble__video-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            </span>
          </span>
          <span class="story-bubble__label">{{ post.title }}</span>
        </button>
      </div>
    </div>

    <!-- ── Viewer fullscreen ── -->
    <Transition name="story-viewer">
      <div
        v-if="activeIdx !== null"
        class="sv-overlay"
        @click.self="closeStory"
        role="dialog"
        aria-modal="true"
      >
        <div class="sv-card">
          <!-- Barre de progression -->
          <div class="sv-progress">
            <span
              v-for="(_, i) in posts"
              :key="i"
              class="sv-progress__bar"
              :class="{ 'sv-progress__bar--done': i < activeIdx, 'sv-progress__bar--active': i === activeIdx }"
            >
              <span
                v-if="i === activeIdx"
                class="sv-progress__fill"
                :style="{ animationDuration: autoAdvanceDuration + 'ms' }"
              ></span>
            </span>
          </div>

          <!-- Header -->
          <div class="sv-header">
            <img v-if="settings.shopLogoUrl" :src="settings.shopLogoUrl" :alt="settings.shopName" class="sv-header__logo" />
            <span class="sv-header__brand">{{ settings.shopName }}</span>
            <span class="sv-header__time">{{ formatDate(currentPost.published_at) }}</span>
            <button class="sv-header__close" @click="closeStory" :aria-label="$t('common.close')">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>

          <!-- Média -->
          <div class="sv-media" @click="handleMediaClick">
            <!-- Vidéo -->
            <template v-if="currentPost.video_url">
              <video
                ref="videoRef"
                :src="currentPost.video_url"
                class="sv-media__video"
                :key="currentPost.id"
                playsinline
                autoplay
                muted
                loop
                @canplay="onVideoCanPlay"
              ></video>
              <!-- Overlay play/pause -->
              <button class="sv-media__play-btn" @click.stop="toggleVideo" :aria-label="videoPaused ? 'Lire' : 'Pause'">
                <svg v-if="videoPaused" width="36" height="36" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                <svg v-else width="36" height="36" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
              </button>
            </template>

            <!-- Image -->
            <img
              v-else
              :src="currentPost.cover_image || PLACEHOLDER"
              :alt="currentPost.title"
              class="sv-media__img"
              :key="currentPost.id"
            />

            <!-- Zones tap gauche / droite -->
            <button class="sv-media__zone sv-media__zone--prev" @click.stop="prevStory" :aria-label="$t('common.previous')"></button>
            <button class="sv-media__zone sv-media__zone--next" @click.stop="nextStory" :aria-label="$t('common.next')"></button>
          </div>

          <!-- Footer -->
          <div class="sv-footer">
            <p class="sv-footer__title">{{ currentPost.title }}</p>
            <p v-if="currentPost.excerpt" class="sv-footer__excerpt">{{ currentPost.excerpt }}</p>
            <RouterLink
              :to="`/blog/${currentPost.slug}`"
              class="sv-footer__cta"
              @click="closeStory"
            >
              {{ $t('blog.readArticle') }}
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </RouterLink>
          </div>
        </div>
      </div>
    </Transition>
  </section>
</template>

<script setup>
import { useSettingsStore } from '@/stores/settings'
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import api from '@/api'
import { useAuthStore } from '@/features/auth/auth.store'

const router    = useRouter()
const authStore = useAuthStore()

const PLACEHOLDER      = 'https://placehold.co/200x200/f9ecee/e8336d?text=🌹'
const settings = useSettingsStore()
const AUTO_ADVANCE_MS  = 5000

const posts       = ref([])
const loading     = ref(true)
const activeIdx   = ref(null)
const seen        = ref(new Set())
const videoPaused = ref(false)
const videoRef    = ref(null)
const trackRef    = ref(null)
let   autoTimer   = null

const autoAdvanceDuration = AUTO_ADVANCE_MS

const currentPost = computed(() => activeIdx.value !== null ? posts.value[activeIdx.value] : null)

async function fetchPosts() {
  try {
    const { data } = await api.get('/blog', { params: { per_page: 10 } })
    posts.value = data.data ?? data
  } catch {
    posts.value = []
  } finally {
    loading.value = false
  }
}

function openStory(idx) {
  activeIdx.value = idx
  seen.value.add(posts.value[idx].id)
  videoPaused.value = false
  document.body.style.overflow = 'hidden'
  scheduleAutoAdvance()
}

function closeStory() {
  activeIdx.value = null
  clearAutoAdvance()
  document.body.style.overflow = ''
}

function nextStory() {
  clearAutoAdvance()
  if (activeIdx.value < posts.value.length - 1) {
    activeIdx.value++
    seen.value.add(posts.value[activeIdx.value].id)
    videoPaused.value = false
    scheduleAutoAdvance()
  } else {
    closeStory()
  }
}

function prevStory() {
  clearAutoAdvance()
  if (activeIdx.value > 0) {
    activeIdx.value--
    videoPaused.value = false
    scheduleAutoAdvance()
  }
}

function handleMediaClick() { /* zones latérales gèrent le clic */ }

function toggleVideo() {
  if (!videoRef.value) return
  if (videoRef.value.paused) {
    videoRef.value.play()
    videoPaused.value = false
    scheduleAutoAdvance()
  } else {
    videoRef.value.pause()
    videoPaused.value = true
    clearAutoAdvance()
  }
}

function onVideoCanPlay() {
  videoPaused.value = false
}

function scheduleAutoAdvance() {
  clearAutoAdvance()
  if (currentPost.value?.video_url) return // vidéo : pas d'avance auto
  autoTimer = setTimeout(nextStory, AUTO_ADVANCE_MS)
}

function clearAutoAdvance() {
  if (autoTimer) { clearTimeout(autoTimer); autoTimer = null }
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })
}

function onKeyDown(e) {
  if (activeIdx.value === null) return
  if (e.key === 'ArrowRight') nextStory()
  else if (e.key === 'ArrowLeft') prevStory()
  else if (e.key === 'Escape') closeStory()
}

watch(activeIdx, (idx) => {
  if (idx !== null) nextTick(() => {
    if (videoRef.value) videoRef.value.play().catch(() => {})
  })
})

onMounted(() => {
  fetchPosts()
  window.addEventListener('keydown', onKeyDown)
})
onUnmounted(() => {
  window.removeEventListener('keydown', onKeyDown)
  clearAutoAdvance()
  document.body.style.overflow = ''
})
</script>

<style scoped>
/* ── Section ── */
.blog-stories {
  padding: var(--space-10) 0 var(--space-12);
  background: var(--cream-50, #fdf7f8);
  border-top: 1px solid var(--cream-200);
  border-bottom: 1px solid var(--cream-200);
  overflow: hidden;
}

.blog-stories__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-6);
}

.blog-stories__title {
  font-family: var(--font-display);
  font-size: clamp(1.25rem, 3vw, 1.75rem);
  font-weight: 700;
  color: var(--gray-800);
}

.blog-stories__see-all {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-primary, var(--rose-500));
  text-decoration: none;
  transition: gap var(--transition-fast);
}
.blog-stories__see-all:hover { gap: 8px; }

/* ── Carrousel ── */
.stories-track {
  display: flex;
  gap: var(--space-5);
  overflow-x: auto;
  padding-bottom: var(--space-3);
  scrollbar-width: none;
}
.stories-track::-webkit-scrollbar { display: none; }

/* ── Bubble ── */
.story-bubble {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
  background: none;
  border: none;
  cursor: pointer;
  flex-shrink: 0;
  width: 80px;
  padding: 0;
  margin-top: 0.2rem;
  transition: transform 0.18s ease;
}
.story-bubble:hover { transform: scale(1.05); }
.story-bubble:active { transform: scale(0.97); }

.story-bubble__ring {
  position: relative;
  width: 72px; height: 72px;
  border-radius: 50%;
  padding: 3px;
  background: linear-gradient(135deg, var(--rose-500), #ff8fab, var(--rose-500));
  box-shadow: 0 2px 12px rgba(var(--rose-500-rgb), .3);
  display: block;
  flex-shrink: 0;
}
.story-bubble--seen .story-bubble__ring {
  background: var(--gray-300);
  box-shadow: none;
}

.story-bubble__img {
  width: 100%; height: 100%;
  border-radius: 50%;
  object-fit: cover;
  border: 2.5px solid #fff;
  display: block;
}

.story-bubble__video-icon {
  position: absolute;
  bottom: 4px; right: 4px;
  width: 20px; height: 20px;
  border-radius: 50%;
  background: var(--color-primary, var(--rose-500));
  color: #fff;
  display: flex; align-items: center; justify-content: center;
  border: 1.5px solid #fff;
}

.story-bubble__label {
  font-size: 0.6875rem;
  font-weight: 500;
  color: var(--gray-600);
  text-align: center;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  width: 100%;
}
.story-bubble--seen .story-bubble__label { color: var(--gray-400); }

/* Skeleton */
.story-bubble--skeleton .story-bubble__ring {
  background: var(--cream-200);
  animation: shimmer 1.4s infinite;
}
.story-bubble__label-ph {
  width: 64px; height: 10px;
  border-radius: 4px;
  background: var(--cream-200);
  animation: shimmer 1.4s infinite;
}
@keyframes shimmer {
  0%   { opacity: 1; }
  50%  { opacity: 0.5; }
  100% { opacity: 1; }
}

/* ── Bubble "Ajouter" ── */
.story-bubble--add .story-bubble__ring--add {
  background: linear-gradient(135deg, var(--rose-500), #ff8fab, #c2185b);
  animation: add-ring-pulse 2.8s ease-in-out infinite;
}
.story-bubble__add-inner {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  background: #fff;
  border: 2.5px solid #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--rose-500);
  transition: background 0.18s;
}
.story-bubble--add:hover .story-bubble__add-inner {
  background: #fff0f4;
}
@keyframes add-ring-pulse {
  0%, 100% { box-shadow: 0 2px 12px rgba(var(--rose-500-rgb), .30); }
  50%       { box-shadow: 0 2px 20px rgba(var(--rose-500-rgb), .55); }
}

/* ── Viewer ── */
.sv-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(0,0,0,.85);
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(6px);
}

.sv-card {
  position: relative;
  width: min(400px, 100vw);
  height: min(700px, 100svh);
  border-radius: 20px;
  overflow: hidden;
  background: #111;
  display: flex;
  flex-direction: column;
  box-shadow: 0 24px 64px rgba(0,0,0,.6);
}

/* Barre de progression */
.sv-progress {
  position: absolute;
  top: 10px;
  left: 10px; right: 10px;
  z-index: 10;
  display: flex;
  gap: 3px;
}
.sv-progress__bar {
  flex: 1;
  height: 3px;
  border-radius: 2px;
  background: rgba(255,255,255,.35);
  overflow: hidden;
}
.sv-progress__bar--done { background: rgba(255,255,255,.8); }
.sv-progress__fill {
  display: block;
  height: 100%;
  background: #fff;
  border-radius: 2px;
  animation: story-progress linear forwards;
  animation-duration: inherit;
}
@keyframes story-progress { from { width: 0; } to { width: 100%; } }

/* Header */
.sv-header {
  position: absolute;
  top: 22px;
  left: 10px; right: 10px;
  z-index: 10;
  display: flex;
  align-items: center;
  gap: 8px;
}
.sv-header__logo {
  width: 32px; height: 32px;
  border-radius: 50%;
  border: 1.5px solid rgba(255,255,255,.6);
  object-fit: cover;
  flex-shrink: 0;
}
.sv-header__brand {
  font-size: 0.8125rem;
  font-weight: 600;
  color: #fff;
}
.sv-header__time {
  font-size: 0.75rem;
  color: rgba(255,255,255,.65);
  margin-left: 2px;
}
.sv-header__close {
  margin-left: auto;
  background: rgba(255,255,255,.15);
  border: none;
  border-radius: 50%;
  width: 32px; height: 32px;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  color: #fff;
  transition: background 0.15s;
}
.sv-header__close:hover { background: rgba(255,255,255,.28); }

/* Média */
.sv-media {
  position: absolute;
  inset: 0;
}
.sv-media__img {
  width: 100%; height: 100%;
  object-fit: cover;
}
.sv-media__video {
  width: 100%; height: 100%;
  object-fit: cover;
}

/* Zones tap */
.sv-media__zone {
  position: absolute;
  top: 0; bottom: 0;
  width: 35%;
  background: none;
  border: none;
  cursor: pointer;
  z-index: 5;
}
.sv-media__zone--prev { left: 0; }
.sv-media__zone--next { right: 0; }

/* Play/pause overlay */
.sv-media__play-btn {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: none;
  border: none;
  color: rgba(255,255,255,.9);
  cursor: pointer;
  z-index: 6;
  opacity: 0;
  transition: opacity 0.2s;
}
.sv-media:hover .sv-media__play-btn { opacity: 1; }

/* Footer */
.sv-footer {
  position: absolute;
  bottom: 0;
  left: 0; right: 0;
  z-index: 10;
  padding: var(--space-6) var(--space-5) var(--space-5);
  background: linear-gradient(to top, rgba(0,0,0,.85) 0%, transparent 100%);
}
.sv-footer__title {
  font-size: 1.0625rem;
  font-weight: 700;
  color: #fff;
  line-height: 1.3;
  margin-bottom: var(--space-1);
}
.sv-footer__excerpt {
  font-size: 0.8125rem;
  color: rgba(255,255,255,.75);
  line-height: 1.5;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  margin-bottom: var(--space-3);
}
.sv-footer__cta {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 20px;
  border-radius: 50px;
  background: #fff;
  color: var(--color-primary, var(--rose-500));
  font-size: 0.875rem;
  font-weight: 700;
  text-decoration: none;
  transition: transform 0.15s, box-shadow 0.15s;
}
.sv-footer__cta:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 16px rgba(0,0,0,.3);
}

/* ── Transition viewer ── */
.story-viewer-enter-active,
.story-viewer-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.story-viewer-enter-from,
.story-viewer-leave-to {
  opacity: 0;
  transform: scale(0.96);
}

/* ── Mobile : plein écran ── */
@media (max-width: 480px) {
  .sv-card {
    width: 100vw;
    height: 100svh;
    border-radius: 0;
  }
  .story-bubble { width: 68px; }
  .story-bubble__ring { width: 60px; height: 60px; }
}
</style>
