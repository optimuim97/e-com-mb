<template>
  <aside class="products-filters" :class="{ 'products-filters--open': open }">
    <div class="products-filters__header">
      <h2 class="products-filters__title">{{ $t('filters.title') }}</h2>
      <button class="products-filters__close hide-desktop" @click="$emit('close')">×</button>
    </div>

    <!-- Catégories -->
    <div class="filter-group">
      <h3 class="filter-group__title">{{ $t('filters.category') }}</h3>
      <ul class="filter-group__list">
        <li>
          <label class="filter-checkbox">
            <input type="radio" v-model="filters.category" :value="null" class="filter-checkbox__input" />
            <span class="filter-checkbox__box"></span>
            <span class="filter-checkbox__label">{{ $t('filters.all') }}</span>
          </label>
        </li>
        <li v-for="cat in categories" :key="cat.id">
          <label class="filter-checkbox">
            <input type="radio" v-model="filters.category" :value="cat.slug" class="filter-checkbox__input" />
            <span class="filter-checkbox__box"></span>
            <span class="filter-checkbox__label">{{ cat.name }}</span>
          </label>
        </li>
      </ul>
    </div>

    <!-- Prix -->
    <div class="filter-group">
      <h3 class="filter-group__title">{{ $t('filters.price') }}</h3>
      <div class="filter-price-inputs">
        <input v-model.number="filters.min_price" type="number" :placeholder="$t('filters.min')" class="input filter-price-input" />
        <span class="filter-price-sep">–</span>
        <input v-model.number="filters.max_price" type="number" :placeholder="$t('filters.max')" class="input filter-price-input" />
      </div>
    </div>

    <!-- Reset -->
    <button v-if="hasActiveFilters" class="filter-reset" @click="$emit('reset')">
      {{ $t('filters.clearAll') }}
    </button>
  </aside>
</template>

<script setup>
defineProps({
  categories:       { type: Array,   required: true },
  filters:          { type: Object,  required: true },
  hasActiveFilters: { type: Boolean, default: false },
  open:             { type: Boolean, default: false },
})

defineEmits(['reset', 'close'])
</script>

<style scoped>
.products-filters {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  border: 1px solid var(--color-border-light);
  padding: var(--space-5);
  position: sticky;
  top: calc(var(--navbar-height) + var(--space-6));
}

.products-filters__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-5);
  padding-bottom: var(--space-4);
  border-bottom: 1px solid var(--cream-200);
}

.products-filters__title {
  font-family: var(--font-display);
  font-size: 1.125rem;
  font-weight: 500;
  color: var(--gray-800);
}

.products-filters__close {
  font-size: 1.5rem;
  color: var(--gray-400);
  padding: 4px;
}

/* ── Groupes de filtres ── */
.filter-group {
  margin-bottom: var(--space-6);
  padding-bottom: var(--space-6);
  border-bottom: 1px solid var(--cream-200);
}
.filter-group:last-of-type { border-bottom: none; }

.filter-group__title {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--gray-600);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-bottom: var(--space-4);
}

.filter-group__list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.filter-checkbox {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  cursor: pointer;
}

.filter-checkbox__input { display: none; }

.filter-checkbox__box {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
  border: 1.5px solid var(--cream-300);
  border-radius: 50%;
  background: var(--cream-50);
  transition: all var(--transition-fast);
  display: flex;
  align-items: center;
  justify-content: center;
}
.filter-checkbox:hover .filter-checkbox__box {
  border-color: var(--rose-300);
  background: var(--rose-50);
}

.filter-checkbox__input:checked + .filter-checkbox__box {
  background: #fff;
  border-color: var(--rose-500);
  border-width: 2px;
  box-shadow: inset 0 0 0 4px var(--rose-500), 0 0 0 3px rgba(var(--rose-500-rgb), 0.12);
}

.filter-checkbox__label {
  font-size: 0.875rem;
  color: var(--gray-600);
  flex: 1;
}

/* ── Prix ── */
.filter-price-inputs {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.filter-price-input {
  flex: 1;
  width: 0;
  padding: 8px 14px;
  font-size: 0.8125rem;
}
.filter-price-sep { color: var(--gray-300); }

.filter-reset {
  width: 100%;
  padding: 10px;
  font-size: 0.8125rem;
  color: var(--rose-500);
  border: 1.5px dashed var(--rose-200);
  border-radius: var(--radius-md);
  background: none;
  margin-top: var(--space-2);
  transition: all var(--transition-fast);
}
.filter-reset:hover { background: var(--rose-50); }

/* ── Responsive (drawer mobile) ── */
@media (max-width: 1024px) {
  .products-filters {
    position: fixed;
    top: 0;
    left: -100%;
    bottom: 0;
    z-index: var(--z-modal);
    width: 280px;
    border-radius: 0;
    overflow-y: auto;
    transition: left var(--transition-normal);
    box-shadow: var(--shadow-lg);
  }
  .products-filters--open { left: 0; }
}
</style>
