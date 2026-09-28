<template>
  <div class="contact-page">

    <!-- ── Hero ── -->
    <section class="cp-hero">
      <div class="container cp-hero__inner">
        <div class="cp-hero__text">
          <span class="eyebrow">{{ $t('contact.eyebrow') }}</span>
          <h1 class="display-lg cp-hero__title">{{ $t('contact.title') }}</h1>
          <p class="cp-hero__desc">
            {{ $t('contact.desc') }}
          </p>

        <!-- ── Coordonnées visibles + copier ── -->
        <div class="cp-coords">
          <div v-if="settings.whatsappNumber" class="cp-coord">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.56 1.18h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 8.7a16 16 0 0 0 6.34 6.34l.88-.88a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
            </svg>
            <span class="cp-coord__val">{{ settings.whatsappNumber }}</span>
            <button
              class="cp-coord__copy"
              :class="{ 'cp-coord__copy--done': copied === 'phone' }"
              @click="copyToClipboard(settings.whatsappNumber, 'phone')"
              :title="copied === 'phone' ? $t('orders.copied') : $t('orders.copyNumber')"
            >
              <svg v-if="copied !== 'phone'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
              <svg v-else width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
              {{ copied === 'phone' ? $t('orders.copied') : $t('contact.copy') }}
            </button>
          </div>
          <div class="cp-coord__sep" v-if="settings.whatsappNumber && shopEmail">·</div>
          <div v-if="shopEmail" class="cp-coord">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="4" width="20" height="16" rx="2"/>
              <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
            </svg>
            <span class="cp-coord__val">{{ shopEmail }}</span>
            <button
              class="cp-coord__copy"
              :class="{ 'cp-coord__copy--done': copied === 'email' }"
              @click="copyToClipboard(shopEmail, 'email')"
              :title="copied === 'email' ? $t('orders.copied') : $t('contact.copyEmail')"
            >
              <svg v-if="copied !== 'email'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
              <svg v-else width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
              {{ copied === 'email' ? $t('orders.copied') : $t('contact.copy') }}
            </button>
          </div>
        </div>

        <!-- ── Actions rapides ── -->
        <div class="cp-quick">

          <a
            v-if="phoneNumber"
            :href="`https://wa.me/${phoneNumber}`"
            target="_blank"
            rel="noopener"
            class="cp-quick__btn cp-quick__btn--wa"
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
              <path d="M12 0C5.373 0 0 5.373 0 12c0 2.124.556 4.118 1.528 5.845L0 24l6.335-1.508A11.95 11.95 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.89 0-3.663-.493-5.195-1.355l-.372-.218-3.862.919.979-3.761-.242-.387A9.96 9.96 0 0 1 2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/>
            </svg>
            WhatsApp
          </a>

          <a
            v-if="phoneNumber"
            :href="`tel:${phoneNumber}`"
            class="cp-quick__btn cp-quick__btn--call"
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.56 1.18h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 8.7a16 16 0 0 0 6.34 6.34l.88-.88a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
            </svg>
            {{ $t('contact.call') }}
          </a>

          <a
            v-if="shopEmail"
            :href="`mailto:${shopEmail}`"
            class="cp-quick__btn cp-quick__btn--email"
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="4" width="20" height="16" rx="2"/>
              <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
            </svg>
            {{ $t('contact.sendEmail') }}
          </a>
        </div>
        </div><!-- /cp-hero__text -->

        <!-- ── Image ── -->
        <div class="cp-hero__img-wrap">
          <img :src="settings.data.contact_image_url || '/placeholders/photo-2.jpg'" :alt="settings.shopName" class="cp-hero__img" />
          <div class="cp-hero__img-overlay"></div>
          <div class="cp-hero__img-badge">
            <span><FlowerMark /></span>
            <span>{{ $t('contact.reply24h') }}</span>
          </div>
        </div>

      </div>
    </section>

    <section class="cp-body">
      <div class="container cp-body__grid">

        <!-- ── Formulaire ── -->
        <div class="cp-form-wrap">
          <h2 class="cp-section-title">{{ $t('contact.formTitle') }}</h2>

          <!-- Succès -->
          <Transition name="fade">
            <div v-if="success" class="cp-success">
              <div class="cp-success__icon"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg></div>
              <h3>{{ $t('contact.successTitle') }}</h3>
              <p>{{ $t('contact.successThanks') }} <strong>{{ sentName }}</strong>{{ $t('contact.successDesc') }}</p>
              <button class="btn btn-outline" @click="resetForm">{{ $t('contact.sendAnother') }}</button>
            </div>
          </Transition>

          <form v-if="!success" class="cp-form" @submit.prevent="submit" novalidate>
            <!-- Nom + Email -->
            <div class="cp-form__row">
              <div class="cp-field">
                <label for="cf-name">{{ $t('auth.fullName') }} <span class="req">*</span></label>
                <input
                  id="cf-name"
                  v-model="form.name"
                  class="input"
                  :class="{ 'input--error': errors.name }"
                  type="text"
                  :placeholder="$t('contact.namePlaceholder')"
                  autocomplete="name"
                />
                <span v-if="errors.name" class="cp-error">{{ errors.name }}</span>
              </div>
              <div class="cp-field">
                <label for="cf-email">{{ $t('auth.email') }} <span class="req">*</span></label>
                <input
                  id="cf-email"
                  v-model="form.email"
                  class="input"
                  :class="{ 'input--error': errors.email }"
                  type="email"
                  :placeholder="$t('contact.emailPlaceholder')"
                  autocomplete="email"
                />
                <span v-if="errors.email" class="cp-error">{{ errors.email }}</span>
              </div>
            </div>

            <!-- Téléphone + Sujet -->
            <div class="cp-form__row">
              <div class="cp-field">
                <label for="cf-phone">{{ $t('auth.phone') }}</label>
                <PhoneInput
                  id="cf-phone"
                  v-model="form.phone"
                  placeholder="07 00 00 00 00"
                />
              </div>
              <div class="cp-field">
                <label for="cf-subject">{{ $t('contact.subject') }} <span class="req">*</span></label>
                <select
                  id="cf-subject"
                  v-model="form.subject"
                  class="input"
                  :class="{ 'input--error': errors.subject }"
                >
                  <option value="" disabled>{{ $t('contact.chooseSubject') }}</option>
                  <option v-for="s in subjects" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <span v-if="errors.subject" class="cp-error">{{ errors.subject }}</span>
              </div>
            </div>

            <!-- Message -->
            <div class="cp-field">
              <label for="cf-message">{{ $t('contact.message') }} <span class="req">*</span></label>
              <textarea
                id="cf-message"
                v-model="form.message"
                class="input cp-textarea"
                :class="{ 'input--error': errors.message }"
                :placeholder="$t('contact.messagePlaceholder')"
                rows="5"
              ></textarea>
              <div class="cp-char">{{ form.message.length }}/3000</div>
              <span v-if="errors.message" class="cp-error">{{ errors.message }}</span>
            </div>

            <!-- Erreur globale -->
            <div v-if="globalError" class="cp-global-error">{{ globalError }}</div>

            <button type="submit" class="btn btn-primary cp-submit" :disabled="submitting">
              <span v-if="!submitting">{{ $t('contact.submit') }}</span>
              <span v-else class="cp-loading">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="spin">
                  <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                </svg>
                {{ $t('contact.submitting') }}
              </span>
            </button>
          </form>
        </div>

        <!-- ── Infos de contact ── -->
        <div class="cp-info">
          <h2 class="cp-section-title">{{ $t('contact.findUs') }}</h2>

          <div class="cp-info__cards">
            <!-- WhatsApp -->
            <a
              v-if="phoneNumber"
              :href="`https://wa.me/${phoneNumber}`"
              target="_blank"
              rel="noopener"
              class="cp-info-card cp-info-card--whatsapp"
            >
              <span class="cp-info-card__icon cp-info-card__icon--wa">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                  <path d="M12 0C5.373 0 0 5.373 0 12c0 2.124.556 4.118 1.528 5.845L0 24l6.335-1.508A11.95 11.95 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.89 0-3.663-.493-5.195-1.355l-.372-.218-3.862.919.979-3.761-.242-.387A9.96 9.96 0 0 1 2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/>
                </svg>
              </span>
              <div class="cp-info-card__body">
                <p class="cp-info-card__label">WhatsApp</p>
                <p class="cp-info-card__val">{{ settings.whatsappNumber }}</p>
                <span class="cp-info-card__sub">{{ $t('contact.whatsappSub') }}</span>
              </div>
              <svg class="cp-info-card__arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>

            <!-- Appel direct -->
            <a
              v-if="phoneNumber"
              :href="`tel:${phoneNumber}`"
              class="cp-info-card cp-info-card--call"
            >
              <span class="cp-info-card__icon cp-info-card__icon--call">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.56 1.18h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 8.7a16 16 0 0 0 6.34 6.34l.88-.88a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                </svg>
              </span>
              <div class="cp-info-card__body">
                <p class="cp-info-card__label">{{ $t('auth.phone') }}</p>
                <p class="cp-info-card__val">{{ settings.whatsappNumber }}</p>
                <span class="cp-info-card__sub">{{ $t('contact.callSub') }}</span>
              </div>
              <svg class="cp-info-card__arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>

            <!-- Email cliquable -->
            <a
              v-if="shopEmail"
              :href="`mailto:${shopEmail}`"
              class="cp-info-card cp-info-card--email"
            >
              <span class="cp-info-card__icon cp-info-card__icon--email">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="2" y="4" width="20" height="16" rx="2"/>
                  <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                </svg>
              </span>
              <div class="cp-info-card__body">
                <p class="cp-info-card__label">Email</p>
                <p class="cp-info-card__val">{{ shopEmail }}</p>
                <span class="cp-info-card__sub">{{ $t('contact.emailSub') }}</span>
              </div>
              <svg class="cp-info-card__arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>

            <!-- Horaires -->
            <div class="cp-info-card">
              <span class="cp-info-card__icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></span>
              <div class="cp-info-card__body">
                <p class="cp-info-card__label">{{ $t('contact.hours') }}</p>
                <p class="cp-info-card__val">{{ $t('contact.hoursDays') }}</p>
                <span class="cp-info-card__sub">{{ $t('contact.hoursRange') }}</span>
              </div>
            </div>

            <!-- Localisation -->
            <div class="cp-info-card">
              <span class="cp-info-card__icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
              <div class="cp-info-card__body">
                <p class="cp-info-card__label">{{ $t('contact.location') }}</p>
                <p class="cp-info-card__val">{{ [settings.shopCity, settings.shopCountry].filter(Boolean).join(', ') || $t('contact.locationVal') }}</p>
                <span class="cp-info-card__sub">{{ $t('contact.locationSub') }}</span>
              </div>
            </div>
          </div>

          <!-- FAQ rapide -->
          <div class="cp-faq">
            <h3 class="cp-faq__title">{{ $t('contact.faqTitle') }}</h3>
            <div
              v-for="(faq, i) in faqs"
              :key="i"
              class="cp-faq__item"
              :class="{ 'cp-faq__item--open': openFaq === i }"
            >
              <button class="cp-faq__q" @click="openFaq = openFaq === i ? null : i">
                {{ faq.q }}
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                  <polyline points="6 9 12 15 18 9"/>
                </svg>
              </button>
              <Transition name="faq-open">
                <p v-if="openFaq === i" class="cp-faq__a">{{ faq.a }}</p>
              </Transition>
            </div>
          </div>
        </div>

      </div>
    </section>

  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/api'
