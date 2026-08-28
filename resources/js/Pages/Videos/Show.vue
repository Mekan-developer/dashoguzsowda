<script setup>
import { ref, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import Icon from '@/Components/Icon.vue'

const { t, locale } = useI18n()
const page = usePage()

const props = defineProps({ video: Object, rejectionReasons: Array })

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

// ── Правка заголовка (единственное поле, которое модератору можно менять) ────
const editing = ref(false)
const title   = ref(props.video.title)

function saveTitle() {
    if (!title.value.trim()) return
    router.put(route('videos.update', props.video.id), { title: title.value }, {
        preserveScroll: true,
        onSuccess: () => { editing.value = false },
    })
}

function cancelEdit() {
    title.value = props.video.title
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
const confirmDelete = ref(false)

function doDelete() {
    router.delete(route('videos.destroy', props.video.id))
}
</script>

<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center gap-2 text-[14px]">
        <Link :href="route('videos.index')" class="text-muted hover:text-blue transition">{{ t('nav.videos') }}</Link>
        <span class="text-muted">/</span>
        <span class="text-ink dark:text-slate-100 max-w-[300px] truncate">{{ video.title }}</span>
      </div>
    </template>

    <div class="grid gap-5 lg:grid-cols-[1fr_360px]">
      <!-- Плеер + информация -->
      <div class="space-y-5">
        <div class="rounded-card bg-white shadow-soft dark:bg-dcard p-5">
          <h3 class="text-[15px] font-extrabold text-ink dark:text-slate-100 mb-4">{{ t('videos.player') }}</h3>

          <!-- Вертикальный формат 9:16 — как в мобильной ленте -->
          <div class="mx-auto w-full max-w-[320px] overflow-hidden rounded-[12px] bg-navy aspect-[9/16]">
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
            class="mt-3 flex items-center justify-center gap-1.5 text-[12px] font-bold text-orange"
          >
            <Icon kind="clock" :size="12" />{{ t('videos.notProcessed') }}
          </p>
        </div>

        <div class="rounded-card bg-white shadow-soft dark:bg-dcard p-5">
          <h3 class="text-[15px] font-extrabold text-ink dark:text-slate-100 mb-4">{{ t('videos.info') }}</h3>

          <!-- Заголовок — единственное, что правит модератор (UpdateVideoRequest) -->
          <div class="mb-4">
            <p class="text-[12px] font-semibold text-muted mb-1">{{ t('common.title') }}:</p>
            <div v-if="editing" class="flex gap-2">
              <input
                v-model="title"
                type="text"
                maxlength="255"
                class="flex-1 rounded-btn border-2 border-line bg-white px-3 py-2 text-[13px] font-bold text-ink outline-none transition focus:border-blue dark:border-dline dark:bg-dbg dark:text-slate-200"
                @keyup.enter="saveTitle"
                @keyup.esc="cancelEdit"
              />
              <button
                @click="saveTitle"
                :disabled="!title.trim()"
                class="rounded-btn bg-blue px-4 text-[13px] font-bold text-white transition hover:opacity-90 disabled:opacity-40"
              >{{ t('actions.save') }}</button>
              <button
                @click="cancelEdit"
                class="rounded-btn border-2 border-line px-4 text-[13px] font-bold text-muted transition hover:border-blue hover:text-blue dark:border-dline"
              >{{ t('actions.cancel') }}</button>
            </div>
            <div v-else class="flex items-start gap-2">
              <span class="flex-1 text-[15px] font-bold text-ink dark:text-slate-200">{{ video.title }}</span>
              <button
                @click="editing = true"
                class="flex h-[30px] w-[30px] flex-none items-center justify-center rounded-[7px] text-muted transition hover:bg-blue hover:text-white"
                :title="t('actions.edit')" :aria-label="t('actions.edit')"
              >
                <Icon kind="pencil" :size="14" />
              </button>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4 text-[13px]">
            <div>
              <span class="text-muted font-semibold">{{ t('videos.duration') }}:</span><br>
              <span class="font-data font-bold text-ink dark:text-slate-200">{{ formatDuration(video.duration_seconds) }}</span>
            </div>
            <div>
              <span class="text-muted font-semibold">{{ t('common.date') }}:</span><br>
              <span class="font-data font-bold text-ink dark:text-slate-200">{{ formatDate(video.created_at) }}</span>
            </div>
            <div>
              <span class="text-muted font-semibold">{{ t('videos.likes') }}:</span><br>
              <span class="inline-flex items-center gap-1.5 font-data font-bold text-ink dark:text-slate-200">
                <Icon kind="heart" :size="14" class="text-pink" />{{ video.likes_count ?? 0 }}
              </span>
            </div>
            <div>
              <span class="text-muted font-semibold">{{ t('common.views') }}:</span><br>
              <span class="inline-flex items-center gap-1.5 font-data font-bold text-ink dark:text-slate-200">
                <Icon kind="eye" :size="14" />{{ video.views ?? 0 }}
              </span>
            </div>
          </div>

          <div v-if="video.tags?.length" class="mt-4 pt-4 border-t border-line dark:border-dline">
            <p class="text-[12px] font-semibold text-muted mb-2">{{ t('videos.tags') }}:</p>
            <div class="flex flex-wrap gap-1.5">
              <span v-for="tag in video.tags" :key="tag" class="rounded-pill bg-blue-light px-2.5 py-0.5 text-[11px] font-bold text-blue">#{{ tag }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Сайдбар -->
      <div class="space-y-4">
        <!-- Автор -->
        <div class="rounded-card bg-white shadow-soft dark:bg-dcard p-5">
          <h3 class="text-[13px] font-extrabold text-ink dark:text-slate-100 mb-3 uppercase tracking-wide">{{ t('common.author') }}</h3>
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 flex-shrink-0 rounded-full bg-blue flex items-center justify-center text-[14px] font-extrabold text-white">
              {{ (video.user?.name || video.user?.phone || '?').charAt(0).toUpperCase() }}
            </div>
            <div>
              <div class="text-[13px] font-bold text-ink dark:text-slate-200">{{ video.user?.name || '—' }}</div>
              <div class="text-[12px] font-data text-muted">{{ video.user?.phone }}</div>
            </div>
          </div>
          <div
            v-if="tariffLine"
            class="mt-3 text-[12px]"
            :class="tariffLine.exhausted ? 'font-bold text-orange' : 'text-muted'"
          >
            {{ tariffLine.text }}
            <template v-if="tariffLine.exhausted"> {{ t('videos.limitSuffix') }}</template>
          </div>
          <div v-if="video.user_id" class="mt-3">
            <Link :href="route('users.show', video.user_id)" class="text-[12px] font-bold text-blue hover:underline">{{ t('videos.profileLink') }}</Link>
          </div>
        </div>

        <!-- Модерация -->
        <div class="rounded-card bg-white shadow-soft dark:bg-dcard p-5">
          <h3 class="text-[13px] font-extrabold text-ink dark:text-slate-100 mb-3 uppercase tracking-wide">{{ t('videos.moderation') }}</h3>
          <div class="mb-3"><StatusBadge :status="video.status" /></div>
          <div v-if="video.rejection_reason" class="mb-3 rounded-btn bg-red/10 p-3 text-[12px] font-semibold text-red">
            {{ nameOf(video.rejection_reason) }}
          </div>
          <div class="space-y-2">
            <button
              v-if="video.status !== 'approved'"
              @click="approve"
              class="w-full rounded-btn bg-green/10 border-2 border-green/20 py-[9px] text-[13px] font-bold text-green transition hover:bg-green hover:text-white"
            >{{ t('videos.approveBtn') }}</button>
            <button
              v-if="video.status !== 'rejected'"
              @click="showRejectModal = true"
              class="w-full rounded-btn bg-red/10 border-2 border-red/20 py-[9px] text-[13px] font-bold text-red transition hover:bg-red hover:text-white"
            >{{ t('videos.rejectBtn') }}</button>
            <button
              v-if="isAdmin"
              @click="confirmDelete = true"
              class="w-full rounded-btn border-2 border-line py-[9px] text-[13px] font-bold text-muted transition hover:border-red hover:text-red dark:border-dline"
            >{{ t('videos.deleteBtn') }}</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Отклонение: выбор причины из справочника -->
    <div v-if="showRejectModal" class="fixed inset-0 z-[600] flex items-center justify-center bg-black/40 backdrop-blur-sm" @click.self="showRejectModal = false">
      <div class="w-[440px] rounded-card bg-white p-6 shadow-[0_24px_48px_rgba(0,0,0,.18)] dark:bg-dcard">
        <h3 class="mb-4 text-[17px] font-extrabold text-ink dark:text-slate-100">{{ t('videos.rejectTitle') }}</h3>
        <div class="space-y-1 mb-5">
          <label v-for="r in rejectionReasons" :key="r.id" class="flex cursor-pointer items-center gap-3 rounded-btn p-3 transition hover:bg-surface dark:hover:bg-white/5">
            <input type="radio" :value="r.id" v-model="rejectReason" class="accent-blue" />
            <span class="text-[13px] font-semibold text-ink dark:text-slate-200">{{ nameOf(r) }}</span>
          </label>
        </div>
        <div class="flex gap-2.5">
          <button @click="showRejectModal = false" class="flex-1 rounded-btn border-2 border-line py-[11px] text-[13px] font-bold text-muted transition hover:border-blue hover:text-blue dark:border-dline">{{ t('actions.cancel') }}</button>
          <button @click="doReject" :disabled="!rejectReason" class="flex-1 rounded-btn bg-red py-[11px] text-[13px] font-bold text-white transition hover:opacity-90 disabled:opacity-40">{{ t('actions.reject') }}</button>
        </div>
      </div>
    </div>

    <ConfirmModal
      :open="confirmDelete"
      :message="t('videos.deleteConfirm')"
      @confirm="doDelete"
      @cancel="confirmDelete = false"
    />
  </AppLayout>
</template>
