<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppDrawer from '@/Components/AppDrawer.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Pagination from '@/Components/Pagination.vue'
import Modal from '@/Components/Modal.vue'
import SearchInput from '@/Components/SearchInput.vue'
import Icon from '@/Components/Icon.vue'

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

    <div class="space-y-4">
      <!-- Поиск + фильтр по статусу -->
      <div class="flex flex-wrap items-center gap-3">
        <div class="w-full sm:w-80">
          <SearchInput
            :model-value="searchQuery"
            :placeholder="t('reviews.searchPlaceholder')"
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
      </div>

      <div class="rounded-card bg-white dark:bg-dcard border border-line dark:border-dline overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-surface dark:bg-dbg">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-extrabold text-muted uppercase w-16">{{ t('common.id') }}</th>
                <th class="px-4 py-3 text-left text-xs font-extrabold text-muted uppercase">{{ t('common.author') }}</th>
                <th class="px-4 py-3 text-left text-xs font-extrabold text-muted uppercase">{{ t('reviews.colText') }}</th>
                <th class="px-4 py-3 text-left text-xs font-extrabold text-muted uppercase">{{ t('reviews.colObject') }}</th>
                <th class="px-4 py-3 text-left text-xs font-extrabold text-muted uppercase">{{ t('common.status') }}</th>
                <th class="px-4 py-3 text-left text-xs font-extrabold text-muted uppercase">{{ t('common.date') }}</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line dark:divide-dline">
              <tr
                v-for="r in reviews.data"
                :key="r.id"
                @click="openDetails(r)"
                class="cursor-pointer hover:bg-surface/50 dark:hover:bg-white/2 transition"
              >
                <td class="px-4 py-3 font-data text-xs text-muted">{{ r.id }}</td>
                <td class="px-4 py-3">
                  <div class="font-semibold text-ink dark:text-slate-100">{{ r.user?.name || '—' }}</div>
                  <div class="text-xs text-muted">{{ r.user?.phone }}</div>
                </td>
                <td class="px-4 py-3">
                  <div v-if="r.rating" class="mb-0.5 flex items-center gap-0.5">
                    <Icon
                      v-for="star in 5"
                      :key="star"
                      kind="star"
                      :size="13"
                      :class="star <= r.rating ? 'text-orange' : 'text-line dark:text-dline'"
                    />
                  </div>
                  <div class="max-w-[300px] line-clamp-2 text-ink dark:text-slate-200">{{ r.text }}</div>
                </td>
                <td class="px-4 py-3">
                  <Link
                    v-if="r.listing"
                    :href="route('listings.show', r.listing.id)"
                    @click.stop
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue hover:underline"
                  >
                    <Icon kind="listing" :size="14" class="flex-shrink-0" />
                    <span class="max-w-[180px] truncate">{{ r.listing.title }}</span>
                  </Link>
                  <Link
                    v-else-if="r.target_user"
                    :href="route('users.show', r.target_user.id)"
                    @click.stop
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue hover:underline"
                  >
                    <Icon kind="users" :size="14" class="flex-shrink-0" />
                    <span class="max-w-[180px] truncate">{{ r.target_user.name }}</span>
                  </Link>
                  <span v-else class="text-xs text-muted">—</span>
                </td>
                <td class="px-4 py-3"><StatusBadge :status="r.status" /></td>
                <td class="px-4 py-3 text-xs text-muted whitespace-nowrap">{{ formatDate(r.created_at) }}</td>
                <td class="px-4 py-3">
                  <div v-if="r.status === 'pending'" class="flex gap-1.5 justify-end">
                    <button
                      @click.stop="approve(r)"
                      class="px-2.5 py-1 rounded-btn bg-green/10 text-green text-xs font-bold hover:bg-green/20 transition"
                    >{{ t('actions.approve') }}</button>
                    <button
                      @click.stop="openReject(r)"
                      class="px-2.5 py-1 rounded-btn bg-red/10 text-red text-xs font-bold hover:bg-red/20 transition"
                    >{{ t('actions.reject') }}</button>
                  </div>
                </td>
              </tr>
              <tr v-if="!reviews.data?.length">
                <td colspan="7" class="px-4 py-10 text-center text-muted text-sm">
                  {{ hasActiveFilters ? t('reviews.emptyFiltered') : t('reviews.empty') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <Pagination :links="reviews.links" />
    </div>

    <!-- Drawer: полный отзыв -->
    <AppDrawer :open="drawer" :title="t('reviews.drawerTitle')" @close="drawer = false">
      <template v-if="selected">
        <div class="mb-4 flex items-center justify-between">
          <StatusBadge :status="selected.status" />
          <span class="text-xs text-muted">{{ formatDate(selected.created_at) }}</span>
        </div>

        <div class="mb-4">
          <div class="mb-1.5 text-[12px] font-bold uppercase tracking-wide text-muted">{{ t('common.author') }}</div>
          <div class="font-semibold text-ink dark:text-slate-100">{{ selected.user?.name || '—' }}</div>
          <div class="text-xs text-muted">{{ selected.user?.phone }}</div>
        </div>

        <div class="mb-4">
          <div class="mb-1.5 text-[12px] font-bold uppercase tracking-wide text-muted">{{ t('reviews.colObject') }}</div>
          <Link
            v-if="selected.listing"
            :href="route('listings.show', selected.listing.id)"
            class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue hover:underline"
          >
            <Icon kind="listing" :size="15" class="flex-shrink-0" />
            {{ selected.listing.title }}
          </Link>
          <Link
            v-else-if="selected.target_user"
            :href="route('users.show', selected.target_user.id)"
            class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue hover:underline"
          >
            <Icon kind="users" :size="15" class="flex-shrink-0" />
            {{ selected.target_user.name }}
          </Link>
          <span v-else class="text-sm text-muted">—</span>
        </div>

        <div v-if="selected.rating" class="mb-4">
          <div class="mb-1.5 text-[12px] font-bold uppercase tracking-wide text-muted">{{ t('reviews.rating') }}</div>
          <div class="flex items-center gap-1">
            <Icon
              v-for="star in 5"
              :key="star"
              kind="star"
              :size="18"
              :class="star <= selected.rating ? 'text-orange' : 'text-line dark:text-dline'"
            />
            <span class="ml-1.5 text-sm font-bold text-ink dark:text-slate-100">{{ selected.rating }}/5</span>
          </div>
        </div>

        <div class="mb-4">
          <div class="mb-1.5 text-[12px] font-bold uppercase tracking-wide text-muted">{{ t('reviews.colText') }}</div>
          <p class="whitespace-pre-line rounded-btn bg-surface dark:bg-dbg px-3.5 py-3 text-sm leading-relaxed text-ink dark:text-slate-200">{{ selected.text }}</p>
        </div>

        <div v-if="selected.status === 'rejected' && selected.rejection_reason" class="mb-4">
          <div class="mb-1.5 text-[12px] font-bold uppercase tracking-wide text-muted">{{ t('reviews.reason') }}</div>
          <span class="inline-flex rounded-pill bg-red/10 px-2.5 py-1 text-xs font-bold text-red">{{ nameOf(selected.rejection_reason) }}</span>
        </div>
      </template>

      <template v-if="selected?.status === 'pending'" #footer>
        <div class="flex gap-2.5">
          <button
            @click="approve(selected)"
            class="flex-1 rounded-btn bg-green py-[11px] text-[13px] font-bold text-white transition hover:opacity-90"
          >{{ t('actions.approve') }}</button>
          <button
            @click="openReject(selected)"
            class="flex-1 rounded-btn bg-red py-[11px] text-[13px] font-bold text-white transition hover:opacity-90"
          >{{ t('actions.reject') }}</button>
        </div>
      </template>
    </AppDrawer>

    <!-- Модалка отклонения -->
    <Modal :show="rejectModal" @close="rejectModal = false">
      <div class="p-6 space-y-4">
        <h3 class="text-base font-extrabold text-ink dark:text-slate-100">{{ t('reviews.rejectTitle') }}</h3>
        <div>
          <label class="block text-sm font-semibold text-ink dark:text-slate-200 mb-1">{{ t('reviews.reason') }}</label>
          <select v-model="rejectReason" class="input w-full">
            <option value="">{{ t('reviews.choose') }}</option>
            <option v-for="rr in rejectionReasons" :key="rr.id" :value="rr.id">{{ nameOf(rr) }}</option>
          </select>
        </div>
        <div class="flex gap-3 justify-end">
          <button
            @click="rejectModal = false"
            class="px-4 py-2 rounded-btn border border-line dark:border-dline text-sm font-bold text-ink dark:text-slate-200"
          >{{ t('actions.cancel') }}</button>
          <button
            @click="submitReject"
            :disabled="!rejectReason"
            class="px-4 py-2 rounded-btn bg-red text-white text-sm font-bold hover:bg-red/90 disabled:opacity-50 transition"
          >{{ t('actions.reject') }}</button>
        </div>
      </div>
    </Modal>
  </AppLayout>
</template>
