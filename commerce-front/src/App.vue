<template>
  <RouterView />
  <WhatsAppButton
    v-if="!isAdminRoute && !isCourierRoute && !settings.whatsappButtonHidden && settings.whatsappNumber"
    :phone="settings.whatsappNumber"
  />
  <FloatingCart v-if="!isAdminRoute && !isCourierRoute" />
  <!-- Installation / mise à jour PWA — hors back-office : l'invite
       « installez la boutique » n'a aucun sens pour un agent.
       L'espace livreur porte la sienne, formulée pour lui : bouton d'achat,
       panier flottant et invite boutique n'y ont rien à faire. -->
  <PwaPrompts v-if="!isAdminRoute" :install="!isCourierRoute" />
</template>

<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import { RouterView, useRoute } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useI18n } from 'vue-i18n';
import WhatsAppButton from '@/components/shop/WhatsAppButton.vue';
import FloatingCart   from '@/components/shop/FloatingCart.vue';
import PwaPrompts     from '@/components/ui/PwaPrompts.vue';
import { useSettingsStore } from '@/stores/settings';

const { t } = useI18n();
const route    = useRoute();
const toast    = useToast();
const settings = useSettingsStore();

// Charger les settings dès le démarrage de l'app (tous les layouts en profitent)
settings.fetch().catch(() => {})

const isAdminRoute   = computed(() => route.path.startsWith('/admin'));
const isCourierRoute = computed(() => route.path.startsWith('/livreur'));

function onServerError() {
  toast.error(
    t('serverError'),
    { timeout: 7000, closeOnClick: true },
  );
}

onMounted(()  => window.addEventListener('api:server-error', onServerError));
onUnmounted(() => window.removeEventListener('api:server-error', onServerError));
</script>