import { useSettingsStore } from '@/stores/settings'
import PhoneInput from '@/components/ui/PhoneInput.vue'
import { useSeo, breadcrumbJsonLd } from '@/composables/useSeo'

const { t }    = useI18n()
const settings = useSettingsStore()

useSeo(() => ({
  title: t('contact.seoTitle'),
  description: t('contact.seoDescription'),
  canonical: '/contact',
  jsonLd: breadcrumbJsonLd([
    { name: t('common.home'), path: '/' },
    { name: t('contact.breadcrumb'), path: '/contact' },
  ]),
}))

// Charger les paramètres au montage
onMounted(() => {
  settings.fetch()
})

// Numéro nettoyé (digits only) pour les liens tel: et wa.me:
const phoneNumber = computed(() =>
  (settings.whatsappNumber || '').replace(/\D/g, '')
)
// Email du shop
const shopEmail = computed(() =>
  settings.shopEmail || ''
)

// La valeur envoyée à l'API reste en français : seul le libellé est traduit.
const subjects = computed(() => [
  { value: 'Question sur un produit', label: t('contact.subjects.product') },
  { value: 'Suivi de commande',       label: t('contact.subjects.orderTracking') },
  { value: 'Retour / remboursement',  label: t('contact.subjects.returnRefund') },
  { value: 'Partenariat',             label: t('contact.subjects.partnership') },
  { value: 'Presse & médias',         label: t('contact.subjects.press') },
  { value: 'Autre',                   label: t('contact.subjects.other') },
])

