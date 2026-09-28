<template>
  <div v-if="user" class="profile-page">

    <!-- ── Header page ── -->
    <div class="profile-hero">
      <div class="profile-hero__avatar">
        <span>{{ initials }}</span>
      </div>
      <div>
        <h1 class="profile-hero__name">{{ user?.name }}</h1>
        <p class="profile-hero__meta">
          <span v-if="user?.phone">{{ user.phone }}</span>
          <span v-if="!user?.is_generated_email && user?.email">{{ user.email }}</span>
          <span v-else class="profile-hero__quick-badge">{{ $t('profile.quickAccount') }}</span>
        </p>
      </div>
    </div>

    <!-- ── Bannière setup (compte rapide non configuré) ── -->
    <div v-if="user?.is_generated_email" class="profile-setup-banner">
      <div class="profile-setup-banner__icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg></div>
      <div class="profile-setup-banner__body">
        <strong>{{ $t('profile.secureBannerTitle') }}</strong>
        <p>{{ $t('profile.secureBannerDesc') }}</p>
      </div>
      <button class="btn btn-primary btn-sm" @click="scrollToSetup">{{ $t('profile.configure') }} →</button>
    </div>

    <div class="profile-grid">

      <!-- ── Colonne gauche : infos + commandes ── -->
      <div class="profile-col">

        <!-- Informations personnelles -->
        <section class="profile-card">
          <div class="profile-card__header">
            <h2 class="profile-card__title">{{ $t('profile.infoTitle') }}</h2>
            <button v-if="!editingInfo" class="profile-card__edit-btn" @click="editingInfo = true">
              {{ $t('common.edit') }}
            </button>
          </div>

          <form v-if="editingInfo" @submit.prevent="saveInfo" class="profile-form">
            <div class="profile-field">
              <label class="label">{{ $t('auth.fullName') }}</label>
              <input v-model="infoForm.name" type="text" class="input" :placeholder="$t('contact.namePlaceholder')" />
            </div>
            <div class="profile-field">
              <label class="label">{{ $t('auth.phone') }}</label>
              <PhoneInput v-model="infoForm.phone" placeholder="07 00 00 00" />
            </div>
            <div class="profile-form__actions">
              <button type="submit" class="btn btn-primary btn-sm" :disabled="infoSaving">
                <span v-if="infoSaving" class="profile-spin"></span>
                <span v-else>{{ $t('common.save') }}</span>
              </button>
              <button type="button" class="btn btn-outline btn-sm" @click="cancelInfo">{{ $t('common.cancel') }}</button>
            </div>
            <p v-if="infoSuccess" class="profile-msg profile-msg--ok">✓ {{ infoSuccess }}</p>
            <p v-if="infoError"   class="profile-msg profile-msg--err">{{ infoError }}</p>
          </form>

          <dl v-else class="profile-dl">
            <div class="profile-dl__row">
              <dt>{{ $t('fields.lastName') }}</dt>
              <dd>{{ user?.name }}</dd>
            </div>
            <div class="profile-dl__row">
              <dt>{{ $t('auth.phone') }}</dt>
              <dd>{{ user?.phone || '—' }}</dd>
            </div>
            <div class="profile-dl__row">
              <dt>{{ $t('auth.email') }}</dt>
              <dd v-if="!user?.is_generated_email">{{ user?.email }}</dd>
              <dd v-else class="profile-dl__no-email">{{ $t('profile.notProvided') }}</dd>
            </div>
          </dl>
        </section>

        <!-- Mes commandes récentes -->
        <section class="profile-card">
          <div class="profile-card__header">
            <h2 class="profile-card__title">{{ $t('common.myOrders') }}</h2>
            <RouterLink :to="{ name: 'orders' }" class="profile-card__edit-btn">{{ $t('common.seeAll') }} →</RouterLink>
          </div>
          <div v-if="loadingOrders" class="profile-loading">{{ $t('common.loading') }}</div>
          <div v-else-if="recentOrders.length === 0" class="profile-empty">
            {{ $t('profile.noOrder') }}
          </div>
          <ul v-else class="profile-orders">
            <li v-for="o in recentOrders" :key="o.id" class="profile-order">
              <RouterLink :to="{ name: 'order', params: { number: o.number } }" class="profile-order__link">
                <span class="profile-order__num">{{ o.number }}</span>
                <span class="profile-order__status" :class="`profile-order__status--${o.status}`">
                  {{ statusLabel(o.status) }}
                </span>
                <span class="profile-order__total">{{ fmt(o.total) }}</span>
              </RouterLink>
            </li>
          </ul>
          <div v-if="ordersMeta && ordersMeta.last_page > 1" class="profile-orders-pagination">
            <button
              class="profile-pgbtn"
              :disabled="ordersPage <= 1"
              @click="fetchRecentOrders(ordersPage - 1)"
            >‹</button>
            <span class="profile-pginfo">{{ ordersPage }} / {{ ordersMeta.last_page }}</span>
            <button
              class="profile-pgbtn"
              :disabled="ordersPage >= ordersMeta.last_page"
              @click="fetchRecentOrders(ordersPage + 1)"
            >›</button>
          </div>
        </section>

      </div>

      <!-- ── Colonne droite : sécurité ── -->
      <div class="profile-col">

        <!-- Setup compte rapide -->
        <section v-if="user?.is_generated_email" ref="setupSection" class="profile-card profile-card--accent">
          <div class="profile-card__header">
            <h2 class="profile-card__title">{{ $t('profile.setupTitle') }}</h2>
          </div>
          <p class="profile-card__desc">
            {{ $t('profile.setupDesc') }}
          </p>

          <form @submit.prevent="doSetupAccount" class="profile-form">
            <div class="profile-field">
              <label class="label">{{ $t('profile.emailLabel') }} *</label>
              <input
                v-model="setupForm.email"
                type="email"
                class="input"
                :placeholder="$t('auth.emailPlaceholder')"
                autocomplete="email"
                required
              />
            </div>
            <div class="profile-field">
              <label class="label">{{ $t('auth.password') }} *</label>
              <div class="profile-field__pw">
                <input
                  v-model="setupForm.password"
                  :type="showPw1 ? 'text' : 'password'"
                  class="input"
                  :placeholder="$t('profile.pwPlaceholder')"
                  autocomplete="new-password"
                  required
                />
                <button type="button" class="profile-field__pw-toggle" @click="showPw1 = !showPw1">
                  <span class="pw-eye" v-html="eyeIcon(showPw1)"></span>
                </button>
              </div>
              <p class="profile-field__hint">{{ $t('profile.pwHint') }}</p>
            </div>
            <div class="profile-field">
              <label class="label">{{ $t('auth.confirmPassword') }} *</label>
              <div class="profile-field__pw">
                <input
                  v-model="setupForm.password_confirmation"
                  :type="showPw2 ? 'text' : 'password'"
                  class="input"
                  :placeholder="$t('profile.pwRepeatPlaceholder')"
                  autocomplete="new-password"
                  required
                />
                <button type="button" class="profile-field__pw-toggle" @click="showPw2 = !showPw2">
                  <span class="pw-eye" v-html="eyeIcon(showPw2)"></span>
                </button>
              </div>
            </div>

            <div v-if="setupErrors.length" class="profile-errors">
              <p v-for="(e, i) in setupErrors" :key="i">{{ e }}</p>
            </div>
            <p v-if="setupSuccess" class="profile-msg profile-msg--ok">✓ {{ setupSuccess }}</p>

            <button type="submit" class="btn btn-primary btn-lg profile-form__submit" :disabled="setupSaving">
              <span v-if="setupSaving" class="profile-spin"></span>
              <span v-else>{{ $t('profile.secureAccount') }}</span>
            </button>
          </form>
        </section>

        <!-- Changer le mot de passe (compte normal) -->
        <section v-else class="profile-card">
          <div class="profile-card__header">
            <h2 class="profile-card__title">{{ $t('auth.password') }}</h2>
          </div>

          <form @submit.prevent="doChangePassword" class="profile-form">
            <div class="profile-field">
              <label class="label">{{ $t('profile.currentPassword') }} *</label>
              <div class="profile-field__pw">
                <input
                  v-model="pwForm.current_password"
                  :type="showPwCurrent ? 'text' : 'password'"
                  class="input"
                  autocomplete="current-password"
                  :placeholder="$t('profile.currentPwPlaceholder')"
                  required
                />
                <button type="button" class="profile-field__pw-toggle" @click="showPwCurrent = !showPwCurrent">
                  <span class="pw-eye" v-html="eyeIcon(showPwCurrent)"></span>
                </button>
              </div>
            </div>
            <div class="profile-field">
              <label class="label">{{ $t('forgot.newPassword') }} *</label>
              <div class="profile-field__pw">
                <input
                  v-model="pwForm.password"
                  :type="showPw1 ? 'text' : 'password'"
                  class="input"
                  autocomplete="new-password"
                  :placeholder="$t('profile.pwPlaceholder')"
                  required
                />
                <button type="button" class="profile-field__pw-toggle" @click="showPw1 = !showPw1">
                  <span class="pw-eye" v-html="eyeIcon(showPw1)"></span>
                </button>
              </div>
            </div>
            <div class="profile-field">
              <label class="label">{{ $t('profile.confirmNewPassword') }} *</label>
              <div class="profile-field__pw">
                <input
                  v-model="pwForm.password_confirmation"
                  :type="showPw2 ? 'text' : 'password'"
                  class="input"
                  autocomplete="new-password"
                  :placeholder="$t('profile.pwRepeatPlaceholder')"
                  required
                />
                <button type="button" class="profile-field__pw-toggle" @click="showPw2 = !showPw2">
                  <span class="pw-eye" v-html="eyeIcon(showPw2)"></span>
                </button>
              </div>
            </div>

            <div v-if="pwErrors.length" class="profile-errors">
              <p v-for="(e, i) in pwErrors" :key="i">{{ e }}</p>
            </div>
            <p v-if="pwSuccess" class="profile-msg profile-msg--ok">✓ {{ pwSuccess }}</p>

            <button type="submit" class="btn btn-primary btn-sm" :disabled="pwSaving">
              <span v-if="pwSaving" class="profile-spin"></span>
              <span v-else>{{ $t('profile.changePassword') }}</span>
            </button>
          </form>
        </section>

        <!-- Déconnexion -->
        <section class="profile-card profile-card--danger">
          <div class="profile-card__header">
            <h2 class="profile-card__title">{{ $t('profile.session') }}</h2>
          </div>
          <p class="profile-card__desc">{{ $t('profile.logoutDesc') }}</p>
          <button class="btn btn-outline profile-logout-btn" @click="handleLogout" :disabled="logoutLoading">
            <span v-if="logoutLoading" class="profile-spin profile-spin--danger"></span>
            <span v-else>{{ $t('profile.logout') }}</span>
          </button>
        </section>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCurrencyStore } from '@/stores/currency'
