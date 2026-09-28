<template>
  <!--
    Téléportée dans `body` : ouverte depuis la commande rapide, elle vivait
    sinon à l'intérieur de la modale et passait dessous.
  -->
  <Teleport to="body">
    <div class="dup" @click.self="$emit('cancel')">
      <div class="dup__box" role="dialog" aria-modal="true" :aria-label="$t('duplicate.title')">
        <!--
          Un colis déjà parti : la cliente saisit l'idée avant d'avoir lu. D'où
          le dessin avant le texte, et le texte en une phrase.
        -->
        <div class="dup__icon" aria-hidden="true">
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 8.5 12 4l9 4.5v7L12 20l-9-4.5z" />
            <path d="M3 8.5 12 13l9-4.5M12 13v7" />
          </svg>
        </div>

        <h2 class="dup__title">{{ $t('duplicate.title') }}</h2>
        <p class="dup__lead">{{ $t('duplicate.lead') }}</p>

        <div class="dup__card">
          <div class="dup__head">
            <strong>{{ order.number }}</strong>
            <span class="dup__status">{{ order.status_label }}</span>
          </div>
          <p class="dup__date">{{ placedOn }}</p>

          <!--
            Les articles en vignettes, qui défilent au doigt. Une liste de noms
            demande de lire ; une image se reconnaît d'un coup d'œil.
          -->
          <ul class="dup__items">
            <li v-for="(item, i) in order.items" :key="i" class="dup__item">
              <img
                v-if="item.image"
                :src="item.image"
                :alt="item.name"
                class="dup__thumb"
                loading="lazy"
              />
              <span v-else class="dup__thumb dup__thumb--empty" aria-hidden="true">🌹</span>
              <span class="dup__qty">×{{ item.quantity }}</span>
              <span class="dup__name">{{ item.name }}</span>
            </li>
          </ul>

          <p v-if="order.total" class="dup__total">
            <span>{{ $t('common.total') }}</span>
            <strong>{{ formattedTotal }}</strong>
          </p>
        </div>

        <!-- Aller voir celle qui est déjà en route, plutôt que d'en refaire une. -->
        <RouterLink
          v-if="orderLink"
          :to="orderLink"
          class="dup__see"
          @click="$emit('cancel')"
        >
          {{ $t('duplicate.see') }} →
        </RouterLink>

        <div class="dup__actions">
          <button type="button" class="dup__btn dup__btn--ghost" @click="$emit('cancel')">
            {{ $t('duplicate.cancel') }}
          </button>
          <button
            ref="confirmButton"
            type="button"
            class="dup__btn dup__btn--main"
            :disabled="busy"
            @click="$emit('confirm')"
          >
            {{ busy ? $t('common.loading') : $t('duplicate.confirm') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/features/auth/auth.store'

const props = defineProps({
  order: { type: Object, required: true },
  busy:  { type: Boolean, default: false },
})

const emit = defineEmits(['confirm', 'cancel'])

const { locale } = useI18n()
const auth = useAuthStore()

const confirmButton = ref(null)

/*
 * Le lien n'est proposé qu'à une cliente connectée : la page de suivi exige un
 * compte, et l'envoyer sur un écran de connexion serait pire que rien. En
 * commande rapide, elle ne l'est pas encore.
 */
const orderLink = computed(() => auth.user && props.order.number
  ? { name: 'order', params: { number: props.order.number } }
  : null)

const placedOn = computed(() => {
  const value = props.order.created_at
  if (!value) return ''
  return new Date(value).toLocaleString(locale.value, {
    day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit',
  })
})

const formattedTotal = computed(() => new Intl.NumberFormat(locale.value, {
  style: 'currency',
  currency: props.order.currency || 'XOF',
  minimumFractionDigits: 0,
}).format(Number(props.order.total ?? 0)))

/* Échap annule, et le focus part sur le bouton principal : la question se
   répond au clavier, sans chercher la souris. */
function onKeydown(event) {
  if (event.key === 'Escape') emit('cancel')
}

onMounted(() => {
  window.addEventListener('keydown', onKeydown)
  confirmButton.value?.focus()
})

onUnmounted(() => window.removeEventListener('keydown', onKeydown))
</script>

<style scoped>
/*
 * Pixels et couleurs littérales, sans variable de thème : cette fenêtre s'ouvre
 * par-dessus d'autres écrans et doit s'afficher pareil d'où qu'on l'appelle.
 */
.dup {
  position: fixed;
  inset: 0;
  /* Au-dessus de la modale de commande rapide (1100), d'où elle est ouverte. */
  z-index: 1300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  background: rgba(30, 12, 20, 0.55);
}

.dup__box {
  box-sizing: border-box;
  width: 100%;
  max-width: 340px;
  max-height: 88vh;
  overflow-y: auto;
  padding: 24px 20px 20px;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.25);
  text-align: center;
  font-family: inherit;
}

.dup__icon {
  width: 56px;
  height: 56px;
  margin: 0 auto 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: #fdeaf1;
  color: var(--rose-500);
}

.dup__title {
  margin: 0 0 6px;
  font-size: 1.1875rem;
  font-weight: 700;
  line-height: 1.3;
  color: #3d2731;
}

.dup__lead {
  margin: 0 0 16px;
  font-size: 0.9375rem;
  line-height: 1.5;
  color: #6b5560;
}

.dup__card {
  box-sizing: border-box;
  padding: 14px;
  border-radius: 12px;
  background: #fdf4f5;
  text-align: left;
}

.dup__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.dup__head strong { font-size: 0.9375rem; color: #3d2731; }

.dup__status {
  flex-shrink: 0;
  padding: 2px 9px;
  border-radius: 999px;
  background: #fef3c7;
  color: #b45309;
  font-size: 0.6875rem;
  font-weight: 600;
  white-space: nowrap;
}

.dup__date { margin: 2px 0 10px; font-size: 0.75rem; color: #9b8792; }

/* Défilement horizontal, avec accroche : au doigt sur téléphone. */
.dup__items {
  display: flex;
  gap: 10px;
  margin: 0;
  padding: 0 0 4px;
  list-style: none;
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  -webkit-overflow-scrolling: touch;
}
.dup__items::-webkit-scrollbar { height: 3px; }
.dup__items::-webkit-scrollbar-thumb { background: #f0d5dd; border-radius: 2px; }

.dup__item {
  position: relative;
  flex: 0 0 64px;
  scroll-snap-align: start;
  text-align: center;
}

.dup__thumb {
  display: block;
  width: 64px;
  height: 64px;
  object-fit: cover;
  border-radius: 10px;
  background: #fff;
  border: 1px solid #f3dfe5;
}
.dup__thumb--empty {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
}

.dup__qty {
  position: absolute;
  top: -5px;
  right: -5px;
  min-width: 20px;
  padding: 1px 5px;
  border-radius: 999px;
  background: var(--rose-500);
  color: #fff;
  font-size: 0.6875rem;
  font-weight: 700;
}

.dup__name {
  display: block;
  margin-top: 4px;
  font-size: 0.6875rem;
  line-height: 1.25;
  color: #6b5560;
  /* Deux lignes au plus : au-delà, les vignettes ne s'alignent plus. */
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.dup__total {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  margin: 12px 0 0;
  padding-top: 10px;
  border-top: 1px solid #f3dfe5;
  font-size: 0.875rem;
  color: #6b5560;
}
.dup__total strong { font-size: 1rem; color: var(--rose-500); }

.dup__see {
  display: inline-block;
  margin-top: 12px;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--rose-500);
  text-decoration: none;
}
.dup__see:hover { text-decoration: underline; }

.dup__actions {
  display: flex;
  gap: 10px;
  margin-top: 16px;
}

.dup__btn {
  flex: 1;
  padding: 12px 10px;
  border-radius: 999px;
  border: 1.5px solid transparent;
  font-family: inherit;
  font-size: 0.9375rem;
  font-weight: 600;
  line-height: 1.2;
  cursor: pointer;
}
.dup__btn--ghost {
  border-color: #f0d5dd;
  background: #fff;
  color: #6b5560;
}
.dup__btn--ghost:hover { background: #fdf4f5; }

.dup__btn--main { background: var(--rose-500); color: #fff; }
.dup__btn--main:hover { background: #d42a60; }
.dup__btn--main:disabled { opacity: 0.6; cursor: default; }

/* Sur un petit téléphone, les boutons l'un sous l'autre : aucun mauvais appui,
   et la réponse la plus engageante reste en haut. */
@media (max-width: 380px) {
  .dup__actions { flex-direction: column-reverse; }
}
</style>
