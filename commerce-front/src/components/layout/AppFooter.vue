<template>
  <footer class="footer">
    <!-- Newsletter -->
    <div v-if="settings.moduleEnabled('newsletter')" class="footer__newsletter">
      <div class="container footer__newsletter-inner">
        <div class="footer__nl-content">
          <span class="eyebrow">{{ $t('footer.newsletter') }}</span>
          <h2 class="display-md footer__nl-title">
            {{ $t('footer.nlTitle') }}
          </h2>
          <p class="footer__nl-desc">
            {{ $t('footer.nlDesc') }}
          </p>
        </div>
        <form class="footer__nl-form" @submit.prevent="subscribeNewsletter">
          <div class="footer__nl-input-wrap">
            <input
              v-model="email"
              type="email"
              :placeholder="$t('footer.nlPlaceholder')"
              required
              class="footer__nl-input"
              :aria-label="$t('auth.email')"
            />
            <button type="submit" class="btn btn-primary footer__nl-btn" :disabled="subscribed">
              <Transition name="swap" mode="out-in">
                <span v-if="!subscribed" key="sub">{{ $t('footer.nlJoin') }}</span>
                <span v-else key="done">{{ $t('footer.nlDone') }}</span>
              </Transition>
            </button>
          </div>
          <p class="footer__nl-privacy">
            {{ $t('footer.nlPrivacy') }}
          </p>
        </form>
      </div>
    </div>

    <!-- Corps du footer -->
    <div class="footer__body">
      <div class="container footer__grid">
        <!-- Logo & Description -->
        <div class="footer__brand">
          <RouterLink to="/" class="footer__logo">
            <BrandLogo img-class="footer__logo-img" size="lg" width="220" height="220" />
          </RouterLink>
          <p class="footer__brand-desc">
            {{ settings.shopTagline || $t('about.defaultTagline') }}
          </p>
          <!-- Réseaux sociaux -->
          <div class="footer__social">
            <a
              v-for="social in settings.activeSocials"
              :key="social.name"
              :href="social.url"
              target="_blank"
              rel="noopener noreferrer"
              class="footer__social-btn"
              :aria-label="social.name"
            >
              <span v-html="social.icon"></span>
            </a>
          </div>
          <!-- WhatsApp -->
          <a
            v-if="settings.whatsappNumber"
            :href="`https://wa.me/${settings.whatsappNumber}`"
            target="_blank"
            rel="noopener"
            class="footer__whatsapp"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
              <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
            {{ settings.shopPhone || settings.whatsappNumber }}
          </a>
        </div>

        <!-- Navigation -->
        <div v-for="col in navColumns" :key="col.title" class="footer__col">
          <h3 class="footer__col-title">{{ col.title }}</h3>
          <ul class="footer__col-list">
            <li v-for="link in col.links" :key="link.label">
              <RouterLink :to="link.to" class="footer__col-link">
                {{ link.label }}
              </RouterLink>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Bas du footer ── -->
    <div class="footer__bottom">
      <div class="container footer__bottom-inner">
        <p class="footer__copy">
          {{ $t('footer.copyright', { year: currentYear, shopName: settings.shopName }) }}
        </p>
        <div class="footer__bottom-links">
          <RouterLink to="/confidentialite" class="footer__bottom-link">{{ $t('footer.privacy') }}</RouterLink>
          <RouterLink to="/cgv" class="footer__bottom-link">{{ $t('footer.terms') }}</RouterLink>
          <RouterLink to="/cookies" class="footer__bottom-link">{{ $t('footer.cookies') }}</RouterLink>
          <RouterLink to="/mentions-legales" class="footer__bottom-link">{{ $t('footer.legal') }}</RouterLink>
        </div>
        <!-- Modes de paiement -->
        <div class="footer__payments">
          <span class="footer__payment" v-for="p in settings.activePayments" :key="p" :title="p">{{ p }}</span>
        </div>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { ref, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useSettingsStore } from '@/stores/settings'
import BrandLogo from '@/components/ui/BrandLogo.vue'
import api from '@/api'

const { t } = useI18n()
const email      = ref('')
const subscribed = ref(false)
const settings   = useSettingsStore()

async function subscribeNewsletter() {
  if (!email.value) return
  try {
    await api.post('/newsletter/subscribe', { email: email.value, source: 'footer' })
    subscribed.value = true
    email.value = ''
  } catch {
    // Affiche quand même le retour positif (anti-énumération, UX)
    subscribed.value = true
    email.value = ''
  }
}

const currentYear = new Date().getFullYear()

const navColumns = computed(() => [
  {
    title: t('footer.navTitle'),
    links: [
      { label: t('footer.navHome'),     to: '/' },
      { label: t('footer.navProducts'), to: '/produits' },
      ...(settings.moduleEnabled('program') ? [{ label: t('footer.navCare'), to: '/programme' }] : []),
      ...(settings.moduleEnabled('blog') ? [{ label: t('footer.navBlog'), to: '/blog' }] : []),
      { label: t('footer.navAbout'),    to: '/a-propos' },
      { label: t('footer.navContact'),  to: '/contact' },
    ],
  },
  {
    title: t('footer.infoTitle'),
    links: [
      { label: t('footer.infoDelivery'),  to: '/livraison' },
      { label: t('footer.infoFaq'),       to: '/faq' },
      { label: t('footer.infoAccount'),   to: '/compte' },
      { label: t('footer.infoWishlist'),  to: '/favoris' },
      { label: t('footer.infoTracking'),  to: '/suivi' },
    ],
  },
])

</script>

<style scoped>
.footer {
  background: var(--gray-800);
  color: rgba(255,255,255,0.85);
  font-size: 0.875rem;
}

