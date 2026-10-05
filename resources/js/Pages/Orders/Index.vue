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
// везёт сам и получает деньги. Админу здесь важно одно: кто, у какого магазина,
// сколько и на какую сумму заказал. Действий по заказу тут нет ни одного.

const { t } = useI18n()

const props = defineProps({
    orders: Object,
    buyers: Object,
    summary: Object,
    stores: Array,
    counts: Object,
    filters: Object,
})

const view = computed(() => props.filters?.view || 'orders')
const sort = computed(() => props.filters?.sort || 'desc')

const search = ref(props.filters?.search || '')
const statusFilter = ref(props.filters?.status || '')
const storeFilter = ref(props.filters?.store_id ? String(props.filters.store_id) : '')
const from = ref(props.filters?.from || '')
const to = ref(props.filters?.to || '')

const statuses = computed(() => [
    { value: '',          label: t('common.all') },
    { value: 'pending',   label: t('orders.tabPending'), count: props.counts?.pending },
    { value: 'approved',  label: t('orders.tabApproved') },
    { value: 'completed', label: t('orders.tabCompleted') },
    { value: 'rejected',  label: t('orders.tabRejected') },
    { value: 'canceled',  label: t('orders.tabCanceled') },
])

const hasFilters = computed(() =>
    Boolean(search.value || statusFilter.value || storeFilter.value || from.value || to.value))

// Все фильтры, вкладка и сортировка живут в URL: ссылку на выборку можно
// переслать, а «Назад» в браузере возвращает к ней же
function apply(overrides = {}) {
    const params = {
        view: view.value,
        sort: sort.value,
        search: search.value,
        status: statusFilter.value,
        store_id: storeFilter.value,
        from: from.value,
        to: to.value,
        ...overrides,
    }

    router.get(
        route('orders.index'),
        Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v != null)),
        { preserveState: true, preserveScroll: true, replace: true },
    )
}

function setStatus(value) { statusFilter.value = value; apply() }
function setStore(id) { storeFilter.value = id ? String(id) : ''; apply() }
function toggleSort() { apply({ sort: sort.value === 'desc' ? 'asc' : 'desc' }) }

function resetFilters() {
    search.value = ''
    statusFilter.value = ''
    storeFilter.value = ''
    from.value = ''
    to.value = ''
    apply()
}

// С вкладки «Покупатели» — сразу к его заказам: поиск по телефону находит все
// заказы пользователя, даже если контактный номер в них был другой
function showBuyerOrders(buyer) {
    search.value = buyer.phone || ''
    apply({ view: 'orders' })
}

// ── Раскрытие состава ──────────────────────────────────────────────────────
const expanded = ref(new Set())
function toggle(id) {
    const next = new Set(expanded.value)
    next.has(id) ? next.delete(id) : next.add(id)
    expanded.value = next
}

// ── Форматирование ─────────────────────────────────────────────────────────

function num(value) {
    return Number(value ?? 0)
}
function money(value) {
    return num(value).toLocaleString('ru-RU', { maximumFractionDigits: 2 })
}
function date(value) {
    return value
        ? new Date(value).toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' })
        : '—'
}
function time(value) {
    return value ? new Date(value).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' }) : ''
}
function orderNumber(order) {
    return String(order.id).padStart(6, '0')
}

// Комиссия удерживается с магазина: покупатель платит сумму целиком,
// магазину остаётся она за вычетом комиссии
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

// Точка, которую покупатель отправил с телефона, — ссылкой на карту
function mapUrl(order) {
    if (order.latitude == null || order.longitude == null) return null
    return `https://www.google.com/maps?q=${order.latitude},${order.longitude}`
}

const th = 'px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[.06em] text-muted whitespace-nowrap'
const td = 'px-4 py-3 align-top'
</script>

