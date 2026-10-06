<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppDrawer from '@/Components/AppDrawer.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Pagination from '@/Components/Pagination.vue'
import { confirmDialog } from '@/confirm'
import SearchInput from '@/Components/SearchInput.vue'
import Icon from '@/Components/Icon.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { th, td, tr, thead } from '@/table'

const { t, locale } = useI18n()

const props = defineProps({
    complaints: Object,
    reasons:    Array,
    filters:    Object,
    counts:     Object,
})

const searchQuery  = ref(props.filters?.search ?? '')
const statusFilter = ref(props.filters?.status ?? '')
const reasonFilter = ref(props.filters?.reason_id ?? '')

const totalCount = computed(() => (props.counts?.pending ?? 0) + (props.counts?.resolved ?? 0))

const statusChips = computed(() => [
    { value: '', label: t('common.all'), count: totalCount.value },
    { value: 'new', label: t('complaints.tabPending'), count: props.counts?.pending },
    { value: 'resolved', label: t('complaints.tabResolved'), count: props.counts?.resolved },
])

// Список пагинируется на бэкенде — все фильтры уходят в запрос
let searchDebounce = null
function applyFilters() {
    clearTimeout(searchDebounce)
    router.get(route('complaints.index'), {
        search:    searchQuery.value || undefined,
        status:    statusFilter.value || undefined,
        reason_id: reasonFilter.value || undefined,
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

const hasActiveFilters = computed(() => !!(searchQuery.value || statusFilter.value || reasonFilter.value))
function resetFilters() {
    searchQuery.value = ''; statusFilter.value = ''; reasonFilter.value = ''
    applyFilters()
}

// Справочники двуязычные — показываем имя активного языка
const nameOf = (item) => (locale.value === 'tk' && item?.name_tk) ? item.name_tk : item?.name_ru

const formatDate = (value) => new Date(value).toLocaleDateString('ru', {
    day: '2-digit', month: '2-digit', year: 'numeric',
})

// ── Просмотр жалобы (drawer) ────────────────────────────────────────────────
const drawer         = ref(false)
const selected       = ref(null)
const resolutionNote = ref('')

function openDetails(complaint) {
    selected.value       = complaint
    resolutionNote.value = ''
    drawer.value         = true
}

// ── Решение жалобы ──────────────────────────────────────────────────────────
async function askResolve(complaint) {
    const ok = await confirmDialog(t('complaints.confirmResolve'), {
        title: t('complaints.resolveBtn'),
        danger: false,
    })
    if (ok) resolve(complaint, '')
}
function resolve(complaint, note) {
    router.patch(route('complaints.resolve', complaint.id), {
        resolution_note: note || null,
    }, { onSuccess: () => { drawer.value = false } })
}
</script>

<template>
  <AppLayout>
    <template #header>{{ t('nav.complaints') }}</template>

    <!-- Поиск + статус + причина — одна строка одной высоты -->
    <div class="mb-4 flex flex-wrap items-center gap-2.5">
      <SearchInput
        :model-value="searchQuery"
        :placeholder="t('complaints.searchPlaceholder')"
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
      <select
        :value="reasonFilter"
        @change="reasonFilter = $event.target.value; applyFilters()"
        :aria-label="t('complaints.colReason')"
        class="filter-select"
      >
        <option value="">{{ t('complaints.allReasons') }}</option>
        <option v-for="reason in reasons" :key="reason.id" :value="reason.id">{{ nameOf(reason) }}</option>
      </select>
      <span class="ml-auto whitespace-nowrap text-[12.5px] tabular-nums text-[var(--text-muted)]">
        {{ t('dataTable.countOf', { shown: complaints.data.length, total: complaints.total }) }}
      </span>
    </div>

    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[720px]">
          <thead>
            <tr :class="thead">
              <th :class="th" class="w-[72px]">{{ t('common.id') }}</th>
              <th :class="th">{{ t('complaints.complainant') }}</th>
              <th :class="th">{{ t('listings.colListing') }}</th>
              <th :class="th">{{ t('complaints.colReason') }}</th>
              <th :class="th">{{ t('common.status') }}</th>
              <th :class="th" class="hidden md:table-cell">{{ t('common.date') }}</th>
              <th :class="th" class="text-right">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="c in complaints.data" :key="c.id"
              @click="openDetails(c)"
              :class="tr" class="cursor-pointer"
            >
              <td :class="td" class="font-data tabular-nums text-[var(--text-muted)]">{{ c.id }}</td>
              <td :class="td">
                <div class="max-w-[200px] truncate font-semibold text-[var(--text)]">{{ c.user?.name || '—' }}</div>
                <div class="font-data text-[12px] text-[var(--text-muted)]">{{ c.user?.phone }}</div>
              </td>
              <td :class="td">
                <Link
                  v-if="c.listing"
                  :href="route('listings.show', c.listing.id)"
                  @click.stop
                  class="inline-flex max-w-[240px] items-center gap-1.5 text-link hover:underline"
                >
                  <Icon kind="listing" :size="14" class="flex-shrink-0" />
                  <span class="truncate">{{ c.listing.title }}</span>
                </Link>
                <span v-else class="text-[var(--text-muted)]">—</span>
              </td>
              <td :class="td">
                <div class="max-w-[240px] truncate text-[var(--text)]">{{ nameOf(c.complaint_reason) || '—' }}</div>
                <div v-if="c.text" class="mt-0.5 max-w-[240px] truncate text-[12px] text-[var(--text-muted)]">{{ c.text }}</div>
              </td>
              <td :class="td"><StatusBadge :status="c.status" /></td>
              <td :class="td" class="hidden whitespace-nowrap font-data tabular-nums text-[var(--text-secondary)] md:table-cell">{{ formatDate(c.created_at) }}</td>
              <td :class="td">
                <div class="flex items-center justify-end gap-1.5">
                  <button type="button" @click.stop="openDetails(c)" class="icon-btn" :title="t('actions.show')" :aria-label="t('actions.show')"><Icon kind="eye" :size="16" /></button>
                  <button
                    v-if="c.status !== 'resolved'"
                    type="button"
                    @click.stop="askResolve(c)"
                    class="icon-btn icon-btn-success"
                    :title="t('complaints.resolveBtn')" :aria-label="t('complaints.resolveBtn')"
                  ><Icon kind="check" :size="16" /></button>
                </div>
              </td>
            </tr>
            <tr v-if="!complaints.data?.length">
              <td colspan="7">
                <EmptyState
                  :icon="hasActiveFilters ? 'search' : 'flag'"
                  :title="hasActiveFilters ? t('complaints.emptyFiltered') : t('complaints.empty')"
                  :text="hasActiveFilters ? t('common.emptyFiltered') : ''"
                >
                  <button v-if="hasActiveFilters" type="button" class="btn btn-secondary" @click="resetFilters">{{ t('common.resetFilters') }}</button>
                </EmptyState>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="complaints.links" :from="complaints.from" :to="complaints.to" :total="complaints.total" />
    </div>

    <!-- Drawer: детали жалобы -->
    <AppDrawer :open="drawer" :title="t('complaints.drawerTitle')" @close="drawer = false">
      <template v-if="selected">
        <div class="mb-5 flex items-center justify-between">
          <StatusBadge :status="selected.status" />
          <span class="font-data text-[12.5px] text-[var(--text-muted)]">#{{ selected.id }} · {{ formatDate(selected.created_at) }}</span>
        </div>

        <div class="mb-4">
          <div class="field-label">{{ t('complaints.complainant') }}</div>
          <Link
            v-if="selected.user"
            :href="route('users.show', selected.user.id)"
            class="text-[13.5px] font-medium text-link hover:underline"
          >{{ selected.user.name || selected.user.phone }}</Link>
          <span v-else class="text-[13.5px] text-[var(--text-muted)]">—</span>
          <div v-if="selected.user?.name" class="font-data text-[12px] text-[var(--text-muted)]">{{ selected.user.phone }}</div>
        </div>

        <div class="mb-4">
          <div class="field-label">{{ t('listings.colListing') }}</div>
          <Link
            v-if="selected.listing"
            :href="route('listings.show', selected.listing.id)"
            class="inline-flex items-center gap-1.5 text-[13.5px] font-medium text-link hover:underline"
          >
            <Icon kind="listing" :size="15" class="flex-shrink-0" />
            {{ selected.listing.title }}
          </Link>
          <span v-else class="text-[13.5px] text-[var(--text-muted)]">—</span>
        </div>

        <div class="mb-4">
          <div class="field-label">{{ t('complaints.colReason') }}</div>
          <span class="inline-flex h-6 items-center rounded-full bg-amber-500/10 px-2.5 text-[12px] font-semibold text-amber-700 dark:text-amber-300">
            {{ nameOf(selected.complaint_reason) || '—' }}
          </span>
        </div>

        <div class="mb-4">
          <div class="field-label">{{ t('reviews.colText') }}</div>
          <p
            v-if="selected.text"
            class="whitespace-pre-line rounded-[8px] bg-[var(--field-bg)] px-3.5 py-3 text-[13.5px] leading-relaxed text-[var(--text)]"
          >{{ selected.text }}</p>
          <span v-else class="text-[13.5px] text-[var(--text-muted)]">{{ t('complaints.noText') }}</span>
        </div>

        <!-- Решена: кто и как -->
        <template v-if="selected.status === 'resolved'">
          <div v-if="selected.resolver" class="mb-4">
            <div class="field-label">{{ t('complaints.resolvedBy') }}</div>
            <div class="text-[13.5px] font-medium text-[var(--text)]">{{ selected.resolver.name }}</div>
          </div>
          <div v-if="selected.resolution_note" class="mb-4">
            <div class="field-label">{{ t('complaints.resolutionNote') }}</div>
            <p class="whitespace-pre-line rounded-[8px] bg-emerald-500/10 px-3.5 py-3 text-[13.5px] leading-relaxed text-[var(--text)]">{{ selected.resolution_note }}</p>
          </div>
        </template>

        <!-- Новая: заметка + решение -->
        <div v-else class="mb-4">
          <div class="field-label">{{ t('complaints.resolutionNote') }}</div>
          <textarea
            v-model="resolutionNote"
            :placeholder="t('complaints.resolutionNotePlaceholder')"
            rows="3"
            class="input w-full resize-none"
          ></textarea>
        </div>
      </template>

      <!-- Главное действие модерации — справа, основной кнопкой -->
      <template v-if="selected && selected.status !== 'resolved'" #footer>
        <div class="flex justify-end gap-2">
          <button type="button" @click="drawer = false" class="btn btn-secondary">{{ t('actions.cancel') }}</button>
          <button type="button" @click="resolve(selected, resolutionNote)" class="btn btn-success">
            <Icon kind="check" :size="16" />{{ t('complaints.markResolved') }}
          </button>
        </div>
      </template>
    </AppDrawer>

  </AppLayout>
</template>