import { useRouter, RouterLink } from 'vue-router'
import api from '@/api'
import { useAuthStore } from '@/features/auth/auth.store'
import { storeToRefs } from 'pinia'
import PhoneInput from '@/components/ui/PhoneInput.vue'

const { t }     = useI18n()
const authStore = useAuthStore()
const router    = useRouter()
const { user }  = storeToRefs(authStore)

// ── Initiales avatar ──────────────────────────────────────────────────────────
const initials = computed(() => {
  const name = user.value?.name ?? ''
  return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2) || '?'
})

// ── Infos personnelles ────────────────────────────────────────────────────────
const editingInfo = ref(false)
const infoSaving  = ref(false)
const infoSuccess = ref('')
const infoError   = ref('')
const infoForm    = ref({ name: '', phone: '' })

function startEditInfo() {
  infoForm.value = { name: user.value?.name ?? '', phone: user.value?.phone ?? '' }
  editingInfo.value = true
}
function cancelInfo() {
  editingInfo.value = false
  infoSuccess.value = ''
  infoError.value   = ''
}
async function saveInfo() {
  infoSaving.value  = true
  infoSuccess.value = ''
  infoError.value   = ''
  try {
    await authStore.updateInfo(infoForm.value)
    infoSuccess.value = t('profile.infoUpdated')
    setTimeout(() => { editingInfo.value = false; infoSuccess.value = '' }, 1500)
  } catch (e) {
    infoError.value = e.response?.data?.message ?? t('common.error')
  } finally {
    infoSaving.value = false
  }
}

