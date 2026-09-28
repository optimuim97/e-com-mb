<template>
  <div v-if="totalCount > 0" class="pagination">
    <div class="pagination__left">
      <select
        v-if="showPerPage"
        :value="perPage"
        @change="onPerPageChange"
        class="input pagination__perpage"
      >
        <option v-for="opt in perPageOptions" :key="opt" :value="opt">
          {{ opt }} / page
        </option>
      </select>
      <span class="pagination__total">
        {{ totalCount.toLocaleString('fr-FR') }} {{ itemLabel(totalCount) }}
      </span>
    </div>

    <div class="pagination__actions">
      <button
        @click="go(1)"
        :disabled="currentPage <= 1"
        class="btn btn-outline btn-sm"
        title="Première page"
      >«</button>
      <button
        @click="go(currentPage - 1)"
        :disabled="currentPage <= 1"
        class="btn btn-outline btn-sm"
        title="Page précédente"
      >←</button>
      <button
        v-for="(p, i) in pageNumbers"
        :key="i"
        :disabled="p === '…'"
        :class="[
          'btn',
          'btn-sm',
          p === currentPage ? 'btn-primary' : 'btn-outline',
          { 'pagination__ellipsis': p === '…' },
        ]"
        @click="typeof p === 'number' && go(p)"
      >{{ p }}</button>
      <button
        @click="go(currentPage + 1)"
        :disabled="currentPage >= lastPage"
        class="btn btn-outline btn-sm"
        title="Page suivante"
      >→</button>
      <button
        @click="go(lastPage)"
        :disabled="currentPage >= lastPage"
        class="btn btn-outline btn-sm"
        title="Dernière page"
      >»</button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  /** Objet meta type Laravel : { current_page, last_page, total, per_page } */
  meta: { type: Object, default: () => ({}) },
  /** Override : si tu n'as pas l'objet meta complet */
  currentPage: { type: Number, default: null },
  lastPage:    { type: Number, default: null },
  total:       { type: Number, default: null },
  perPage:     { type: Number, default: 20 },
  perPageOptions: { type: Array, default: () => [10, 20, 50, 100] },
  showPerPage: { type: Boolean, default: true },
  /** Libellé singulier/pluriel (par défaut "commande/commandes") */
  itemSingular: { type: String, default: 'élément' },
  itemPlural:   { type: String, default: 'éléments' },
});

const emit = defineEmits(['update:page', 'update:perPage']);

const currentPage = computed(() => props.currentPage ?? props.meta.current_page ?? 1);
const lastPage    = computed(() => props.lastPage    ?? props.meta.last_page    ?? 1);
const totalCount  = computed(() => props.total       ?? props.meta.total        ?? 0);
const perPage     = computed(() => props.perPage     ?? props.meta.per_page     ?? 20);

function itemLabel(n) {
  return n > 1 ? props.itemPlural : props.itemSingular;
}

// Pages affichées : max ~5 boutons numérotés, avec ellipses
const pageNumbers = computed(() => {
  const last = lastPage.value;
  const cur  = currentPage.value;
  if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);

  const pages = new Set([1, last, cur]);
  for (let d = 1; d <= 2; d++) {
    if (cur - d > 1)        pages.add(cur - d);
    if (cur + d < last)     pages.add(cur + d);
  }
  const sorted = Array.from(pages).sort((a, b) => a - b);
  const out = [];
  for (let i = 0; i < sorted.length; i++) {
    out.push(sorted[i]);
    if (i < sorted.length - 1 && sorted[i + 1] - sorted[i] > 1) out.push('…');
  }
  return out;
});

function go(p) {
  if (typeof p !== 'number') return;
  if (p < 1 || p > lastPage.value || p === currentPage.value) return;
  emit('update:page', p);
}

function onPerPageChange(e) {
  emit('update:perPage', Number(e.target.value));
}
</script>
