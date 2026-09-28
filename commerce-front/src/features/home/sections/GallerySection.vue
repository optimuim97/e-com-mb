<template>
  <section v-if="loading || photos.length" class="gallery-section">
    <div class="container">
      <SectionHeader :eyebrow="$t('gallery.eyebrow')">
      </SectionHeader>

      <!-- Skeleton -->
      <div v-if="loading" class="muses-grid">
        <div
          v-for="i in 10"
          :key="i"
          class="muse-card muse-card--skeleton"
          :class="{ 'muse-card--featured': i === 1 }"
        ></div>
      </div>

      <!-- Grille éditoriale -->
      <div v-else class="muses-grid">
        <button
          v-for="(photo, idx) in photos"
          :key="photo.id ?? idx"
          class="muse-card"
          :class="{ 'muse-card--featured': idx === 0 }"
          @click="openLightbox(idx)"
          :aria-label="photo.title || $t('gallery.photoAlt')"
        >
          <img
            :src="photo.image_url"
            :alt="photo.title || $t('gallery.museAlt')"
            loading="lazy"
          />
          <!-- Voile dégradé permanent pour lisibilité du texte -->
          <div class="muse-card__scrim" aria-hidden="true"></div>

          <div v-if="photo.title || photo.description" class="muse-card__caption">
            <span v-if="photo.title" class="muse-card__name">{{ photo.title }}</span>
            <span v-if="photo.description" class="muse-card__desc">{{
              photo.description
            }}</span>
          </div>

          <span class="muse-card__zoom" aria-hidden="true">
            <svg
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
            >
              <circle cx="11" cy="11" r="8" />
              <path d="m21 21-4.35-4.35" />
              <path d="M11 8v6M8 11h6" />
            </svg>
          </span>
        </button>
      </div>
    </div>

    <!-- Lightbox -->
    <Teleport to="body">
      <Transition name="lb">
        <div
          v-if="lightboxIndex !== null"
          class="lightbox"
          @click.self="lightboxIndex = null"
        >
          <button
            class="lightbox__close"
            @click="lightboxIndex = null"
            :aria-label="$t('common.close')"
          >
            ×
          </button>
          <button
            class="lightbox__prev"
            @click="prevImg"
            v-if="lightboxIndex > 0"
            :aria-label="$t('common.previous')"
          >
            ‹
          </button>
          <button
            class="lightbox__next"
            @click="nextImg"
            v-if="lightboxIndex < photos.length - 1"
            :aria-label="$t('common.next')"
          >
            ›
          </button>

          <figure class="lightbox__figure">
            <img
              :src="photos[lightboxIndex].image_url"
              class="lightbox__img"
              :alt="photos[lightboxIndex].title || ''"
            />
            <figcaption
              v-if="photos[lightboxIndex].title || photos[lightboxIndex].description"
              class="lightbox__caption"
            >
              <strong v-if="photos[lightboxIndex].title">{{
                photos[lightboxIndex].title
              }}</strong>
              <span v-if="photos[lightboxIndex].description">{{
                photos[lightboxIndex].description
              }}</span>
            </figcaption>
          </figure>
        </div>
      </Transition>
    </Teleport>
  </section>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import SectionHeader from "@/components/ui/SectionHeader.vue";
import api from "@/api";

/**
 * Galerie photos — publiées depuis l'admin (/admin/galerie).
 * Rien de publié : la section ne s'affiche pas.
 */
const FALLBACK = [];

const photos = ref([]);
const loading = ref(true);
const lightboxIndex = ref(null);

async function fetchPhotos() {
  try {
    const { data } = await api.get("/gallery");
    const list = data.data ?? data;
    photos.value = Array.isArray(list) && list.length ? list : FALLBACK;
  } catch {
    photos.value = FALLBACK;
  } finally {
    loading.value = false;
  }
}

function openLightbox(idx) {
  lightboxIndex.value = idx;
}
function prevImg() {
  if (lightboxIndex.value > 0) lightboxIndex.value--;
}
function nextImg() {
  if (lightboxIndex.value < photos.value.length - 1) lightboxIndex.value++;
}

function onKeyDown(e) {
  if (lightboxIndex.value === null) return;
  if (e.key === "ArrowRight") nextImg();
  else if (e.key === "ArrowLeft") prevImg();
  else if (e.key === "Escape") lightboxIndex.value = null;
}

onMounted(() => {
  fetchPhotos();
  window.addEventListener("keydown", onKeyDown);
});
onUnmounted(() => window.removeEventListener("keydown", onKeyDown));
</script>

<style scoped>
.gallery-section {
  padding: var(--space-16) 0;
  background: var(--color-bg);
}

.gallery-sub {
  text-align: center;
  color: var(--gray-500);
  font-size: 0.9375rem;
  margin: calc(-1 * var(--space-4)) auto var(--space-8);
  max-width: 480px;
}

/* ── Grille éditoriale ──
   1ère photo en vedette (2×2), les suivantes en 1×1.
   dense pour combler les trous quel que soit le nombre de photos. */