const faqs = computed(() => [
  { q: t('contact.faq1q'), a: t('contact.faq1a', { zone: settings.homeCity }) },
  { q: t('contact.faq2q'), a: t('contact.faq2a') },
  { q: t('contact.faq3q'), a: t('contact.faq3a') },
  { q: t('contact.faq4q'), a: t('contact.faq4a') },
])

const form = reactive({
  name:    '',
  email:   '',
  phone:   '',
  subject: '',
  message: '',
})

const errors      = reactive({})
const globalError = ref('')
const submitting  = ref(false)
const success     = ref(false)
const sentName    = ref('')
const openFaq     = ref(null)
const copied      = ref('')   // 'phone' | 'email' | ''

async function copyToClipboard(text, key) {
  try {
    await navigator.clipboard.writeText(text)
    copied.value = key
    setTimeout(() => { copied.value = '' }, 2000)
  } catch {
    // Fallback pour les navigateurs sans Clipboard API
    const el = document.createElement('textarea')
    el.value = text
    document.body.appendChild(el)
    el.select()
    document.execCommand('copy')
    document.body.removeChild(el)
    copied.value = key
    setTimeout(() => { copied.value = '' }, 2000)
  }
}

function validate() {
  Object.keys(errors).forEach(k => delete errors[k])
  let ok = true
  if (!form.name.trim())    { errors.name    = t('contact.errNameRequired'); ok = false }
  if (!form.email.trim())   { errors.email   = t('contact.errEmailRequired'); ok = false }
  else if (!/\S+@\S+\.\S+/.test(form.email)) { errors.email = t('contact.errEmailInvalid'); ok = false }
  if (!form.subject)        { errors.subject = t('contact.errSubjectRequired'); ok = false }
  if (!form.message.trim()) { errors.message = t('contact.errMessageRequired'); ok = false }
  else if (form.message.length > 3000) { errors.message = t('contact.errMessageTooLong'); ok = false }
  return ok
}

