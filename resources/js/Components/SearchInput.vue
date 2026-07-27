<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import Icon from '@/Components/Icon.vue'

/**
 * Глобальное поле поиска.
 *
 * Режимы:
 *  - живая фильтрация на клиенте (DataTable): :debounce="250" @search="..."
 *  - серверный поиск по Enter (Inertia router.get): @submit="applyFilters"
 *
 * <SearchInput
 *   v-model="search"
 *   :placeholder="t('users.searchPlaceholder')"
 *   :loading="form.processing"
 *   shortcut
 *   @submit="applyFilters"
 * />
 */

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    /** sm | md | lg */
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    /** мс; 0 — событие search не эмитится */
    debounce: { type: Number, default: 0 },
    /** показать подсказку ⌘K / Ctrl+K и вешать глобальный хоткей */
    shortcut: { type: Boolean, default: false },
    autofocus: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'submit', 'clear', 'search'])

const { t } = useI18n()

const input = ref(null)
const focused = ref(false)
let timer = null

const sizes = {
    sm: { box: 'h-8  gap-2   px-2.5 rounded-lg',     text: 'text-[12px]', icon: 14, close: 12 },
    md: { box: 'h-10 gap-2.5 px-3   rounded-[10px]', text: 'text-[13px]', icon: 16, close: 14 },
    lg: { box: 'h-12 gap-3   px-3.5 rounded-xl',     text: 'text-[15px]', icon: 18, close: 16 },
}
const s = computed(() => sizes[props.size])

const isMac = computed(
    () => typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform),
)
const shortcutLabel = computed(() => (isMac.value ? '⌘K' : 'Ctrl K'))
const showShortcut = computed(
    () => props.shortcut && !props.modelValue && !focused.value && !props.disabled,
)

function onInput(e) {
    emit('update:modelValue', e.target.value)
}

watch(
    () => props.modelValue,
    (value) => {
        if (!props.debounce) return
        clearTimeout(timer)
        timer = setTimeout(() => emit('search', value), props.debounce)
    },
)

function onEnter() {
    clearTimeout(timer)
    emit('submit', props.modelValue)
}

function onEscape() {
    if (props.modelValue) clear()
    else input.value?.blur()
}

function clear() {
    clearTimeout(timer)
    emit('update:modelValue', '')
    emit('search', '')
    emit('submit', '')
    emit('clear')
    input.value?.focus()
}

function focus() {
    input.value?.focus()
    input.value?.select()
}

function onHotkey(e) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault()
        focus()
    }
}

onMounted(() => {
    if (props.shortcut) window.addEventListener('keydown', onHotkey)
    if (props.autofocus) input.value?.focus()
})

onBeforeUnmount(() => {
    clearTimeout(timer)
    if (props.shortcut) window.removeEventListener('keydown', onHotkey)
})

defineExpose({ focus, clear })
</script>

<template>
  <div
    :class="[
      'group relative flex items-center bg-[var(--field-bg)]',
      s.box,
      disabled
        ? 'cursor-not-allowed opacity-55'
        : 'focus-within:ring-2 focus-within:ring-inset focus-within:ring-[var(--accent)]',
    ]"
    @click="focus"
  >
    <!-- иконка / индикатор загрузки -->
    <span
      class="relative flex flex-shrink-0 items-center justify-center"
      :style="{ width: s.icon + 'px', height: s.icon + 'px' }"
    >
      <Icon
        v-if="!loading"
        kind="search"
        :size="s.icon"
        :class="focused ? 'text-[var(--accent)]' : 'text-[var(--text-muted)]'"
      />
      <svg
        v-else
        class="animate-spin text-[var(--accent)]"
        :width="s.icon"
        :height="s.icon"
        viewBox="0 0 24 24"
        fill="none"
        aria-hidden="true"
      >
        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" opacity="0.2" />
        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
      </svg>
    </span>

    <input
      ref="input"
      :value="modelValue"
      :placeholder="placeholder || t('common.search')"
      :disabled="disabled"
      type="text"
      role="searchbox"
      inputmode="search"
      enterkeyhint="search"
      autocomplete="off"
      autocapitalize="off"
      spellcheck="false"
      :aria-label="placeholder || t('common.search')"
      :aria-busy="loading"
      :class="[
        'min-w-0 flex-1 border-0 bg-transparent text-[var(--text)] outline-none ring-0 focus:ring-0',
        'placeholder-[var(--text-muted)] placeholder:font-normal',
        'disabled:cursor-not-allowed',
        s.text,
      ]"
      @input="onInput"
      @focus="focused = true"
      @blur="focused = false"
      @keydown.enter.prevent="onEnter"
      @keydown.esc.prevent="onEscape"
    />

    <!-- подсказка хоткея -->
    <kbd
      v-if="showShortcut"
      class="pointer-events-none hidden flex-shrink-0 select-none rounded-md bg-[var(--nav-hover)] px-1.5 py-0.5 font-sans text-[11px] font-medium leading-none text-[var(--text-muted)] sm:block"
    >
      {{ shortcutLabel }}
    </kbd>

    <!-- очистка -->
    <button
      v-if="modelValue && !disabled"
      type="button"
      :aria-label="t('common.clear')"
      class="flex flex-shrink-0 items-center justify-center rounded-full p-1 text-[var(--text-muted)] hover:bg-[var(--nav-hover)] hover:text-[var(--text)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--accent)]"
      @click.stop="clear"
    >
      <Icon kind="close" :size="s.close" />
    </button>
  </div>
</template>
