<template>
  <Teleport to="body">
    <!-- Mise à jour disponible — jamais appliquée sans accord :
         un rechargement en pleine saisie de commande ferait perdre le panier. -->
    <Transition name="pwa-slide">
      <div v-if="updateReady" class="pwa-bar pwa-bar--update" role="status">
        <span class="pwa-bar__text">Une nouvelle version de la boutique est disponible.</span>
        <div class="pwa-bar__actions">
          <button class="pwa-bar__btn pwa-bar__btn--ghost" @click="updateReady = false">
            Plus tard
          </button>
          <button class="pwa-bar__btn" @click="applyUpdate">Actualiser</button>
        </div>
      </div>
    </Transition>

    <!-- Invite d'installation -->
    <Transition name="pwa-slide">
      <div v-if="showInstall" class="pwa-bar" role="dialog" aria-label="Installer l'application">
        <img :src="settings.shopFaviconUrl || '/logos/favicon-192.png'" alt="" class="pwa-bar__icon" />
        <span class="pwa-bar__text">
          Installez {{ settings.shopName }} sur votre téléphone — accès direct, même en
          connexion faible.
        </span>
        <div class="pwa-bar__actions">
          <button class="pwa-bar__btn pwa-bar__btn--ghost" @click="dismiss">Non merci</button>
          <button class="pwa-bar__btn" @click="install">Installer</button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { useSettingsStore } from '@/stores/settings'

const settings = useSettingsStore()
import { ref, computed, onMounted } from 'vue'
import {
  updateReady,
  installPrompt,
  applyUpdate,
  promptInstall,
  isStandalone,
} from '@/pwa'

// Un refus se respecte : sans mémoire du refus, la bannière reviendrait à
// chaque page et deviendrait un irritant plutôt qu'une proposition.
const REFUS_CLE   = 'shop_pwa_refuse_le'
const REFUS_JOURS = 30

const refuse = ref(true)

onMounted(() => {
  const depuis = Number(localStorage.getItem(REFUS_CLE) || 0)
  const expire = depuis + REFUS_JOURS * 24 * 60 * 60 * 1000
  refuse.value = depuis > 0 && Date.now() < expire
})

const props = defineProps({
  // L'espace livreur porte sa propre invite, formulée pour lui. Deux
  // bandeaux d'installation sur le même écran, c'est un de trop.
  install: { type: Boolean, default: true },
})

const showInstall = computed(() =>
  props.install && !!installPrompt.value && !refuse.value && !isStandalone()
)

function dismiss() {
  localStorage.setItem(REFUS_CLE, String(Date.now()))
  refuse.value = true
}

async function install() {
  const choix = await promptInstall()
  if (choix === 'dismissed') dismiss()
}
</script>

<style scoped>
.pwa-bar {
  position: fixed;
  left: 50%;
  bottom: 16px;
  transform: translateX(-50%);
  z-index: 400;
  width: calc(100% - 32px);
  max-width: 460px;
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  padding: 14px 16px;
  border-radius: 14px;
  background: #fff;
  border: 1px solid var(--cream-300, #e8ddd6);
  box-shadow: 0 8px 28px rgba(0, 0, 0, 0.14);
}

.pwa-bar--update {
  bottom: auto;
  top: 16px;
}

.pwa-bar__icon {
  width: 38px;
  height: 38px;
  border-radius: 9px;
  flex-shrink: 0;
}

.pwa-bar__text {
  flex: 1;
  min-width: 160px;
  font-size: 0.8125rem;
  line-height: 1.45;
  color: var(--gray-700, #3d3230);
}

.pwa-bar__actions {
  display: flex;
  gap: 8px;
  margin-left: auto;
}

.pwa-bar__btn {
  padding: 8px 16px;
  border: none;
  border-radius: 999px;
  background: var(--rose-500, var(--rose-500));
  color: #fff;
  font-size: 0.8125rem;
  font-weight: 500;
  font-family: inherit;
  cursor: pointer;
  white-space: nowrap;
}
.pwa-bar__btn:hover { background: var(--rose-600, #c0386b); }

.pwa-bar__btn--ghost {
  background: transparent;
  color: var(--gray-500, #7a6e6a);
}
.pwa-bar__btn--ghost:hover {
  background: var(--cream-100, #fdf2ec);
  color: var(--gray-700, #3d3230);
}

.pwa-slide-enter-active,
.pwa-slide-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}
.pwa-slide-enter-from,
.pwa-slide-leave-to {
  opacity: 0;
  transform: translate(-50%, 12px);
}

/* Barre de mise à jour : elle descend du haut */
.pwa-bar--update.pwa-slide-enter-from,
.pwa-bar--update.pwa-slide-leave-to {
  transform: translate(-50%, -12px);
}
</style>