async function submit() {
  if (!validate()) return
  submitting.value  = true
  globalError.value = ''
  try {
    await api.post('/contact', { ...form })
    sentName.value = form.name
    success.value  = true
  } catch (err) {
    globalError.value = err?.response?.data?.message ?? t('checkout.submitError')
  } finally {
    submitting.value = false
  }
}

function resetForm() {
  Object.assign(form, { name: '', email: '', phone: '', subject: '', message: '' })
  success.value  = false
  sentName.value = ''
}

</script>

<style scoped>
/* ── Hero ── */
.cp-hero {
  padding: var(--space-16) 0;
  background: linear-gradient(150deg, var(--cream-50) 0%, var(--rose-50) 100%);
  overflow: hidden;
}
.cp-hero__inner {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-12);
  align-items: center;
}
.cp-hero__text {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}
.cp-hero__title { margin: var(--space-2) 0; }
.cp-hero__desc {
  font-size: 1.0625rem;
  color: var(--gray-500);
  line-height: 1.75;
}

/* Image hero */
.cp-hero__img-wrap {
  position: relative;
  border-radius: var(--radius-xl);
  overflow: hidden;
  height: 480px;
  box-shadow: var(--shadow-lg);
}
.cp-hero__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.cp-hero__img-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    to bottom,
    transparent 55%,
    rgba(33, 31, 31, 0.55) 100%
  );
}
.cp-hero__img-badge {
  position: absolute;
  bottom: 20px;
  left: 20px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(255,255,255,0.92);
  backdrop-filter: blur(10px);
  border-radius: var(--radius-full);
  padding: 8px 18px;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--gray-800);
  box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}

