<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import CreateButton from '@/Components/CreateButton.vue'
import Pagination from '@/Components/Pagination.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import SearchInput from '@/Components/SearchInput.vue'
import ListingCreateModal from '@/Components/ListingCreateModal.vue'
import Icon from '@/Components/Icon.vue'

const { t, locale } = useI18n()

const props = defineProps({
    listings: Object, categories: Array, rejectionReasons: Array, filters: Object, counts: Object,
    // Справочники панели создания — optional-проп, приходит только по запросу
    createForm: { type: Object, default: null },
})

// ── Создание — панель поверх списка, как у пользователей ─────────────────────
const createOpen = ref(false)
function openCreate() {
    createOpen.value = true
    if (!props.createForm) router.reload({ only: ['createForm'] })
}
// Старый адрес /admin/listings/create ведёт сюда с ?create=1
onMounted(() => {
    const url = new URL(window.location.href)
    if (url.searchParams.get('create') !== '1') return
    url.searchParams.delete('create')
    window.history.replaceState(window.history.state, '', url)
    openCreate()
})

const search     = ref(props.filters?.search      || '')
const statusFil  = ref(props.filters?.status      || '')
const catId      = ref(props.filters?.category_id || '')

const tabs = computed(() => [
    { label: t('listings.tabAll'), value: '' },
    { label: t('listings.tabPending', { n: props.counts.pending }), value: 'pending' },
    { label: t('listings.tabApproved', { n: props.counts.approved }), value: 'approved' },
    { label: t('listings.tabRejected', { n: props.counts.rejected }), value: 'rejected' },
])

function applyFilters() {
    router.get(route('listings.index'), { search: search.value, status: statusFil.value, category_id: catId.value }, { preserveState: true, replace: true })
}

function setStatus(s) { statusFil.value = s; applyFilters() }

// Approve
function approve(id) {
    router.patch(route('listings.approve', id))
}

// Reject modal
const rejectTarget = ref(null)
const rejectReason = ref('')

function openReject(listing) { rejectTarget.value = listing; rejectReason.value = '' }
function doReject() {
    if (!rejectReason.value) return
    router.patch(route('listings.reject', rejectTarget.value.id), { rejection_reason_id: rejectReason.value }, {
        onSuccess: () => { rejectTarget.value = null },
    })
}

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('ru', { day: '2-digit', month: '2-digit', year: '2-digit' })
}
function formatPrice(p) {
    if (!p) return '—'
    return Number(p).toLocaleString('ru') + ' TMT'
}
// Город и регион — вторая строка в ячейке объявления
function placeOf(listing) {
    const name = o => o?.[locale.value === 'tk' ? 'name_tk' : 'name_ru'] || o?.name_ru
    return [name(listing.city), name(listing.region)].filter(Boolean).join(', ')
}

// Общие классы ячеек и кнопок действий таблицы
const th = 'h-11 px-5 text-left text-[11.5px] font-semibold uppercase tracking-[.06em] text-[var(--text-muted)] whitespace-nowrap'
const td = 'px-5 text-[13.5px] font-medium'
const actionBtn = 'flex h-[34px] w-[34px] items-center justify-center rounded-[8px] transition-colors duration-150 ease-out'

function categoryPath(category) {
    if (!category) return '—'
    const chain = []
    let c = category
    while (c) { chain.unshift(c.name_ru); c = c.parent }
    return chain.join(' → ')
}
</script>

