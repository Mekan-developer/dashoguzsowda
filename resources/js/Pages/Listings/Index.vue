<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import CreateButton from '@/Components/CreateButton.vue'
import Pagination from '@/Components/Pagination.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import SearchInput from '@/Components/SearchInput.vue'

const { t } = useI18n()

const props = defineProps({
    listings: Object, categories: Array, rejectionReasons: Array, filters: Object, counts: Object,
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
      <CreateButton :label="t('actions.create')" @click="router.visit(route('listings.create'))" />
    </template>

    <!-- Панель: поиск + сегментированный фильтр статусов + категория + счётчик -->
    <div class="mb-4 flex flex-wrap items-center gap-3">
      <SearchInput v-model="search" @submit="applyFilters" :placeholder="t('listings.searchPlaceholder')" class="min-w-[220px] flex-1 sm:max-w-[300px]" />

      <!-- Статус — сегментированный контрол -->
      <div class="inline-flex gap-1 rounded-[11px] border border-[var(--field-border)] bg-[var(--field-bg)] p-1">
        <button v-for="tab in tabs" :key="tab.value"
          type="button"
          @click="setStatus(tab.value)"
          class="rounded-[8px] px-[15px] py-[7px] text-[12.5px] font-semibold transition-colors"
          :class="statusFil === tab.value
            ? 'bg-[var(--accent)] text-white shadow-[0_4px_10px_-3px_var(--accent)]'
            : 'text-[var(--text-secondary)] hover:bg-[var(--nav-hover)]'"
        >{{ tab.label }}</button>
      </div>

      <!-- Категория -->
      <select v-model="catId" @change="applyFilters" class="rounded-[9px] border border-[var(--field-border)] bg-[var(--field-bg)] py-[9px] px-[13px] text-[13px] font-semibold text-[var(--text)] outline-none transition-colors focus:border-[var(--accent)]">
        <option value="">{{ t('listings.allCategories') }}</option>
        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name_ru }}</option>
      </select>

      <!-- Показано из всего -->
      <span class="ml-auto text-[12.5px] font-semibold text-[var(--text-muted)]">
        {{ t('dataTable.countOf', { shown: listings.data.length, total: listings.total }) }}
      </span>
    </div>

    <!-- Table -->
    <div class="rounded-card bg-white shadow-soft dark:bg-dcard overflow-hidden">
      <table class="w-full">
        <thead class="bg-surface/50 dark:bg-dbg/50">
          <tr>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.id') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('listings.colListing') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.author') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.category') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.price') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.views') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.status') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.date') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="listing in listings.data" :key="listing.id" @dblclick="router.visit(route('listings.show', listing.id))" class="hover:bg-surface/30 dark:hover:bg-white/3 transition cursor-pointer">
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline font-data text-muted">{{ listing.id }}</td>
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline">
              <div class="flex items-center gap-3">
                <div v-if="listing.media?.[0]" class="h-10 w-10 rounded-[7px] overflow-hidden flex-shrink-0 bg-surface">
                  <img :src="`/storage/${listing.media[0].path}`" class="h-full w-full object-cover" :alt="listing.title" />
                </div>
                <div v-else class="h-10 w-10 rounded-[7px] bg-surface dark:bg-dbg flex items-center justify-center flex-shrink-0">
                  <svg class="h-4 w-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" stroke-width="2"/><circle cx="8.5" cy="8.5" r="1.5" stroke-width="2"/><polyline stroke-width="2" points="21 15 16 10 5 21"/></svg>
                </div>
                <Link :href="route('listings.show', listing.id)" class="font-bold text-ink dark:text-slate-200 hover:text-blue line-clamp-1">{{ listing.title }}</Link>
              </div>
            </td>
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline text-muted">{{ listing.user?.name || listing.user?.phone || '—' }}</td>
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline text-muted">{{ categoryPath(listing.category) }}</td>
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline font-data font-bold text-ink dark:text-slate-200">{{ formatPrice(listing.price) }}</td>
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline font-data text-muted">{{ listing.views || 0 }}</td>
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline"><StatusBadge :status="listing.status" /></td>
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline font-data text-muted">{{ formatDate(listing.created_at) }}</td>
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline">
              <div class="flex items-center gap-1.5">
                <Link :href="route('listings.show', listing.id)" class="flex h-[30px] w-[30px] items-center justify-center rounded-[7px] bg-blue-light text-blue transition hover:bg-blue hover:text-white">
                  <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" stroke-linecap="round" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3" stroke-width="2"/></svg>
                </Link>
                <button v-if="listing.status === 'pending'" @click="approve(listing.id)" class="flex h-[30px] w-[30px] items-center justify-center rounded-[7px] bg-green/10 text-green transition hover:bg-green hover:text-white" :title="t('actions.approve')">
                  <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline stroke-width="2" stroke-linecap="round" points="20 6 9 17 4 12"/></svg>
                </button>
                <button v-if="listing.status !== 'rejected'" @click="openReject(listing)" class="flex h-[30px] w-[30px] items-center justify-center rounded-[7px] bg-red/10 text-red transition hover:bg-red hover:text-white" :title="t('actions.reject')">
                  <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18" stroke-width="2" stroke-linecap="round"/><line x1="6" y1="6" x2="18" y2="18" stroke-width="2" stroke-linecap="round"/></svg>
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!listings.data?.length">
            <td colspan="9" class="px-4 py-10 text-center text-[13px] text-muted">{{ t('listings.notFound') }}</td>
          </tr>
        </tbody>
      </table>
      <Pagination :links="listings.links" />
    </div>

    <!-- Reject modal -->
    <div v-if="rejectTarget" class="fixed inset-0 z-[600] flex items-center justify-center bg-black/40 backdrop-blur-sm" @click.self="rejectTarget = null">
      <div class="w-[440px] rounded-card bg-white p-6 shadow-[0_24px_48px_rgba(0,0,0,.18)] dark:bg-dcard">
        <h3 class="mb-1 text-[17px] font-extrabold text-ink dark:text-slate-100">{{ t('listings.rejectTitle') }}</h3>
        <p class="mb-4 text-[12px] text-muted">{{ rejectTarget?.title }}</p>
        <div class="space-y-1 mb-5">
          <label v-for="r in rejectionReasons" :key="r.id" class="flex items-center gap-3 cursor-pointer rounded-btn p-3 hover:bg-surface dark:hover:bg-white/5 transition">
            <input type="radio" :value="r.id" v-model="rejectReason" class="accent-blue" />
            <span class="text-[13px] font-semibold text-ink dark:text-slate-200">{{ r.name_ru }}</span>
          </label>
        </div>
        <div class="flex gap-2.5">
          <button @click="rejectTarget = null" class="flex-1 rounded-btn border-2 border-line py-[11px] text-[13px] font-bold text-muted hover:border-blue hover:text-blue transition dark:border-dline">{{ t('actions.cancel') }}</button>
          <button @click="doReject" :disabled="!rejectReason" class="flex-1 rounded-btn bg-red py-[11px] text-[13px] font-bold text-white hover:opacity-90 transition disabled:opacity-40">{{ t('actions.reject') }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
