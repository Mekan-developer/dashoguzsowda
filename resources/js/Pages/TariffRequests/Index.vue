<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import Icon from '@/Components/Icon.vue'
import Pagination from '@/Components/Pagination.vue'
import SearchInput from '@/Components/SearchInput.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmDialog } from '@/confirm'
import { th, td, tr, thead } from '@/table'

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
    { value: 'pending',  label: t('tariffRequests.tabPending'),  count: props.counts?.pending },
    { value: 'approved', label: t('tariffRequests.tabApproved') },
    { value: 'rejected', label: t('tariffRequests.tabRejected') },
])

function applyFilters() {
    router.get(route('tariff-requests.index'), {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function setStatus(value) { statusFilter.value = value; applyFilters() }

// Подтверждение = «деньги получены», поэтому спрашиваем явно
async function approve(item) {
    if (!(await confirmDialog(t('tariffRequests.confirmApprove', { tariff: item.tariff?.name_ru || item.tariff?.name || '' }), { danger: false, title: t('actions.approve') }))) return
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
    return value ? new Date(value).toLocaleDateString('ru', { day: '2-digit', month: '2-digit', year: '2-digit' }) : '—'
}
</script>

<template>
  <AppLayout>
    <template #header>{{ t('tariffRequests.title') }}</template>
    <template #description>{{ t('tariffRequests.hint') }}</template>

    <div class="mb-4 flex flex-wrap items-center gap-2.5">
      <SearchInput
        v-model="search"
        :placeholder="t('tariffRequests.searchPlaceholder')"
        class="w-full sm:w-[280px]"
        @submit="applyFilters"
      />
      <div class="seg">
        <button
          v-for="chip in chips" :key="chip.value"
          type="button"
          @click="setStatus(chip.value)"
          :aria-pressed="statusFilter === chip.value"
          class="seg-item"
          :class="statusFilter === chip.value ? 'seg-item-active' : ''"
        >{{ chip.label }}<template v-if="chip.count"> ({{ chip.count }})</template></button>
      </div>
      <span class="ml-auto whitespace-nowrap text-[12.5px] tabular-nums text-[var(--text-muted)]">
        {{ t('dataTable.countOf', { shown: requests.data.length, total: requests.total }) }}
      </span>
    </div>

    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[760px]">
          <thead>
            <tr :class="thead">
              <th :class="th" class="w-[72px]">{{ t('common.id') }}</th>
              <th :class="th">{{ t('tariffRequests.colApplicant') }}</th>
              <th :class="th">{{ t('tariffRequests.colTariff') }}</th>
              <th :class="th" class="text-right">{{ t('tariffRequests.colAmount') }}</th>
              <th :class="th">{{ t('common.status') }}</th>
              <th :class="th" class="hidden md:table-cell">{{ t('common.date') }}</th>
              <th :class="th" class="hidden lg:table-cell">{{ t('tariffRequests.colProcessedBy') }}</th>
              <th :class="th" class="text-right">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in requests.data" :key="item.id" :class="tr" class="relative">
              <!-- Ждущая заявка заметна сразу: тонкая янтарная полоса слева -->
              <td :class="td" class="relative font-data tabular-nums text-[var(--text-muted)]">
                <span v-if="item.status === 'pending'" class="absolute inset-y-2 left-0 w-[3px] rounded-full bg-amber-500"></span>
                {{ item.id }}
              </td>
              <td :class="td">
                <div class="max-w-[200px] truncate font-semibold text-[var(--text)]">{{ item.user?.name || '—' }}</div>
                <div class="font-data text-[12px] text-[var(--text-muted)]">{{ item.user?.phone }}</div>
              </td>
              <td :class="td" class="text-[var(--text)]">{{ item.tariff?.name_ru || item.tariff?.name || '—' }}</td>
              <td :class="td" class="whitespace-nowrap text-right font-data font-semibold tabular-nums text-[var(--text)]">
                {{ Number(item.amount).toLocaleString('ru-RU') }} {{ t('tariffRequests.amountUnit') }}
              </td>
              <td :class="td">
                <span :title="item.status === 'rejected' && item.comment ? item.comment : undefined">
                  <StatusBadge :status="item.status" />
                </span>
              </td>
              <td :class="td" class="hidden whitespace-nowrap font-data tabular-nums text-[var(--text-secondary)] md:table-cell">{{ formatDate(item.created_at) }}</td>
              <td :class="td" class="hidden text-[var(--text-secondary)] lg:table-cell">{{ item.processor?.name || '—' }}</td>
              <td :class="td">
                <div v-if="item.status === 'pending'" class="flex items-center justify-end gap-1.5">
                  <button type="button" @click="approve(item)" class="btn btn-sm btn-green-soft" :title="t('actions.approve')">
                    <Icon kind="check" :size="15" />{{ t('actions.approve') }}
                  </button>
                  <button type="button" @click="openReject(item)" class="icon-btn icon-btn-danger" :title="t('actions.reject')" :aria-label="t('actions.reject')">
                    <Icon kind="close" :size="16" />
                  </button>
                </div>
                <div v-else class="text-right text-[var(--text-muted)]">—</div>
              </td>
            </tr>
            <tr v-if="!requests.data.length">
              <td colspan="8">
                <EmptyState
                  :icon="search || statusFilter ? 'search' : 'receipt'"
                  :title="t('tariffRequests.empty')"
                  :text="search || statusFilter ? t('common.emptyFiltered') : ''"
                >
                  <button v-if="search || statusFilter" type="button" class="btn btn-secondary" @click="search = ''; setStatus('')">{{ t('common.resetFilters') }}</button>
                </EmptyState>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="requests.links" :from="requests.from" :to="requests.to" :total="requests.total" />
    </div>

    <!-- Отказ: комментарий обязателен — он уходит пользователю -->
    <div v-if="rejectTarget" class="fixed inset-0 z-[600] flex items-center justify-center bg-[#0A0C1A]/50 p-4" @click.self="rejectTarget = null">
      <div role="dialog" aria-modal="true" class="card w-full max-w-[440px] p-6 shadow-lg2">
        <h3 class="mb-1 text-[16px] font-semibold text-[var(--text)]">{{ t('tariffRequests.rejectTitle') }}</h3>
        <p class="mb-4 text-[13px] text-[var(--text-muted)]">{{ rejectTarget.user?.name || rejectTarget.user?.phone }} · {{ rejectTarget.tariff?.name_ru || rejectTarget.tariff?.name }}</p>
        <label for="tariff-reject-comment" class="field-label">{{ t('tariffRequests.rejectCommentLabel') }}</label>
        <textarea
          id="tariff-reject-comment"
          v-model="rejectComment"
          rows="3"
          class="input mb-5 resize-none"
          :placeholder="t('tariffRequests.rejectCommentPlaceholder')"
        ></textarea>
        <div class="flex justify-end gap-2">
          <button type="button" @click="rejectTarget = null" class="btn btn-secondary">{{ t('actions.cancel') }}</button>
          <button type="button" @click="doReject" :disabled="!rejectComment.trim()" class="btn btn-danger">{{ t('actions.reject') }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
