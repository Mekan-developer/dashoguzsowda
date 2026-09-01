<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import Icon from '@/Components/Icon.vue'
import Pagination from '@/Components/Pagination.vue'
import SearchInput from '@/Components/SearchInput.vue'
import StatusBadge from '@/Components/StatusBadge.vue'

const { t } = useI18n()

const props = defineProps({
    requests: Object,
    counts: Object,
    filters: Object,
})

const search = ref(props.filters?.search || '')
const statusFilter = ref(props.filters?.status || '')

const chips = computed(() => [
    { value: '',         label: t('common.all') },
    { value: 'pending',  label: t('tariffRequests.tabPending'),  count: props.counts?.pending, tint: 'bg-orange/15 text-orange' },
    { value: 'approved', label: t('tariffRequests.tabApproved'), tint: 'bg-green/15 text-green' },
    { value: 'rejected', label: t('tariffRequests.tabRejected'), tint: 'bg-red/15 text-red' },
])

function applyFilters() {
    router.get(route('tariff-requests.index'), {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function setStatus(value) { statusFilter.value = value; applyFilters() }

// Подтверждение = «деньги получены», поэтому спрашиваем явно
function approve(item) {
    if (!confirm(t('tariffRequests.confirmApprove', { tariff: item.tariff?.name_ru || item.tariff?.name || '' }))) return
    router.patch(route('tariff-requests.approve', item.id), {}, { preserveScroll: true })
}

const rejectTarget = ref(null)
const rejectComment = ref('')
function openReject(item) { rejectTarget.value = item; rejectComment.value = '' }
function doReject() {
    if (!rejectComment.value.trim()) return
    router.patch(route('tariff-requests.reject', rejectTarget.value.id), { comment: rejectComment.value }, {
        preserveScroll: true,
        onSuccess: () => { rejectTarget.value = null },
    })
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString() : '—'
}
</script>

<template>
  <AppLayout>
    <template #header>{{ t('tariffRequests.title') }}</template>

    <div class="mb-4 flex items-center gap-2 rounded-card bg-blue/8 px-4 py-3 text-[12px] font-semibold text-blue">
      <Icon kind="coin" :size="14" class="flex-none" />
      {{ t('tariffRequests.hint') }}
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-3">
      <div class="w-full sm:w-72">
        <SearchInput
          v-model="search"
          :placeholder="t('tariffRequests.searchPlaceholder')"
          @submit="applyFilters"
        />
      </div>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="chip in chips"
          :key="chip.value"
          @click="setStatus(chip.value)"
          class="flex items-center gap-1.5 rounded-[20px] px-3.5 py-1.5 text-[13px] font-bold transition"
          :class="statusFilter === chip.value
            ? 'bg-[var(--accent)] text-white shadow-[0_4px_12px_var(--accent-tint)]'
            : 'bg-white dark:bg-dcard border border-line dark:border-dline text-ink dark:text-slate-200 hover:bg-surface dark:hover:bg-white/5'"
        >
          {{ chip.label }}
          <span
            v-if="chip.count"
            class="rounded-pill px-1.5 py-px text-[11px] font-extrabold"
            :class="statusFilter === chip.value ? 'bg-white/25 text-white' : chip.tint"
          >{{ chip.count }}</span>
        </button>
      </div>
    </div>

    <div class="rounded-card bg-white shadow-soft dark:bg-dcard overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-surface/50 dark:bg-dbg/50">
            <tr>
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline w-16">{{ t('common.id') }}</th>
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('tariffRequests.colApplicant') }}</th>
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('tariffRequests.colTariff') }}</th>
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('tariffRequests.colAmount') }}</th>
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.status') }}</th>
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.date') }}</th>
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('tariffRequests.colProcessedBy') }}</th>
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in requests.data" :key="item.id" class="hover:bg-surface/30 dark:hover:bg-white/3 transition">
              <td class="px-4 py-3 border-b border-line dark:border-dline text-[13px] font-data text-muted">{{ item.id }}</td>
              <td class="px-4 py-3 border-b border-line dark:border-dline">
                <div class="text-[13px] font-bold text-ink dark:text-slate-100">{{ item.user?.name || '—' }}</div>
                <div class="text-[12px] text-muted">{{ item.user?.phone }}</div>
              </td>
              <td class="px-4 py-3 border-b border-line dark:border-dline text-[13px] text-ink dark:text-slate-200">
                {{ item.tariff?.name_ru || item.tariff?.name || '—' }}
              </td>
              <td class="px-4 py-3 border-b border-line dark:border-dline text-[13px] font-bold text-ink dark:text-slate-200">
                {{ item.amount }} {{ t('tariffRequests.amountUnit') }}
              </td>
              <td class="px-4 py-3 border-b border-line dark:border-dline">
                <span :title="item.status === 'rejected' && item.comment ? item.comment : undefined">
                  <StatusBadge :status="item.status" />
                </span>
              </td>
              <td class="px-4 py-3 border-b border-line dark:border-dline text-[12px] text-muted">
                {{ formatDate(item.created_at) }}
              </td>
              <td class="px-4 py-3 border-b border-line dark:border-dline text-[12px] text-muted">
                {{ item.processor?.name || '—' }}
              </td>
              <td class="px-4 py-3 border-b border-line dark:border-dline">
                <div v-if="item.status === 'pending'" class="flex gap-1">
                  <button
                    @click="approve(item)"
                    class="flex h-8 w-8 items-center justify-center rounded-[8px] bg-green/15 text-green transition hover:bg-green/25"
                    :title="t('actions.approve')" :aria-label="t('actions.approve')"
                  ><Icon kind="check" :size="14" /></button>
                  <button
                    @click="openReject(item)"
                    class="flex h-8 w-8 items-center justify-center rounded-[8px] bg-red/15 text-red transition hover:bg-red/25"
                    :title="t('actions.reject')" :aria-label="t('actions.reject')"
                  ><Icon kind="close" :size="14" /></button>
                </div>
                <span v-else class="text-[12px] text-muted">—</span>
              </td>
            </tr>
            <tr v-if="!requests.data.length">
              <td colspan="8" class="px-4 py-10 text-center text-sm text-muted">{{ t('tariffRequests.empty') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="requests.links" />
    </div>

    <div v-if="rejectTarget" class="fixed inset-0 z-[600] flex items-center justify-center bg-black/40 backdrop-blur-sm" @click.self="rejectTarget = null">
      <div class="w-full max-w-md rounded-card bg-white p-6 shadow-soft dark:bg-dcard">
        <h3 class="mb-4 text-[17px] font-extrabold text-ink dark:text-slate-100">{{ t('tariffRequests.rejectTitle') }}</h3>
        <label class="mb-1.5 block text-[12px] font-bold text-muted">{{ t('tariffRequests.rejectCommentLabel') }}</label>
        <textarea
          v-model="rejectComment"
          rows="3"
          class="input mb-5"
          :placeholder="t('tariffRequests.rejectCommentPlaceholder')"
        ></textarea>
        <div class="flex gap-2">
          <button @click="rejectTarget = null" class="flex-1 rounded-btn border-2 border-line py-[11px] text-[13px] font-bold text-muted hover:border-blue hover:text-blue transition dark:border-dline">{{ t('actions.cancel') }}</button>
          <button @click="doReject" :disabled="!rejectComment.trim()" class="flex-1 rounded-btn bg-red py-[11px] text-[13px] font-bold text-white hover:opacity-90 disabled:opacity-40 transition">{{ t('actions.reject') }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
