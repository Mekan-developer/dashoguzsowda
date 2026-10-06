<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppDrawer from '@/Components/AppDrawer.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Pagination from '@/Components/Pagination.vue'
import SearchInput from '@/Components/SearchInput.vue'
import Icon from '@/Components/Icon.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { th, td, tr, thead } from '@/table'

const { t, locale } = useI18n()

const props = defineProps({
    reviews:          Object,
    rejectionReasons: Array,
    filters:          Object,
    counts:           Object,
})

const searchQuery  = ref(props.filters?.search ?? '')
const statusFilter = ref(props.filters?.status ?? '')

const totalCount = computed(() =>
    (props.counts?.pending ?? 0) + (props.counts?.approved ?? 0) + (props.counts?.rejected ?? 0))

const statusChips = computed(() => [
    { value: '', label: t('common.all'), count: totalCount.value },
    { value: 'pending', label: t('reviews.tabPending'), count: props.counts?.pending },
    { value: 'approved', label: t('reviews.tabApproved'), count: props.counts?.approved },
    { value: 'rejected', label: t('reviews.tabRejected'), count: props.counts?.rejected },
])

// Список пагинируется на бэкенде — поиск и фильтр статуса уходят в запрос
let searchDebounce = null
function applyFilters() {
    clearTimeout(searchDebounce)
    router.get(route('reviews.index'), {
        search: searchQuery.value || undefined,
        status: statusFilter.value || undefined,
    }, { preserveState: true, replace: true })
}
function onSearchInput(value) {
    searchQuery.value = value
    clearTimeout(searchDebounce)
    searchDebounce = setTimeout(applyFilters, 350)
}
function setStatusFilter(value) {
    statusFilter.value = value
    applyFilters()
}

const hasActiveFilters = computed(() => !!(searchQuery.value || statusFilter.value))
function resetFilters() {
    searchQuery.value = ''; statusFilter.value = ''
    applyFilters()
}

// Справочники двуязычные — показываем имя активного языка
const nameOf = (item) => (locale.value === 'tk' && item?.name_tk) ? item.name_tk : item?.name_ru

const formatDate = (value) => new Date(value).toLocaleDateString('ru', {
    day: '2-digit', month: '2-digit', year: 'numeric',
})

// ── Просмотр отзыва (drawer) ────────────────────────────────────────────────
const drawer   = ref(false)
const selected = ref(null)

function openDetails(review) {
    selected.value = review
    drawer.value = true
}

// ── Модерация ───────────────────────────────────────────────────────────────
const rejectModal  = ref(false)
const rejectTarget = ref(null)
const rejectReason = ref('')

function approve(review) {
    router.patch(route('reviews.approve', review.id), {}, {
        onSuccess: () => { drawer.value = false },
    })
}
function openReject(review) {
    rejectTarget.value = review
    rejectReason.value = ''
    drawer.value       = false // модалка ниже drawer-а по z-index — закрываем drawer
    rejectModal.value  = true
}
function submitReject() {
    router.patch(route('reviews.reject', rejectTarget.value.id), {
        rejection_reason_id: rejectReason.value,
    }, { onSuccess: () => { rejectModal.value = false } })
}
</script>

