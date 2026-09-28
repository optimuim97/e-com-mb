<template>
  <div class="app-select" :class="{ 'app-select--open': open, 'app-select--disabled': disabled }" ref="root">

    <!-- Trigger -->
    <button
      ref="trigger"
      type="button"
      class="app-select__trigger input"
      :class="{ 'app-select__trigger--placeholder': modelValue === '' || modelValue === null || modelValue === undefined }"
      @click="toggle"
      @keydown="onKeydown"
      :disabled="disabled"
      :aria-expanded="open"
      aria-haspopup="listbox"
    >
      <span class="app-select__value">{{ selectedLabel }}</span>
      <svg class="app-select__arrow" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
        <polyline points="6 9 12 15 18 9"/>
      </svg>
    </button>

    <!-- Dropdown téléporté au body pour éviter le clipping des parents overflow:hidden -->
    <Teleport to="body">
      <Transition name="select-drop">
        <ul
          v-if="open"
          ref="listEl"
          class="app-select__list"
          role="listbox"
          :aria-activedescendant="modelValue != null ? `opt-${modelValue}` : undefined"
          :style="listStyle"
        >
          <li
            v-for="opt in normalizedOptions"
            :key="opt.value"
            :id="`opt-${opt.value}`"
            role="option"
            :aria-selected="opt.value === modelValue"
            :aria-disabled="opt.disabled"
            class="app-select__option"
            :class="{
              'app-select__option--selected':  opt.value === modelValue,
              'app-select__option--focused':   opt.value === focusedValue,
              'app-select__option--disabled':  opt.disabled,
              'app-select__option--separator': opt.separator,
            }"
            @click="!opt.disabled && select(opt.value)"
            @mouseenter="!opt.disabled && (focusedValue = opt.value)"
          >
            <span v-if="opt.value === modelValue" class="app-select__check">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <polyline points="20 6 9 17 4 12"/>
              </svg>
            </span>
            <span class="app-select__opt-label">{{ opt.label }}</span>
          </li>
        </ul>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'

