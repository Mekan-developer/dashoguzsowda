<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import CreateButton from '@/Components/CreateButton.vue'
import Pagination from '@/Components/Pagination.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import SearchInput from '@/Components/SearchInput.vue'
import { confirmDialog } from '@/confirm'
import Icon from '@/Components/Icon.vue'
import VideoCreateModal from '@/Components/VideoCreateModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { th, td, tr, thead } from '@/table'

const { t, locale } = useI18n()
const page = usePage()

const props = defineProps({
    videos: Object, categories: Array, rejectionReasons: Array, filters: Object, counts: Object,
})

const isAdmin = computed(() => page.props.auth?.user?.role === 'admin')

// ── Создание — панель поверх списка, как у пользователей ─────────────────────
const createOpen = ref(false)
// Старый адрес /admin/videos/create ведёт сюда с ?create=1
onMounted(() => {
    const url = new URL(window.location.href)
    if (url.searchParams.get('create') !== '1') return
    url.searchParams.delete('create')
    window.history.replaceState(window.history.state, '', url)
    createOpen.value = true
})

const search    = ref(props.filters?.search || '')
const statusFil = ref(props.filters?.status || '')
const catId     = ref(props.filters?.category_id || '')

const totalCount = computed(() =>
    (props.counts?.pending ?? 0) + (props.counts?.approved ?? 0) + (props.counts?.rejected ?? 0))

// Вкладки статуса — тот же сегментированный контрол, что на «Объявлениях»
const chips = computed(() => [
    { value: '',         label: t('videos.tabAll'),      count: totalCount.value },
    { value: 'pending',  label: t('videos.tabPending'),  count: props.counts?.pending },
    { value: 'approved', label: t('videos.tabApproved'), count: props.counts?.approved },
    { value: 'rejected', label: t('videos.tabRejected'), count: props.counts?.rejected },
])

// Вид списка — удобство конкретного модератора, поэтому localStorage
const view = ref((() => { try { return localStorage.getItem('videosView') || 'table' } catch { return 'table' } })())
function setView(v) {
    view.value = v
    try { localStorage.setItem('videosView', v) } catch {}
}

const hasFilters = computed(() => !!(search.value || statusFil.value || catId.value))
function resetFilters() {
    search.value = ''; statusFil.value = ''; catId.value = ''
    applyFilters()
}

