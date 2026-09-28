<template>
  <main class="program-page">

    <!-- ── Hero ── -->
    <section class="program-hero">
      <div class="container program-hero__inner">
        <div class="program-hero__text">
          <span class="eyebrow">{{ $t('program.eyebrow') }}</span>
          <h1 class="display-lg program-hero__title">
            {{ $t('program.clubName') }} <em>{{ settings.shopName }}</em>
          </h1>
          <p class="program-hero__desc">
            {{ $t('program.heroDesc') }}
          </p>
          <div v-if="!authStore.user" class="program-hero__cta">
            <RouterLink :to="{ name: 'register' }" class="btn btn-primary btn-lg">{{ $t('program.joinFree') }}</RouterLink>
            <RouterLink :to="{ name: 'login' }"    class="btn btn-ghost btn-lg">{{ $t('common.login') }}</RouterLink>
          </div>
          <div v-else-if="!isMember" class="program-hero__cta">
            <button @click="joinProgram" :disabled="joining" class="btn btn-primary btn-lg">
              <span v-if="joining">{{ $t('program.joining') }}</span>
              <span v-else>{{ $t('program.joinClub') }}</span>
            </button>
          </div>
          <div v-else class="program-hero__badge">
            <span class="tier-badge" :class="`tier-badge--${membership.tier}`">
              <span class="tier-badge__ico" v-html="tierIcon(membership.tier)"></span> {{ $t('program.memberTier', { tier: tierLabel(membership.tier) }) }}
            </span>
            <span class="points-display">{{ membership.points ?? 0 }} {{ $t('program.pts') }}</span>
          </div>
        </div>
        <div class="program-hero__visual">
          <div class="program-hero__orb orb-1"></div>
          <div class="program-hero__orb orb-2"></div>
          <div class="program-hero__card-stack">
            <div class="loyalty-card loyalty-card--gold">
              <span class="loyalty-card__logo"><FlowerMark /> {{ settings.shopName }}</span>
              <span class="loyalty-card__tier">{{ $t('program.tierNameGold') }}</span>
              <span class="loyalty-card__pts">2 500 {{ $t('program.pts') }}</span>
            </div>
            <div class="loyalty-card loyalty-card--silver">
              <span class="loyalty-card__logo"><FlowerMark /> {{ settings.shopName }}</span>
              <span class="loyalty-card__tier">{{ $t('program.tierNameSilver') }}</span>
              <span class="loyalty-card__pts">1 200 {{ $t('program.pts') }}</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div class="container program-content">

      <!-- ── Dashboard membre ── -->
      <section v-if="authStore.user && isMember" class="program-section">
        <h2 class="program-section__title">{{ $t('program.memberSpace') }}</h2>
        <div class="member-dashboard">

          <!-- Points & niveau -->
          <div class="member-card member-card--points card">
            <div class="member-card__icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6M18 9h1.5a2.5 2.5 0 0 0 0-5H18M4 22h16M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22M18 2H6v7a6 6 0 0 0 12 0V2z"/></svg></div>
            <div>
              <p class="member-card__value">{{ membership.points ?? 0 }}</p>
              <p class="member-card__label">{{ $t('program.loyaltyPoints') }}</p>
            </div>
            <div class="tier-progress">
              <p class="tier-progress__label">
                <span class="tier-badge tier-badge--sm" :class="`tier-badge--${membership.tier}`">
                  {{ tierLabel(membership.tier) }}
                </span>
                <span class="text-xs text-gray-400">→ {{ nextTierLabel }}</span>
              </p>
              <div class="tier-progress__bar">
                <div class="tier-progress__fill" :style="{ width: tierProgressPct + '%' }"></div>
              </div>
              <p class="text-xs text-gray-400 mt-1">{{ $t('program.ptsToNextTier', { count: pointsToNextTier }) }}</p>
            </div>
          </div>

          <!-- Commandes / Achats -->
          <div class="member-card card">
            <div class="member-card__icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/></svg></div>
            <div>
              <p class="member-card__value">{{ membership.orders_count ?? 0 }}</p>
              <p class="member-card__label">{{ $t('program.ordersPlaced') }}</p>
            </div>
          </div>

          <!-- Routine tracker -->
          <div class="member-card card">
            <div class="member-card__icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 5.8L19.5 9l-4.5 3.4L16.5 18 12 14.7 7.5 18l1.5-5.6L4.5 9l5.6-.2z"/></svg></div>
            <div>
              <p class="member-card__value">{{ trackerStreak }} {{ $t('program.dayShort') }}</p>
              <p class="member-card__label">{{ $t('program.routineDays') }}</p>
            </div>
            <button @click="showTracker = true" class="btn btn-outline btn-sm mt-2">{{ $t('program.viewTracker') }}</button>
          </div>

          <!-- Consultations -->
          <div class="member-card card">
            <div class="member-card__icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg></div>
            <div>
              <p class="member-card__value">{{ myConsultations.length }}</p>
              <p class="member-card__label">{{ $t('program.consultations') }}</p>
            </div>
            <button @click="showConsultationForm = true" class="btn btn-primary btn-sm mt-2">{{ $t('program.newAppointment') }}</button>
          </div>

        </div>
      </section>

      <!-- ── Suivi routine (modal) ── -->
      <Teleport to="body">
        <div v-if="showTracker" class="modal-overlay" @click.self="showTracker = false">
          <div class="modal-card card">
            <div class="modal-card__header">
              <h3>{{ $t('program.trackerTitle') }}</h3>
              <button @click="showTracker = false" class="modal-card__close">✕</button>
            </div>
            <div class="tracker-body">
              <div class="tracker-streak">
                <span class="tracker-streak__num">{{ trackerStreak }}</span>
                <span class="tracker-streak__label">{{ $t('program.consecutiveDays') }}</span>
              </div>
              <div class="tracker-week">
                <div
                  v-for="(day, i) in trackerWeek"
                  :key="i"
                  class="tracker-day"
                  :class="{ 'tracker-day--done': day.done, 'tracker-day--today': day.isToday }"
                >
                  <span class="tracker-day__label">{{ day.label }}</span>
                  <span class="tracker-day__icon">
                    <svg v-if="day.done" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg>
                    <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/></svg>
                  </span>
                </div>
              </div>
              <div class="tracker-actions">
                <button
                  v-if="!todayCheckedIn"
                  @click="doCheckin"
                  :disabled="checkingIn"
                  class="btn btn-primary w-full"
                >
                  {{ checkingIn ? $t('program.saving') : $t('program.validateRoutine') }}
                </button>
                <p v-else class="text-green-600 text-center font-medium">{{ $t('program.routineDone') }}</p>
              </div>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- ── Consultation form (modal) ── -->
      <Teleport to="body">
        <div v-if="showConsultationForm" class="modal-overlay" @click.self="showConsultationForm = false">
          <div class="modal-card card">
            <div class="modal-card__header">
              <h3>{{ $t('program.consultTitle') }}</h3>
              <button @click="showConsultationForm = false" class="modal-card__close">✕</button>
            </div>

            <form @submit.prevent="submitConsultation" class="consultation-form">
              <FormField :def="CONSULTATION_FIELDS.type" :error="consultErrors.type">
                <AppSelect v-model="consultForm.type" :options="consultationTypes" :placeholder="$t('program.chooseTopic')" />
              </FormField>

              <FormField :def="CONSULTATION_FIELDS.channel" :error="consultErrors.channel">
                <div class="channel-grid">
                  <label
                    v-for="ch in consultationChannels"
                    :key="ch.value"
                    class="channel-option"
                    :class="{ 'channel-option--active': consultForm.channel === ch.value }"
                  >
                    <input type="radio" :value="ch.value" v-model="consultForm.channel" />
                    <span>{{ ch.label }}</span>
                  </label>
                </div>
              </FormField>

              <FormField v-if="consultForm.channel === 'whatsapp'" :def="CONSULTATION_FIELDS.phone" :error="consultErrors.phone" optional>
                <PhoneInput v-model="consultForm.phone" placeholder="07 00 00 00" />
              </FormField>

              <FormField :def="CONSULTATION_FIELDS.preferred_at" :error="consultErrors.preferred_at" optional>
                <input v-model="consultForm.preferred_at" type="datetime-local" class="input" />
              </FormField>

              <FormField :def="CONSULTATION_FIELDS.message" :error="consultErrors.message" optional>
                <textarea v-model="consultForm.message" rows="3" class="input resize-none"
                  :placeholder="$t('program.requestPlaceholder')"></textarea>
              </FormField>

              <p v-if="consultSuccess" class="text-green-600 text-sm">{{ $t('program.consultSent') }}</p>
              <p v-if="consultError"   class="text-red-500 text-sm">{{ consultError }}</p>

              <button type="submit" :disabled="consultSending" class="btn btn-primary w-full">
                {{ consultSending ? $t('program.sending') : $t('program.sendRequest') }}
              </button>
            </form>
          </div>
        </div>
      </Teleport>

      <!-- ── Niveaux du programme ── -->
      <section class="program-section">
        <h2 class="program-section__title">{{ $t('program.tiersTitle') }}</h2>
        <div class="tiers-grid">
          <div
            v-for="tier in tiers"
            :key="tier.slug"
            class="tier-card card"
            :class="`tier-card--${tier.slug}`"
          >
            <div class="tier-card__header">
              <span class="tier-card__icon" v-html="tier.icon"></span>
              <h3 class="tier-card__name">{{ tier.name }}</h3>
              <p class="tier-card__range">{{ tier.points_min }} – {{ tier.points_max ?? '∞' }} {{ $t('program.pts') }}</p>
            </div>
            <ul class="tier-card__benefits">
              <li v-for="benefit in tier.benefits" :key="benefit">
                <span class="benefit-check">✓</span> {{ benefit }}
              </li>
            </ul>
            <div v-if="authStore.user && membership.tier === tier.slug" class="tier-card__current">
              {{ $t('program.currentTier') }}
            </div>
          </div>
        </div>
      </section>

      <!-- ── Conseiller beauté ── -->
      <section class="program-section">
        <div class="advisor-section">
          <div class="advisor-section__text">
            <span class="eyebrow">{{ $t('program.advisorEyebrow') }}</span>
            <h2 class="program-section__title">{{ $t('program.advisorTitle') }}</h2>
            <p class="advisor-section__desc">
              {{ $t('program.advisorDesc') }}
            </p>
            <ul class="advisor-section__features">
              <li>{{ $t('program.advisorFeature1') }}</li>
              <li>{{ $t('program.advisorFeature2') }}</li>
              <li>{{ $t('program.advisorFeature3') }}</li>
              <li>{{ $t('program.advisorFeature4') }}</li>
            </ul>
            <button
              v-if="authStore.user && isMember"
              @click="showConsultationForm = true"
              class="btn btn-primary btn-lg mt-4"
            >
              {{ $t('program.bookAppointment') }}
            </button>
            <RouterLink
              v-else-if="!authStore.user"
              :to="{ name: 'register' }"
              class="btn btn-primary btn-lg mt-4"
            >
              {{ $t('program.joinToAccess') }}
            </RouterLink>
            <button
              v-else
              @click="joinProgram"
              :disabled="joining"
              class="btn btn-primary btn-lg mt-4"
            >
              {{ $t('program.joinClub') }}
            </button>
          </div>
          <div class="advisor-section__visual">
            <div class="advisor-card">
              <div class="advisor-card__avatar"><FlowerMark /></div>
              <div>
                <p class="advisor-card__name">{{ $t('program.advisorTeam') }}</p>
                <p class="advisor-card__role">{{ $t('program.advisorRole') }}</p>
                <p class="advisor-card__status">
                  <span class="status-dot"></span> {{ $t('program.availableNow') }}
                </p>
              </div>
            </div>
            <div class="advisor-bubbles">
              <div class="advisor-bubble advisor-bubble--in">
                {{ $t('program.bubble1') }}</div>
              <div class="advisor-bubble advisor-bubble--out">
                {{ $t('program.bubble2') }}
              </div>
              <div class="advisor-bubble advisor-bubble--in">
                {{ $t('program.bubble3') }}</div>
            </div>
          </div>
        </div>
      </section>

      <!-- ── Comment gagner des points ── -->
      <section class="program-section">
        <h2 class="program-section__title">{{ $t('program.earnTitle') }}</h2>
        <div class="earn-grid">
          <div v-for="action in earnActions" :key="action.label" class="earn-card card">
            <span class="earn-card__icon" v-html="action.icon"></span>
            <div>
              <p class="earn-card__label">{{ action.label }}</p>
              <p class="earn-card__pts">+ {{ action.pts }} {{ $t('program.pts') }}</p>
            </div>
          </div>
        </div>
      </section>

      <!-- ── Mes consultations (membres connectés) ── -->
      <section v-if="authStore.user && isMember && myConsultations.length" class="program-section">
        <h2 class="program-section__title">{{ $t('program.myConsultations') }}</h2>
        <div class="consultations-list">
          <div v-for="c in myConsultations" :key="c.id" class="consultation-item card">
            <div class="consultation-item__info">
              <span class="consultation-item__type">{{ consultationTypeLabel(c.type) }}</span>
              <span class="consultation-item__channel">{{ consultationChannelLabel(c.channel) }}</span>
              <span v-if="c.preferred_at" class="consultation-item__date">
                {{ formatDate(c.preferred_at) }}
              </span>
            </div>
            <span class="consultation-status" :class="`consultation-status--${c.status}`">
              {{ consultationStatusLabel(c.status) }}
            </span>
          </div>
        </div>
      </section>

    </div>
  </main>
