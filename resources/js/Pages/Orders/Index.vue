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
    orders: Object,
    stores: Array,
    counts: Object,
    filters: Object,
})

const search = ref(props.filters?.search || '')
const statusFilter = ref(props.filters?.status || '')
const storeFilter = ref(props.filters?.store_id ? String(props.filters.store_id) : '')

const chips = computed(() => [
    { value: '',           label: t('common.all') },
    { value: 'pending',    label: t('orders.tabPending'),    count: props.counts?.pending, tint: 'bg-orange/15 text-orange' },
    { value: 'approved',   label: t('orders.tabApproved'),   tint: 'bg-green/15 text-green' },
    { value: 'completed',  label: t('orders.tabCompleted'),  tint: 'bg-green/15 text-green' },
    { value: 'rejected',   label: t('orders.tabRejected'),   tint: 'bg-red/15 text-red' },
    { value: 'canceled',   label: t('orders.tabCanceled'),   tint: 'bg-surface text-muted' },
])

function applyFilters() {
    router.get(route('orders.index'), {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        store_id: storeFilter.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
function setStatus(value) { statusFilter.value = value; applyFilters() }
// Клик по магазину в строке — самый частый вопрос админа: «что заказано у них»
function setStore(value) { storeFilter.value = value ? String(value) : ''; applyFilters() }

// ── Раскрытие состава ──────────────────────────────────────────────────────
const expanded = ref(new Set())
function toggle(id) {
    const next = new Set(expanded.value)
    next.has(id) ? next.delete(id) : next.add(id)
    expanded.value = next
}

// ── Действия ───────────────────────────────────────────────────────────────

// Подтверждать положено после ответа магазинов — если кто-то ещё молчит или
// отказался, предупреждаем об этом отдельно, но решение оставляем админу
function approve(order) {
    const waiting = awaitingCount(order)
    const declined = (order.suborders || []).filter(s => s.status === 'declined').length

    const question = waiting
        ? t('orders.confirmApproveWaiting', { number: order.number, count: waiting })
        : declined
            ? t('orders.confirmApproveDeclined', { number: order.number, count: declined })
            : t('orders.confirmApprove', { number: order.number })

    if (!confirm(question)) return
    router.patch(route('orders.approve', order.id), {}, { preserveScroll: true })
}

function cancel(order) {
    if (!confirm(t('orders.confirmCancel', { number: order.number }))) return
    router.patch(route('orders.cancel', order.id), {}, { preserveScroll: true })
}

function complete(order) {
    if (!confirm(t('orders.confirmCompleted', { number: order.number }))) return
    router.patch(route('orders.complete', order.id), {}, { preserveScroll: true })
}

const rejectTarget = ref(null)
const rejectComment = ref('')
function openReject(order) { rejectTarget.value = order; rejectComment.value = '' }
function doReject() {
    if (!rejectComment.value.trim()) return
    router.patch(route('orders.reject', rejectTarget.value.id), { comment: rejectComment.value }, {
        preserveScroll: true,
        onSuccess: () => { rejectTarget.value = null },
    })
}

// ── Вспомогательное ────────────────────────────────────────────────────────

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString() : '—'
}

function money(value) {
    return Number(value ?? 0).toLocaleString()
}

// Сколько магазинов ещё не ответило — по ним админ и звонит
function awaitingCount(order) {
    return (order.suborders || []).filter(s => s.status === 'pending').length
}

function deliveryLine(order) {
    return [order.city?.name_ru, order.district?.name_ru, order.address].filter(Boolean).join(', ')
}
</script>

<template>
  <AppLayout>
    <template #header>{{ t('orders.title') }}</template>

    <div class="mb-4 flex items-center gap-2 rounded-card bg-blue/8 px-4 py-3 text-[12px] font-semibold text-blue">
      <Icon kind="cart" :size="14" class="flex-none" />
      {{ t('orders.hint') }}
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-3">
      <div class="w-full sm:w-72">
        <SearchInput
          v-model="search"
          :placeholder="t('orders.searchPlaceholder')"
          @submit="applyFilters"
        />
      </div>

      <select
        v-model="storeFilter"
        @change="applyFilters"
        class="input h-[38px] w-full py-0 sm:w-56"
      >
        <option value="">{{ t('orders.allStores') }}</option>
        <option v-for="store in stores" :key="store.id" :value="String(store.id)">{{ store.name }}</option>
      </select>

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
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('orders.colOrder') }}</th>
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('orders.colBuyer') }}</th>
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('orders.colStores') }}</th>
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('orders.colDelivery') }}</th>
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('orders.colTotal') }}</th>
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.status') }}</th>
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="order in orders.data" :key="order.id">
              <tr class="hover:bg-surface/30 dark:hover:bg-white/3 transition">
                <td class="px-4 py-3 border-b border-line dark:border-dline align-top">
                  <button
                    @click="toggle(order.id)"
                    class="flex items-center gap-1.5 text-[13px] font-data font-bold text-ink dark:text-slate-100 hover:text-blue transition"
                    :title="expanded.has(order.id) ? t('orders.collapse') : t('orders.expand')"
                  >
                    <Icon :kind="expanded.has(order.id) ? 'arrowUp' : 'arrowDown'" :size="12" class="flex-none text-muted" />
                    №{{ order.number }}
                  </button>
                  <div class="mt-0.5 text-[12px] text-muted">{{ formatDate(order.created_at) }}</div>
                  <div class="text-[11px] text-muted">{{ t('orders.itemsCount', { count: order.items_count }) }}</div>
                </td>

                <td class="px-4 py-3 border-b border-line dark:border-dline align-top">
                  <div class="text-[13px] font-bold text-ink dark:text-slate-100">{{ order.contact_name || order.user?.name || '—' }}</div>
                  <div class="text-[12px] text-muted">{{ order.phone }}</div>
                </td>

                <!-- Главное для админа: чей товар в заказе и что магазин ответил -->
                <td class="px-4 py-3 border-b border-line dark:border-dline align-top">
                  <div class="flex flex-col gap-1">
                    <button
                      v-for="part in order.suborders"
                      :key="part.id"
                      @click="setStore(part.store?.id)"
                      class="flex items-center gap-2 text-left transition hover:opacity-80"
                      :title="t('orders.colStores')"
                    >
                      <span class="text-[13px] font-semibold text-ink dark:text-slate-200">{{ part.store?.name || '—' }}</span>
                      <StatusBadge :status="part.status" />
                    </button>
                    <div v-if="!order.suborders?.length" class="text-[12px] text-muted">—</div>
                    <!-- Магазины отвечают до подтверждения: пока кто-то молчит,
                         админу есть кому звонить -->
                    <div
                      v-else-if="order.status === 'pending' && awaitingCount(order)"
                      class="text-[11px] font-semibold text-orange"
                    >{{ t('orders.awaitingStores', { count: awaitingCount(order) }) }}</div>
                  </div>
                </td>

                <td class="px-4 py-3 border-b border-line dark:border-dline align-top">
                  <div class="max-w-[220px] text-[12px] text-ink dark:text-slate-200">{{ deliveryLine(order) }}</div>
                  <div v-if="order.comment" class="mt-1 max-w-[220px] text-[11px] text-muted">
                    {{ t('orders.buyerComment') }}: {{ order.comment }}
                  </div>
                </td>

                <td class="px-4 py-3 border-b border-line dark:border-dline align-top text-[13px] font-bold text-ink dark:text-slate-200 whitespace-nowrap">
                  {{ money(order.total) }} {{ t('orders.amountUnit') }}
                </td>

                <td class="px-4 py-3 border-b border-line dark:border-dline align-top">
                  <span :title="order.admin_comment || undefined">
                    <StatusBadge :status="order.status" />
                  </span>
                  <div v-if="order.admin_comment" class="mt-1 max-w-[180px] text-[11px] text-muted">{{ order.admin_comment }}</div>
                  <div v-if="order.processor" class="mt-1 text-[11px] text-muted">{{ order.processor.name }}</div>
                </td>

                <td class="px-4 py-3 border-b border-line dark:border-dline align-top">
                  <div class="flex gap-1">
                    <template v-if="order.status === 'pending'">
                      <button
                        @click="approve(order)"
                        class="flex h-8 w-8 items-center justify-center rounded-[8px] bg-green/15 text-green transition hover:bg-green/25"
                        :title="t('actions.approve')" :aria-label="t('actions.approve')"
                      ><Icon kind="check" :size="14" /></button>
                      <button
                        @click="openReject(order)"
                        class="flex h-8 w-8 items-center justify-center rounded-[8px] bg-red/15 text-red transition hover:bg-red/25"
                        :title="t('actions.reject')" :aria-label="t('actions.reject')"
                      ><Icon kind="close" :size="14" /></button>
                    </template>

                    <template v-else-if="order.status === 'approved'">
                      <button
                        @click="complete(order)"
                        class="rounded-[8px] bg-green/15 px-2.5 h-8 text-[12px] font-bold text-green transition hover:bg-green/25"
                      >{{ t('orders.actionCompleted') }}</button>
                      <button
                        @click="cancel(order)"
                        class="flex h-8 w-8 items-center justify-center rounded-[8px] bg-red/15 text-red transition hover:bg-red/25"
                        :title="t('actions.cancel')" :aria-label="t('actions.cancel')"
                      ><Icon kind="close" :size="14" /></button>
                    </template>

                    <span v-else class="text-[12px] text-muted">—</span>
                  </div>
                </td>
              </tr>

              <!-- Состав заказа: что именно собирать каждому магазину -->
              <tr v-if="expanded.has(order.id)" class="bg-surface/40 dark:bg-dbg/40">
                <td colspan="7" class="px-4 py-4 border-b border-line dark:border-dline">
                  <div class="flex flex-col gap-4">
                    <div v-for="part in order.suborders" :key="part.id" class="rounded-card bg-white p-3 shadow-soft dark:bg-dcard">
                      <div class="mb-2 flex flex-wrap items-center gap-2">
                        <span class="text-[13px] font-extrabold text-ink dark:text-slate-100">{{ part.store?.name || '—' }}</span>
                        <StatusBadge :status="part.status" />
                        <span v-if="part.store?.phone" class="text-[12px] text-muted">{{ part.store.phone }}</span>
                        <span class="ml-auto text-[13px] font-bold text-ink dark:text-slate-200">
                          {{ money(part.subtotal) }} {{ t('orders.amountUnit') }}
                        </span>
                      </div>

                      <div
                        v-for="item in part.items"
                        :key="item.id"
                        class="flex items-baseline justify-between gap-3 border-t border-line py-1.5 text-[12px] dark:border-dline"
                      >
                        <span class="text-ink dark:text-slate-200">
                          {{ item.title }}
                          <span class="text-muted">{{ t('orders.qtyShort', { qty: item.qty }) }}</span>
                          <span v-if="item.is_wholesale" class="ml-1 rounded-pill bg-purple/10 px-1.5 py-px text-[10px] font-bold text-purple">
                            {{ t('orders.wholesale') }}
                          </span>
                        </span>
                        <span class="whitespace-nowrap font-data text-muted">
                          {{ money(item.unit_price) }} × {{ item.qty }} = <b class="text-ink dark:text-slate-200">{{ money(item.total) }}</b>
                        </span>
                      </div>

                      <div v-if="part.comment" class="mt-2 text-[11px] text-muted">
                        {{ t('orders.storeComment') }}: {{ part.comment }}
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            </template>

            <tr v-if="!orders.data.length">
              <td colspan="7" class="px-4 py-10 text-center text-sm text-muted">{{ t('orders.empty') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="orders.links" />
    </div>

    <div v-if="rejectTarget" class="fixed inset-0 z-[600] flex items-center justify-center bg-black/40 backdrop-blur-sm" @click.self="rejectTarget = null">
      <div class="w-full max-w-md rounded-card bg-white p-6 shadow-soft dark:bg-dcard">
        <h3 class="mb-4 text-[17px] font-extrabold text-ink dark:text-slate-100">{{ t('orders.rejectTitle') }}</h3>
        <label class="mb-1.5 block text-[12px] font-bold text-muted">{{ t('orders.rejectCommentLabel') }}</label>
        <textarea
          v-model="rejectComment"
          rows="3"
          class="input mb-5"
          :placeholder="t('orders.rejectCommentPlaceholder')"
        ></textarea>
        <div class="flex gap-2">
          <button @click="rejectTarget = null" class="flex-1 rounded-btn border-2 border-line py-[11px] text-[13px] font-bold text-muted hover:border-blue hover:text-blue transition dark:border-dline">{{ t('actions.cancel') }}</button>
          <button @click="doReject" :disabled="!rejectComment.trim()" class="flex-1 rounded-btn bg-red py-[11px] text-[13px] font-bold text-white hover:opacity-90 disabled:opacity-40 transition">{{ t('actions.reject') }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
