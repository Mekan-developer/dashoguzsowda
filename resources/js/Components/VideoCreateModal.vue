<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Icon from '@/Components/Icon.vue'
import AppDrawer from '@/Components/AppDrawer.vue'
import DrawerField from '@/Components/DrawerField.vue'
import DrawerFooter from '@/Components/DrawerFooter.vue'
import UserSearchSelect from '@/Components/UserSearchSelect.vue'

const { t, locale } = useI18n()

const props = defineProps({
    open: { type: Boolean, default: false },
    // Категории ролика — только 1-го уровня (CLAUDE.md → «Ролики»)
    categories: { type: Array, default: () => [] },
})
const emit = defineEmits(['close'])

// Как в StoreVideoRequest: max:102400 (КБ)
const MAX_BYTES = 100 * 1024 * 1024
const ACCEPT = 'video/mp4,video/quicktime,video/webm,video/x-matroska,video/3gpp'

const nameOf = (item) => (locale.value === 'tk' && item?.name_tk) ? item.name_tk : item?.name_ru

const form = useForm({
    user_id: null,
    title: '',
    category_id: '',
    tags: [],
    video: null,
})

// ---- Файл ----
const fileInput = ref(null)
const fileError = ref('')
const dragOver  = ref(false)

function pickFile(file) {
    fileError.value = ''
    dragOver.value = false
    if (!file) return
    // У .mkv / .3gp браузер нередко отдаёт пустой type — тогда судим по расширению
    const okType = ACCEPT.split(',').includes(file.type) || /\.(mp4|mov|webm|mkv|3gp)$/i.test(file.name)
    if (!okType)                { fileError.value = t('videos.wrongFormat'); return }
    if (file.size > MAX_BYTES)                  { fileError.value = t('videos.tooBig'); return }
    form.video = file
}

const fileSize = computed(() => form.video ? (form.video.size / 1024 / 1024).toFixed(1) + ' MB' : '')

// ---- Теги ----
const tagsInput = ref('')

// ---- Submit ----
const canSubmit = computed(() =>
    !form.processing && !!form.user_id && form.title.trim() !== '' && !!form.category_id && !!form.video)

function submit() {
    if (!canSubmit.value) return
    form.tags = tagsInput.value.split(/[,，]/).map(s => s.trim()).filter(Boolean).slice(0, 10)
    form.post(route('videos.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => emit('close'),
    })
}

// Каждое открытие — чистая форма
watch(() => props.open, (open) => {
    if (!open) return
    form.reset()
    form.clearErrors()
    tagsInput.value = ''
    fileError.value = ''
})
</script>

<template>
  <AppDrawer :open="open" :title="t('videos.createTitle')" width="520px" @close="$emit('close')">
    <DrawerField :label="t('users.ownerLabel')" required>
      <UserSearchSelect v-model="form.user_id" :error="form.errors.user_id" />
    </DrawerField>

    <DrawerField :label="t('common.title')" :error="form.errors.title" required>
      <input v-model="form.title" type="text" maxlength="255" class="input" />
    </DrawerField>

    <DrawerField :label="t('common.category')" :error="form.errors.category_id" required>
      <select v-model="form.category_id" class="input">
        <option value="">—</option>
        <option v-for="c in categories" :key="c.id" :value="c.id">{{ nameOf(c) }}</option>
      </select>
    </DrawerField>

    <DrawerField :label="t('videos.tags')" :error="form.errors.tags">
      <input v-model="tagsInput" type="text" class="input" :placeholder="t('listings.tagsPlaceholder')" />
    </DrawerField>

    <DrawerField :label="t('videos.fileLabel')" :error="fileError || form.errors.video" required>
      <!-- Файл выбран — карточка файла с заменой и удалением -->
      <div
        v-if="form.video"
        class="flex items-center gap-3 rounded-[8px] border border-[var(--field-border)] bg-[var(--field-bg)] px-3.5 py-3"
      >
        <span class="flex h-10 w-10 flex-none items-center justify-center rounded-[8px] bg-[var(--accent-tint)] text-[var(--accent)]">
          <Icon kind="video" :size="18" />
        </span>
        <div class="min-w-0 flex-1">
          <div class="truncate text-[13px] font-semibold text-[var(--text)]">{{ form.video.name }}</div>
          <div class="text-[11.5px] text-[var(--text-muted)]">{{ fileSize }}</div>
        </div>
        <button
          type="button" :disabled="form.processing"
          class="flex h-8 w-8 flex-none items-center justify-center rounded-[7px] text-[var(--text-muted)] transition hover:bg-[var(--nav-hover)] hover:text-[var(--text)] disabled:opacity-40"
          :aria-label="t('actions.delete')"
          @click="form.video = null"
        ><Icon kind="close" :size="14" /></button>
      </div>

      <!-- Файл не выбран — зона перетаскивания -->
      <button
        v-else
        type="button"
        class="flex w-full flex-col items-center gap-2 rounded-[8px] border-[1.5px] border-dashed px-4 py-8 text-center transition"
        :class="dragOver
          ? 'border-[var(--accent)] bg-[var(--accent-tint)]'
          : 'border-[var(--field-border)] bg-[var(--field-bg)] hover:border-[var(--accent)]'"
        @click="fileInput.click()"
        @dragover.prevent="dragOver = true"
        @dragleave="dragOver = false"
        @drop.prevent="pickFile($event.dataTransfer.files[0])"
      >
        <Icon kind="video" :size="22" class="text-[var(--text-muted)]" />
        <span class="text-[13px] font-semibold text-[var(--text-secondary)]">{{ t('videos.pickFile') }}</span>
        <span class="text-[11.5px] text-[var(--text-muted)]">{{ t('videos.hint') }}</span>
      </button>
      <input ref="fileInput" type="file" :accept="ACCEPT" class="hidden" @change="pickFile($event.target.files[0]); $event.target.value = ''" />
    </DrawerField>

    <template #footer>
      <!-- Видео до 100 МБ — без прогресса кнопка выглядела бы зависшей -->
      <div v-if="form.progress" class="mb-3">
        <div class="mb-1 flex justify-between text-[11.5px] text-[var(--text-muted)]">
          <span>{{ t('videos.uploading') }}</span>
          <span class="tabular-nums">{{ form.progress.percentage }}%</span>
        </div>
        <div class="h-1 overflow-hidden rounded-full bg-[var(--field-bg)]">
          <div class="h-full bg-[var(--accent)] transition-[width] duration-200" :style="{ width: form.progress.percentage + '%' }"></div>
        </div>
      </div>
      <DrawerFooter
        :can-save="canSubmit"
        :save-label="t('actions.create')"
        @cancel="$emit('close')"
        @save="submit"
      />
    </template>
  </AppDrawer>
</template>