// Фильтры уходят в запрос — пагинация серверная
let searchDebounce = null
function applyFilters() {
    clearTimeout(searchDebounce)
    router.get(route('videos.index'), {
        search: search.value || undefined,
        status: statusFil.value || undefined,
        category_id: catId.value || undefined,
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

function rejectTitle(video) {
    return video.status === 'rejected' && video.rejection_reason
        ? t('videos.reasonTitle', { reason: nameOf(video.rejection_reason) })
        : undefined
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
async function doDelete(video) {
    if (!(await confirmDialog(t('videos.deleteConfirm')))) return
    router.delete(route('videos.destroy', video.id))
}
</script>

<template>
  <AppLayout>
    <template #header>{{ t('nav.videos') }}</template>
    <!-- Подсказка: ограничение длительности + автосжатие -->
    <template #description>{{ t('videos.hint') }}</template>

    <template #actions>
      <CreateButton :label="t('actions.create')" @click="createOpen = true" />
    </template>

    <!-- Панель: поиск, статус, категория, вид списка — одна строка одной высоты -->
    <div class="mb-4 flex flex-wrap items-center gap-2.5">
      <SearchInput
        :model-value="search"
        :placeholder="t('videos.searchPlaceholder')"
        class="w-full sm:w-[280px]"
        @update:model-value="onSearchInput"
        @submit="applyFilters"
      />

      <div class="seg">
        <button
          v-for="chip in chips" :key="chip.value"
          type="button"
          @click="setStatus(chip.value)"
          :aria-pressed="statusFil === chip.value"
          class="seg-item"
          :class="statusFil === chip.value ? 'seg-item-active' : ''"
        >{{ chip.label }} ({{ chip.count ?? 0 }})</button>
      </div>

      <select v-model="catId" @change="applyFilters" :aria-label="t('common.category')" class="filter-select">
        <option value="">{{ t('listings.allCategories') }}</option>
        <option v-for="c in categories" :key="c.id" :value="c.id">{{ nameOf(c) }}</option>
      </select>

      <div class="ml-auto flex items-center gap-3">
        <span class="whitespace-nowrap text-[12.5px] tabular-nums text-[var(--text-muted)]">
          {{ t('dataTable.countOf', { shown: videos.data.length, total: videos.total }) }}
        </span>
        <!-- Вид: таблица / плитка -->
        <div class="seg !h-10" role="group" :aria-label="t('videos.viewLabel')">
          <button
            v-for="v in ['table', 'grid']" :key="v"
            type="button"
            @click="setView(v)"
            :aria-pressed="view === v"
            :title="t(v === 'table' ? 'videos.viewTable' : 'videos.viewGrid')"
            :aria-label="t(v === 'table' ? 'videos.viewTable' : 'videos.viewGrid')"
            class="seg-item !px-2.5"
            :class="view === v ? 'seg-item-active' : ''"
          ><Icon :kind="v === 'table' ? 'menu' : 'grid'" :size="16" /></button>
        </div>
      </div>
    </div>

    <!-- ── Таблица ───────────────────────────────────────────────────────── -->
    <div v-if="view === 'table'" class="card overflow-hidden">
      <div class="overflow-x-auto">
      <table class="w-full min-w-[720px]">
        <thead>
          <tr :class="thead">
            <th :class="th" class="w-[72px]">{{ t('common.id') }}</th>
            <th :class="th">{{ t('videos.colVideo') }}</th>
            <th :class="th" class="hidden lg:table-cell">{{ t('common.category') }}</th>
            <th :class="th" class="hidden md:table-cell">{{ t('videos.colAuthor') }}</th>
            <th :class="th">{{ t('common.status') }}</th>
            <th :class="th" class="hidden xl:table-cell">{{ t('videos.likes') }}</th>
            <th :class="th" class="hidden xl:table-cell">{{ t('common.views') }}</th>
            <th :class="th" class="hidden md:table-cell">{{ t('common.date') }}</th>
            <th :class="th" class="text-right">{{ t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="video in videos.data" :key="video.id" :class="tr" class="!h-[76px]">
            <td :class="td" class="font-data tabular-nums text-[var(--text-muted)]">{{ video.id }}</td>

            <!-- Превью 9:16 с длительностью + название, теги, обработка -->
            <td :class="td">
              <div class="flex min-w-0 items-center gap-3">
                <Link
                  :href="route('videos.show', video.id)"
                  class="relative block h-[56px] w-[38px] flex-none overflow-hidden rounded-[6px] border border-[var(--card-border)] bg-navy"
                  :title="t('videos.openCard')"
                >
                  <img v-if="video.preview_url" :src="video.preview_url" class="h-full w-full object-cover" alt="" loading="lazy" />
                  <div v-else class="flex h-full w-full items-center justify-center text-white/40"><Icon kind="play" :size="16" /></div>
                  <span class="absolute bottom-0.5 left-1/2 -translate-x-1/2 rounded-[4px] bg-black/65 px-1 font-data text-[9.5px] font-semibold leading-[14px] text-white">
                    {{ formatDuration(video.duration_seconds) }}
                  </span>
                </Link>
                <div class="min-w-0">
                  <Link :href="route('videos.show', video.id)" class="block max-w-[280px] truncate font-semibold text-[var(--text)] transition-colors duration-150 hover:text-link">{{ video.title }}</Link>
                  <div v-if="video.tags?.length" class="mt-0.5 max-w-[280px] truncate text-[12px] text-[var(--text-muted)]">
                    <span v-for="tag in video.tags" :key="tag" class="mr-1.5">#{{ tag }}</span>
                  </div>
                  <span v-if="!video.is_processed" class="mt-1 inline-flex h-5 items-center gap-1 rounded-full bg-amber-500/10 px-2 text-[11px] font-semibold text-amber-700 dark:text-amber-300">
                    <Icon kind="clock" :size="11" />{{ t('videos.processing') }}
                  </span>
                </div>
              </div>
            </td>

            <td :class="td" class="hidden text-[var(--text-secondary)] lg:table-cell">{{ nameOf(video.category) }}</td>

            <!-- Автор · Тариф (использовано/лимит) -->
            <td :class="td" class="hidden md:table-cell">
              <div class="max-w-[200px] truncate text-[var(--text)]">{{ video.user?.name || video.user?.phone || '—' }}</div>
              <div
                v-if="tariffLine(video)"
                class="mt-0.5 text-[12px]"
                :class="tariffLine(video).exhausted ? 'font-semibold text-amber-700 dark:text-amber-300' : 'text-[var(--text-muted)]'"
              >
                {{ tariffLine(video).text }}<template v-if="tariffLine(video).exhausted"> {{ t('videos.limitSuffix') }}</template>
              </div>
            </td>

            <!-- Статус (для отклонённых — причина в title) -->
            <td :class="td">
              <span :title="rejectTitle(video)"><StatusBadge :status="video.status" /></span>
            </td>

            <td :class="td" class="hidden xl:table-cell">
              <span class="inline-flex items-center gap-1.5 font-data tabular-nums text-[var(--text-secondary)]"><Icon kind="heart" :size="14" class="text-[var(--text-muted)]" />{{ video.likes_count }}</span>
            </td>
            <td :class="td" class="hidden xl:table-cell">
              <span class="inline-flex items-center gap-1.5 font-data tabular-nums text-[var(--text-secondary)]"><Icon kind="eye" :size="14" class="text-[var(--text-muted)]" />{{ video.views }}</span>
            </td>
            <td :class="td" class="hidden whitespace-nowrap font-data tabular-nums text-[var(--text-secondary)] md:table-cell">{{ formatDate(video.created_at) }}</td>

            <td :class="td">
              <div class="flex items-center justify-end gap-1.5">
                <Link :href="route('videos.show', video.id)" class="icon-btn" :title="t('actions.show')" :aria-label="t('actions.show')"><Icon kind="eye" :size="16" /></Link>
                <template v-if="video.status === 'pending'">
                  <button type="button" @click="approve(video.id)" class="icon-btn icon-btn-success" :title="t('actions.approve')" :aria-label="t('actions.approve')"><Icon kind="check" :size="16" /></button>
                  <button type="button" @click="openReject(video)" class="icon-btn icon-btn-danger" :title="t('actions.reject')" :aria-label="t('actions.reject')"><Icon kind="close" :size="16" /></button>
                </template>
                <button v-else-if="isAdmin" type="button" @click="doDelete(video)" class="icon-btn icon-btn-danger" :title="t('actions.delete')" :aria-label="t('actions.delete')"><Icon kind="trash" :size="16" /></button>
              </div>
            </td>
          </tr>
          <tr v-if="!videos.data?.length">
            <td colspan="9"><EmptyState icon="video" :title="t('videos.notFound')" :text="hasFilters ? t('common.emptyFiltered') : ''">
              <button v-if="hasFilters" type="button" class="btn btn-secondary" @click="resetFilters">{{ t('common.resetFilters') }}</button>
            </EmptyState></td>
          </tr>
        </tbody>
      </table>
      </div>
      <Pagination :links="videos.links" :from="videos.from" :to="videos.to" :total="videos.total" />
    </div>

    <!-- ── Плитка: кадр 9:16 важнее строки таблицы, когда смотрят содержимое ── -->
    <template v-else>
      <div v-if="videos.data?.length" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6">
        <article v-for="video in videos.data" :key="video.id" class="card flex min-w-0 flex-col overflow-hidden">
          <Link :href="route('videos.show', video.id)" class="relative block aspect-[9/14] overflow-hidden bg-navy" :title="t('videos.openCard')">
            <img v-if="video.preview_url" :src="video.preview_url" class="h-full w-full object-cover transition-opacity duration-150 hover:opacity-90" alt="" loading="lazy" />
            <div v-else class="flex h-full w-full items-center justify-center text-white/40"><Icon kind="play" :size="28" /></div>
            <span class="absolute bottom-2 right-2 rounded-[4px] bg-black/65 px-1.5 font-data text-[11px] font-semibold leading-[18px] text-white">{{ formatDuration(video.duration_seconds) }}</span>
            <span class="absolute left-2 top-2" :title="rejectTitle(video)"><StatusBadge :status="video.status" class="!bg-black/55 !text-white" /></span>
          </Link>
          <div class="flex flex-1 flex-col p-3">
            <Link :href="route('videos.show', video.id)" class="line-clamp-2 text-[13.5px] font-semibold leading-snug text-[var(--text)] hover:text-link">{{ video.title }}</Link>
            <div class="mt-1 truncate text-[12px] text-[var(--text-muted)]">{{ video.user?.name || video.user?.phone || '—' }} · {{ nameOf(video.category) }}</div>
            <div class="mt-2 flex items-center gap-3 font-data text-[12px] tabular-nums text-[var(--text-secondary)]">
              <span class="inline-flex items-center gap-1"><Icon kind="eye" :size="13" />{{ video.views }}</span>
              <span class="inline-flex items-center gap-1"><Icon kind="heart" :size="13" />{{ video.likes_count }}</span>
              <span class="ml-auto text-[var(--text-muted)]">{{ formatDate(video.created_at) }}</span>
            </div>
            <div v-if="video.status === 'pending' || isAdmin" class="mt-3 flex gap-1.5 border-t border-[var(--card-border)] pt-3">
              <template v-if="video.status === 'pending'">
                <button type="button" @click="approve(video.id)" class="btn btn-sm btn-green-soft flex-1" :title="t('actions.approve')"><Icon kind="check" :size="15" /></button>
                <button type="button" @click="openReject(video)" class="btn btn-sm btn-red-soft flex-1" :title="t('actions.reject')"><Icon kind="close" :size="15" /></button>
              </template>
              <button v-else type="button" @click="doDelete(video)" class="icon-btn icon-btn-danger ml-auto" :title="t('actions.delete')" :aria-label="t('actions.delete')"><Icon kind="trash" :size="15" /></button>
            </div>
          </div>
        </article>
      </div>
      <div v-else class="card">
        <EmptyState icon="video" :title="t('videos.notFound')" :text="hasFilters ? t('common.emptyFiltered') : ''">
          <button v-if="hasFilters" type="button" class="btn btn-secondary" @click="resetFilters">{{ t('common.resetFilters') }}</button>
        </EmptyState>
      </div>
      <div class="card mt-4 overflow-hidden"><Pagination :links="videos.links" :from="videos.from" :to="videos.to" :total="videos.total" /></div>
    </template>

    <!-- Отклонение: выбор причины из справочника -->
    <div v-if="rejectTarget" class="fixed inset-0 z-[600] flex items-center justify-center bg-[#0A0C1A]/50 p-4" @click.self="rejectTarget = null">
      <div class="card w-full max-w-[440px] p-6 shadow-lg2">
        <h3 class="mb-4 text-[16px] font-semibold text-[var(--text)]">{{ t('videos.rejectTitle') }}</h3>
        <div class="space-y-1 mb-5">
          <label v-for="r in rejectionReasons" :key="r.id" class="flex items-center gap-3 cursor-pointer rounded-btn p-3 hover:bg-surface dark:hover:bg-white/5 transition">
            <input type="radio" :value="r.id" v-model="rejectReason" class="accent-blue" />
            <span class="text-[13px] font-semibold text-ink dark:text-slate-200">{{ nameOf(r) }}</span>
          </label>
        </div>
        <div class="flex justify-end gap-2">
          <button @click="rejectTarget = null" class="btn btn-secondary">{{ t('actions.cancel') }}</button>
          <button @click="doReject" :disabled="!rejectReason" class="btn btn-danger">{{ t('actions.reject') }}</button>
        </div>
      </div>
    </div>

    <!-- Категории ролика — те же корневые, что и в фильтре списка -->
    <VideoCreateModal :open="createOpen" :categories="categories" @close="createOpen = false" />
  </AppLayout>
</template>