</template>

<script setup>
import { useSettingsStore } from '@/stores/settings'

const settings = useSettingsStore()
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/features/auth/auth.store'
import FormField  from '@/shared/components/ui/FormField.vue'
import AppSelect  from '@/components/ui/AppSelect.vue'
import PhoneInput from '@/components/ui/PhoneInput.vue'
import { programApi } from './program.api'
import {
  CONSULTATION_FIELDS,
  CONSULTATION_TYPES,
  CONSULTATION_CHANNELS,
  makeConsultationForm,
  mapConsultationErrors,
} from './program.fields'

const { t, locale } = useI18n()
const authStore = useAuthStore()

// ── Programme data (public) ────────────────────────────────────────────────
const SVG_ICON = (p) => `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`
const MEDAL = (n) => SVG_ICON(`<circle cx="12" cy="8" r="6"/><path d="M15.5 12.5 17 23l-5-3-5 3 1.5-10.5"/><text x="12" y="10" font-size="6" text-anchor="middle" fill="currentColor" stroke="none">${n}</text>`)
const ICON_BAG    = SVG_ICON('<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/>')
const ICON_SPARK  = SVG_ICON('<path d="M12 3l1.9 5.8L19.5 9l-4.5 3.4L16.5 18 12 14.7 7.5 18l1.5-5.6L4.5 9l5.6-.2z"/>')
const ICON_STAR   = SVG_ICON('<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>')
const ICON_GIFT   = SVG_ICON('<rect x="3" y="8" width="18" height="4"/><path d="M12 8v13M5 12v9h14v-9M12 8a3 3 0 1 0-3-3c0 1.66 1.34 3 3 3zM12 8a3 3 0 1 1 3-3c0 1.66-1.34 3-3 3z"/>')
const ICON_PHONE  = SVG_ICON('<rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/>')
const ICON_CHAT   = SVG_ICON('<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>')