/* ── Newsletter ── */
.footer__newsletter {
  background: linear-gradient(135deg, var(--rose-600) 0%, var(--rose-800) 100%);
  padding: var(--space-12) 0;
}

.footer__newsletter-inner {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-12);
  align-items: center;
}

.footer__nl-content {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.footer__nl-content .eyebrow {
  color: rgba(255,255,255,0.65);
}

.footer__nl-title {
  color: #fff;
  font-size: clamp(1.5rem, 3vw, 2rem);
}
.footer__nl-title em {
  font-style: italic;
  color: var(--gold-300);
}

.footer__nl-desc {
  color: rgba(255,255,255,0.72);
  line-height: 1.65;
}

.footer__nl-form {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.footer__nl-input-wrap {
  display: flex;
  gap: var(--space-2);
  background: rgba(255,255,255,0.12);
  border: 1px solid rgba(255,255,255,0.2);
  border-radius: var(--radius-full);
  padding: 5px 5px 5px var(--space-5);
}

.footer__nl-input {
  flex: 1;
  background: none;
  border: none;
  color: #fff;
  font-size: 0.9375rem;
  font-family: var(--font-body);
  outline: none;
}
.footer__nl-input::placeholder { color: rgba(255,255,255,0.45); }

.footer__nl-btn {
  background: #fff;
  color: var(--rose-700);
  border-color: #fff;
  padding: 12px 24px;
  font-weight: 600;
}
.footer__nl-btn:hover {
  background: var(--cream-100);
}
.footer__nl-btn:disabled {
  background: var(--rose-300);
  color: #fff;
  border-color: var(--rose-300);
  cursor: default;
  transform: none;
}

.footer__nl-privacy {
  font-size: 0.75rem;
  color: rgba(255,255,255,0.5);
}

/* ── Corps ── */
.footer__body { padding: var(--space-16) 0; }

.footer__grid {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr;
  gap: var(--space-12);
}

/* ── Brand ── */
.footer__logo {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  margin-bottom: var(--space-5);
}

.footer__logo-img {
  height: 130px;
  width: auto;
  object-fit: contain;
  display: block;
  /* Filter to soften image on dark/cream footer if needed */
}

.footer__logo-name {
  font-family: var(--font-display);
  font-size: 1.25rem;
  font-weight: 500;
  color: #fff;
}

.footer__logo-sub {
  font-size: 0.625rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--rose-300);
  font-weight: 500;
}

.footer__brand-desc {
  color: rgba(255,255,255,0.55);
  line-height: 1.75;
  margin-bottom: var(--space-6);
}

.footer__social {
  display: flex;
  gap: var(--space-2);
  margin-bottom: var(--space-4);
}

.footer__social-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: rgba(255,255,255,0.08);
  color: rgba(255,255,255,0.7);
  border: 1px solid rgba(255,255,255,0.12);
  transition: all var(--transition-fast);
}

.footer__social-btn:hover {
  background: var(--rose-500);
  border-color: var(--rose-500);
  color: #fff;
  transform: translateY(-2px);
}

.footer__whatsapp {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  color: #25d366;
  font-weight: 500;
  font-size: 0.9375rem;
  transition: opacity var(--transition-fast);
}
.footer__whatsapp:hover { opacity: 0.8; }

/* ── Colonnes nav ── */
.footer__col {}

.footer__col-title {
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.15em;
  text-transform: uppercase;
  color: rgba(255,255,255,0.4);
  margin-bottom: var(--space-5);
}

.footer__col-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.footer__col-link {
  color: rgba(255,255,255,0.62);
  transition: color var(--transition-fast);
  font-size: 0.875rem;
}

.footer__col-link:hover { color: var(--rose-300); }

/* ── Bottom ── */
.footer__bottom {
  border-top: 1px solid rgba(255,255,255,0.08);
  padding: var(--space-5) 0;
}

.footer__bottom-inner {
  display: flex;
  align-items: center;
  gap: var(--space-6);
  flex-wrap: wrap;
}

.footer__copy {
  color: rgba(255,255,255,0.35);
  font-size: 0.8125rem;
}

.footer__bottom-links {
  display: flex;
  gap: var(--space-4);
  margin-left: auto;
  flex-wrap: wrap;
}

.footer__bottom-link {
  color: rgba(255,255,255,0.4);
  font-size: 0.8125rem;
  transition: color var(--transition-fast);
}
.footer__bottom-link:hover { color: rgba(255,255,255,0.7); }

.footer__payments {
  display: flex;
  gap: var(--space-2);
  flex-wrap: wrap;
}

.footer__payment {
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: var(--radius-sm);
  padding: 3px 10px;
  font-size: 0.6875rem;
  font-weight: 500;
  color: rgba(255,255,255,0.55);
  letter-spacing: 0.04em;
}

/* ── Transition ── */
.swap-enter-active, .swap-leave-active { transition: all 0.2s; }
.swap-enter-from { opacity: 0; transform: translateY(-6px); }
.swap-leave-to   { opacity: 0; transform: translateY(6px); }

/* ── Responsive ── */
@media (max-width: 1024px) {
  .footer__newsletter-inner {
    grid-template-columns: 1fr;
    gap: var(--space-8);
  }
  .footer__grid {
    grid-template-columns: 1fr 1fr;
  }
  .footer__brand {
    grid-column: 1 / -1;
  }
}

@media (max-width: 640px) {
  .footer__grid {
    grid-template-columns: 1fr;
  }
  .footer__bottom-inner {
    flex-direction: column;
    align-items: flex-start;
    gap: var(--space-3);
  }
  .footer__bottom-links { margin-left: 0; }
}
</style>