.muses-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  grid-auto-rows: 210px;
  grid-auto-flow: dense;
  gap: var(--space-3);
}

.muse-card {
  position: relative;
  border-radius: var(--radius-xl);
  overflow: hidden;
  cursor: pointer;
  background: var(--cream-100);
  padding: 0;
  border: none;
  text-align: left;
  display: block;
  width: 100%;
  height: 100%;
}

.muse-card--featured {
  grid-column: span 2;
  grid-row: span 2;
}

.muse-card img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

@media (hover: hover) {
  .muse-card:hover img {
    transform: scale(1.05);
  }
  .muse-card:hover .muse-card__zoom {
    opacity: 1;
    transform: scale(1);
  }
  .muse-card:hover .muse-card__scrim {
    opacity: 1;
  }
}

/* Voile bas permanent — renforcé au hover */
.muse-card__scrim {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    to top,
    rgba(20, 12, 16, 0.72) 0%,
    rgba(20, 12, 16, 0.18) 38%,
    transparent 62%
  );
  opacity: 0.9;
  transition: opacity var(--transition-normal);
  pointer-events: none;
}

/* ── Légende (nom + description) ── */
.muse-card__caption {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  padding: var(--space-4) var(--space-4) var(--space-3);
  display: flex;
  flex-direction: column;
  gap: 2px;
  pointer-events: none;
}

.muse-card__name {
  font-family: var(--font-display);
  font-size: 1.125rem;
  font-weight: 600;
  color: #fff;
  letter-spacing: 0.01em;
  line-height: 1.2;
  text-shadow: 0 1px 8px rgba(0, 0, 0, 0.35);
}
.muse-card--featured .muse-card__name {
  font-size: 1.5rem;
}

.muse-card__desc {
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.85);
  line-height: 1.45;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-shadow: 0 1px 6px rgba(0, 0, 0, 0.35);
}
.muse-card--featured .muse-card__desc {
  font-size: 0.8438rem;
  -webkit-line-clamp: 3;
}

/* ── Zoom ── */
.muse-card__zoom {
  position: absolute;
  top: 12px;
  right: 12px;
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.92);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transform: scale(0.7);
  transition: all var(--transition-fast);
  color: var(--gray-700);
}

/* ── Skeleton ── */
.muse-card--skeleton {
  background: var(--cream-200);
  animation: gallery-pulse 1.5s ease-in-out infinite;
  cursor: default;
}
@keyframes gallery-pulse {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.55;
  }
}

/* ── Lightbox ── */
.lightbox {
  position: fixed;
  inset: 0;
  z-index: 1000;
  background: rgba(0, 0, 0, 0.92);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-8);
}

.lightbox__figure {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-3);
  max-width: 90vw;
}

.lightbox__img {
  max-width: 90vw;
  max-height: 78vh;
  object-fit: contain;
  border-radius: var(--radius-lg);
}

.lightbox__caption {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
  text-align: center;
  max-width: 560px;
}
.lightbox__caption strong {
  font-family: var(--font-display);
  font-size: 1.25rem;
  font-weight: 600;
  color: #fff;
}
.lightbox__caption span {
  font-size: 0.875rem;
  color: rgba(255, 255, 255, 0.75);
  line-height: 1.55;
}

.lightbox__close {
  position: absolute;
  top: 20px;
  right: 24px;
  font-size: 2rem;
  color: rgba(255, 255, 255, 0.7);
  line-height: 1;
  transition: color var(--transition-fast);
  z-index: 2;
}
.lightbox__close:hover {
  color: #fff;
}

.lightbox__prev,
.lightbox__next {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  font-size: 3rem;
  color: rgba(255, 255, 255, 0.6);
  padding: 16px;
  transition: color var(--transition-fast);
  z-index: 2;
}
.lightbox__prev:hover,
.lightbox__next:hover {
  color: #fff;
}
.lightbox__prev {
  left: 16px;
}
.lightbox__next {
  right: 16px;
}

.lb-enter-active,
.lb-leave-active {
  transition: opacity 0.2s ease;
}
.lb-enter-from,
.lb-leave-to {
  opacity: 0;
}

/* ── Responsive ── */
@media (max-width: 1024px) {
  .muses-grid {
    grid-template-columns: repeat(3, 1fr);
    grid-auto-rows: 190px;
  }
}

@media (max-width: 640px) {
  /* Mobile : carrousel horizontal scroll-snap — plus agréable que la grille empilée */
  .muses-grid {
    display: flex;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    gap: var(--space-3);
    padding-bottom: var(--space-3);
    margin-inline: calc(-1 * var(--space-4));
    padding-inline: var(--space-4);
    scrollbar-width: none;
  }
  .muses-grid::-webkit-scrollbar {
    display: none;
  }

  .muse-card,
  .muse-card--featured {
    flex: 0 0 78vw;
    max-width: 340px;
    height: 380px;
    scroll-snap-align: center;
  }

  .muse-card--featured .muse-card__name {
    font-size: 1.25rem;
  }

  .lightbox {
    padding: var(--space-4);
  }
  .lightbox__img {
    max-height: 66vh;
  }
}
</style>
