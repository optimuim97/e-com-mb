<template>
  <AuthShell
    :eyebrow="$t('auth.registerEyebrow')"
    :title="$t('auth.registerTitle')"
    :desc="$t('auth.registerDesc')"
    :footer-text="$t('auth.registerFooter')"
    footer-link="/login"
    :footer-link-text="$t('auth.registerFooterLink')"
  >
    <form @submit.prevent="handleSubmit" class="auth-form">
      <div>
        <label class="label">{{ $t('auth.fullName') }}</label>
        <input v-model="form.name" type="text" class="input" :placeholder="$t('auth.namePlaceholder')" required />
      </div>
      <div>
        <label class="label">{{ $t('auth.email') }}</label>
        <input v-model="form.email" type="email" class="input" :placeholder="$t('auth.emailPlaceholder')" required />
      </div>
      <div>
        <label class="label">{{ $t('auth.phone') }}</label>
        <PhoneInput v-model="form.phone" placeholder="07 00 00 00" />
      </div>
      <div>
        <label class="label">{{ $t('auth.password') }}</label>
        <input v-model="form.password" type="password" class="input" :placeholder="$t('auth.passwordMinPlaceholder')" required />
      </div>
      <div>
        <label class="label">{{ $t('auth.confirmPassword') }}</label>
        <input v-model="form.password_confirmation" type="password" class="input" :placeholder="$t('auth.passwordPlaceholder')" required />
      </div>

      <p v-if="error" class="auth-error">{{ error }}</p>

      <button type="submit" class="btn btn-primary btn-lg auth-submit" :disabled="loading">
        <span v-if="loading" class="spinner spinner--sm"></span>
        <span v-else>{{ $t('auth.registerSubmit') }}</span>
      </button>
    </form>
  </AuthShell>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/features/auth/auth.store';
import AuthShell   from '@/components/auth/AuthShell.vue';
import PhoneInput  from '@/components/ui/PhoneInput.vue';

const { t } = useI18n();
const auth   = useAuthStore();
const router = useRouter();

const form    = ref({ name: '', email: '', phone: '', password: '', password_confirmation: '' });
const error   = ref('');
const loading = ref(false);

async function handleSubmit() {
  error.value   = '';
  loading.value = true;
  try {
    await auth.register(form.value);
    router.push('/');
  } catch (e) {
    if (!e._serverError) {
      const errors = e.response?.data?.errors;
      error.value = errors ? Object.values(errors).flat()[0] : t('common.error');
    }
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.auth-form {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.auth-error {
  background: #fee2e2;
  color: #b91c1c;
  border-radius: var(--radius-md);
  padding: 10px 14px;
  font-size: 0.8125rem;
}

.auth-submit {
  width: 100%;
  justify-content: center;
  gap: var(--space-2);
  margin-top: var(--space-2);
}
</style>
