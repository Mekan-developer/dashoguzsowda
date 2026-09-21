<script setup>
import { ref, watch, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import Icon from '@/Components/Icon.vue'

const props = defineProps({
    modelValue: { type: [Number, String, null], default: null },
    error: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()

const query = ref('')
const open = ref(false)
const loading = ref(false)
const results = ref([])
const selected = ref(null)
let timer = null
let abortCtrl = null

watch(() => props.modelValue, (id) => {
    if (!id) {
        selected.value = null
        query.value = ''
    }
})

function labelOf(user) {
    if (!user) return ''
    return user.name ? `${user.name} · ${user.phone}` : user.phone
}

function onInput() {
    selected.value = null
    emit('update:modelValue', null)
    clearTimeout(timer)
    timer = setTimeout(fetchUsers, 250)
}

async function fetchUsers() {
    const q = query.value.trim()
    if (q.length < 2) {
        results.value = []
        open.value = false
        return
    }

    abortCtrl?.abort()
    abortCtrl = new AbortController()
    loading.value = true
    open.value = true

    try {
        const res = await fetch(`${route('users.search')}?q=${encodeURIComponent(q)}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: abortCtrl.signal,
        })
        const json = await res.json()
        results.value = json.data || []
    } catch (e) {
        if (e.name !== 'AbortError') results.value = []
    } finally {
        loading.value = false
    }
}

function pick(user) {
    selected.value = user
    query.value = labelOf(user)
    emit('update:modelValue', user.id)
    open.value = false
    results.value = []
}

function clear() {
    selected.value = null
    query.value = ''
    emit('update:modelValue', null)
    results.value = []
    open.value = false
}

function onBlur() {
    setTimeout(() => { open.value = false }, 150)
}

onBeforeUnmount(() => {
    clearTimeout(timer)
    abortCtrl?.abort()
})
</script>

<template>
  <div class="relative">
    <div class="relative">
      <input
        v-model="query"
        type="text"
        :disabled="disabled"
        :placeholder="t('users.selectOwnerPlaceholder')"
        class="input pr-9"
        autocomplete="off"
        @input="onInput"
        @focus="results.length && (open = true)"
        @blur="onBlur"
      />
      <button
        v-if="modelValue || query"
        type="button"
        class="absolute inset-y-0 right-2 flex items-center text-muted hover:text-ink dark:hover:text-slate-200"
        :disabled="disabled"
        @mousedown.prevent="clear"
      >
        <Icon kind="close" :size="14" />
      </button>
    </div>

    <div
      v-if="open"
      class="absolute z-40 mt-1 max-h-56 w-full overflow-y-auto rounded-[10px] border border-[var(--field-border)] bg-[var(--card-bg)] shadow-[var(--card-shadow)]"
    >
      <div v-if="loading" class="px-3 py-2.5 text-[12px] text-muted">{{ t('common.loading') }}</div>
      <button
        v-for="user in results"
        :key="user.id"
        type="button"
        class="flex w-full flex-col gap-0.5 px-3 py-2 text-left transition hover:bg-[var(--nav-hover)]"
        @mousedown.prevent="pick(user)"
      >
        <span class="text-[13px] font-semibold text-[var(--text)]">{{ user.name || t('users.noName') }}</span>
        <span class="text-[11px] text-muted">{{ user.phone }}</span>
      </button>
      <div
        v-if="!loading && !results.length"
        class="px-3 py-2.5 text-[12px] text-muted"
      >{{ t('users.notFound') }}</div>
    </div>

    <p v-if="error" class="mt-1 text-[11px] font-semibold text-red">{{ error }}</p>
  </div>
</template>