const props = defineProps({
  modelValue: { default: '' },
  options: {
    type: Array,
    default: () => [],
  },
  placeholder: { type: String, default: 'Sélectionner…' },
  disabled:    { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const root         = ref(null)
const trigger      = ref(null)
const listEl       = ref(null)
const open         = ref(false)
const focusedValue = ref(null)

/* Position fixed calculée depuis le trigger */
const listStyle = ref({})

function computePosition() {
  if (!trigger.value) return
  const rect = trigger.value.getBoundingClientRect()
  const spaceBelow = window.innerHeight - rect.bottom
  const spaceAbove = rect.top
  const dropH = 260 // max-height

  // Ouvrir vers le bas si assez d'espace, sinon vers le haut
  if (spaceBelow >= Math.min(dropH, 160) || spaceBelow >= spaceAbove) {
    listStyle.value = {
      position: 'fixed',
      top:   `${rect.bottom + 6}px`,
      left:  `${rect.left}px`,
      width: `${rect.width}px`,
      zIndex: 9999,
    }
  } else {
    listStyle.value = {
      position: 'fixed',
      bottom:   `${window.innerHeight - rect.top + 6}px`,
      left:     `${rect.left}px`,
      width:    `${rect.width}px`,
      zIndex:   9999,
    }
  }
}

/* Normaliser les options */
const normalizedOptions = computed(() => {
  return props.options.map(o => {
    if (typeof o === 'string' || typeof o === 'number') {
      return { value: o, label: String(o), disabled: false }
    }
    return {
      value:    o.value ?? o.val ?? o.id ?? o,
      label:    o.label ?? o.name ?? o.text ?? String(o.value),
      disabled: !!o.disabled,
    }
  })
})

const selectedLabel = computed(() => {
  if (props.modelValue === '' || props.modelValue === null || props.modelValue === undefined) {
    return props.placeholder
  }
  return normalizedOptions.value.find(o => o.value === props.modelValue)?.label ?? props.placeholder
})

async function toggle() {
  if (props.disabled) return
  open.value = !open.value
  if (open.value) {
    focusedValue.value = props.modelValue
    await nextTick()
    computePosition()
  }
}

function select(val) {
  emit('update:modelValue', val)
  open.value = false
}

function close() {
  open.value = false
}

/* Navigation clavier */
function onKeydown(e) {
  const opts = normalizedOptions.value.filter(o => !o.disabled)
  const idx  = opts.findIndex(o => o.value === (focusedValue.value ?? props.modelValue))

  if (e.key === 'Escape')     { close(); return }
  if (e.key === 'Enter' || e.key === ' ') {
    e.preventDefault()
    if (!open.value) { toggle(); return }
    if (focusedValue.value !== null) select(focusedValue.value)
    return
  }
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    if (!open.value) { toggle(); return }
    focusedValue.value = opts[Math.min(idx + 1, opts.length - 1)]?.value ?? opts[0]?.value
  }
  if (e.key === 'ArrowUp') {
    e.preventDefault()
    focusedValue.value = opts[Math.max(idx - 1, 0)]?.value ?? opts[0]?.value
  }
  if (e.key === 'Tab') close()
}

/* Clic hors du composant → fermer */
function onOutside(e) {
  if (!open.value) return
  const clickedRoot = root.value?.contains(e.target)
  const clickedList = listEl.value?.contains(e.target)
  if (!clickedRoot && !clickedList) close()
}

/* Recalcule la position si scroll ou resize */
function onScroll() { if (open.value) computePosition() }
function onResize() { if (open.value) computePosition() }

onMounted(() => {
  document.addEventListener('click', onOutside, true)
  window.addEventListener('scroll', onScroll, true)
  window.addEventListener('resize', onResize)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onOutside, true)
  window.removeEventListener('scroll', onScroll, true)
  window.removeEventListener('resize', onResize)
})

watch(open, v => { if (!v) focusedValue.value = null })
</script>

<style>
/* Styles NON scoped car la liste est téléportée hors du composant */

.app-select {
  position: relative;
  width: 100%;
}

/* Trigger */
.app-select__trigger {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  text-align: left;
  cursor: pointer;
  user-select: none;
  padding-right: 14px;
  width: 100%;
}
.app-select__trigger--placeholder .app-select__value {
  color: var(--gray-400);
  font-style: italic;
}
.app-select__value {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.app-select__arrow {
  flex-shrink: 0;
  color: var(--rose-400);
  transition: transform var(--transition-fast);
}
.app-select--open .app-select__arrow {
  transform: rotate(180deg);
}

/* Dropdown (rendu dans le body via Teleport) */
.app-select__list {
  background: #fff;
  border: 1.5px solid var(--rose-200);
  border-radius: var(--radius-md);
  box-shadow: 0 8px 30px rgba(0,0,0,0.12), 0 2px 8px rgba(0,0,0,0.06);
  padding: var(--space-2) 0;
  max-height: 260px;
  overflow-y: auto;
  list-style: none;
  margin: 0;
  /* Scrollbar rose */
  scrollbar-width: thin;
  scrollbar-color: var(--rose-200) transparent;
}
.app-select__list::-webkit-scrollbar { width: 4px; }
.app-select__list::-webkit-scrollbar-thumb { background: var(--rose-200); border-radius: 2px; }

/* Option */
.app-select__option {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: 9px var(--space-4);
  font-size: 0.875rem;
  color: var(--gray-700);
  cursor: pointer;
  transition: background var(--transition-fast), color var(--transition-fast);
  position: relative;
  list-style: none;
}

.app-select__option--focused,
.app-select__option:hover {
  background: var(--rose-50);
  color: var(--rose-700);
}

.app-select__option--selected {
  color: var(--rose-600);
  font-weight: 500;
}
.app-select__option--selected:not(:hover):not(.app-select__option--focused) {
  background: var(--rose-50);
}

.app-select__option--disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.app-select__option--disabled:hover { background: transparent; color: var(--gray-700); }

.app-select__check {
  width: 16px;
  display: flex;
  align-items: center;
  flex-shrink: 0;
  color: var(--rose-500);
}
.app-select__option:not(.app-select__option--selected) {
  padding-left: calc(var(--space-4) + 20px);
}
.app-select__option--selected { padding-left: var(--space-4); }

.app-select__opt-label { flex: 1; }

/* Transition */
.select-drop-enter-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.select-drop-leave-active { transition: opacity 0.1s ease, transform 0.1s ease; }
.select-drop-enter-from  { opacity: 0; transform: translateY(-6px) scaleY(0.97); }
.select-drop-leave-to    { opacity: 0; transform: translateY(-4px) scaleY(0.98); }
</style>
