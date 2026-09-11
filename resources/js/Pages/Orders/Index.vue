<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import Icon from '@/Components/Icon.vue'
import Pagination from '@/Components/Pagination.vue'
import SearchInput from '@/Components/SearchInput.vue'
import StatusBadge from '@/Components/StatusBadge.vue'

// Раздел только для наблюдения: заказ ведёт владелец магазина — принимает,
// везёт сам и получает деньги. Админу здесь важно одно: какой товар, у кого,
// кому и на какую сумму продан. Действий по заказу тут нет ни одного.

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
// Клик по магазину в строке — самый частый вопрос админа: «что продали они»
function setStore(value) { storeFilter.value = value ? String(value) : ''; applyFilters() }

// ── Раскрытие состава ──────────────────────────────────────────────────────
const expanded = ref(new Set())
function toggle(id) {
    const next = new Set(expanded.value)
    next.has(id) ? next.delete(id) : next.add(id)
    expanded.value = next
}

// ── Вспомогательное ────────────────────────────────────────────────────────

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString() : '—'
}

function money(value) {
    return Number(value ?? 0).toLocaleString()
}

// Комиссия платформы удерживается с магазина: покупатель платит total целиком,
// магазину остаётся сумма за вычетом комиссии
function num(value) {
    return Number(value ?? 0)
}
function payout(sum, commission) {
    return Math.round((num(sum) - num(commission)) * 100) / 100
}

// В заказе всегда один магазин, но данные лежат в его части заказа
function part(order) {
    return order.suborders?.[0] || null
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
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('orders.colSeller') }}</th>
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('orders.colBuyer') }}</th>
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('orders.colDelivery') }}</th>
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('orders.colTotal') }}</th>
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.status') }}</th>
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

                <!-- Кто продал: магазин, его владелец и телефон для связи -->
                <td class="px-4 py-3 border-b border-line dark:border-dline align-top">
                  <button
                    v-if="part(order)"
                    @click="setStore(part(order).store?.id)"
                    class="text-left text-[13px] font-bold text-ink transition hover:text-blue dark:text-slate-100"
                    :title="t('orders.colSeller')"
                  >{{ part(order).store?.name || '—' }}</button>
                  <div v-else class="text-[12px] text-muted">—</div>
                  <div v-if="part(order)?.user" class="text-[12px] text-muted">{{ part(order).user.name }}</div>
                  <div v-if="part(order)?.store?.phone" class="text-[12px] text-muted">{{ part(order).store.phone }}</div>
                </td>

                <td class="px-4 py-3 border-b border-line dark:border-dline align-top">
                  <div class="text-[13px] font-bold text-ink dark:text-slate-100">{{ order.contact_name || order.user?.name || '—' }}</div>
                  <div class="text-[12px] text-muted">{{ order.phone }}</div>
                </td>

                <td class="px-4 py-3 border-b border-line dark:border-dline align-top">
                  <div class="max-w-[220px] text-[12px] text-ink dark:text-slate-200">{{ deliveryLine(order) }}</div>
                  <div v-if="order.comment" class="mt-1 max-w-[220px] text-[11px] text-muted">
                    {{ t('orders.buyerComment') }}: {{ order.comment }}
                  </div>
                </td>

                <!-- Сумма покупателя не зависит от комиссии: она удерживается
                     с магазина, поэтому рядом — сколько из неё наше -->
                <td class="px-4 py-3 border-b border-line dark:border-dline align-top whitespace-nowrap">
                  <div class="text-[13px] font-bold text-ink dark:text-slate-200">
                    {{ money(order.total) }} {{ t('orders.amountUnit') }}
                  </div>
                  <template v-if="num(order.commission_total) > 0">
                    <div class="mt-0.5 text-[11px] font-bold text-purple">
                      {{ t('orders.commission') }}: {{ money(order.commission_total) }} {{ t('orders.amountUnit') }}
                    </div>
                    <div class="text-[11px] text-muted">
                      {{ t('orders.payout') }}: {{ money(payout(order.total, order.commission_total)) }} {{ t('orders.amountUnit') }}
                    </div>
                  </template>
                </td>

                <td class="px-4 py-3 border-b border-line dark:border-dline align-top">
                  <StatusBadge :status="order.status" />
                  <!-- Причина отказа — от продавца: решение по заказу его -->
                  <div v-if="order.decision_comment" class="mt-1 max-w-[180px] text-[11px] text-muted">
                    {{ t('orders.sellerComment') }}: {{ order.decision_comment }}
                  </div>
                  <div v-if="order.decided_at" class="mt-1 text-[11px] text-muted">{{ formatDate(order.decided_at) }}</div>
                </td>
              </tr>

              <!-- Состав заказа: что именно продано, сколько и по какой цене -->
              <tr v-if="expanded.has(order.id)" class="bg-surface/40 dark:bg-dbg/40">
                <td colspan="6" class="px-4 py-4 border-b border-line dark:border-dline">
                  <div v-if="part(order)" class="rounded-card bg-white p-3 shadow-soft dark:bg-dcard">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                      <span class="text-[13px] font-extrabold text-ink dark:text-slate-100">{{ part(order).store?.name || '—' }}</span>
                      <StatusBadge :status="part(order).status" />
                      <span class="ml-auto text-[13px] font-bold text-ink dark:text-slate-200">
                        {{ money(part(order).subtotal) }} {{ t('orders.amountUnit') }}
                      </span>
                    </div>

                    <!-- Ставка магазина зафиксирована при оформлении: если её
                         потом поменяли, заказ всё равно считается по старой -->
                    <div
                      v-if="num(part(order).commission_percent) > 0"
                      class="mb-1 flex flex-wrap items-baseline justify-end gap-x-3 text-[11px]"
                    >
                      <span class="font-bold text-purple">
                        {{ t('orders.commissionAt', { percent: num(part(order).commission_percent) }) }}:
                        {{ money(part(order).commission_total) }} {{ t('orders.amountUnit') }}
                      </span>
                      <span class="text-muted">
                        {{ t('orders.payout') }}: {{ money(payout(part(order).subtotal, part(order).commission_total)) }} {{ t('orders.amountUnit') }}
                      </span>
                    </div>

                    <div
                      v-for="item in part(order).items"
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
                      <span class="whitespace-nowrap text-right font-data text-muted">
                        {{ money(item.unit_price) }} × {{ item.qty }} = <b class="text-ink dark:text-slate-200">{{ money(item.total) }}</b>
                        <!-- Комиссия считается с каждого товара отдельно -->
                        <span v-if="num(item.commission_amount) > 0" class="block text-[11px] font-bold text-purple">
                          − {{ money(item.commission_amount) }} {{ t('orders.amountUnit') }}
                        </span>
                      </span>
                    </div>

                    <div v-if="part(order).comment" class="mt-2 text-[11px] text-muted">
                      {{ t('orders.sellerComment') }}: {{ part(order).comment }}
                    </div>
                  </div>
                </td>
              </tr>
            </template>

            <tr v-if="!orders.data.length">
              <td colspan="6" class="px-4 py-10 text-center text-sm text-muted">{{ t('orders.empty') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="orders.links" />
    </div>
  </AppLayout>
</template>