// Niveaux : ceux de l'admin s'ils existent, sinon les niveaux par défaut ci-dessous
const programData = ref({ tiers: null })

const defaultTiers = computed(() => [
  {
    slug: 'bronze', name: t('program.tierNameBronze'), icon: MEDAL(3),
    points_min: 0, points_max: 499,
    benefits: [
      t('program.tierBronzeBenefit1'),
      t('program.tierBronzeBenefit2'),
      t('program.tierBronzeBenefit3'),
    ],
  },
  {
    slug: 'silver', name: t('program.tierNameSilver'), icon: MEDAL(2),
    points_min: 500, points_max: 1999,
    benefits: [
      t('program.tierSilverBenefit1'),
      t('program.tierSilverBenefit2'),
      t('program.tierSilverBenefit3'),
      t('program.tierSilverBenefit4'),
    ],
  },
  {
    slug: 'gold', name: t('program.tierNameGold'), icon: MEDAL(1),
    points_min: 2000, points_max: null,
    benefits: [
      t('program.tierGoldBenefit1'),
      t('program.tierGoldBenefit2'),
      t('program.tierGoldBenefit3'),
      t('program.tierGoldBenefit4'),
      t('program.tierGoldBenefit5'),
    ],
  },
])

const tiers = computed(() =>
  programData.value.tiers?.length ? programData.value.tiers : defaultTiers.value
)

