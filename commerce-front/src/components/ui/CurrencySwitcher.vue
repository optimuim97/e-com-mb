<template>
  <div class="cur-switcher" ref="wrapper">
    <button class="cur-switcher__btn" @click="open = !open" :aria-expanded="open" aria-label="Devise">
      <span class="cur-switcher__code">{{ active }}</span>
      <svg class="cur-switcher__chevron" :class="{ open }" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
        <polyline points="6 9 12 15 18 9"/>
      </svg>
    </button>

    <Transition name="dropdown">
      <div v-if="open" class="cur-switcher__menu">
        <button
          v-for="c in currency.supported"
          :key="c.code"
          class="cur-switcher__option"
          :class="{ 'cur-switcher__option--active': c.code === active }"
          @click="select(c.code)"
        >
          <span class="cur-switcher__flag">{{ c.flag }}</span>
          <span class="cur-switcher__label">{{ c.label }} <small>({{ c.symbol }})</small></span>
          <svg v-if="c.code === active" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <polyline points="20 6 9 17 4 12"/>
          </svg>
        </button>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useCurrencyStore } from '@/stores/currency'

const currency = useCurrencyStore()
const active = computed(() => currency.active)
const open = ref(false)
const wrapper = ref(null)

function select(code) {
  currency.setCurrency(code)
  open.value = false
}

function onClickOutside(e) {
  if (wrapper.value && !wrapper.value.contains(e.target)) open.value = false
}
onMounted(() => document.addEventListener('click', onClickOutside))
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside))
</script>

<style scoped>
.cur-switcher { position: relative; z-index: 10; }
.cur-switcher__btn {
  display: flex; align-items: center; gap: 4px;
  padding: 6px 10px; border-radius: var(--radius-full);
  border: 1.5px solid var(--cream-300); background: rgba(255,255,255,0.7);
  font-size: 0.75rem; font-weight: 600; color: var(--gray-600); cursor: pointer;
  transition: all var(--transition-fast); letter-spacing: 0.04em;
}
.cur-switcher__btn:hover { border-color: var(--rose-300); background: var(--rose-50); color: var(--rose-600); }
.cur-switcher__chevron { transition: transform var(--transition-fast); color: var(--gray-400); }
.cur-switcher__chevron.open { transform: rotate(180deg); }

.cur-switcher__menu {
  position: absolute; top: calc(100% + 6px); right: 0;
  background: #fff; border: 1px solid var(--cream-200); border-radius: var(--radius-lg);
  box-shadow: 0 8px 24px rgba(0,0,0,0.1); min-width: 180px; overflow: hidden; z-index: 20;
}
.cur-switcher__option {
  display: flex; align-items: center; gap: var(--space-3); width: 100%;
  padding: 10px 14px; font-size: 0.8125rem; color: var(--gray-700);
  background: none; border: none; cursor: pointer; transition: background var(--transition-fast);
}
.cur-switcher__option:hover { background: var(--rose-50); }
.cur-switcher__option--active { color: var(--rose-600); font-weight: 600; }
.cur-switcher__flag { font-size: 1.125rem; line-height: 1; }
.cur-switcher__label { flex: 1; text-align: left; }
.cur-switcher__label small { color: var(--gray-400); }

.dropdown-enter-active, .dropdown-leave-active { transition: all 0.15s ease; }
.dropdown-enter-from, .dropdown-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