<template>
  <AppLayout>
    <template #header>{{ t('nav.listings') }}</template>

    <template #actions>
      <CreateButton :label="t('actions.create')" @click="openCreate" />
    </template>

    <!-- Панель управления: поиск, статус, категория — одна строка одной высоты (42px);
         на узком экране переносится, счётчик уходит вправо -->
    <div class="mb-4 flex flex-wrap items-center gap-2.5">
      <SearchInput
        v-model="search"
        @submit="applyFilters"
        :placeholder="t('listings.searchPlaceholder')"
        class="!h-[42px] w-full !rounded-[8px] border border-[var(--field-border)] sm:w-[280px]"
      />

      <!-- Статус — сегментированный контрол -->
      <div class="flex h-[42px] max-w-full items-center gap-0.5 overflow-x-auto rounded-[8px] border border-[var(--field-border)] bg-[var(--field-bg)] p-1 [scrollbar-width:none]">
        <button v-for="tab in tabs" :key="tab.value"
          type="button"
          @click="setStatus(tab.value)"
          :aria-pressed="statusFil === tab.value"
          class="h-full flex-none whitespace-nowrap rounded-[6px] px-3.5 text-[13px] font-medium transition-colors duration-150 ease-out"
          :class="statusFil === tab.value
            ? 'bg-[var(--accent)] font-semibold text-white'
            : 'text-[var(--text-secondary)] hover:bg-[var(--nav-hover)] hover:text-[var(--text)]'"
        >{{ tab.label }}</button>
      </div>

      <!-- Категория -->
      <div class="relative">
        <Icon kind="tag" :size="15" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[var(--text-muted)]" />
        <select
          v-model="catId"
          @change="applyFilters"
          :aria-label="t('common.category')"
          class="h-[42px] max-w-[240px] cursor-pointer appearance-none truncate rounded-[8px] border border-[var(--field-border)] bg-[var(--field-bg)] bg-none py-0 pl-9 pr-9 text-[13px] font-medium text-[var(--text)] outline-none transition-colors duration-150 ease-out hover:border-[var(--text-muted)] focus:border-[var(--accent)] focus:ring-0"
        >
          <option value="">{{ t('listings.allCategories') }}</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name_ru }}</option>
        </select>
        <Icon kind="chevronDown" :size="14" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[var(--text-muted)]" />
      </div>

      <!-- Показано из всего -->
      <span class="ml-auto whitespace-nowrap text-[12.5px] text-[var(--text-muted)] tabular-nums">
        {{ t('dataTable.countOf', { shown: listings.data.length, total: listings.total }) }}
      </span>
    </div>

    <!-- Таблица. Второстепенные колонки скрываются по мере сужения экрана,
         на совсем узком — горизонтальная прокрутка внутри карточки -->
    <div class="overflow-hidden rounded-xl border border-[var(--card-border)] bg-[var(--card-bg)]">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] border-collapse">
          <thead>
            <tr class="border-b border-[var(--card-border)] bg-black/[.015] dark:bg-white/[.02]">
              <th :class="th" class="w-[72px]">{{ t('common.id') }}</th>
              <th :class="th">{{ t('listings.colListing') }}</th>
              <th :class="th" class="hidden md:table-cell">{{ t('common.author') }}</th>
              <th :class="th" class="hidden lg:table-cell">{{ t('common.category') }}</th>
              <th :class="th">{{ t('common.price') }}</th>
              <th :class="th" class="hidden xl:table-cell">{{ t('common.views') }}</th>
              <th :class="th">{{ t('common.status') }}</th>
              <th :class="th" class="hidden md:table-cell">{{ t('common.date') }}</th>
              <th :class="th" class="text-right">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="listing in listings.data" :key="listing.id"
              @dblclick="router.visit(route('listings.show', listing.id))"
              class="h-[68px] cursor-pointer border-b border-[var(--card-border)] transition-colors duration-150 ease-out last:border-b-0 hover:bg-[var(--nav-hover)]"
            >
              <td :class="td" class="font-data tabular-nums text-[var(--text-muted)]">{{ listing.id }}</td>
              <td :class="td">
                <div class="flex min-w-0 items-center gap-3">
                  <div class="h-11 w-11 flex-none overflow-hidden rounded-[8px] border border-[var(--card-border)] bg-[var(--field-bg)]">
                    <img v-if="listing.media?.[0]" :src="`/storage/${listing.media[0].path}`" class="h-full w-full object-cover" :alt="listing.title" loading="lazy" />
                    <div v-else class="flex h-full w-full items-center justify-center text-[var(--text-muted)]">
                      <Icon kind="image" :size="16" />
                    </div>
                  </div>
                  <div class="min-w-0">
                    <Link :href="route('listings.show', listing.id)" class="block max-w-[320px] truncate text-[13.5px] font-semibold text-[var(--text)] transition-colors duration-150 hover:text-[var(--accent)] dark:hover:text-[var(--nav-indicator)]">{{ listing.title }}</Link>
                    <div v-if="placeOf(listing)" class="mt-0.5 max-w-[320px] truncate text-[12px] text-[var(--text-muted)]">{{ placeOf(listing) }}</div>
                  </div>
                </div>
              </td>
              <td :class="td" class="hidden max-w-[180px] truncate text-[var(--text-secondary)] md:table-cell">{{ listing.user?.name || listing.user?.phone || '—' }}</td>
              <td :class="td" class="hidden max-w-[240px] truncate text-[var(--text-secondary)] lg:table-cell">{{ categoryPath(listing.category) }}</td>
              <td :class="td" class="whitespace-nowrap font-data font-semibold tabular-nums text-[var(--text)]">{{ formatPrice(listing.price) }}</td>
              <td :class="td" class="hidden font-data tabular-nums text-[var(--text-secondary)] xl:table-cell">{{ listing.views || 0 }}</td>
              <td :class="td"><StatusBadge :status="listing.status" /></td>
              <td :class="td" class="hidden whitespace-nowrap font-data tabular-nums text-[var(--text-secondary)] md:table-cell">{{ formatDate(listing.created_at) }}</td>
              <td :class="td">
                <div class="flex items-center justify-end gap-1.5">
                  <Link :href="route('listings.show', listing.id)" :class="[actionBtn, 'bg-[var(--nav-hover)] text-[var(--text-secondary)] hover:bg-sky-500/15 hover:text-sky-600 dark:hover:text-sky-300']" :title="t('actions.show')" :aria-label="t('actions.show')">
                    <Icon kind="eye" :size="16" />
                  </Link>
                  <button v-if="listing.status === 'pending'" type="button" @click="approve(listing.id)" :class="[actionBtn, 'bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20 dark:text-emerald-400 dark:hover:text-emerald-300']" :title="t('actions.approve')" :aria-label="t('actions.approve')">
                    <Icon kind="check" :size="16" />
                  </button>
                  <button v-if="listing.status !== 'rejected'" type="button" @click="openReject(listing)" :class="[actionBtn, 'bg-red-500/10 text-red-600 hover:bg-red-500/20 dark:text-red-400 dark:hover:text-red-300']" :title="t('actions.reject')" :aria-label="t('actions.reject')">
                    <Icon kind="close" :size="16" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!listings.data?.length">
              <td colspan="9" class="px-5 py-14 text-center text-[13px] text-[var(--text-muted)]">{{ t('listings.notFound') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="listings.links" :from="listings.from" :to="listings.to" :total="listings.total" />
    </div>

    <!-- Reject modal -->
    <div v-if="rejectTarget" class="fixed inset-0 z-[600] flex items-center justify-center bg-[#0A0C1A]/50 p-4" @click.self="rejectTarget = null">
      <div class="card w-full max-w-[440px] p-6 shadow-lg2">
        <h3 class="mb-1 text-[17px] font-extrabold text-ink dark:text-slate-100">{{ t('listings.rejectTitle') }}</h3>
        <p class="mb-4 text-[12px] text-muted">{{ rejectTarget?.title }}</p>
        <div class="space-y-1 mb-5">
          <label v-for="r in rejectionReasons" :key="r.id" class="flex items-center gap-3 cursor-pointer rounded-btn p-3 hover:bg-surface dark:hover:bg-white/5 transition">
            <input type="radio" :value="r.id" v-model="rejectReason" class="accent-blue" />
            <span class="text-[13px] font-semibold text-ink dark:text-slate-200">{{ r.name_ru }}</span>
          </label>
        </div>
        <div class="flex justify-end gap-2">
          <button @click="rejectTarget = null" class="btn btn-secondary">{{ t('actions.cancel') }}</button>
          <button @click="doReject" :disabled="!rejectReason" class="btn btn-danger">{{ t('actions.reject') }}</button>
        </div>
      </div>
    </div>

    <ListingCreateModal :open="createOpen" :data="createForm" @close="createOpen = false" />
  </AppLayout>
</template>