const earnActions = computed(() => [
  { icon: ICON_BAG,   label: t('program.earnPurchase'),     pts: t('program.earnPurchasePts') },
  { icon: ICON_SPARK, label: t('program.earnRoutine'),      pts: '5' },
  { icon: ICON_STAR,  label: t('program.earnReview'),       pts: '20' },
  { icon: ICON_GIFT,  label: t('program.earnBirthday'),     pts: '100' },
  { icon: ICON_PHONE, label: t('program.earnReferral'),     pts: '150' },
  { icon: ICON_CHAT,  label: t('program.earnConsultation'), pts: '50' },
])

const consultationTypes = computed(() =>
  CONSULTATION_TYPES.map(o => ({ ...o, label: t(o.labelKey) }))
)
const consultationChannels = computed(() =>
  CONSULTATION_CHANNELS.map(o => ({ ...o, label: t(o.labelKey) }))
)

// ── Membership ──────────────────────────────────────────────────────────────
const membership    = ref({})
const isMember      = ref(false)
const joining       = ref(false)

const TIER_THRESHOLDS = { bronze: 0, silver: 500, gold: 2000 }
const TIER_LABEL_KEYS = { bronze: 'program.tierBronze', silver: 'program.tierSilver', gold: 'program.tierGold' }
const TIER_NEXT       = { bronze: 'silver', silver: 'gold', gold: null }