/* ── Coordonnées lisibles + copier ── */
.cp-coords {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: center;
  gap: var(--space-3);
  margin-top: var(--space-5);
  padding: var(--space-4) var(--space-5);
  background: rgba(255,255,255,0.7);
  border: 1px solid var(--cream-200);
  border-radius: var(--radius-xl);
  backdrop-filter: blur(8px);
}
.cp-coord {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  color: var(--gray-600);
}
.cp-coord svg { flex-shrink: 0; color: var(--rose-400); }
.cp-coord__val {
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--gray-800);
  letter-spacing: 0.01em;
  user-select: all;  /* sélection facile en un clic */
}
.cp-coord__sep { color: var(--gray-300); font-size: 1.2rem; }
.cp-coord__copy {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: var(--radius-full);
  border: 1.5px solid var(--cream-300);
  background: transparent;
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--gray-500);
  cursor: pointer;
  transition: all var(--transition-fast);
  white-space: nowrap;
}
.cp-coord__copy:hover {
  border-color: var(--rose-300);
  color: var(--rose-500);
  background: var(--rose-50);
}
.cp-coord__copy--done {
  border-color: #16a34a !important;
  color: #16a34a !important;
  background: #f0fdf4 !important;
}

/* ── Boutons d'action rapide (hero) ── */
.cp-quick {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: var(--space-3);
  margin-top: var(--space-8);
}
.cp-quick__btn {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 14px 24px;
  border-radius: var(--radius-full);
  font-size: 0.9375rem;
  font-weight: 600;
  text-decoration: none;
  transition: all var(--transition-normal);
  letter-spacing: 0.01em;
}
.cp-quick__btn--wa {
  background: #25d366;
  color: #fff;
  box-shadow: 0 6px 24px rgba(37, 211, 102, 0.35);
}
.cp-quick__btn--wa:hover {
  background: #1ebe5a;
  transform: translateY(-2px);
  box-shadow: 0 10px 32px rgba(37, 211, 102, 0.45);
}
.cp-quick__btn--call {
  background: var(--rose-500);
  color: #fff;
  box-shadow: var(--shadow-rose);
}
.cp-quick__btn--call:hover {
  background: var(--rose-600);
  transform: translateY(-2px);
  box-shadow: 0 10px 32px rgba(var(--rose-500-rgb), 0.40);
}
.cp-quick__btn--email {
  background: #fff;
  color: var(--gray-800);
  border: 1.5px solid var(--cream-300);
  box-shadow: var(--shadow-sm);
}
.cp-quick__btn--email:hover {
  border-color: var(--rose-300);
  background: var(--rose-50);
  color: var(--rose-600);
  transform: translateY(-2px);
}

/* ── Body ── */
.cp-body {
  padding: var(--space-16) 0;
  background: var(--color-bg);
}
.cp-body__grid {
  display: grid;
  grid-template-columns: 3fr 2fr;
  gap: var(--space-12);
  align-items: start;
}

.cp-section-title {
  font-family: var(--font-display);
  font-size: 1.375rem;
  font-weight: 500;
  color: var(--gray-800);
  margin-bottom: var(--space-6);
}

/* ── Formulaire ── */
.cp-form-wrap {
  background: #fff;
  border-radius: var(--radius-xl);
  padding: var(--space-8);
  box-shadow: 0 2px 16px rgba(0,0,0,0.05);
  border: 1px solid var(--cream-200);
}

.cp-form {
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

.cp-form__row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-5);
}

.cp-field {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}
.cp-field label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--gray-700);
}
.req { color: var(--rose-500); }

.cp-textarea { resize: vertical; min-height: 120px; }

.cp-char {
  font-size: 0.75rem;
  color: var(--gray-400);
  text-align: right;
  margin-top: -8px;
}