// ── Setup compte rapide ───────────────────────────────────────────────────────
const setupSection  = ref(null)
const setupSaving   = ref(false)
const setupSuccess  = ref('')
const setupErrors   = ref([])
const showPw1       = ref(false)
const showPw2       = ref(false)
const setupForm     = ref({ email: '', password: '', password_confirmation: '' })

function scrollToSetup() {
  setupSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

async function doSetupAccount() {
  setupSaving.value  = true
  setupErrors.value  = []
  setupSuccess.value = ''
  try {
    await authStore.setupAccount(setupForm.value)
    setupSuccess.value = t('profile.accountSecured')
    setupForm.value = { email: '', password: '', password_confirmation: '' }
  } catch (e) {
    const errs = e.response?.data?.errors
    if (errs) {
      setupErrors.value = Object.values(errs).flat()
    } else {
      setupErrors.value = [e.response?.data?.message ?? t('common.error')]
    }
  } finally {
    setupSaving.value = false
  }
}

// ── Changement de mot de passe (compte normal) ────────────────────────────────
const pwSaving       = ref(false)
const pwSuccess      = ref('')
const pwErrors       = ref([])
const showPwCurrent  = ref(false)
const pwForm         = ref({ current_password: '', password: '', password_confirmation: '' })

async function doChangePassword() {
  pwSaving.value  = true
  pwErrors.value  = []
  pwSuccess.value = ''
  try {
    await authStore.changePassword(pwForm.value)
    pwSuccess.value = t('profile.passwordChanged')
    pwForm.value = { current_password: '', password: '', password_confirmation: '' }
  } catch (e) {
    const errs = e.response?.data?.errors
    if (errs) {
      pwErrors.value = Object.values(errs).flat()
    } else {
      pwErrors.value = [e.response?.data?.message ?? t('common.error')]
    }
  } finally {
    pwSaving.value = false
  }
}

// ── Commandes récentes ────────────────────────────────────────────────────────
const recentOrders  = ref([])
const loadingOrders = ref(true)
const ordersMeta    = ref(null)
const ordersPage    = ref(1)

async function fetchRecentOrders(page = 1) {
  loadingOrders.value = true
  ordersPage.value    = page
  try {
    const { data } = await api.get('/orders', { params: { per_page: 4, page } })
    recentOrders.value = data.data ?? data
    ordersMeta.value   = data.meta ?? null
  } catch { /* ignore */ } finally {
    loadingOrders.value = false
  }
}

onMounted(async () => {
  // Pré-remplir le formulaire info
  infoForm.value = { name: user.value?.name ?? '', phone: user.value?.phone ?? '' }
  await fetchRecentOrders()
})

// ── Déconnexion ───────────────────────────────────────────────────────────────
const logoutLoading = ref(false)

async function handleLogout() {
  logoutLoading.value = true
  try {
    // Naviguer d'abord pour éviter le flash blanc (v-if="user" masque la page dès user=null)
    await router.push({ name: 'home' })
    await authStore.logout()
  } finally {
    logoutLoading.value = false
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const STATUS_KEYS = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded']
function statusLabel(s) { return STATUS_KEYS.includes(s) ? t(`orders.status.${s}`) : s }

function fmt(val) {
  return useCurrencyStore().format(val ?? 0)
}

// Icône œil ouvert / barré pour les champs mot de passe
function eyeIcon(shown) {
  return shown
    ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>'
    : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
}
</script>

<style scoped>
/* ── Page ── */
.profile-page {
  max-width: 900px;
  margin: 0 auto;
  padding: var(--space-8) var(--space-4) var(--space-16);
  display: flex;
  flex-direction: column;
  gap: var(--space-6);
}

/* ── Hero ── */
.profile-hero {
  display: flex;
  align-items: center;
  gap: var(--space-5);
}
.profile-hero__avatar {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--rose-400), var(--rose-600));
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: var(--font-display);
  font-size: 1.5rem;
  font-weight: 600;
  flex-shrink: 0;
  box-shadow: 0 4px 16px rgba(var(--rose-500-rgb), 0.3);
}
.profile-hero__name {
  font-family: var(--font-display);
  font-size: 1.75rem;
  font-weight: 500;
  color: var(--gray-800);
  margin-bottom: var(--space-1);
}
.profile-hero__meta {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
  font-size: 0.875rem;
  color: var(--gray-500);
}
.profile-hero__quick-badge {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 2px 10px;
  background: var(--gold-100);
  color: var(--gold-700);
  border-radius: var(--radius-full);
  border: 1px solid var(--gold-200);
}

/* ── Banner setup ── */
.profile-setup-banner {
  display: flex;
  align-items: center;
  gap: var(--space-4);
  padding: var(--space-4) var(--space-5);
  background: linear-gradient(135deg, #fff7ed, #fef3c7);
  border: 1.5px solid #fde68a;
  border-radius: var(--radius-xl);
}
.profile-setup-banner__icon { font-size: 2rem; flex-shrink: 0; }
.profile-setup-banner__body { flex: 1; }
.profile-setup-banner__body strong {
  display: block;
  font-size: 0.9375rem;
  font-weight: 600;
  color: #92400e;
  margin-bottom: 4px;
}
.profile-setup-banner__body p {
  font-size: 0.8125rem;
  color: #a16207;
  line-height: 1.5;
}

/* ── Grid ── */
.profile-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-5);
}
.profile-col {
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

/* ── Card ── */
.profile-card {
  background: #fff;
  border-radius: var(--radius-xl);
  border: 1px solid var(--cream-200);
  padding: var(--space-5);
  box-shadow: 0 2px 12px rgba(0,0,0,.04);
}
.profile-card--accent {
  border-color: var(--rose-200);
  background: linear-gradient(160deg, #fff9fb, #fff);
}
.profile-card--danger {
  border-color: #fecaca;
  background: #fff;
}

.profile-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-4);
}
.profile-card__title {
  font-family: var(--font-display);
  font-size: 1.0625rem;
  font-weight: 600;
  color: var(--gray-800);
}
.profile-card__desc {
  font-size: 0.8125rem;
  color: var(--gray-500);
  margin-bottom: var(--space-4);
  line-height: 1.6;
}
.profile-card__edit-btn {
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--rose-500);
  text-decoration: none;
  transition: color var(--transition-fast);
}
.profile-card__edit-btn:hover { color: var(--rose-700); }

