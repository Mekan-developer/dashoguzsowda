<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import Icon from '@/Components/Icon.vue'

/**
 * Выбор значка категории: библиотека готовых SVG (10 стандартных + все
 * ранее загруженные админом) или загрузка нового SVG, если подходящего
 * нет — новый файл сразу сохраняется в общую библиотеку на сервере.
 */
const props = defineProps({
    library:     { type: Array, default: () => [] },  // [{ path, url, is_system }]
    iconPath:    { type: String, default: null },      // выбран существующий из библиотеки
    iconFile:    { type: File, default: null },        // выбран новый файл на загрузку
    existingUrl: { type: String, default: null },      // текущий значок категории (режим редактирования)
})
const emit = defineEmits(['update:iconPath', 'update:iconFile'])

const { t } = useI18n()

const fileInput = ref(null)
const error = ref('')
const newFilePreview = ref(null)

watch(() => props.iconFile, file => {
    if (newFilePreview.value) URL.revokeObjectURL(newFilePreview.value)
    newFilePreview.value = file ? URL.createObjectURL(file) : null
})
onBeforeUnmount(() => { if (newFilePreview.value) URL.revokeObjectURL(newFilePreview.value) })

const currentPreview = computed(() => {
    if (newFilePreview.value) return newFilePreview.value
    if (props.iconPath) return props.library.find(i => i.path === props.iconPath)?.url ?? props.existingUrl
    return props.existingUrl
})

function pick(icon) {
    error.value = ''
    emit('update:iconFile', null)
    emit('update:iconPath', icon.path)
}

function openUpload() {
    fileInput.value?.click()
}

function onFileSelected(e) {
    const file = e.target.files?.[0]
    e.target.value = ''
    if (!file) return
    error.value = ''
    if (!file.name.toLowerCase().endsWith('.svg')) {
        error.value = t('iconPicker.svgOnly')
        return
    }
    if (file.size > 1024 * 1024) {
        error.value = t('iconPicker.tooBig')
        return
    }
    emit('update:iconPath', null)
    emit('update:iconFile', file)
}

function clear() {
    error.value = ''
    emit('update:iconPath', null)
    emit('update:iconFile', null)
}
</script>

<template>
  <div>
    <input ref="fileInput" type="file" accept=".svg,image/svg+xml" class="hidden" @change="onFileSelected" />

    <div class="flex items-center gap-3">
      <div class="flex h-16 w-16 flex-shrink-0 items-center justify-center rounded-[8px] border border-[var(--field-border)] bg-[var(--field-bg)] overflow-hidden">
        <img v-if="currentPreview" :src="currentPreview" class="h-10 w-10 object-contain" alt="" />
        <Icon v-else kind="image" :size="20" class="text-[var(--text-muted)]" />
      </div>
      <div class="flex-1 text-[12px] text-[var(--text-muted)]">
        {{ t('iconPicker.hint') }}
      </div>
      <button
        v-if="currentPreview"
        type="button" @click="clear"
        class="rounded-[8px] px-3 py-[7px] text-[12px] font-semibold text-red transition-colors hover:bg-red/10 flex-shrink-0"
      >{{ t('actions.delete') }}</button>
    </div>

    <div class="mt-3 grid grid-cols-6 gap-2">
      <button
        v-for="icon in library" :key="icon.path" type="button"
        @click="pick(icon)"
        :title="icon.slug"
        class="flex h-11 w-11 items-center justify-center rounded-[8px] border-2 transition-colors"
        :class="iconPath === icon.path
          ? 'border-[var(--accent)] bg-[var(--accent-tint)]'
          : 'border-[var(--field-border)] bg-[var(--field-bg)] hover:border-[var(--accent)]'"
      >
        <img :src="icon.url" class="h-6 w-6 object-contain" alt="" />
      </button>

      <button
        type="button" @click="openUpload"
        :title="t('iconPicker.uploadNew')"
        class="flex h-11 w-11 items-center justify-center rounded-[8px] border-2 border-dashed transition-colors"
        :class="iconFile
          ? 'border-[var(--accent)] bg-[var(--accent-tint)]'
          : 'border-[var(--field-border)] text-[var(--text-muted)] hover:border-[var(--accent)] hover:text-[var(--accent)]'"
      >
        <span class="text-lg font-bold leading-none">+</span>
      </button>
    </div>

    <p v-if="error" class="mt-1.5 text-[12px] font-semibold text-red">{{ error }}</p>
  </div>
</template>
