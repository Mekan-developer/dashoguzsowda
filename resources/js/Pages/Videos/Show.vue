<script setup>
import { ref, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { confirmDialog } from '@/confirm'
import Icon from '@/Components/Icon.vue'

const { t, locale } = useI18n()
const page = usePage()

const props = defineProps({ video: Object, categories: Array, rejectionReasons: Array })

const isAdmin = computed(() => page.props.auth?.user?.role === 'admin')

// Справочники двуязычные — показываем имя активного языка
const nameOf = (item) => (locale.value === 'tk' && item?.name_tk) ? item.name_tk : item?.name_ru

function formatDuration(seconds) {
    const s = Number(seconds) || 0
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`
}

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('ru', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })
}

const tariffLine = computed(() => {
    const usage = props.video.tariff_usage
    if (!usage?.name_ru) return null
    return {
        text: `${t('videos.tariffLabel', { name: nameOf(usage) })} · ${usage.used}/${usage.limit ?? '∞'}`,
        exhausted: usage.limit !== null && usage.used >= usage.limit,
    }
})

// ── Правка заголовка и категории ─────────────────────────────────────────────
const editing    = ref(false)
const title      = ref(props.video.title)
const categoryId = ref(props.video.category_id ?? props.video.category?.id ?? '')
const tagsInput  = ref((props.video.tags || []).join(', '))

function saveEdit() {
    if (!title.value.trim() || !categoryId.value) return
    const tags = tagsInput.value
        .split(/[,，]/)
        .map(s => s.trim())
        .filter(Boolean)
        .slice(0, 10)
    router.put(route('videos.update', props.video.id), {
        title: title.value,
        category_id: categoryId.value,
        tags,
    }, {
        preserveScroll: true,
        onSuccess: () => { editing.value = false },
    })
}

function cancelEdit() {
    title.value = props.video.title
    categoryId.value = props.video.category_id ?? props.video.category?.id ?? ''
    tagsInput.value = (props.video.tags || []).join(', ')
    editing.value = false
}

// ── Модерация ───────────────────────────────────────────────────────────────
const showRejectModal = ref(false)
const rejectReason    = ref('')

function approve() {
    router.patch(route('videos.approve', props.video.id), {}, { preserveScroll: true })
}

function doReject() {
    if (!rejectReason.value) return
    router.patch(route('videos.reject', props.video.id), { rejection_reason_id: rejectReason.value }, {
        preserveScroll: true,
        onSuccess: () => { showRejectModal.value = false },
    })
}

// ── Удаление (только admin) — контроллер уводит обратно в список ─────────────
async function doDelete() {
    if (!(await confirmDialog(t('videos.deleteConfirm')))) return
    router.delete(route('videos.destroy', props.video.id))
}
</script>

<template>
  <AppLayout>
    <template #breadcrumb>
      <Link :href="route('videos.index')" class="transition-colors duration-150 hover:text-link">{{ t('nav.videos') }}</Link>
      <span>/</span>
      <span>#{{ video.id }}</span>
    </template>
    <template #header>
      <span class="flex min-w-0 items-center gap-3">
        <span class="max-w-[640px] truncate">{{ video.title }}</span>
        <StatusBadge :status="video.status" />
      </span>
    </template>

    <div class="grid gap-5 lg:grid-cols-[1fr_360px]">
      <!-- Плеер + информация — в одной карточке: вертикальное видео слева, данные справа -->
      <div class="space-y-5">
        <div class="card p-5 grid gap-5 sm:grid-cols-[200px_minmax(0,1fr)]">
          <div>
            <!-- Вертикальный формат 9:16 — как в мобильной ленте -->
            <div class="mx-auto aspect-[9/16] w-full max-w-[200px] overflow-hidden rounded-[8px] bg-navy">
              <video
                v-if="video.video_url"
                :src="video.video_url"
                :poster="video.preview_url || undefined"
                controls
                playsinline
                class="h-full w-full object-contain"
              ></video>
              <div v-else class="flex h-full w-full items-center justify-center text-white/40">
                <Icon kind="play" :size="32" />
              </div>
            </div>

            <p
              v-if="!video.is_processed"
              class="mt-3 flex items-center justify-center gap-1.5 text-[12px] font-semibold text-amber-700 dark:text-amber-300"
            >
              <Icon kind="clock" :size="12" />{{ t('videos.notProcessed') }}
            </p>
          </div>

          <div>
            <h2 class="card-title mb-4">{{ t('videos.info') }}</h2>

            <!-- Заголовок и категория — правит модератор (UpdateVideoRequest) -->
            <div class="mb-4">
              <p class="field-label mb-1">{{ t('common.title') }}:</p>
              <div v-if="editing" class="space-y-3">
                <input
                  v-model="title"
                  type="text"
                  maxlength="255"
                  class="input"
                  @keyup.enter="saveEdit"
                  @keyup.esc="cancelEdit"
                />
                <div>
                  <p class="field-label mb-1">{{ t('common.category') }}:</p>
                  <select
                    v-model="categoryId"
                    class="input"
                  >
                    <option value="">{{ t('common.category') }}</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ nameOf(c) }}</option>
                  </select>
                </div>
                <div>
                  <p class="field-label mb-1">{{ t('videos.tags') }}:</p>
                  <input
                    v-model="tagsInput"
                    type="text"
                    class="input"
                    :placeholder="t('listings.tagsPlaceholder')"
                  />
                </div>
                <div class="flex gap-2">
                  <button
                    @click="saveEdit"
                    :disabled="!title.trim() || !categoryId"
                    class="btn btn-primary"
                  >{{ t('actions.save') }}</button>
                  <button
                    @click="cancelEdit"
                    class="btn btn-secondary"
                  >{{ t('actions.cancel') }}</button>
                </div>
              </div>
              <div v-else class="space-y-3">
                <div class="flex items-start gap-2">
                  <span class="flex-1 text-[15px] font-semibold text-[var(--text)]">{{ video.title }}</span>
                  <button
                    @click="editing = true; tagsInput = (video.tags || []).join(', ')"
                    class="icon-btn"
                    :title="t('actions.edit')" :aria-label="t('actions.edit')"
                  >
                    <Icon kind="pencil" :size="16" />
                  </button>
                </div>
                <div>
                  <p class="field-label mb-1">{{ t('common.category') }}:</p>
                  <span class="text-[13px] font-semibold text-[var(--text)]">
                    {{ nameOf(video.category) }}
                  </span>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4 text-[13px]">
              <div>
                <span class="text-[var(--text-muted)]">{{ t('videos.duration') }}:</span><br>
                <span class="font-data font-semibold text-[var(--text)]">{{ formatDuration(video.duration_seconds) }}</span>
              </div>
              <div>
                <span class="text-[var(--text-muted)]">{{ t('common.date') }}:</span><br>
                <span class="font-data font-semibold text-[var(--text)]">{{ formatDate(video.created_at) }}</span>
              </div>
              <div>
                <span class="text-[var(--text-muted)]">{{ t('videos.likes') }}:</span><br>
                <span class="inline-flex items-center gap-1.5 font-data font-semibold text-[var(--text)]">
                  <Icon kind="heart" :size="14" class="text-[var(--text-muted)]" />{{ video.likes_count ?? 0 }}
                </span>
              </div>
              <div>
                <span class="text-[var(--text-muted)]">{{ t('common.views') }}:</span><br>
                <span class="inline-flex items-center gap-1.5 font-data font-semibold text-[var(--text)]">
                  <Icon kind="eye" :size="14" />{{ video.views ?? 0 }}
                </span>
              </div>
            </div>

            <div v-if="video.tags?.length" class="mt-4 pt-4 border-t border-line dark:border-dline">
              <p class="field-label mb-2">{{ t('videos.tags') }}:</p>
              <div class="flex flex-wrap gap-1.5">
                <span v-for="tag in video.tags" :key="tag" class="inline-flex h-6 items-center rounded-full bg-[var(--accent-tint)] px-2.5 text-[12px] font-medium text-link">#{{ tag }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Сайдбар -->
      <div class="space-y-4">
        <!-- Автор -->
        <div class="card p-5">
          <h3 class="card-title mb-3">{{ t('common.author') }}</h3>
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-[var(--accent-tint)] text-[14px] font-semibold text-link">
              {{ (video.user?.name || video.user?.phone || '?').charAt(0).toUpperCase() }}
            </div>
            <div>
              <div class="text-[13px] font-semibold text-[var(--text)]">{{ video.user?.name || '—' }}</div>
              <div class="text-[12px] font-data text-muted">{{ video.user?.phone }}</div>
            </div>
          </div>
          <div
            v-if="tariffLine"
            class="mt-3 text-[12px]"
            :class="tariffLine.exhausted ? 'font-semibold text-amber-700 dark:text-amber-300' : 'text-muted'"
          >
            {{ tariffLine.text }}
            <template v-if="tariffLine.exhausted"> {{ t('videos.limitSuffix') }}</template>
          </div>
          <div v-if="video.user_id" class="mt-3">
            <Link :href="route('users.show', video.user_id)" class="text-[13px] font-medium text-link hover:underline">{{ t('videos.profileLink') }}</Link>
          </div>
        </div>

        <!-- Модерация -->
        <div class="card p-5">
          <h3 class="card-title mb-3">{{ t('videos.moderation') }}</h3>
                    <div v-if="video.rejection_reason" class="mb-3 rounded-[8px] bg-red-500/10 px-3.5 py-3 text-[13px] text-red-700 dark:text-red-300">
            {{ nameOf(video.rejection_reason) }}
          </div>
          <div class="space-y-2">
            <button
              v-if="video.status !== 'approved'"
              @click="approve"
              class="btn btn-green-soft w-full"
            >{{ t('videos.approveBtn') }}</button>
            <button
              v-if="video.status !== 'rejected'"
              @click="showRejectModal = true"
              class="btn btn-red-soft w-full"
            >{{ t('videos.rejectBtn') }}</button>
            <button
              v-if="isAdmin"
              @click="doDelete"
              class="btn btn-secondary w-full hover:!text-red"
            >{{ t('videos.deleteBtn') }}</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Отклонение: выбор причины из справочника -->
    <div v-if="showRejectModal" class="fixed inset-0 z-[600] flex items-center justify-center bg-[#0A0C1A]/50 p-4" @click.self="showRejectModal = false">
      <div class="card w-full max-w-[440px] p-6 shadow-lg2">
        <h3 class="mb-4 text-[16px] font-semibold text-[var(--text)]">{{ t('videos.rejectTitle') }}</h3>
        <div class="space-y-1 mb-5">
          <label v-for="r in rejectionReasons" :key="r.id" class="flex cursor-pointer items-center gap-3 rounded-btn p-3 transition hover:bg-surface dark:hover:bg-white/5">
            <input type="radio" :value="r.id" v-model="rejectReason" class="accent-blue" />
            <span class="text-[13px] font-semibold text-ink dark:text-slate-200">{{ nameOf(r) }}</span>
          </label>
        </div>
        <div class="flex justify-end gap-2">
          <button @click="showRejectModal = false" class="btn btn-secondary">{{ t('actions.cancel') }}</button>
          <button @click="doReject" :disabled="!rejectReason" class="btn btn-danger">{{ t('actions.reject') }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