const tierLabel  = (tier) => (TIER_LABEL_KEYS[tier] ? t(TIER_LABEL_KEYS[tier]) : tier)
const tierIcon   = (tier) => ({ bronze: MEDAL(3), silver: MEDAL(2), gold: MEDAL(1) }[tier] ?? MEDAL(''))

const nextTierLabel = computed(() => {
  const next = TIER_NEXT[membership.value.tier]
  return next ? tierLabel(next) : t('program.maxTier')
})

const tierProgressPct = computed(() => {
  const tier = membership.value.tier
  const pts  = membership.value.points ?? 0
  const min  = TIER_THRESHOLDS[tier] ?? 0
  const next = TIER_NEXT[tier]
  if (!next) return 100
  const max  = TIER_THRESHOLDS[next]
  return Math.min(100, Math.round(((pts - min) / (max - min)) * 100))
})

const pointsToNextTier = computed(() => {
  const next = TIER_NEXT[membership.value.tier]
  if (!next) return 0
  return Math.max(0, TIER_THRESHOLDS[next] - (membership.value.points ?? 0))
})

async function joinProgram() {
  joining.value = true
  try {
    const { data } = await programApi.join()
    membership.value = data.membership ?? data
    isMember.value   = true
  } catch (e) {
    console.error(e)
  } finally {
    joining.value = false
  }
}

// ── Tracker ─────────────────────────────────────────────────────────────────
const showTracker    = ref(false)
const trackerData    = ref(null)
const checkingIn     = ref(false)
const todayCheckedIn = ref(false)

const trackerStreak = computed(() => trackerData.value?.streak ?? 0)

const trackerWeek = computed(() => {
  const days = [
    t('program.days.mon'), t('program.days.tue'), t('program.days.wed'), t('program.days.thu'),
    t('program.days.fri'), t('program.days.sat'), t('program.days.sun'),
  ]
  const checkins = trackerData.value?.week ?? []
  const today = new Date().getDay() // 0=Sun,1=Mon…
  const todayIdx = today === 0 ? 6 : today - 1 // Mon=0…Sun=6
  return days.map((label, i) => ({
    label,
    done: checkins.includes(i),
    isToday: i === todayIdx,
  }))
})

async function doCheckin() {
  checkingIn.value = true
  try {
    await programApi.checkin({ date: new Date().toISOString().slice(0, 10) })
    todayCheckedIn.value = true
    if (trackerData.value) {
      trackerData.value.streak = (trackerData.value.streak ?? 0) + 1
    }
    membership.value.points = (membership.value.points ?? 0) + 5
  } catch (e) {
    console.error(e)
  } finally {
    checkingIn.value = false
  }
}

// ── Consultations ───────────────────────────────────────────────────────────
const showConsultationForm = ref(false)
const consultForm   = ref(makeConsultationForm())
const consultErrors = ref({})
const consultSending= ref(false)
const consultSuccess= ref(false)
const consultError  = ref('')
const myConsultations = ref([])

async function submitConsultation() {
  consultSending.value = true
  consultSuccess.value = false
  consultError.value   = ''
  consultErrors.value  = {}
  try {
    const { data } = await programApi.requestConsultation(consultForm.value)
    myConsultations.value.unshift(data.consultation ?? data)
    consultSuccess.value = true
    consultForm.value = makeConsultationForm()
    setTimeout(() => { showConsultationForm.value = false; consultSuccess.value = false }, 3000)
  } catch (e) {
    if (e.response?.status === 422) {
      consultErrors.value = mapConsultationErrors(e.response.data?.errors ?? {})
    }
    consultError.value = e.response?.data?.message ?? t('common.error')
  } finally {
    consultSending.value = false
  }
}

