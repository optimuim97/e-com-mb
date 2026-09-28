<template>
  <a
    :href="whatsappUrl"
    target="_blank"
    rel="noopener noreferrer"
    class="wa-btn"
    :class="{ 'wa-btn--expanded': expanded }"
    @mouseenter="expanded = true"
    @mouseleave="expanded = false"
    aria-label="Nous contacter sur WhatsApp"
  >
    <svg
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 24 24"
      fill="currentColor"
      class="wa-btn__icon"
    >
      <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
      <path d="M12 0C5.373 0 0 5.373 0 12c0 2.127.558 4.121 1.532 5.847L.057 23.884a.5.5 0 0 0 .613.613l6.037-1.475A11.943 11.943 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22a9.943 9.943 0 0 1-5.073-1.389l-.363-.216-3.762.919.955-3.668-.236-.377A9.944 9.944 0 0 1 2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/>
    </svg>

    <span class="wa-btn__label">{{ label }}</span>
  </a>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  phone: {
    type: String,
    required: true,
  },
  message: {
    type: String,
    default: "Bonjour ! J'ai besoin d'aide.",
  },
  label: {
    type: String,
    default: 'Support WhatsApp',
  },
});

const expanded = ref(false);

const whatsappUrl = computed(
  () => `https://wa.me/${props.phone}?text=${encodeURIComponent(props.message)}`
);
</script>

<style scoped>
.wa-btn {
  position: fixed;
  right: 20px;
  /* Desktop : coin bas-droit classique */
  bottom: 24px;
  z-index: 45; /* sous le drawer panier (300) et la navbar, au-dessus du contenu */
  display: flex;
  align-items: center;
  gap: 0;
  height: 56px;
  padding: 0 16px;
  background: #25d366;
  color: #fff;
  border-radius: 999px;
  text-decoration: none;
  box-shadow: 0 6px 18px -4px rgba(37, 211, 102, 0.5);
  /* Transition douce, AUCUNE animation en boucle */
  transition: padding 0.25s ease, background 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
  -webkit-tap-highlight-color: transparent;
}
.wa-btn:hover {
  background: #1ebe5d;
  box-shadow: 0 10px 24px -6px rgba(37, 211, 102, 0.6);
}

.wa-btn__icon {
  width: 26px;
  height: 26px;
  flex-shrink: 0;
}

/* Label : caché par défaut, révélé au hover sur desktop */
.wa-btn__label {
  max-width: 0;
  opacity: 0;
  overflow: hidden;
  white-space: nowrap;
  font-size: 0.875rem;
  font-weight: 600;
  margin-left: 0;
  transition: max-width 0.25s ease, opacity 0.2s ease, margin-left 0.25s ease;
}
.wa-btn--expanded .wa-btn__label {
  max-width: 180px;
  opacity: 1;
  margin-left: 10px;
}

/* ── Mobile : icône seule, remontée AU-DESSUS du BottomNav ──────────────── */
@media (max-width: 768px) {
  .wa-btn {
    /* BottomNav fait ~58px + safe-area. On se place juste au-dessus. */
    bottom: calc(72px + env(safe-area-inset-bottom, 0px));
    right: 16px;
    height: 48px;
    width: 48px;
    padding: 0;
    justify-content: center;
  }
  .wa-btn__icon { width: 24px; height: 24px; }
  /* Jamais de label sur mobile (pas de hover fiable) */
  .wa-btn__label,
  .wa-btn--expanded .wa-btn__label {
    display: none;
  }
}

/* Respecte la préférence de réduction de mouvement */
@media (prefers-reduced-motion: reduce) {
  .wa-btn,
  .wa-btn__label { transition: none; }
}
</style>