<template>
  <AppLayout>
    <template #header>{{ t('nav.reviews') }}</template>

    <!-- Поиск + статус — одна строка одной высоты -->
    <div class="mb-4 flex flex-wrap items-center gap-2.5">
      <SearchInput
        :model-value="searchQuery"
        :placeholder="t('reviews.searchPlaceholder')"
        class="w-full sm:w-[300px]"
        @update:model-value="onSearchInput"
        @submit="applyFilters"
      />
      <div class="seg">
        <button
          v-for="chip in statusChips" :key="chip.value"
          type="button"
          @click="setStatusFilter(chip.value)"
          :aria-pressed="statusFilter === chip.value"
          class="seg-item"
          :class="statusFilter === chip.value ? 'seg-item-active' : ''"
        >{{ chip.label }} ({{ chip.count ?? 0 }})</button>
      </div>
      <span class="ml-auto whitespace-nowrap text-[12.5px] tabular-nums text-[var(--text-muted)]">
        {{ t('dataTable.countOf', { shown: reviews.data.length, total: reviews.total }) }}
      </span>
    </div>

    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[760px]">
          <thead>
            <tr :class="thead">
              <th :class="th" class="w-[72px]">{{ t('common.id') }}</th>
              <th :class="th">{{ t('common.author') }}</th>
              <th :class="th" class="w-[80px]">{{ t('reviews.rating') }}</th>
              <th :class="th">{{ t('reviews.colText') }}</th>
              <th :class="th" class="hidden lg:table-cell">{{ t('reviews.colObject') }}</th>
              <th :class="th">{{ t('common.status') }}</th>
              <th :class="th" class="hidden md:table-cell">{{ t('common.date') }}</th>
              <th :class="th" class="text-right">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in reviews.data" :key="r.id" @click="openDetails(r)" :class="tr" class="cursor-pointer">
              <td :class="td" class="font-data tabular-nums text-[var(--text-muted)]">{{ r.id }}</td>
              <td :class="td">
                <div class="max-w-[180px] truncate font-semibold text-[var(--text)]">{{ r.user?.name || '—' }}</div>
                <div class="font-data text-[12px] text-[var(--text-muted)]">{{ r.user?.phone }}</div>
              </td>
              <!-- Оценка: одна звезда и число — читается быстрее пяти звёзд -->
              <td :class="td">
                <span v-if="r.rating" class="inline-flex items-center gap-1 font-data font-semibold tabular-nums text-[var(--text)]">
                  <Icon kind="star" :size="14" class="text-amber-500" />{{ r.rating }}
                </span>
                <span v-else class="text-[var(--text-muted)]">—</span>
              </td>
              <td :class="td">
                <div class="line-clamp-2 max-w-[340px] font-normal text-[var(--text)]">{{ r.text }}</div>
              </td>
              <td :class="td" class="hidden lg:table-cell">
                <Link
                  v-if="r.listing"
                  :href="route('listings.show', r.listing.id)"
                  @click.stop
                  class="inline-flex max-w-[200px] items-center gap-1.5 text-link hover:underline"
                >
                  <Icon kind="listing" :size="14" class="flex-shrink-0" />
                  <span class="truncate">{{ r.listing.title }}</span>
                </Link>
                <Link
                  v-else-if="r.target_user"
                  :href="route('users.show', r.target_user.id)"
                  @click.stop
                  class="inline-flex max-w-[200px] items-center gap-1.5 text-link hover:underline"
                >
                  <Icon kind="users" :size="14" class="flex-shrink-0" />
                  <span class="truncate">{{ r.target_user.name }}</span>
                </Link>
                <span v-else class="text-[var(--text-muted)]">—</span>
              </td>
              <td :class="td"><StatusBadge :status="r.status" /></td>
              <td :class="td" class="hidden whitespace-nowrap font-data tabular-nums text-[var(--text-secondary)] md:table-cell">{{ formatDate(r.created_at) }}</td>
              <td :class="td">
                <div class="flex items-center justify-end gap-1.5">
                  <button type="button" @click.stop="openDetails(r)" class="icon-btn" :title="t('actions.show')" :aria-label="t('actions.show')"><Icon kind="eye" :size="16" /></button>
                  <template v-if="r.status === 'pending'">
                    <button type="button" @click.stop="approve(r)" class="icon-btn icon-btn-success" :title="t('actions.approve')" :aria-label="t('actions.approve')"><Icon kind="check" :size="16" /></button>
                    <button type="button" @click.stop="openReject(r)" class="icon-btn icon-btn-danger" :title="t('actions.reject')" :aria-label="t('actions.reject')"><Icon kind="close" :size="16" /></button>
                  </template>
                </div>
              </td>
            </tr>
            <tr v-if="!reviews.data?.length">
              <td colspan="8">
                <EmptyState
                  :icon="hasActiveFilters ? 'search' : 'star'"
                  :title="hasActiveFilters ? t('reviews.emptyFiltered') : t('reviews.empty')"
                  :text="hasActiveFilters ? t('common.emptyFiltered') : ''"
                >
                  <button v-if="hasActiveFilters" type="button" class="btn btn-secondary" @click="resetFilters">{{ t('common.resetFilters') }}</button>
                </EmptyState>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="reviews.links" :from="reviews.from" :to="reviews.to" :total="reviews.total" />
    </div>

    <!-- Drawer: полный отзыв -->
    <AppDrawer :open="drawer" :title="t('reviews.drawerTitle')" @close="drawer = false">
      <template v-if="selected">
        <div class="mb-5 flex items-center justify-between">
          <StatusBadge :status="selected.status" />
          <span class="font-data text-[12.5px] text-[var(--text-muted)]">#{{ selected.id }} · {{ formatDate(selected.created_at) }}</span>
        </div>

        <div class="mb-4">
          <div class="field-label">{{ t('common.author') }}</div>
          <div class="text-[13.5px] font-medium text-[var(--text)]">{{ selected.user?.name || '—' }}</div>
          <div class="font-data text-[12px] text-[var(--text-muted)]">{{ selected.user?.phone }}</div>
        </div>

        <div class="mb-4">
          <div class="field-label">{{ t('reviews.colObject') }}</div>
          <Link
            v-if="selected.listing"
            :href="route('listings.show', selected.listing.id)"
            class="inline-flex items-center gap-1.5 text-[13.5px] font-medium text-link hover:underline"
          >
            <Icon kind="listing" :size="15" class="flex-shrink-0" />
            {{ selected.listing.title }}
          </Link>
          <Link
            v-else-if="selected.target_user"
            :href="route('users.show', selected.target_user.id)"
            class="inline-flex items-center gap-1.5 text-[13.5px] font-medium text-link hover:underline"
          >
            <Icon kind="users" :size="15" class="flex-shrink-0" />
            {{ selected.target_user.name }}
          </Link>
          <span v-else class="text-[13.5px] text-[var(--text-muted)]">—</span>
        </div>

        <div v-if="selected.rating" class="mb-4">
          <div class="field-label">{{ t('reviews.rating') }}</div>
          <div class="flex items-center gap-1">
            <Icon
              v-for="star in 5"
              :key="star"
              kind="star"
              :size="18"
              :class="star <= selected.rating ? 'text-amber-500' : 'text-[var(--field-border)]'"
            />
            <span class="ml-1.5 font-data text-[13.5px] font-semibold text-[var(--text)]">{{ selected.rating }}/5</span>
          </div>
        </div>

        <div class="mb-4">
          <div class="field-label">{{ t('reviews.colText') }}</div>
          <p class="whitespace-pre-line rounded-[8px] bg-[var(--field-bg)] px-3.5 py-3 text-[13.5px] leading-relaxed text-[var(--text)]">{{ selected.text }}</p>
        </div>

        <div v-if="selected.status === 'rejected' && selected.rejection_reason" class="mb-4">
          <div class="field-label">{{ t('reviews.reason') }}</div>
          <span class="inline-flex h-6 items-center rounded-full bg-red-500/10 px-2.5 text-[12px] font-semibold text-red-700 dark:text-red-300">{{ nameOf(selected.rejection_reason) }}</span>
        </div>
      </template>

      <template v-if="selected?.status === 'pending'" #footer>
        <div class="flex justify-end gap-2">
          <button type="button" @click="openReject(selected)" class="btn btn-red-soft"><Icon kind="close" :size="16" />{{ t('actions.reject') }}</button>
          <button type="button" @click="approve(selected)" class="btn btn-success"><Icon kind="check" :size="16" />{{ t('actions.approve') }}</button>
        </div>
      </template>
    </AppDrawer>

    <!-- Модалка отклонения -->
    <!-- Модалка отклонения — того же вида, что на объявлениях и роликах -->
    <div v-if="rejectModal" class="fixed inset-0 z-[600] flex items-center justify-center bg-[#0A0C1A]/50 p-4" @click.self="rejectModal = false">
      <div role="dialog" aria-modal="true" class="card w-full max-w-[440px] space-y-4 p-6 shadow-lg2">
        <h3 class="text-[16px] font-semibold text-[var(--text)]">{{ t('reviews.rejectTitle') }}</h3>
        <div>
          <label for="review-reject-reason" class="field-label">{{ t('reviews.reason') }}</label>
          <select id="review-reject-reason" v-model="rejectReason" class="input w-full">
            <option value="">{{ t('reviews.choose') }}</option>
            <option v-for="rr in rejectionReasons" :key="rr.id" :value="rr.id">{{ nameOf(rr) }}</option>
          </select>
        </div>
        <div class="flex justify-end gap-2">
          <button
            @click="rejectModal = false"
            class="btn btn-secondary"
          >{{ t('actions.cancel') }}</button>
          <button
            @click="submitReject"
            :disabled="!rejectReason"
            class="btn btn-danger"
          >{{ t('actions.reject') }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
