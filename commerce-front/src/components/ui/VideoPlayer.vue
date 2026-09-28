<template>
  <div
    class="brand-player-wrap"
    :style="{ aspectRatio: aspectRatio, maxHeight: maxHeight }"
  >
    <video
      ref="videoEl"
      class="video-js vjs-brand-theme"
      :poster="poster"
      preload="metadata"
      playsinline
    >
      <source :src="src" type="video/mp4" />
      <p class="vjs-no-js">
        Votre navigateur ne supporte pas la vidéo HTML5.
      </p>
    </video>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import videojs from 'video.js'
import 'video.js/dist/video-js.css'

const props = defineProps({
  src:    { type: String, required: true },
  poster: { type: String, default: '' },
  // Cadre maîtrisé : la vidéo remplit ce conteneur (letterbox, jamais rognée),
  // au lieu de prendre la hauteur native de la source (souvent portrait → trop haut).
  aspectRatio: { type: String, default: '4 / 5' },
  maxHeight:   { type: String, default: '70vh' },
})

const videoEl = ref(null)
let player    = null

onMounted(() => {
  player = videojs(videoEl.value, {
    controls:    true,
    fill:        true,          // remplit le conteneur (taille maîtrisée via CSS)
    playbackRates: [0.75, 1, 1.25, 1.5],
    controlBar: {
      children: [
        'playToggle',
        'volumePanel',
        'currentTimeDisplay',
        'timeDivider',
        'durationDisplay',
        'progressControl',
        'playbackRateMenuButton',
        'fullscreenToggle',
      ],
    },
  })
})

onBeforeUnmount(() => {
  if (player) {
    player.dispose()
    player = null
  }
})
</script>

<style>
/*
 * ── Video.js — thème aux couleurs de la boutique ──
 * Couleurs : rose var(--rose-500), crème #fdfaf7, gris foncé #211f1f
 * Toutes les règles sont NON-scoped (video.js injecte dans le body).
 */

/* Conteneur à cadre maîtrisé (aspect-ratio + max-height pilotés par les props) */
.brand-player-wrap {
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 12px 48px rgba(var(--rose-500-rgb), 0.18), 0 4px 16px rgba(0,0,0,0.10);
  background: #141213;
  position: relative;
  width: 100%;
  margin-inline: auto;
}

/* Wrapper video.js — remplit le conteneur (mode fill) */
.vjs-brand-theme {
  border-radius: 20px;
  font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
  width: 100% !important;
  height: 100% !important;
}

/* La vidéo elle-même : visible en entier, centrée, jamais rognée */
.vjs-brand-theme video {
  object-fit: contain !important;
}

/* ── Gros bouton play central ── */
.vjs-brand-theme .vjs-big-play-button {
  background: rgba(var(--rose-500-rgb), 0.88) !important;
  border: 2.5px solid rgba(255, 255, 255, 0.4) !important;
  border-radius: 50% !important;
  width: 72px !important;
  height: 72px !important;
  top: 50% !important;
  left: 50% !important;
  transform: translate(-50%, -50%) !important;
  margin: 0 !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  transition: background 0.2s ease, transform 0.2s ease !important;
  box-shadow: 0 8px 32px rgba(var(--rose-500-rgb), 0.40) !important;
}
.vjs-brand-theme .vjs-big-play-button:hover {
  background: rgba(201, 24, 90, 0.95) !important;
  transform: translate(-50%, -50%) scale(1.08) !important;
}
.vjs-brand-theme .vjs-big-play-button .vjs-icon-placeholder::before {
  font-size: 2.2rem !important;
  line-height: 72px !important;
}
/* Cacher le bouton play central dès que la vidéo a démarré */
.vjs-brand-theme.vjs-has-started .vjs-big-play-button {
  display: none !important;
}

/* ── Barre de contrôle ── */
.vjs-brand-theme .vjs-control-bar {
  background: linear-gradient(to top, rgba(33, 31, 31, 0.92), transparent) !important;
  height: 48px !important;
  padding: 0 12px !important;
  align-items: center !important;
  border-radius: 0 0 20px 20px !important;
}

/* Icônes et textes */
.vjs-brand-theme .vjs-control-bar .vjs-button > .vjs-icon-placeholder::before,
.vjs-brand-theme .vjs-time-control,
.vjs-brand-theme .vjs-playback-rate .vjs-playback-rate-value {
  color: #f8f3ed !important;
  font-size: 0.8125rem !important;
}
.vjs-brand-theme .vjs-button:hover > .vjs-icon-placeholder::before {
  color: var(--rose-300) !important;
}

/* ── Barre de progression ── */
.vjs-brand-theme .vjs-progress-control {
  flex: 1 !important;
  height: 4px !important;
  align-items: center !important;
}
.vjs-brand-theme .vjs-progress-holder {
  height: 4px !important;
  border-radius: 4px !important;
  background: rgba(255,255,255,0.18) !important;
  margin: 0 8px !important;
}
.vjs-brand-theme .vjs-progress-holder:hover {
  height: 6px !important;
}
/* Partie jouée */
.vjs-brand-theme .vjs-play-progress {
  background: linear-gradient(90deg, var(--rose-500), var(--rose-400)) !important;
  border-radius: 4px !important;
}
.vjs-brand-theme .vjs-play-progress::before {
  color: var(--rose-500) !important;
  font-size: 14px !important;
  top: -5px !important;
}
/* Buffer */
.vjs-brand-theme .vjs-load-progress,
.vjs-brand-theme .vjs-load-progress div {
  background: rgba(255,255,255,0.12) !important;
}

/* ── Volume ── */
.vjs-brand-theme .vjs-volume-bar {
  background: rgba(255,255,255,0.18) !important;
  border-radius: 4px !important;
}
.vjs-brand-theme .vjs-volume-level {
  background: var(--rose-500) !important;
  border-radius: 4px !important;
}
.vjs-brand-theme .vjs-volume-level::before {
  color: var(--rose-500) !important;
}

/* ── Tooltip de temps ── */
.vjs-brand-theme .vjs-time-tooltip {
  background: var(--rose-500) !important;
  color: #fff !important;
  border-radius: 6px !important;
  font-size: 0.75rem !important;
  font-weight: 500 !important;
  padding: 3px 8px !important;
}

/* ── Menu vitesse ── */
.vjs-brand-theme .vjs-menu-content {
  background: #211f1f !important;
  border-radius: 8px !important;
  border: 1px solid rgba(255,255,255,0.08) !important;
}
.vjs-brand-theme .vjs-menu-item {
  font-size: 0.8125rem !important;
  color: #f0eeed !important;
}
.vjs-brand-theme .vjs-menu-item:hover,
.vjs-brand-theme .vjs-menu-item.vjs-selected {
  background: rgba(var(--rose-500-rgb), 0.18) !important;
  color: var(--rose-300) !important;
}

/* ── État paused : overlay léger ── */
.vjs-brand-theme.vjs-paused .vjs-poster {
  display: block !important;
}

/* ── Mode plein écran ── */
.vjs-brand-theme.vjs-fullscreen .vjs-control-bar {
  border-radius: 0 !important;
}
</style>
