<template>
  <div class="form-field" :class="{ 'form-field--error': !!error }">
    <label v-if="def" class="label">
      {{ def.labelKey ? $t(def.labelKey) : def.label }}
      <span v-if="def.required" class="form-field__req">*</span>
      <span v-if="optional"     class="form-field__opt">{{ $t('common.optional') }}</span>
    </label>
    <slot />
    <p v-if="error" class="form-field__error">{{ error }}</p>
  </div>
</template>

<script setup>
/**
 * FormField — wrapper générique label + input (slot) + erreur.
 *
 * Usage :
 *   import FormField from '@/shared/components/ui/FormField.vue'
 *   import { FIELDS } from './my-feature.fields'
 *
 *   <FormField :def="FIELDS.email" :error="fieldErrors.email">
 *     <input v-model="form.email" class="input" />
 *   </FormField>
 *
 * Props :
 *   def      — entrée de FIELDS { label | labelKey, required, api }
 *   error    — message d'erreur Laravel mappé via mapErrors()
 *   optional — affiche "(optionnel)" après le label
 */
defineProps({
  def:      { type: Object,  default: null },
  error:    { type: String,  default: '' },
  optional: { type: Boolean, default: false },
})
</script>

<style scoped>
.form-field { display: flex; flex-direction: column; gap: 4px; }


.form-field__req {
  color: var(--rose-500);
  margin-left: 2px;
}

.form-field__opt {
  font-size: 0.75rem;
  color: var(--gray-400);
  font-style: italic;
  font-weight: 400;
  margin-left: var(--space-1);
}

.form-field__error {
  font-size: 0.75rem;
  color: #b91c1c;
  margin-top: 2px;
}

/* Bordure rouge sur l'input/select enfant en cas d'erreur */
.form-field--error :deep(.input),
.form-field--error :deep(.app-select__trigger) {
  border-color: #fca5a5;
  background: #fff5f5;
}
</style>
