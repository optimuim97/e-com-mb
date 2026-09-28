<template>
  <div class="cf-wrap">

    <!-- Ville -->
    <div class="cf-field">
      <label class="label">Ville *</label>
      <input
        :value="city"
        type="text"
        class="input"
        required
        placeholder="Ex : Paris, Dakar, Montréal…"
        @input="emit('update:city', $event.target.value)"
      />
    </div>

    <!-- État / Région (optionnel selon le pays) -->
    <div class="cf-field">
      <label class="label">
        {{ regionLabel }}
        <span class="cf-opt">(optionnel)</span>
      </label>
      <input
        :value="region"
        type="text"
        class="input"
        :placeholder="regionPlaceholder"
        @input="emit('update:region', $event.target.value)"
      />
    </div>

  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  city:    { type: String, default: '' },
  region:  { type: String, default: '' },
  country: { type: String, default: '' },
})

const emit = defineEmits(['update:city', 'update:region'])

// ── Labels adaptatifs selon le pays ─────────────────────────────────────────
const regionLabel = computed(() => {
  const map = {
    FR: 'Département / Région',
    BE: 'Province',
    CA: 'Province / Territoire',
    US: 'État',
    SN: 'Région',
    ML: 'Région',
    BF: 'Province',
    GN: 'Préfecture',
    TG: 'Région',
    BJ: 'Département',
    GH: 'Région',
  }
  return map[props.country] ?? 'État / Région / Province'
})

const regionPlaceholder = computed(() => {
  const map = {
    FR: 'Ex : Île-de-France, Auvergne…',
    BE: 'Ex : Bruxelles-Capitale…',
    CA: 'Ex : Québec, Ontario…',
    US: 'Ex : California, Texas…',
    SN: 'Ex : Dakar, Thiès…',
    GH: 'Ex : Greater Accra…',
  }
  return map[props.country] ?? 'Ex : votre région'
})

</script>

<style scoped>
.cf-wrap {
  display: flex;
  flex-direction: column;
  gap: var(--space-4, 1rem);
}

/* ── Fields ── */
.cf-field {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.cf-opt {
  font-size: 0.8125rem;
  color: var(--gray-400, #9ca3af);
  font-style: italic;
  font-weight: 400;
}
</style>