/* ── DL ── */
.profile-dl {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}
.profile-dl__row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-2) 0;
  border-bottom: 1px solid var(--cream-100);
  font-size: 0.875rem;
}
.profile-dl__row:last-child { border-bottom: none; }
.profile-dl__row dt { color: var(--gray-500); }
.profile-dl__row dd { color: var(--gray-800); font-weight: 500; }
.profile-dl__no-email { color: var(--gray-400); font-style: italic; font-weight: 400; }

/* ── Formulaire ── */
.profile-form {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}
.profile-field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}
.profile-field__hint {
  font-size: 0.6875rem;
  color: var(--gray-400);
  margin-top: 2px;
}
.profile-field__pw {
  position: relative;
}
.profile-field__pw .input {
  padding-right: 40px;
}
.profile-field__pw-toggle {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 1rem;
  background: none;
  border: none;
  cursor: pointer;
  line-height: 1;
  opacity: 0.7;
  transition: opacity var(--transition-fast);
}
.profile-field__pw-toggle:hover { opacity: 1; }

.profile-form__actions {
  display: flex;
  gap: var(--space-2);
}
.profile-form__submit {
  width: 100%;
  justify-content: center;
}

/* ── Messages ── */
.profile-msg {
  font-size: 0.8125rem;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
}
.profile-msg--ok  { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.profile-msg--err { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

.profile-errors {
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: var(--radius-md);
  padding: var(--space-3);
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}
.profile-errors p {
  font-size: 0.8125rem;
  color: #dc2626;
}

/* ── Commandes ── */
.profile-loading,
.profile-empty {
  font-size: 0.875rem;
  color: var(--gray-400);
  padding: var(--space-3) 0;
  text-align: center;
}
.profile-orders {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  list-style: none;
}
.profile-order__link {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  border: 1px solid var(--cream-200);
  text-decoration: none;
  background: #fff;
  transition: all var(--transition-fast);
}
.profile-order__link:hover { border-color: var(--rose-300); background: var(--rose-50); }
.profile-order__num {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--gray-700);
  flex: 1;
}
.profile-order__status {
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 2px 8px;
  border-radius: var(--radius-full);
  background: var(--cream-200);
  color: var(--gray-600);
}
.profile-order__status--delivered   { background: #dcfce7; color: #15803d; }
.profile-order__status--shipped     { background: #dbeafe; color: #1d4ed8; }
.profile-order__status--pending     { background: #fef9c3; color: #a16207; }
.profile-order__status--cancelled   { background: #fee2e2; color: #dc2626; }
.profile-order__status--processing  { background: #f3e8ff; color: #7c3aed; }
.profile-order__total {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--rose-600);
}

/* ── Pagination commandes ── */
.profile-orders-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-3);
  margin-top: var(--space-3);
  padding-top: var(--space-3);
  border-top: 1px solid var(--cream-100);
}
.profile-pgbtn {
  width: 28px; height: 28px;
  border-radius: var(--radius-md);
  border: 1.5px solid var(--cream-300);
  background: #fff;
  font-size: 1rem;
  color: var(--gray-600);
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: all var(--transition-fast);
}
.profile-pgbtn:hover:not(:disabled) { border-color: var(--rose-400); color: var(--rose-500); }
.profile-pgbtn:disabled { opacity: 0.35; cursor: default; }
.profile-pginfo {
  font-size: 0.8125rem;
  color: var(--gray-500);
}

/* ── Déconnexion ── */
.profile-logout-btn {
  color: #dc2626;
  border-color: #fecaca;
  width: 100%;
  justify-content: center;
}
.profile-logout-btn:hover { background: #fef2f2; border-color: #dc2626; }

/* ── Spinner ── */
.profile-spin {
  display: inline-block;
  width: 14px; height: 14px;
  border: 2px solid rgba(255,255,255,.4);
  border-top-color: #fff;
  border-radius: 50%;
  animation: pspin 0.7s linear infinite;
}
.profile-spin--danger {
  border-color: rgba(220, 38, 38, .25);
  border-top-color: #dc2626;
}
@keyframes pspin { to { transform: rotate(360deg); } }

/* ── Responsive ── */
@media (max-width: 700px) {
  .profile-grid { grid-template-columns: 1fr; }
  .profile-hero__name { font-size: 1.375rem; }
}
</style>
