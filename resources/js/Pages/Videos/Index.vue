<script setup>
import { ref, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import SearchInput from '@/Components/SearchInput.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import Icon from '@/Components/Icon.vue'

const { t, locale } = useI18n()
const page = usePage()

const props = defineProps({
    videos: Object, rejectionReasons: Array, filters: Object, counts: Object,
})

const isAdmin = computed(() => page.props.auth?.user?.role === 'admin')

const search    = ref(props.filters?.search || '')
const statusFil = ref(props.filters?.status || '')

const totalCount = computed(() =>
    (props.counts?.pending ?? 0) + (props.counts?.approved ?? 0) + (props.counts?.rejected ?? 0))

// Чипы-фильтры: счётчик «На проверке» — янтарный, «Одобрено» — зелёный, «Отклонено» — красный
const chips = computed(() => [
    { value: '',         label: t('videos.tabAll'),      count: totalCount.value,       tint: 'bg-surface text-muted dark:bg-white/10 dark:text-slate-300' },
    { value: 'pending',  label: t('videos.tabPending'),  count: props.counts?.pending,  tint: 'bg-orange/15 text-orange' },
    { value: 'approved', label: t('videos.tabApproved'), count: props.counts?.approved, tint: 'bg-green/15 text-green' },
    { value: 'rejected', label: t('videos.tabRejected'), count: props.counts?.rejected, tint: 'bg-red/15 text-red' },
])

// Фильтры уходят в запрос — пагинация серверная
let searchDebounce = null
function applyFilters() {
    clearTimeout(searchDebounce)
    router.get(route('videos.index'), {
        search: search.value || undefined,
        status: statusFil.value || undefined,
    }, { preserveState: true, replace: true })
}
function onSearchInput(value) {
    search.value = value
    clearTimeout(searchDebounce)
    searchDebounce = setTimeout(applyFilters, 350)
}
function setStatus(s) { statusFil.value = s; applyFilters() }

// Справочники двуязычные — показываем имя активного языка
const nameOf = (item) => (locale.value === 'tk' && item?.name_tk) ? item.name_tk : item?.name_ru

function tariffLine(video) {
    const usage = video.tariff_usage
    if (!usage?.name_ru) return null
    return {
        text: `${t('videos.tariffLabel', { name: nameOf(usage) })} · ${usage.used}/${usage.limit}`,
        exhausted: usage.limit !== null && usage.used >= usage.limit,
    }
}

function formatDuration(seconds) {
    const s = Number(seconds) || 0
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`
}

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('ru', { day: '2-digit', month: '2-digit', year: '2-digit' })
}

// ── Модерация ───────────────────────────────────────────────────────────────
function approve(id) { router.patch(route('videos.approve', id)) }

const rejectTarget = ref(null)
const rejectReason = ref('')

function openReject(video) { rejectTarget.value = video; rejectReason.value = '' }
function doReject() {
    if (!rejectReason.value) return
    router.patch(route('videos.reject', rejectTarget.value.id), { rejection_reason_id: rejectReason.value }, {
        onSuccess: () => { rejectTarget.value = null },
    })
}

// ── Удаление (только admin, с подтверждением) ───────────────────────────────
const deleteTarget = ref(null)

function doDelete() {
    router.delete(route('videos.destroy', deleteTarget.value.id), {
        onSuccess: () => { deleteTarget.value = null },
    })
}
</script>

<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center gap-3">
        <span class="text-[28px] font-extrabold leading-none tracking-[-.01em]">{{ t('nav.videos') }}</span>
        <span class="rounded-pill bg-[var(--accent-tint)] px-2.5 py-1 text-[12px] font-bold text-[var(--accent)]">
          {{ t('videos.countPill', totalCount) }}
        </span>
      </div>
    </template>

    <!-- Подсказка: ограничение длительности + автосжатие -->
    <div class="mb-4 flex items-center gap-1.5 text-[12.5px] text-muted dark:text-slate-400">
      <Icon kind="clock" :size="14" class="flex-none" />
      {{ t('videos.hint') }}
    </div>

    <!-- Поиск + чипы-фильтры по статусу -->
    <div class="mb-4 flex flex-wrap items-center gap-3">
      <div class="w-full sm:w-72">
        <SearchInput
          :model-value="search"
          :placeholder="t('videos.searchPlaceholder')"
          @update:model-value="onSearchInput"
          @submit="applyFilters"
        />
      </div>
      <div class="flex gap-2 flex-wrap">
        <button
          v-for="chip in chips"
          :key="chip.value"
          @click="setStatus(chip.value)"
          class="flex items-center gap-1.5 rounded-[20px] px-3.5 py-1.5 text-[13px] font-bold transition"
          :class="statusFil === chip.value
            ? 'bg-[var(--accent)] text-white shadow-[0_4px_12px_var(--accent-tint)]'
            : 'bg-white dark:bg-dcard border border-line dark:border-dline text-ink dark:text-slate-200 hover:bg-surface dark:hover:bg-white/5'"
        >
          {{ chip.label }}
          <span
            class="rounded-pill px-1.5 py-px text-[11px] font-extrabold"
            :class="statusFil === chip.value ? 'bg-white/25 text-white' : chip.tint"
          >{{ chip.count ?? 0 }}</span>
        </button>
      </div>
    </div>

    <div class="rounded-card bg-white shadow-soft dark:bg-dcard overflow-hidden">
      <div class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-surface/50 dark:bg-dbg/50">
          <tr>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline w-16">{{ t('common.id') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline w-[54px]"></th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('videos.colVideo') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('videos.colAuthor') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.status') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('videos.likes') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.views') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.date') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="video in videos.data" :key="video.id" class="hover:bg-surface/30 dark:hover:bg-white/3 transition">
            <td class="px-4 py-[10px] text-[13px] border-b border-line dark:border-dline font-data text-muted">{{ video.id }}</td>
            <!-- Превью 38×56 (кадр 9:16) с бейджем длительности -->
            <td class="px-4 py-[10px] border-b border-line dark:border-dline">
              <Link
                :href="route('videos.show', video.id)"
                class="relative block h-[56px] w-[38px] flex-none overflow-hidden rounded-[8px] bg-navy"
                :title="t('videos.openCard')"
              >
                <img
                  v-if="video.preview_url"
                  :src="video.preview_url"
                  class="h-full w-full object-cover"
                  alt=""
                />
                <div v-else class="flex h-full w-full items-center justify-center text-white/40">
                  <Icon kind="play" :size="16" />
                </div>
                <span class="absolute bottom-0.5 left-1/2 -translate-x-1/2 rounded-[4px] bg-black/60 px-1 text-[9px] font-bold leading-[14px] text-white font-data">
                  {{ formatDuration(video.duration_seconds) }}
                </span>
              </Link>
            </td>

            <!-- Название + теги -->
            <td class="max-w-[260px] px-4 py-[13px] text-[13px] border-b border-line dark:border-dline">
              <Link :href="route('videos.show', video.id)" class="block truncate font-bold text-ink transition hover:text-blue dark:text-slate-200">{{ video.title }}</Link>
              <div v-if="video.tags?.length" class="mt-0.5 truncate text-[12px] font-semibold text-blue">
                <span v-for="tag in video.tags" :key="tag" class="mr-1.5">#{{ tag }}</span>
              </div>
              <span
                v-if="!video.is_processed"
                class="mt-1 inline-flex items-center gap-1 rounded-pill bg-orange/10 px-2 py-px text-[10px] font-bold text-orange"
              >
                <Icon kind="clock" :size="10" />
                {{ t('videos.processing') }}
              </span>
            </td>

            <!-- Автор · Тариф (использовано/лимит) -->
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline">
              <div class="font-semibold text-ink dark:text-slate-200">{{ video.user?.name || video.user?.phone || '—' }}</div>
              <div
                v-if="tariffLine(video)"
                class="mt-0.5 text-[12px]"
                :class="tariffLine(video).exhausted ? 'font-bold text-orange' : 'text-muted'"
              >
                {{ tariffLine(video).text }}
                <template v-if="tariffLine(video).exhausted"> {{ t('videos.limitSuffix') }}</template>
              </div>
            </td>

            <!-- Статус (для отклонённых — причина в title) -->
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline">
              <span :title="video.status === 'rejected' && video.rejection_reason ? t('videos.reasonTitle', { reason: nameOf(video.rejection_reason) }) : undefined">
                <StatusBadge :status="video.status" />
              </span>
            </td>

            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline">
              <span class="inline-flex items-center gap-1.5 font-data text-muted">
                <Icon kind="heart" :size="14" class="text-pink" />{{ video.likes_count }}
              </span>
            </td>
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline">
              <span class="inline-flex items-center gap-1.5 font-data text-muted">
                <Icon kind="eye" :size="14" />{{ video.views }}
              </span>
            </td>
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline font-data text-muted">{{ formatDate(video.created_at) }}</td>

            <!-- Действия -->
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline">
              <div class="flex items-center gap-1.5">
                <template v-if="video.status === 'pending'">
                  <button
                    @click="approve(video.id)"
                    class="flex h-[30px] w-[30px] items-center justify-center rounded-[7px] bg-green/10 text-green transition hover:bg-green hover:text-white"
                    :title="t('actions.approve')" :aria-label="t('actions.approve')"
                  >
                    <Icon kind="check" :size="14" />
                  </button>
                  <button
                    @click="openReject(video)"
                    class="flex h-[30px] w-[30px] items-center justify-center rounded-[7px] bg-red/10 text-red transition hover:bg-red hover:text-white"
                    :title="t('actions.reject')" :aria-label="t('actions.reject')"
                  >
                    <Icon kind="close" :size="14" />
                  </button>
                </template>
                <button
                  v-else-if="isAdmin"
                  @click="deleteTarget = video"
                  class="flex h-[30px] w-[30px] items-center justify-center rounded-[7px] text-muted transition hover:bg-red hover:text-white"
                  :title="t('actions.delete')" :aria-label="t('actions.delete')"
                >
                  <Icon kind="trash" :size="14" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!videos.data?.length"><td colspan="9" class="px-4 py-12 text-center text-[13px] text-muted">{{ t('videos.notFound') }}</td></tr>
        </tbody>
      </table>
      </div>
      <Pagination :links="videos.links" />
    </div>

    <!-- Отклонение: выбор причины из справочника -->
    <div v-if="rejectTarget" class="fixed inset-0 z-[600] flex items-center justify-center bg-black/40 backdrop-blur-sm" @click.self="rejectTarget = null">
      <div class="w-[440px] rounded-card bg-white p-6 shadow-[0_24px_48px_rgba(0,0,0,.18)] dark:bg-dcard">
        <h3 class="mb-4 text-[17px] font-extrabold text-ink dark:text-slate-100">{{ t('videos.rejectTitle') }}</h3>
        <div class="space-y-1 mb-5">
          <label v-for="r in rejectionReasons" :key="r.id" class="flex items-center gap-3 cursor-pointer rounded-btn p-3 hover:bg-surface dark:hover:bg-white/5 transition">
            <input type="radio" :value="r.id" v-model="rejectReason" class="accent-blue" />
            <span class="text-[13px] font-semibold text-ink dark:text-slate-200">{{ nameOf(r) }}</span>
          </label>
        </div>
        <div class="flex gap-2.5">
          <button @click="rejectTarget = null" class="flex-1 rounded-btn border-2 border-line py-[11px] text-[13px] font-bold text-muted hover:border-blue hover:text-blue transition dark:border-dline">{{ t('actions.cancel') }}</button>
          <button @click="doReject" :disabled="!rejectReason" class="flex-1 rounded-btn bg-red py-[11px] text-[13px] font-bold text-white hover:opacity-90 disabled:opacity-40 transition">{{ t('actions.reject') }}</button>
        </div>
      </div>
    </div>

    <!-- Подтверждение удаления -->
    <ConfirmModal
      :open="!!deleteTarget"
      :message="t('videos.deleteConfirm')"
      @confirm="doDelete"
      @cancel="deleteTarget = null"
    />
  </AppLayout>
</template>