.cp-error { font-size: 0.8125rem; color: #ef4444; }

.cp-global-error {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #dc2626;
  padding: 12px 16px;
  border-radius: var(--radius-md);
  font-size: 0.875rem;
}

.cp-submit {
  width: 100%;
  justify-content: center;
  padding: 14px;
  font-size: 1rem;
}
.cp-submit:disabled { opacity: 0.65; cursor: not-allowed; }

.cp-loading {
  display: flex;
  align-items: center;
  gap: 8px;
}
.spin {
  animation: spin 0.9s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Succès ── */
.cp-success {
  text-align: center;
  padding: var(--space-12) var(--space-8);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-4);
}
.cp-success__icon { font-size: 3rem; }
.cp-success h3 { font-size: 1.5rem; font-weight: 600; color: var(--gray-800); }
.cp-success p  { color: var(--gray-500); }

/* ── Info cards ── */
.cp-info-card {
  display: flex;
  align-items: center;
  gap: var(--space-4);
  padding: var(--space-5);
  background: #fff;
  border-radius: var(--radius-lg);
  border: 1.5px solid var(--cream-200);
  transition: all var(--transition-fast);
  text-decoration: none;
  color: inherit;
}
a.cp-info-card { cursor: pointer; }
a.cp-info-card:hover {
  box-shadow: 0 6px 20px rgba(0,0,0,0.08);
  transform: translateY(-2px);
}
.cp-info-card--whatsapp { border-color: #bbf7d0; }
.cp-info-card--whatsapp:hover { border-color: #16a34a; box-shadow: 0 6px 20px rgba(37,211,102,0.15); }
.cp-info-card--call { border-color: var(--rose-100); }
.cp-info-card--call:hover { border-color: var(--rose-400); box-shadow: 0 6px 20px rgba(var(--rose-500-rgb),0.12); }
.cp-info-card--email { border-color: var(--gold-200); }
.cp-info-card--email:hover { border-color: var(--gold-500); box-shadow: 0 6px 20px rgba(var(--gold-500-rgb),0.15); }

/* Icônes colorées */
.cp-info-card__icon {
  width: 44px;
  height: 44px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.375rem;
  flex-shrink: 0;
  background: var(--cream-100);
}
.cp-info-card__icon--wa   { background: #dcfce7; color: #16a34a; }
.cp-info-card__icon--call { background: var(--rose-100); color: var(--rose-600); }
.cp-info-card__icon--email { background: var(--gold-100); color: var(--gold-600); }

.cp-info-card__body { flex: 1; min-width: 0; }
.cp-info-card__label {
  font-size: 0.6875rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--gray-400);
  margin-bottom: 2px;
}
.cp-info-card__val {
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--gray-800);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.cp-info-card__sub {
  font-size: 0.75rem;
  color: var(--gray-400);
  margin-top: 1px;
}
.cp-info-card__arrow { margin-left: auto; color: var(--gray-300); flex-shrink: 0; }

.cp-info__cards {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  margin-bottom: var(--space-8);
}

/* ── FAQ ── */
.cp-faq__title {
  font-size: 1rem;
  font-weight: 600;
  color: var(--gray-800);
  margin-bottom: var(--space-4);
}
.cp-faq__item {
  border-bottom: 1px solid var(--cream-200);
}
.cp-faq__q {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-4) 0;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--gray-700);
  text-align: left;
  gap: var(--space-3);
  transition: color var(--transition-fast);
}
.cp-faq__q:hover { color: var(--color-primary); }
.cp-faq__item--open .cp-faq__q { color: var(--color-primary); }
.cp-faq__item--open .cp-faq__q svg { transform: rotate(180deg); }
.cp-faq__q svg { transition: transform var(--transition-fast); flex-shrink: 0; }
.cp-faq__a {
  font-size: 0.875rem;
  color: var(--gray-500);
  line-height: 1.65;
  padding-bottom: var(--space-4);
}

/* FAQ transition */
.faq-open-enter-active,
.faq-open-leave-active { transition: all 0.2s ease; overflow: hidden; }
.faq-open-enter-from,
.faq-open-leave-to { opacity: 0; max-height: 0; }
.faq-open-enter-to,
.faq-open-leave-from { opacity: 1; max-height: 200px; }

/* Fade */
.fade-enter-active, .fade-leave-active { transition: opacity 0.25s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* ── Responsive ── */
@media (max-width: 1024px) {
  .cp-hero__inner  { grid-template-columns: 1fr; }
  .cp-hero__img-wrap { height: 300px; }
  .cp-body__grid   { grid-template-columns: 1fr; }
  .cp-form__row    { grid-template-columns: 1fr; }
  .cp-coords       { flex-direction: column; gap: var(--space-2); }
  .cp-coord__sep   { display: none; }
}
@media (max-width: 640px) {
  .cp-form-wrap   { padding: var(--space-5); }
  .cp-hero__img-wrap { height: 220px; }
  .cp-quick       { flex-direction: column; }
  .cp-quick__btn  { justify-content: center; }
}
</style>