const consultationTypeLabel   = (v) => consultationTypes.value.find(o => o.value === v)?.label ?? v
const consultationChannelLabel= (v) => consultationChannels.value.find(o => o.value === v)?.label ?? v
const consultationStatusLabel = (s) => ({
  pending:   t('program.status.pending'),
  confirmed: t('program.status.confirmed'),
  done:      t('program.status.done'),
  cancelled: t('program.status.cancelled'),
}[s] ?? s)

function formatDate(dt) {
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(dt))
}

// ── Init ─────────────────────────────────────────────────────────────────────
onMounted(async () => {
  // Charger les données publiques du programme (niveaux, avantages admin-configurés)
  try {
    const { data } = await programApi.getProgram()
    if (data.tiers?.length) programData.value.tiers = data.tiers
  } catch { /* fallback sur les données statiques */ }

  if (!authStore.user) return

  // Charger membership + tracker + consultations en parallèle
  try {
    const [memRes, trackerRes, consultRes] = await Promise.allSettled([
      programApi.getMembership(),
      programApi.getTracker(),
      programApi.getMyConsultations(),
    ])

    if (memRes.status === 'fulfilled') {
      const d = memRes.value.data
      membership.value = d.membership ?? d
      isMember.value   = true
    }

    if (trackerRes.status === 'fulfilled') {
      trackerData.value    = trackerRes.value.data
      todayCheckedIn.value = trackerRes.value.data?.today_done ?? false
    }

    if (consultRes.status === 'fulfilled') {
      myConsultations.value = consultRes.value.data.data ?? consultRes.value.data
    }
  } catch { /* utilisateur non-membre */ }
})
</script>

<style scoped>
.program-page {
  min-height: 100vh;
  background: var(--color-bg);
}

/* ── Hero ── */
.program-hero {
  background: linear-gradient(135deg, #fdf2f8 0%, var(--cream-100) 50%, #fef9ec 100%);
  border-bottom: 1px solid var(--cream-200);
  padding: var(--space-16) 0 var(--space-12);
  overflow: hidden;
}
.program-hero__inner {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-12);
  align-items: center;
}
.program-hero__title { margin: var(--space-2) 0; color: var(--gray-800); }
.program-hero__title em { color: var(--color-primary); font-style: italic; }
.program-hero__desc { color: var(--gray-500); font-size: 1rem; line-height: 1.7; margin-bottom: var(--space-6); }
.program-hero__cta  { display: flex; gap: var(--space-3); flex-wrap: wrap; }

.program-hero__badge {
  display: flex;
  align-items: center;
  gap: var(--space-4);
}
.points-display {
  font-family: var(--font-display);
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--rose-600);
}