<template>
  <AppLayout>
    <template #header>{{ t('orders.title') }}</template>

    <p class="mb-4 text-[13px] text-muted">{{ t('orders.hint') }}</p>

    <!-- ── Фильтры ─────────────────────────────────────────────────────── -->
    <div class="mb-4 rounded-card bg-white p-4 shadow-soft dark:bg-dcard">
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_13rem_10rem_10rem]">
        <div>
          <label class="mb-1 block text-[11px] font-semibold text-muted">{{ t('common.search') }}</label>
          <SearchInput
            v-model="search"
            :placeholder="t('orders.searchPlaceholder')"
            @submit="apply()"
          />
        </div>

        <div>
          <label class="mb-1 block text-[11px] font-semibold text-muted">{{ t('orders.colStore') }}</label>
          <select v-model="storeFilter" @change="apply()" class="input h-[38px] w-full py-0">
            <option value="">{{ t('orders.allStores') }}</option>
            <option v-for="store in stores" :key="store.id" :value="String(store.id)">{{ store.name }}</option>
          </select>
        </div>

        <div>
          <label class="mb-1 block text-[11px] font-semibold text-muted">{{ t('orders.dateFrom') }}</label>
          <input v-model="from" type="date" :max="to || undefined" @change="apply()" class="input h-[38px] w-full py-0" />
        </div>

        <div>
          <label class="mb-1 block text-[11px] font-semibold text-muted">{{ t('orders.dateTo') }}</label>
          <input v-model="to" type="date" :min="from || undefined" @change="apply()" class="input h-[38px] w-full py-0" />
        </div>
      </div>

      <div class="mt-3 flex flex-wrap items-center gap-1.5">
        <button
          v-for="item in statuses"
          :key="item.value"
          @click="setStatus(item.value)"
          class="flex items-center gap-1.5 rounded-pill px-3 py-1 text-[12px] font-bold transition"
          :class="statusFilter === item.value
            ? 'bg-[var(--accent)] text-white'
            : 'bg-surface text-ink hover:bg-line dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10'"
        >
          {{ item.label }}
          <span
            v-if="item.count"
            class="rounded-pill px-1.5 text-[11px] font-extrabold"
            :class="statusFilter === item.value ? 'bg-white/25' : 'bg-orange/15 text-orange'"
          >{{ item.count }}</span>
        </button>

        <button
          v-if="hasFilters"
          @click="resetFilters"
          class="ml-auto flex items-center gap-1 text-[12px] font-semibold text-muted transition hover:text-red"
        >
          <Icon kind="close" :size="12" />
          {{ t('orders.resetFilters') }}
        </button>
      </div>
    </div>

    <!-- ── Сводка по выбранным заказам ─────────────────────────────────── -->
    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
      <div class="rounded-card bg-white px-4 py-3 shadow-soft dark:bg-dcard">
        <div class="text-[11px] font-semibold text-muted">{{ t('orders.sumOrders') }}</div>
        <div class="mt-1 font-data text-[22px] font-black leading-tight text-ink dark:text-slate-100">{{ summary.orders }}</div>
      </div>
      <div class="rounded-card bg-white px-4 py-3 shadow-soft dark:bg-dcard">
        <div class="text-[11px] font-semibold text-muted">{{ t('orders.sumBuyers') }}</div>
        <div class="mt-1 font-data text-[22px] font-black leading-tight text-ink dark:text-slate-100">{{ summary.buyers }}</div>
      </div>
      <div class="rounded-card bg-white px-4 py-3 shadow-soft dark:bg-dcard">
        <div class="text-[11px] font-semibold text-muted">{{ t('orders.sumQty') }}</div>
        <div class="mt-1 font-data text-[22px] font-black leading-tight text-ink dark:text-slate-100">{{ money(summary.qty) }}</div>
      </div>
      <div class="rounded-card bg-white px-4 py-3 shadow-soft dark:bg-dcard">
        <div class="text-[11px] font-semibold text-muted">{{ t('orders.sumTotal') }}</div>
        <div class="mt-1 font-data text-[22px] font-black leading-tight text-ink dark:text-slate-100">
          {{ money(summary.total) }} <span class="text-[13px] font-bold text-muted">{{ t('orders.amountUnit') }}</span>
        </div>
      </div>
      <div class="rounded-card bg-white px-4 py-3 shadow-soft dark:bg-dcard">
        <div class="text-[11px] font-semibold text-muted">{{ t('orders.sumCommission') }}</div>
        <div class="mt-1 font-data text-[22px] font-black leading-tight text-purple">
          {{ money(summary.commission) }} <span class="text-[13px] font-bold">{{ t('orders.amountUnit') }}</span>
        </div>
      </div>
    </div>
    <p class="mb-5 mt-2 text-[11px] text-muted">{{ t('orders.summaryNote') }}</p>

    <!-- ── Вкладки и сортировка ────────────────────────────────────────── -->
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
      <div class="inline-flex rounded-btn bg-surface p-1 dark:bg-white/5">
        <button
          v-for="tab in [{ value: 'orders', label: t('orders.viewOrders') }, { value: 'buyers', label: t('orders.viewBuyers') }]"
          :key="tab.value"
          @click="apply({ view: tab.value })"
          class="rounded-[8px] px-4 py-1.5 text-[13px] font-bold transition"
          :class="view === tab.value
            ? 'bg-white text-ink shadow-soft dark:bg-dcard dark:text-slate-100'
            : 'text-muted hover:text-ink dark:hover:text-slate-200'"
        >{{ tab.label }}</button>
      </div>

      <button
        @click="toggleSort"
        class="flex items-center gap-1.5 rounded-btn border border-line bg-white px-3 py-1.5 text-[12px] font-bold text-ink transition hover:bg-surface dark:border-dline dark:bg-dcard dark:text-slate-200 dark:hover:bg-white/5"
      >
        <Icon :kind="sort === 'desc' ? 'arrowDown' : 'arrowUp'" :size="12" />
        {{ sort === 'desc' ? t('orders.sortNewest') : t('orders.sortOldest') }}
      </button>
    </div>

    <!-- ── Заказы ──────────────────────────────────────────────────────── -->
    <div v-if="view === 'orders' && orders" class="overflow-hidden rounded-card bg-white shadow-soft dark:bg-dcard">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="border-b border-line dark:border-dline">
            <tr>
              <th :class="th">
                <button @click="toggleSort" class="flex items-center gap-1 uppercase hover:text-ink dark:hover:text-slate-200">
                  {{ t('orders.colDate') }}
                  <Icon :kind="sort === 'desc' ? 'arrowDown' : 'arrowUp'" :size="10" />
                </button>
              </th>
              <th :class="th">{{ t('orders.colOrder') }}</th>
              <th :class="th">{{ t('orders.colBuyer') }}</th>
              <th :class="th">{{ t('orders.colStore') }}</th>
              <th :class="[th, 'text-right']">{{ t('orders.colQty') }}</th>
              <th :class="[th, 'text-right']">{{ t('orders.colTotal') }}</th>
              <th :class="th">{{ t('common.status') }}</th>
              <th class="w-8"></th>
            </tr>
          </thead>
          <tbody>
            <template v-for="order in orders.data" :key="order.id">
              <tr
                @click="toggle(order.id)"
                class="cursor-pointer border-b border-line transition hover:bg-surface/50 dark:border-dline dark:hover:bg-white/3"
                :class="expanded.has(order.id) ? 'bg-surface/50 dark:bg-white/3' : ''"
              >
                <td :class="[td, 'whitespace-nowrap']">
                  <div class="text-[13px] font-semibold text-ink dark:text-slate-100">{{ date(order.created_at) }}</div>
                  <div class="text-[11px] text-muted">{{ time(order.created_at) }}</div>
                </td>

                <td :class="[td, 'whitespace-nowrap font-data text-[13px] font-bold text-ink dark:text-slate-100']">
                  №{{ orderNumber(order) }}
                </td>

                <td :class="td">
                  <div class="text-[13px] font-bold text-ink dark:text-slate-100">{{ order.contact_name || order.user?.name || '—' }}</div>
                  <div class="text-[12px] text-muted">{{ order.phone }}</div>
                </td>

                <!-- Клик по магазину — «что продали они»: фильтр без перехода -->
                <td :class="td">
                  <button
                    v-if="part(order)?.store"
                    @click.stop="setStore(part(order).store.id)"
                    class="text-left text-[13px] font-bold text-ink transition hover:text-blue dark:text-slate-100"
                  >{{ part(order).store.name }}</button>
                  <div v-else class="text-[13px] text-muted">—</div>
                  <div v-if="part(order)?.user" class="text-[12px] text-muted">{{ part(order).user.name }}</div>
                </td>

                <td :class="[td, 'whitespace-nowrap text-right']">
                  <div class="text-[13px] font-bold text-ink dark:text-slate-100">{{ t('orders.pcs', { qty: num(order.items_qty) }) }}</div>
                  <div class="text-[11px] text-muted">{{ t('orders.itemsCount', { count: order.items_count }) }}</div>
                </td>

                <td :class="[td, 'whitespace-nowrap text-right']">
                  <div class="text-[13px] font-bold text-ink dark:text-slate-100">{{ money(order.total) }} {{ t('orders.amountUnit') }}</div>
                  <div v-if="num(order.commission_total) > 0" class="text-[11px] font-semibold text-purple">
                    {{ t('orders.commission') }} {{ money(order.commission_total) }}
                  </div>
                </td>

                <td :class="td"><StatusBadge :status="order.status" /></td>

                <td class="pr-4 align-middle text-muted">
                  <Icon kind="chevronDown" :size="14" class="transition" :class="expanded.has(order.id) ? 'rotate-180' : ''" />
                </td>
              </tr>

              <!-- Подробности: что именно куплено, куда везти и что ответил продавец -->
              <tr v-if="expanded.has(order.id)" class="border-b border-line dark:border-dline">
                <td colspan="8" class="bg-surface/40 px-4 py-4 dark:bg-dbg/40">
                  <div class="grid gap-3 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                    <div class="rounded-card bg-white p-4 dark:bg-dcard">
                      <div class="mb-2 text-[11px] font-bold uppercase tracking-[.06em] text-muted">{{ t('orders.items') }}</div>
                      <div class="overflow-x-auto">
                        <table class="w-full text-[13px]">
                          <thead>
                            <tr class="text-[11px] text-muted">
                              <th class="pb-1.5 text-left font-semibold">{{ t('orders.colProduct') }}</th>
                              <th class="pb-1.5 text-right font-semibold">{{ t('orders.colPrice') }}</th>
                              <th class="pb-1.5 text-right font-semibold">{{ t('orders.colQty') }}</th>
                              <th class="pb-1.5 text-right font-semibold">{{ t('orders.colTotal') }}</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="item in part(order)?.items || []" :key="item.id" class="border-t border-line dark:border-dline">
                              <td class="py-1.5 pr-3 text-ink dark:text-slate-200">
                                {{ item.title }}
                                <span v-if="item.is_wholesale" class="ml-1 rounded-pill bg-purple/10 px-1.5 py-px text-[10px] font-bold text-purple">
                                  {{ t('orders.wholesale') }}
                                </span>
                              </td>
                              <td class="whitespace-nowrap py-1.5 text-right font-data text-muted">{{ money(item.unit_price) }}</td>
                              <td class="whitespace-nowrap py-1.5 text-right font-data text-ink dark:text-slate-200">{{ item.qty }}</td>
                              <td class="whitespace-nowrap py-1.5 text-right font-data font-bold text-ink dark:text-slate-200">{{ money(item.total) }}</td>
                            </tr>
                          </tbody>
                          <tfoot class="border-t-2 border-line dark:border-dline">
                            <tr>
                              <td colspan="3" class="pt-2 text-right text-[12px] font-semibold text-muted">{{ t('orders.totalLabel') }}</td>
                              <td class="whitespace-nowrap pt-2 text-right font-data font-black text-ink dark:text-slate-100">
                                {{ money(order.total) }} {{ t('orders.amountUnit') }}
                              </td>
                            </tr>
                            <!-- Ставка зафиксирована при оформлении: если её потом
                                 поменяли, заказ всё равно считается по старой -->
                            <template v-if="num(part(order)?.commission_percent) > 0">
                              <tr>
                                <td colspan="3" class="pt-1 text-right text-[12px] font-semibold text-purple">
                                  {{ t('orders.commissionAt', { percent: num(part(order).commission_percent) }) }}
                                </td>
                                <td class="whitespace-nowrap pt-1 text-right font-data font-bold text-purple">
                                  − {{ money(order.commission_total) }}
                                </td>
                              </tr>
                              <tr>
                                <td colspan="3" class="pt-1 text-right text-[12px] font-semibold text-muted">{{ t('orders.payout') }}</td>
                                <td class="whitespace-nowrap pt-1 text-right font-data font-bold text-ink dark:text-slate-200">
                                  {{ money(payout(order.total, order.commission_total)) }}
                                </td>
                              </tr>
                            </template>
                          </tfoot>
                        </table>
                      </div>
                    </div>

                    <div class="space-y-3 rounded-card bg-white p-4 text-[13px] dark:bg-dcard">
                      <div>
                        <div class="text-[11px] font-bold uppercase tracking-[.06em] text-muted">{{ t('orders.colDelivery') }}</div>
                        <div class="mt-0.5 text-ink dark:text-slate-200">{{ deliveryLine(order) || '—' }}</div>
                        <a
                          v-if="mapUrl(order)"
                          :href="mapUrl(order)" target="_blank" rel="noopener"
                          class="mt-0.5 inline-block text-[12px] font-bold text-blue hover:underline"
                        >{{ t('orders.openOnMap') }}</a>
                      </div>
                      <!-- Чем покупатель обещал рассчитаться: деньги продавец
                           получает на месте, значит должен приехать готовым -->
                      <div>
                        <div class="text-[11px] font-bold uppercase tracking-[.06em] text-muted">{{ t('orders.paymentMethod') }}</div>
                        <div class="mt-0.5 text-ink dark:text-slate-200">
                          {{ order.payment_method ? (order.payment_method.name_ru || order.payment_method.name_tk) : t('orders.paymentNotChosen') }}
                        </div>
                      </div>
                      <div v-if="part(order)?.store?.phone">
                        <div class="text-[11px] font-bold uppercase tracking-[.06em] text-muted">{{ t('orders.storePhone') }}</div>
                        <div class="mt-0.5 text-ink dark:text-slate-200">{{ part(order).store.phone }}</div>
                      </div>
                      <div v-if="order.comment">
                        <div class="text-[11px] font-bold uppercase tracking-[.06em] text-muted">{{ t('orders.buyerComment') }}</div>
                        <div class="mt-0.5 text-ink dark:text-slate-200">{{ order.comment }}</div>
                      </div>
                      <!-- Решение по заказу — за продавцом: причина отказа или его комментарий -->
                      <div v-if="order.decision_comment || part(order)?.comment || order.decided_at">
                        <div class="text-[11px] font-bold uppercase tracking-[.06em] text-muted">{{ t('orders.sellerAnswer') }}</div>
                        <div v-if="order.decision_comment || part(order)?.comment" class="mt-0.5 text-ink dark:text-slate-200">
                          {{ order.decision_comment || part(order).comment }}
                        </div>
                        <div v-if="order.decided_at" class="text-[11px] text-muted">{{ date(order.decided_at) }} {{ time(order.decided_at) }}</div>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            </template>

            <tr v-if="!orders.data.length">
              <td colspan="8" class="px-4 py-12 text-center text-sm text-muted">{{ t('orders.empty') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="orders.links" />
    </div>

    <!-- ── Покупатели: кто сколько заказал и где ───────────────────────── -->
    <div v-if="view === 'buyers' && buyers" class="overflow-hidden rounded-card bg-white shadow-soft dark:bg-dcard">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="border-b border-line dark:border-dline">
            <tr>
              <th :class="th">{{ t('orders.colBuyer') }}</th>
              <th :class="[th, 'text-right']">{{ t('orders.colOrders') }}</th>
              <th :class="th">{{ t('orders.colStores') }}</th>
              <th :class="[th, 'text-right']">{{ t('orders.colQty') }}</th>
              <th :class="[th, 'text-right']">{{ t('orders.colTotal') }}</th>
              <th :class="th">
                <button @click="toggleSort" class="flex items-center gap-1 uppercase hover:text-ink dark:hover:text-slate-200">
                  {{ t('orders.colLastOrder') }}
                  <Icon :kind="sort === 'desc' ? 'arrowDown' : 'arrowUp'" :size="10" />
                </button>
              </th>
              <th class="w-8"></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="buyer in buyers.data"
              :key="buyer.id"
              class="border-b border-line transition hover:bg-surface/50 dark:border-dline dark:hover:bg-white/3"
            >
              <td :class="td">
                <div class="text-[13px] font-bold text-ink dark:text-slate-100">{{ buyer.name || '—' }}</div>
                <div class="text-[12px] text-muted">{{ buyer.phone }}</div>
              </td>

              <td :class="[td, 'text-right font-data text-[15px] font-black text-ink dark:text-slate-100']">
                {{ buyer.orders_count }}
              </td>

              <td :class="td">
                <div class="flex flex-wrap gap-1">
                  <button
                    v-for="store in buyer.stores"
                    :key="store.id ?? 'none'"
                    @click="setStore(store.id)"
                    :disabled="!store.id"
                    class="rounded-pill bg-surface px-2 py-0.5 text-[12px] font-semibold text-ink transition enabled:hover:bg-blue/10 enabled:hover:text-blue dark:bg-white/5 dark:text-slate-200"
                  >
                    {{ store.name || '—' }}
                    <span class="text-muted">· {{ store.orders_count }}</span>
                  </button>
                </div>
              </td>

              <td :class="[td, 'whitespace-nowrap text-right text-[13px] font-bold text-ink dark:text-slate-100']">
                {{ t('orders.pcs', { qty: buyer.qty }) }}
              </td>

              <td :class="[td, 'whitespace-nowrap text-right text-[13px] font-bold text-ink dark:text-slate-100']">
                {{ money(buyer.total) }} {{ t('orders.amountUnit') }}
              </td>

              <td :class="[td, 'whitespace-nowrap']">
                <div class="text-[13px] font-semibold text-ink dark:text-slate-100">{{ date(buyer.last_order_at) }}</div>
                <div class="text-[11px] text-muted">{{ time(buyer.last_order_at) }}</div>
              </td>

              <td class="pr-4 text-right align-middle">
                <button
                  @click="showBuyerOrders(buyer)"
                  class="whitespace-nowrap text-[12px] font-bold text-blue hover:underline"
                >{{ t('orders.showOrders') }}</button>
              </td>
            </tr>

            <tr v-if="!buyers.data.length">
              <td colspan="7" class="px-4 py-12 text-center text-sm text-muted">{{ t('orders.emptyBuyers') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="buyers.links" />
    </div>
  </AppLayout>
</template>
