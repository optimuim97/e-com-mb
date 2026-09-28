<template>
  <div class="lang-switcher" ref="wrapper">
    <button
      class="lang-switcher__btn"
      @click="open = !open"
      :aria-expanded="open"
      aria-label="Language"
    >
      <span class="lang-switcher__flag">{{ current.flag }}</span>
      <span class="lang-switcher__code">{{ current.code }}</span>
      <svg class="lang-switcher__chevron" :class="{ 'lang-switcher__chevron--open': open }" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
        <polyline points="6 9 12 15 18 9"/>
      </svg>
    </button>

    <Transition name="dropdown">
      <div v-if="open" class="lang-switcher__menu">
        <button
          v-for="lang in languages"
          :key="lang.value"
          class="lang-switcher__option"
          :class="{ 'lang-switcher__option--active': lang.value === locale }"
          @click="switchLang(lang.value)"
        >
          <span class="lang-switcher__option-flag">{{ lang.flag }}</span>
          <span class="lang-switcher__option-label">{{ lang.label }}</span>
          <svg v-if="lang.value === locale" class="lang-switcher__check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <polyline points="20 6 9 17 4 12"/>
          </svg>
        </button>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import { setLocale } from '@/i18n'

const { locale } = useI18n()
const open = ref(false)
const wrapper = ref(null)

const languages = [
  { value: 'fr', flag: '🇫🇷', label: 'Français', code: 'FR' },
  { value: 'en', flag: '🇬🇧', label: 'English', code: 'EN' },
]

const current = computed(() => languages.find(l => l.value === locale.value) || languages[0])

function switchLang(lang) {
  setLocale(lang)
  open.value = false
}

function onClickOutside(e) {
  if (wrapper.value && !wrapper.value.contains(e.target)) {
    open.value = false
  }
}

onMounted(() => document.addEventListener('click', onClickOutside))
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside))
</script>

<style scoped>
.lang-switcher {
  position: relative;
  z-index: 10;
}

.lang-switcher__btn {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 6px 10px;
  border-radius: var(--radius-full);
  border: 1.5px solid var(--cream-300);
  background: rgba(255, 255, 255, 0.7);
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--gray-600);
  cursor: pointer;
  transition: all var(--transition-fast);
  letter-spacing: 0.04em;
}

.lang-switcher__btn:hover {
  border-color: var(--rose-300);
  background: var(--rose-50);
  color: var(--rose-600);
}

.lang-switcher__flag {
  font-size: 0.9375rem;
  line-height: 1;
}

.lang-switcher__chevron {
  transition: transform var(--transition-fast);
  color: var(--gray-400);
}

.lang-switcher__chevron--open {
  transform: rotate(180deg);
}

.lang-switcher__menu {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  background: #fff;
  border: 1px solid var(--cream-200);
  border-radius: var(--radius-lg);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
  min-width: 160px;
  overflow: hidden;
  z-index: 20;
}

.lang-switcher__option {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  width: 100%;
  padding: 10px 14px;
  font-size: 0.8125rem;
  color: var(--gray-700);
  background: none;
  border: none;
  cursor: pointer;
  transition: background var(--transition-fast);
}

.lang-switcher__option:hover {
  background: var(--rose-50);
}

.lang-switcher__option--active {
  color: var(--rose-600);
  font-weight: 600;
}

.lang-switcher__option-flag {
  font-size: 1.125rem;
  line-height: 1;
}

.lang-switcher__option-label {
  flex: 1;
  text-align: left;
}

.lang-switcher__check {
  color: var(--rose-500);
}

/* Dropdown transition */
.dropdown-enter-active,
.dropdown-leave-active {
  transition: all 0.15s ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