/* ── Visual / Card stack ── */
.program-hero__visual {
  position: relative;
  height: 300px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.program-hero__orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(40px);
  opacity: 0.35;
}
.orb-1 { width: 220px; height: 220px; background: var(--rose-300); top: 10%; right: 5%; }
.orb-2 { width: 160px; height: 160px; background: #fbbf24; bottom: 5%; left: 10%; }

.program-hero__card-stack { position: relative; width: 240px; height: 220px; }
.loyalty-card {
  position: absolute;
  width: 220px;
  border-radius: 16px;
  padding: var(--space-5);
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  color: #fff;
  font-family: var(--font-display);
  box-shadow: 0 8px 32px rgba(0,0,0,0.15);
}
.loyalty-card--gold {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  top: 0; left: 20px;
  transform: rotate(-3deg);
  z-index: 2;
}
.loyalty-card--silver {
  background: linear-gradient(135deg, #9ca3af, #6b7280);
  top: 60px; left: 0;
  transform: rotate(3deg);
  z-index: 1;
}
.loyalty-card__logo { font-size: 0.75rem; opacity: 0.85; }
.loyalty-card__tier { font-size: 1.125rem; font-weight: 600; }
.loyalty-card__pts  { font-size: 0.875rem; opacity: 0.8; margin-top: auto; }

/* ── Content layout ── */
.program-content { padding: var(--space-12) var(--space-6) var(--space-16); display: flex; flex-direction: column; gap: var(--space-12); }
.program-section__title {
  font-family: var(--font-display);
  font-size: 1.75rem;
  font-weight: 500;
  color: var(--gray-800);
  margin-bottom: var(--space-6);
}

/* ── Member dashboard ── */
.member-dashboard {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: var(--space-4);
}
.member-card {
  padding: var(--space-5);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}
.member-card__icon { font-size: 2rem; line-height: 1; }
.member-card__value {
  font-family: var(--font-display);
  font-size: 2rem;
  font-weight: 700;
  color: var(--gray-800);
  line-height: 1;
}
.member-card__label { font-size: 0.8125rem; color: var(--gray-500); }

.tier-progress { margin-top: var(--space-2); }
.tier-progress__label { display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-2); }
.tier-progress__bar {
  height: 6px;
  background: var(--cream-200);
  border-radius: 99px;
  overflow: hidden;
}
.tier-progress__fill {
  height: 100%;
  background: linear-gradient(90deg, var(--rose-400), var(--rose-600));
  border-radius: 99px;
  transition: width 0.6s ease;
}

/* ── Tier badge ── */
.tier-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 10px;
  border-radius: 99px;
  font-size: 0.8125rem;
  font-weight: 600;
}
.tier-badge--bronze { background: #fef3c7; color: #92400e; }
.tier-badge--silver { background: #f3f4f6; color: #374151; }
.tier-badge--gold   { background: #fef9c3; color: #78350f; }
.tier-badge--sm     { font-size: 0.6875rem; padding: 2px 8px; }

/* ── Tiers grid ── */
.tiers-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: var(--space-5);
}
.tier-card { padding: 0; overflow: hidden; }
.tier-card__header {
  padding: var(--space-6);
  text-align: center;
}
.tier-card--bronze .tier-card__header { background: linear-gradient(135deg, #fef3c7, #fde68a); }
.tier-card--silver .tier-card__header { background: linear-gradient(135deg, #f3f4f6, #e5e7eb); }
.tier-card--gold   .tier-card__header { background: linear-gradient(135deg, #fef9c3, #fde047); }

.tier-card__icon  { font-size: 2.5rem; display: block; margin-bottom: var(--space-2); }
.tier-card__name  { font-family: var(--font-display); font-size: 1.25rem; font-weight: 600; color: var(--gray-800); }
.tier-card__range { font-size: 0.75rem; color: var(--gray-500); margin-top: 4px; }
.tier-card__benefits {
  list-style: none;
  padding: var(--space-5) var(--space-6);
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}
.tier-card__benefits li { font-size: 0.875rem; color: var(--gray-700); display: flex; align-items: flex-start; gap: var(--space-2); }
.benefit-check { color: var(--rose-500); font-weight: 700; flex-shrink: 0; }
.tier-card__current {
  text-align: center;
  padding: var(--space-2) var(--space-4) var(--space-4);
  font-size: 0.75rem;
  color: var(--rose-600);
  font-weight: 600;
}

/* ── Advisor section ── */
.advisor-section {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-10);
  align-items: center;
}
.advisor-section__desc {
  color: var(--gray-500);
  line-height: 1.7;
  margin-bottom: var(--space-4);
}
.advisor-section__features {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  font-size: 0.9375rem;
  color: var(--gray-700);
}

.advisor-card {
  display: flex;
  align-items: center;
  gap: var(--space-4);
  background: #fff;
  border-radius: var(--radius-xl);
  padding: var(--space-4) var(--space-5);
  box-shadow: 0 4px 20px rgba(0,0,0,0.07);
  margin-bottom: var(--space-4);
}
.advisor-card__avatar {
  width: 52px; height: 52px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--rose-100), var(--rose-200));
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem;
  flex-shrink: 0;
}
.advisor-card__name { font-weight: 600; color: var(--gray-800); font-size: 0.9375rem; }
.advisor-card__role { font-size: 0.8125rem; color: var(--gray-500); }
.advisor-card__status { display: flex; align-items: center; gap: 6px; font-size: 0.75rem; color: #16a34a; margin-top: 4px; }
.status-dot { width: 8px; height: 8px; background: #16a34a; border-radius: 50%; animation: pulse 2s infinite; }

.advisor-bubbles { display: flex; flex-direction: column; gap: var(--space-2); }
.advisor-bubble {
  max-width: 80%;
  padding: var(--space-3) var(--space-4);
  border-radius: 18px;
  font-size: 0.875rem;
  line-height: 1.4;
}
.advisor-bubble--in {
  background: var(--cream-100);
  color: var(--gray-700);
  border-bottom-left-radius: 4px;
  align-self: flex-start;
}
.advisor-bubble--out {
  background: var(--rose-500);
  color: #fff;
  border-bottom-right-radius: 4px;
  align-self: flex-end;
}

/* ── Earn actions ── */
.earn-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: var(--space-4);
}
.earn-card {
  padding: var(--space-5);
  display: flex;
  align-items: center;
  gap: var(--space-4);
  transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}
.earn-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
.earn-card__icon { font-size: 2rem; flex-shrink: 0; }
.earn-card__label { font-size: 0.875rem; color: var(--gray-700); }
.earn-card__pts { font-family: var(--font-display); font-size: 1rem; font-weight: 700; color: var(--rose-600); }

/* ── Consultations list ── */
.consultations-list { display: flex; flex-direction: column; gap: var(--space-3); }
.consultation-item {
  padding: var(--space-4) var(--space-5);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-4);
}
.consultation-item__info { display: flex; align-items: center; gap: var(--space-3); flex-wrap: wrap; }
.consultation-item__type { font-weight: 500; color: var(--gray-800); font-size: 0.875rem; }
.consultation-item__channel { font-size: 0.8125rem; color: var(--gray-500); }
.consultation-item__date { font-size: 0.8125rem; color: var(--gray-400); }

.consultation-status {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 99px;
  white-space: nowrap;
}
.consultation-status--pending   { background: #fef3c7; color: #92400e; }
.consultation-status--confirmed { background: #dcfce7; color: #166534; }
.consultation-status--done      { background: #f3f4f6; color: #374151; }
.consultation-status--cancelled { background: #fee2e2; color: #991b1b; }

/* ── Modals ── */
.modal-overlay {
  position: fixed; inset: 0; z-index: 500;
  background: rgba(0,0,0,0.45);
  backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center;
  padding: var(--space-4);
}
.modal-card {
  width: 100%; max-width: 520px;
  max-height: 90vh;
  overflow-y: auto;
  padding: 0;
}
.modal-card__header {
  display: flex; align-items: center; justify-content: space-between;
  padding: var(--space-5) var(--space-6);
  border-bottom: 1px solid var(--cream-200);
  font-family: var(--font-display);
  font-size: 1.125rem;
  font-weight: 500;
  position: sticky; top: var(--navbar-height);
  background: #fff;
  z-index: 1;
}
.modal-card__close {
  width: 32px; height: 32px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: var(--gray-400);
  font-size: 0.875rem;
  transition: all var(--transition-fast);
}
.modal-card__close:hover { background: var(--cream-100); color: var(--gray-700); }

/* ── Consultation form ── */
.consultation-form {
  padding: var(--space-6);
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}
.channel-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-2);
}
.channel-option {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3);
  border: 1.5px solid var(--cream-300);
  border-radius: var(--radius-md);
  cursor: pointer;
  font-size: 0.875rem;
  transition: all var(--transition-fast);
}
.channel-option input { display: none; }
.channel-option:hover { border-color: var(--rose-300); }
.channel-option--active { border-color: var(--rose-500); background: var(--rose-50); font-weight: 500; }

/* ── Tracker ── */
.tracker-body { padding: var(--space-6); }
.tracker-streak {
  display: flex;
  align-items: baseline;
  gap: var(--space-2);
  margin-bottom: var(--space-6);
  justify-content: center;
}
.tracker-streak__num {
  font-family: var(--font-display);
  font-size: 4rem;
  font-weight: 700;
  color: var(--rose-600);
  line-height: 1;
}
.tracker-streak__label { font-size: 1.125rem; color: var(--gray-500); }
.tracker-week {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: var(--space-2);
  margin-bottom: var(--space-5);
}
.tracker-day {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}
.tracker-day__label { font-size: 0.625rem; color: var(--gray-400); text-transform: uppercase; }
.tracker-day__icon  { font-size: 1.25rem; }
.tracker-day--today .tracker-day__label { color: var(--rose-500); font-weight: 700; }
.tracker-actions { margin-top: var(--space-4); }

/* ── Animations ── */
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0.5; }
}

/* ── Responsive ── */
@media (max-width: 1024px) {
  .program-hero__inner { grid-template-columns: 1fr; }
  .program-hero__visual { display: none; }
  .advisor-section { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
  .tiers-grid { grid-template-columns: 1fr; }
  .channel-grid { grid-template-columns: 1fr; }
  .tracker-week { grid-template-columns: repeat(7, 1fr); gap: 4px; }
  .tracker-day__label { font-size: 0.5rem; }
}
</style>
