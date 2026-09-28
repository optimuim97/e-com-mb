<template>
  <AuthShell
    :eyebrow="$t('auth.loginEyebrow')"
    :title="$t('auth.loginTitle')"
    :desc="$t('auth.loginDesc')"
    :footer-text="$t('auth.loginFooter')"
    footer-link="/register"
    :footer-link-text="$t('auth.loginFooterLink')"
  >
    <form @submit.prevent="handleSubmit" class="auth-form">
      <div>
        <label class="label">{{ $t('auth.identifier') }}</label>
        <!--
          `type="text"` et non `email` : le champ accepte aussi un identifiant
          court (« souattara »), pour les comptes sans adresse. En `email`, le
          navigateur refusait la saisie avant même l'envoi.
          `autocapitalize` : un clavier de téléphone met sinon une majuscule au
          premier caractère, et la saisie échoue sans que rien ne l'explique.
        -->
        <input
          v-model="form.email"
          type="text"
          inputmode="email"
          autocomplete="username"
          autocapitalize="none"
          autocorrect="off"
          spellcheck="false"
          class="input"
          :placeholder="$t('auth.identifierPlaceholder')"
          required
        />
      </div>
      <div>
        <div class="auth-row">
          <label class="label">{{ $t('auth.password') }}</label>
          <button type="button" class="auth-forgot-link" @click="showForgot = true">
            {{ $t('auth.forgotPassword') }}
          </button>
        </div>
        <input v-model="form.password" type="password" class="input" :placeholder="$t('auth.passwordPlaceholder')" required />
      </div>

      <p v-if="error" class="auth-error">{{ error }}</p>

      <button type="submit" class="btn btn-primary btn-lg auth-submit" :disabled="loading">
        <span v-if="loading" class="spinner spinner--sm"></span>
        <span v-else>{{ $t('auth.loginSubmit') }}</span>
      </button>

      <!-- Séparateur + connexion sociale -->
      <!-- Facebook masqué tant que la configuration côté Meta n'est pas
           finalisée. Le composant et la route restent en place : il suffit
           de remettre <FacebookButton> pour le réactiver. -->
      <div class="auth-divider"><span>{{ $t('common.or') }}</span></div>
      <GoogleButton @success="onSocialSuccess" @error="error = $event" />
    </form>

    <ForgotSecretModal v-if="showForgot" mode="password" @close="showForgot = false" />
  </AuthShell>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/features/auth/auth.store';
import AuthShell from '@/components/auth/AuthShell.vue';
import GoogleButton from '@/features/auth/GoogleButton.vue';
import ForgotSecretModal from '@/features/auth/ForgotSecretModal.vue';

const { t } = useI18n();
const auth   = useAuthStore();
const router = useRouter();
const route  = useRoute();

const form    = ref({ email: '', password: '' });
const error   = ref('');
const loading = ref(false);
const showForgot = ref(false);

async function handleSubmit() {
  error.value   = '';
  loading.value = true;
  try {
    const user = await auth.login(form.value.email, form.value.password);
    const redirect = route.query.redirect || auth.landingPath(user);
    router.push(redirect);
  } catch (e) {
    if (!e._serverError) {
      error.value = e.response?.data?.message ?? t('auth.loginError');
    }
  } finally {
    loading.value = false;
  }
}

// Auth sociale (Facebook / Google) : la session est déjà établie par le
// composable useSocialAuth via auth.setSession(). On redirige comme après login.
function onSocialSuccess() {
  const user = auth.user;
  const redirect = route.query.redirect || auth.landingPath(user);
  router.push(redirect);
}
</script>

<style scoped>
.auth-form {
  display: flex;
  flex-direction: column;
  gap: clamp(8px, 1.8vh, 16px);
}

.auth-error {
  background: #fee2e2;
  color: #b91c1c;
  border-radius: var(--radius-md);
  padding: 8px 12px;
  font-size: 0.8125rem;
}

.auth-submit {
  width: 100%;
  justify-content: center;
  gap: var(--space-2);
  padding-top: clamp(9px, 1.5vh, 13px);
  padding-bottom: clamp(9px, 1.5vh, 13px);
}

.auth-divider {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  color: var(--gray-400);
  font-size: 0.75rem;
  margin: 0;
}
.auth-divider::before,
.auth-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--cream-300, #e5e7eb);
}

.auth-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}
.auth-forgot-link {
  background: none;
  border: none;
  color: var(--rose-600);
  font-size: 0.75rem;
  font-weight: 500;
  cursor: pointer;
  padding: 0;
}
.auth-forgot-link:hover { text-decoration: underline; }
</style>
