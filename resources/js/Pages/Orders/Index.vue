<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import Icon from '@/Components/Icon.vue'
import Pagination from '@/Components/Pagination.vue'
import SearchInput from '@/Components/SearchInput.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import StatCard from '@/Components/StatCard.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { th, tr, thead } from '@/table'

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

// Ячейки сверху: в строке заказа по две строки текста разной длины
const td = 'px-5 py-3.5 align-top text-[13.5px]'
</script>

<template>
  <AppLayout>
    <template #header>{{ t('orders.title') }}</template>

    <template #description>{{ t('orders.hint') }}</template>

    <!-- ── Фильтры: одна строка одной высоты, переносится на узком экране ── -->
    <div class="mb-3 flex flex-wrap items-center gap-2.5">
      <SearchInput
        v-model="search"
        :placeholder="t('orders.searchPlaceholder')"
        class="w-full sm:w-[280px]"
        @submit="apply()"
      />
      <select v-model="storeFilter" @change="apply()" :aria-label="t('orders.colStore')" class="filter-select">
        <option value="">{{ t('orders.allStores') }}</option>
        <option v-for="store in stores" :key="store.id" :value="String(store.id)">{{ store.name }}</option>
      </select>
      <div class="flex items-center gap-1.5">
        <input v-model="from" type="date" :max="to || undefined" @change="apply()" :aria-label="t('orders.dateFrom')" :title="t('orders.dateFrom')" class="filter-select !pr-3 font-data" />
        <span class="text-[var(--text-muted)]">–</span>
        <input v-model="to" type="date" :min="from || undefined" @change="apply()" :aria-label="t('orders.dateTo')" :title="t('orders.dateTo')" class="filter-select !pr-3 font-data" />
      </div>
      <button v-if="hasFilters" type="button" @click="resetFilters" class="btn btn-ghost btn-sm">
        <Icon kind="close" :size="14" />{{ t('orders.resetFilters') }}
      </button>
    </div>

    <div class="mb-5 flex flex-wrap items-center gap-2.5">
      <div class="seg">
        <button
          v-for="item in statuses" :key="item.value"
          type="button"
          @click="setStatus(item.value)"
          :aria-pressed="statusFilter === item.value"
          class="seg-item"
          :class="statusFilter === item.value ? 'seg-item-active' : ''"
        >{{ item.label }}<template v-if="item.count"> ({{ item.count }})</template></button>
      </div>
    </div>

    <!-- ── Сводка по выбранным заказам ─────────────────────────────────── -->
    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
      <StatCard :label="t('orders.sumOrders')" :value="summary.orders" icon="cart" />
      <StatCard :label="t('orders.sumBuyers')" :value="summary.buyers" icon="users" />
      <StatCard :label="t('orders.sumQty')" :value="money(summary.qty)" icon="listing" />
      <StatCard :label="t('orders.sumTotal')" :value="`${money(summary.total)} ${t('orders.amountUnit')}`" icon="coin" tone="success" />
      <StatCard :label="t('orders.sumCommission')" :value="`${money(summary.commission)} ${t('orders.amountUnit')}`" icon="receipt" tone="info" />
    </div>
    <p class="mb-6 mt-2 text-[12px] text-[var(--text-muted)]">{{ t('orders.summaryNote') }}</p>

    <!-- ── Вкладки и сортировка ────────────────────────────────────────── -->
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
      <div class="seg !h-10">
        <button
          v-for="tab in [{ value: 'orders', label: t('orders.viewOrders') }, { value: 'buyers', label: t('orders.viewBuyers') }]"
          :key="tab.value"
          type="button"
          @click="apply({ view: tab.value })"
          :aria-pressed="view === tab.value"
          class="seg-item"
          :class="view === tab.value ? 'seg-item-active' : ''"
        >{{ tab.label }}</button>
      </div>

      <button type="button" @click="toggleSort" class="btn btn-secondary btn-sm">
        <Icon :kind="sort === 'desc' ? 'arrowDown' : 'arrowUp'" :size="14" />
        {{ sort === 'desc' ? t('orders.sortNewest') : t('orders.sortOldest') }}
      </button>
    </div>

    <!-- ── Заказы ──────────────────────────────────────────────────────── -->
    <div v-if="view === 'orders' && orders" class="overflow-hidden card">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr :class="thead">
              <th :class="th">
                <button type="button" @click="toggleSort" class="flex items-center gap-1 uppercase hover:text-[var(--text)]">
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
              <th class="w-12"></th>
            </tr>
          </thead>
          <tbody>
            <template v-for="order in orders.data" :key="order.id">
              <tr
                @click="toggle(order.id)"
                :aria-expanded="expanded.has(order.id)"
                class="cursor-pointer border-b border-[var(--card-border)] transition-colors duration-150 hover:bg-[var(--nav-hover)]"
                :class="expanded.has(order.id) ? 'bg-[var(--nav-hover)]' : ''"
              >
                <td :class="[td, 'whitespace-nowrap']">
                  <div class="text-[13px] font-semibold text-ink dark:text-slate-100">{{ date(order.created_at) }}</div>
                  <div class="text-[12px] text-muted">{{ time(order.created_at) }}</div>
                </td>

                <td :class="[td, 'whitespace-nowrap font-data text-[13px] font-semibold text-[var(--text)]']">
                  №{{ orderNumber(order) }}
                </td>

                <td :class="td">
                  <div class="text-[13px] font-semibold text-[var(--text)]">{{ order.contact_name || order.user?.name || '—' }}</div>
                  <div class="text-[12px] text-muted">{{ order.phone }}</div>
                </td>

                <!-- Клик по магазину — «что продали они»: фильтр без перехода -->
                <td :class="td">
                  <button
                    v-if="part(order)?.store"
                    @click.stop="setStore(part(order).store.id)"
                    class="text-left text-[13px] font-semibold text-[var(--text)] transition hover:text-link"
                  >{{ part(order).store.name }}</button>
                  <div v-else class="text-[13px] text-muted">—</div>
                  <div v-if="part(order)?.user" class="text-[12px] text-muted">{{ part(order).user.name }}</div>
                </td>

                <td :class="[td, 'whitespace-nowrap text-right']">
                  <div class="text-[13px] font-semibold text-[var(--text)]">{{ t('orders.pcs', { qty: num(order.items_qty) }) }}</div>
                  <div class="text-[12px] text-muted">{{ t('orders.itemsCount', { count: order.items_count }) }}</div>
                </td>

                <td :class="[td, 'whitespace-nowrap text-right']">
                  <div class="text-[13px] font-semibold text-[var(--text)]">{{ money(order.total) }} {{ t('orders.amountUnit') }}</div>
                  <div v-if="num(order.commission_total) > 0" class="text-[12px] font-semibold text-purple">
                    {{ t('orders.commission') }} {{ money(order.commission_total) }}
                  </div>
                </td>

                <td :class="td"><StatusBadge :status="order.status" /></td>

                <td class="pr-4 align-middle text-[var(--text-muted)]">
                  <span class="icon-btn !h-8 !w-8 !bg-transparent"><Icon kind="chevronDown" :size="16" class="transition-transform duration-150" :class="expanded.has(order.id) ? 'rotate-180' : ''" /></span>
                </td>
              </tr>

              <!-- Подробности: что именно куплено, куда везти и что ответил продавец -->
              <tr v-if="expanded.has(order.id)" class="border-b border-[var(--card-border)]">
                <td colspan="8" class="bg-black/[.015] px-4 py-4 dark:bg-white/[.015] sm:px-5">
                  <div class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                    <div class="card p-4">
                      <div class="card-title mb-3">{{ t('orders.items') }}</div>
                      <div class="overflow-x-auto">
                        <table class="w-full text-[13px]">
                          <thead>
                            <tr class="text-[11.5px] uppercase tracking-[.06em] text-[var(--text-muted)]">
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
                                <span v-if="item.is_wholesale" class="ml-1 inline-flex h-5 items-center rounded-full bg-violet-500/10 px-2 text-[12px] font-semibold text-violet-700 dark:text-violet-300">
                                  {{ t('orders.wholesale') }}
                                </span>
                              </td>
                              <td class="whitespace-nowrap py-1.5 text-right font-data text-muted">{{ money(item.unit_price) }}</td>
                              <td class="whitespace-nowrap py-1.5 text-right font-data text-ink dark:text-slate-200">{{ item.qty }}</td>
                              <td class="whitespace-nowrap py-1.5 text-right font-data font-semibold text-[var(--text)]">{{ money(item.total) }}</td>
                            </tr>
                          </tbody>
                          <tfoot class="border-t border-[var(--field-border)]">
                            <tr>
                              <td colspan="3" class="pt-2 text-right text-[12px] font-semibold text-muted">{{ t('orders.totalLabel') }}</td>
                              <td class="whitespace-nowrap pt-2 text-right font-data font-semibold text-[var(--text)]">
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
                                <td class="whitespace-nowrap pt-1 text-right font-data font-semibold text-[var(--text)]">
                                  {{ money(payout(order.total, order.commission_total)) }}
                                </td>
                              </tr>
                            </template>
                          </tfoot>
                        </table>
                      </div>
                    </div>

                    <div class="card space-y-3 p-4 text-[13.5px]">
                      <!-- Ход заказа: из дат, которые уже есть у заказа -->
                      <div>
                        <div class="card-title mb-3">{{ t('orders.timeline') }}</div>
                        <ol class="relative space-y-3 border-l border-[var(--card-border)] pl-4">
                          <li class="relative">
                            <span class="absolute -left-[21px] top-1.5 h-2.5 w-2.5 rounded-full border-2 border-[var(--card-bg)] bg-[var(--text-muted)]"></span>
                            <div class="text-[var(--text)]">{{ t('orders.timelineCreated') }}</div>
                            <div class="font-data text-[12px] text-[var(--text-muted)]">{{ date(order.created_at) }} {{ time(order.created_at) }}</div>
                          </li>
                          <li v-if="order.decided_at" class="relative">
                            <span class="absolute -left-[21px] top-1.5 h-2.5 w-2.5 rounded-full border-2 border-[var(--card-bg)] bg-[var(--text-muted)]"></span>
                            <div class="text-[var(--text)]">{{ t('orders.timelineDecided') }}</div>
                            <div class="font-data text-[12px] text-[var(--text-muted)]">{{ date(order.decided_at) }} {{ time(order.decided_at) }}</div>
                          </li>
                          <li class="relative">
                            <span class="absolute -left-[21px] top-1.5 h-2.5 w-2.5 rounded-full border-2 border-[var(--card-bg)] bg-[var(--accent)]"></span>
                            <div class="flex items-center gap-2"><span class="text-[var(--text-muted)]">{{ t('orders.timelineNow') }}:</span><StatusBadge :status="order.status" /></div>
                          </li>
                        </ol>
                      </div>
                      <div class="border-t border-[var(--card-border)] pt-3">
                        <div class="text-[12px] text-[var(--text-muted)]">{{ t('orders.colDelivery') }}</div>
                        <div class="mt-0.5 text-ink dark:text-slate-200">{{ deliveryLine(order) || '—' }}</div>
                        <a
                          v-if="mapUrl(order)"
                          :href="mapUrl(order)" target="_blank" rel="noopener"
                          class="mt-0.5 inline-block text-[12.5px] font-medium text-link hover:underline"
                        >{{ t('orders.openOnMap') }}</a>
                      </div>
                      <!-- Чем покупатель обещал рассчитаться: деньги продавец
                           получает на месте, значит должен приехать готовым -->
                      <div>
                        <div class="text-[12px] text-[var(--text-muted)]">{{ t('orders.paymentMethod') }}</div>
                        <div class="mt-0.5 text-ink dark:text-slate-200">
                          {{ order.payment_method ? (order.payment_method.name_ru || order.payment_method.name_tk) : t('orders.paymentNotChosen') }}
                        </div>
                      </div>
                      <div v-if="part(order)?.store?.phone">
                        <div class="text-[12px] text-[var(--text-muted)]">{{ t('orders.storePhone') }}</div>
                        <div class="mt-0.5 text-ink dark:text-slate-200">{{ part(order).store.phone }}</div>
                      </div>
                      <div v-if="order.comment">
                        <div class="text-[12px] text-[var(--text-muted)]">{{ t('orders.buyerComment') }}</div>
                        <div class="mt-0.5 text-ink dark:text-slate-200">{{ order.comment }}</div>
                      </div>
                      <!-- Решение по заказу — за продавцом: причина отказа или его комментарий -->
                      <div v-if="order.decision_comment || part(order)?.comment || order.decided_at">
                        <div class="text-[12px] text-[var(--text-muted)]">{{ t('orders.sellerAnswer') }}</div>
                        <div v-if="order.decision_comment || part(order)?.comment" class="mt-0.5 text-ink dark:text-slate-200">
                          {{ order.decision_comment || part(order).comment }}
                        </div>
                        <div v-if="order.decided_at" class="text-[12px] text-muted">{{ date(order.decided_at) }} {{ time(order.decided_at) }}</div>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            </template>

            <tr v-if="!orders.data.length">
              <td colspan="8">
                <EmptyState icon="cart" :title="t('orders.empty')" :text="hasFilters ? t('common.emptyFiltered') : ''">
                  <button v-if="hasFilters" type="button" class="btn btn-secondary" @click="resetFilters">{{ t('common.resetFilters') }}</button>
                </EmptyState>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="orders.links" :from="orders.from" :to="orders.to" :total="orders.total" />
    </div>

    <!-- ── Покупатели: кто сколько заказал и где ───────────────────────── -->
    <div v-if="view === 'buyers' && buyers" class="overflow-hidden card">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr :class="thead">
              <th :class="th">{{ t('orders.colBuyer') }}</th>
              <th :class="[th, 'text-right']">{{ t('orders.colOrders') }}</th>
              <th :class="th">{{ t('orders.colStores') }}</th>
              <th :class="[th, 'text-right']">{{ t('orders.colQty') }}</th>
              <th :class="[th, 'text-right']">{{ t('orders.colTotal') }}</th>
              <th :class="th">
                <button type="button" @click="toggleSort" class="flex items-center gap-1 uppercase hover:text-[var(--text)]">
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
              :class="tr"
            >
              <td :class="td">
                <div class="text-[13px] font-semibold text-[var(--text)]">{{ buyer.name || '—' }}</div>
                <div class="text-[12px] text-muted">{{ buyer.phone }}</div>
              </td>

              <td :class="[td, 'text-right font-data text-[15px] font-semibold text-[var(--text)]']">
                {{ buyer.orders_count }}
              </td>

              <td :class="td">
                <div class="flex flex-wrap gap-1">
                  <button
                    v-for="store in buyer.stores"
                    :key="store.id ?? 'none'"
                    @click="setStore(store.id)"
                    :disabled="!store.id"
                    type="button"
                    class="inline-flex h-6 items-center gap-1 rounded-full bg-[var(--nav-hover)] px-2.5 text-[12px] font-medium text-[var(--text)] transition-colors duration-150 enabled:hover:bg-[var(--accent-tint)] enabled:hover:text-link"
                  >
                    {{ store.name || '—' }}
                    <span class="text-muted">· {{ store.orders_count }}</span>
                  </button>
                </div>
              </td>

              <td :class="[td, 'whitespace-nowrap text-right text-[13px] font-semibold text-[var(--text)]']">
                {{ t('orders.pcs', { qty: buyer.qty }) }}
              </td>

              <td :class="[td, 'whitespace-nowrap text-right text-[13px] font-semibold text-[var(--text)]']">
                {{ money(buyer.total) }} {{ t('orders.amountUnit') }}
              </td>

              <td :class="[td, 'whitespace-nowrap']">
                <div class="text-[13px] font-semibold text-ink dark:text-slate-100">{{ date(buyer.last_order_at) }}</div>
                <div class="text-[12px] text-muted">{{ time(buyer.last_order_at) }}</div>
              </td>

              <td class="pr-4 text-right align-middle">
                <button type="button" @click="showBuyerOrders(buyer)" class="btn btn-secondary btn-sm">{{ t('orders.showOrders') }}</button>
              </td>
            </tr>

            <tr v-if="!buyers.data.length">
              <td colspan="7"><EmptyState icon="users" :title="t('orders.emptyBuyers')" :text="hasFilters ? t('common.emptyFiltered') : ''" /></td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="buyers.links" :from="buyers.from" :to="buyers.to" :total="buyers.total" />
    </div>
  </AppLayout>
</template>
