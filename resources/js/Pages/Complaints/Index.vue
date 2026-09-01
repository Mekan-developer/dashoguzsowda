<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppDrawer from '@/Components/AppDrawer.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Pagination from '@/Components/Pagination.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import SearchInput from '@/Components/SearchInput.vue'
import Icon from '@/Components/Icon.vue'

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
const confirmOpen   = ref(false)
const confirmTarget = ref(null)

function askResolve(complaint) {
    confirmTarget.value = complaint
    confirmOpen.value   = true
}
function confirmResolve() {
    resolve(confirmTarget.value, '')
    confirmOpen.value = false
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

    <div class="space-y-4">
      <!-- Поиск + фильтры -->
      <div class="flex flex-wrap items-center gap-3">
        <div class="w-full sm:w-80">
          <SearchInput
            :model-value="searchQuery"
            :placeholder="t('complaints.searchPlaceholder')"
            @update:model-value="onSearchInput"
            @submit="applyFilters"
          />
        </div>
        <div class="flex gap-2 flex-wrap">
          <button
            v-for="chip in statusChips"
            :key="chip.value"
            @click="setStatusFilter(chip.value)"
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-btn text-sm font-bold transition"
            :class="statusFilter === chip.value
              ? 'bg-blue text-white'
              : 'bg-white dark:bg-dcard border border-line dark:border-dline text-ink dark:text-slate-200 hover:bg-surface dark:hover:bg-white/5'"
          >
            {{ chip.label }}
            <span
              v-if="chip.count"
              class="rounded-full px-1.5 text-[10px]"
              :class="statusFilter === chip.value ? 'bg-white/20' : 'bg-surface dark:bg-white/10 text-muted'"
            >{{ chip.count }}</span>
          </button>
        </div>
        <select
          :value="reasonFilter"
          @change="reasonFilter = $event.target.value; applyFilters()"
          class="input w-auto text-sm"
        >
          <option value="">{{ t('complaints.allReasons') }}</option>
          <option v-for="reason in reasons" :key="reason.id" :value="reason.id">{{ nameOf(reason) }}</option>
        </select>
      </div>

      <!-- Таблица -->
      <div class="rounded-card bg-white dark:bg-dcard border border-line dark:border-dline overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-surface dark:bg-dbg">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-extrabold text-muted uppercase w-16">{{ t('common.id') }}</th>
                <th class="px-4 py-3 text-left text-xs font-extrabold text-muted uppercase">{{ t('users.colUser') }}</th>
                <th class="px-4 py-3 text-left text-xs font-extrabold text-muted uppercase">{{ t('listings.colListing') }}</th>
                <th class="px-4 py-3 text-left text-xs font-extrabold text-muted uppercase">{{ t('complaints.colReason') }}</th>
                <th class="px-4 py-3 text-left text-xs font-extrabold text-muted uppercase">{{ t('common.status') }}</th>
                <th class="px-4 py-3 text-left text-xs font-extrabold text-muted uppercase">{{ t('common.date') }}</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line dark:divide-dline">
              <tr
                v-for="c in complaints.data"
                :key="c.id"
                @click="openDetails(c)"
                class="cursor-pointer hover:bg-surface/50 dark:hover:bg-white/2 transition"
              >
                <td class="px-4 py-3 font-data text-xs text-muted">{{ c.id }}</td>
                <td class="px-4 py-3">
                  <div class="font-semibold text-ink dark:text-slate-100">{{ c.user?.name || '—' }}</div>
                  <div class="text-xs text-muted">{{ c.user?.phone }}</div>
                </td>
                <td class="px-4 py-3">
                  <Link
                    v-if="c.listing"
                    :href="route('listings.show', c.listing.id)"
                    @click.stop
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue hover:underline"
                  >
                    <Icon kind="listing" :size="14" class="flex-shrink-0" />
                    <span class="max-w-[200px] truncate">{{ c.listing.title }}</span>
                  </Link>
                  <span v-else class="text-xs text-muted">—</span>
                </td>
                <td class="px-4 py-3">
                  <span class="inline-flex rounded-pill bg-orange/10 px-2.5 py-0.5 text-[11px] font-bold text-orange">
                    {{ nameOf(c.complaint_reason) || '—' }}
                  </span>
                  <div v-if="c.text" class="mt-1 max-w-[240px] truncate text-xs text-muted">{{ c.text }}</div>
                </td>
                <td class="px-4 py-3"><StatusBadge :status="c.status" /></td>
                <td class="px-4 py-3 text-xs text-muted whitespace-nowrap">{{ formatDate(c.created_at) }}</td>
                <td class="px-4 py-3 text-right">
                  <button
                    v-if="c.status !== 'resolved'"
                    @click.stop="askResolve(c)"
                    class="px-3 py-1 rounded-btn bg-green/10 text-green text-xs font-bold hover:bg-green/20 transition"
                  >{{ t('complaints.resolveBtn') }}</button>
                </td>
              </tr>
              <tr v-if="!complaints.data?.length">
                <td colspan="7" class="px-4 py-10 text-center text-muted text-sm">
                  {{ hasActiveFilters ? t('complaints.emptyFiltered') : t('complaints.empty') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <Pagination :links="complaints.links" />
    </div>

    <!-- Drawer: детали жалобы -->
    <AppDrawer :open="drawer" :title="t('complaints.drawerTitle')" @close="drawer = false">
      <template v-if="selected">
        <div class="mb-4 flex items-center justify-between">
          <StatusBadge :status="selected.status" />
          <span class="text-xs text-muted">{{ formatDate(selected.created_at) }}</span>
        </div>

        <div class="mb-4">
          <div class="mb-1.5 text-[12px] font-bold uppercase tracking-wide text-muted">{{ t('complaints.complainant') }}</div>
          <Link
            v-if="selected.user"
            :href="route('users.show', selected.user.id)"
            class="text-sm font-semibold text-blue hover:underline"
          >{{ selected.user.name || selected.user.phone }}</Link>
          <span v-else class="text-sm text-muted">—</span>
          <div v-if="selected.user?.name" class="text-xs text-muted">{{ selected.user.phone }}</div>
        </div>

        <div class="mb-4">
          <div class="mb-1.5 text-[12px] font-bold uppercase tracking-wide text-muted">{{ t('listings.colListing') }}</div>
          <Link
            v-if="selected.listing"
            :href="route('listings.show', selected.listing.id)"
            class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue hover:underline"
          >
            <Icon kind="listing" :size="15" class="flex-shrink-0" />
            {{ selected.listing.title }}
          </Link>
          <span v-else class="text-sm text-muted">—</span>
        </div>

        <div class="mb-4">
          <div class="mb-1.5 text-[12px] font-bold uppercase tracking-wide text-muted">{{ t('complaints.colReason') }}</div>
          <span class="inline-flex rounded-pill bg-orange/10 px-2.5 py-1 text-xs font-bold text-orange">
            {{ nameOf(selected.complaint_reason) || '—' }}
          </span>
        </div>

        <div class="mb-4">
          <div class="mb-1.5 text-[12px] font-bold uppercase tracking-wide text-muted">{{ t('reviews.colText') }}</div>
          <p
            v-if="selected.text"
            class="whitespace-pre-line rounded-btn bg-surface dark:bg-dbg px-3.5 py-3 text-sm leading-relaxed text-ink dark:text-slate-200"
          >{{ selected.text }}</p>
          <span v-else class="text-sm text-muted">{{ t('complaints.noText') }}</span>
        </div>

        <!-- Решена: кто и как -->
        <template v-if="selected.status === 'resolved'">
          <div v-if="selected.resolver" class="mb-4">
            <div class="mb-1.5 text-[12px] font-bold uppercase tracking-wide text-muted">{{ t('complaints.resolvedBy') }}</div>
            <div class="text-sm font-semibold text-ink dark:text-slate-100">{{ selected.resolver.name }}</div>
          </div>
          <div v-if="selected.resolution_note" class="mb-4">
            <div class="mb-1.5 text-[12px] font-bold uppercase tracking-wide text-muted">{{ t('complaints.resolutionNote') }}</div>
            <p class="whitespace-pre-line rounded-btn bg-green/5 px-3.5 py-3 text-sm leading-relaxed text-ink dark:text-slate-200">{{ selected.resolution_note }}</p>
          </div>
        </template>

        <!-- Новая: заметка + решение -->
        <div v-else class="mb-4">
          <div class="mb-1.5 text-[12px] font-bold uppercase tracking-wide text-muted">{{ t('complaints.resolutionNote') }}</div>
          <textarea
            v-model="resolutionNote"
            :placeholder="t('complaints.resolutionNotePlaceholder')"
            rows="3"
            class="input w-full resize-none"
          ></textarea>
        </div>
      </template>

      <template v-if="selected && selected.status !== 'resolved'" #footer>
        <button
          @click="resolve(selected, resolutionNote)"
          class="w-full rounded-btn bg-green py-[11px] text-[13px] font-bold text-white transition hover:opacity-90"
        >{{ t('complaints.markResolved') }}</button>
      </template>
    </AppDrawer>

    <!-- Подтверждение быстрого решения из строки -->
    <ConfirmModal
      :open="confirmOpen"
      :title="t('complaints.resolveBtn')"
      :message="t('complaints.confirmResolve')"
      :danger="false"
      @confirm="confirmResolve"
      @cancel="confirmOpen = false"
    />
  </AppLayout>
</template>
