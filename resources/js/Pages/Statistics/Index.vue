<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/StatCard.vue'
import EmptyState from '@/Components/EmptyState.vue'

const { t } = useI18n()

const props = defineProps({
    stats:       Object,
    tariffStats: Array,
    filters:     Object,
})

const from = ref(props.filters?.from || '')
const to   = ref(props.filters?.to   || '')

const presets = ['day', 'week', 'month']
const period = ref(
    props.filters?.from || props.filters?.to
        ? 'custom'
        : (props.filters?.period || 'day')
)

// Доли статусов модерации — для полосы и легенды
const moderation = computed(() => {
    const l = props.stats?.listings || {}
    const rows = [
        { key: 'pending',  label: t('dashboard.onModeration'),     value: Number(l.pending || 0),  bar: 'bg-amber-500' },
        { key: 'approved', label: t('statistics.approvedPlural'),  value: Number(l.approved || 0), bar: 'bg-emerald-500' },
        { key: 'rejected', label: t('statistics.rejectedPlural'),  value: Number(l.rejected || 0), bar: 'bg-red-500' },
    ]
    const sum = rows.reduce((a, r) => a + r.value, 0) || 1
    return rows.map(r => ({ ...r, pct: Math.round(r.value / sum * 100) }))
})

function setPeriod(p) {
    period.value = p
    from.value = ''
    to.value = ''
    router.get(route('statistics.index'), { period: p }, { preserveState: true })
}

function applyFilter() {
    if (!from.value && !to.value) return
    period.value = 'custom'
    router.get(route('statistics.index'), { from: from.value || undefined, to: to.value || undefined }, { preserveState: true })
}
</script>

<template>
  <AppLayout>
    <template #header>{{ t('nav.statistics') }}</template>
    <template #description>{{ t('statistics.description') }}</template>

    <!-- Период: пресеты + свой диапазон — одна строка -->
    <div class="mb-6 flex flex-wrap items-center gap-2.5">
      <div class="seg" role="group" :aria-label="t('statistics.period')">
        <button
          v-for="p in presets" :key="p"
          type="button"
          @click="setPeriod(p)"
          :aria-pressed="period === p"
          class="seg-item"
          :class="period === p ? 'seg-item-active' : ''"
        >{{ t('statistics.' + p) }}</button>
      </div>
      <div class="flex items-center gap-1.5 rounded-[8px]" :class="period === 'custom' ? 'ring-2 ring-[var(--accent-tint)]' : ''">
        <input v-model="from" type="date" :max="to || undefined" :aria-label="t('statistics.from')" :title="t('statistics.from')" class="filter-select !pr-3 font-data" />
        <span class="text-[var(--text-muted)]">–</span>
        <input v-model="to" type="date" :min="from || undefined" :aria-label="t('statistics.to')" :title="t('statistics.to')" class="filter-select !pr-3 font-data" />
      </div>
      <button type="button" @click="applyFilter" :disabled="!from && !to" class="btn" :class="period === 'custom' ? 'btn-primary' : 'btn-secondary'">
        {{ t('actions.apply') }}
      </button>
    </div>

    <!-- Главные цифры -->
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <StatCard :label="t('statistics.users')" :value="stats.users.total" icon="users" tone="info"
        :sub="t('statistics.blocked') + ': ' + stats.users.blocked" />
      <StatCard :label="t('statistics.newUsers')" :value="stats.users.period" icon="users" tone="success"
        :sub="t('statistics.forPeriod')" />
      <StatCard :label="t('statistics.listings')" :value="stats.listings.total" icon="listing" />
      <StatCard :label="t('statistics.newListings')" :value="stats.listings.period" icon="listing" tone="success"
        :sub="t('statistics.forPeriod')" />
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_400px]">
      <!-- Объявления по статусам: одна полоса долей + легенда с числами -->
      <section class="card p-5">
        <h2 class="card-title">{{ t('statistics.moderation') }}</h2>
        <p class="mb-5 text-[12.5px] text-[var(--text-muted)]">{{ t('statistics.moderationHint') }}</p>
        <div class="flex h-3 overflow-hidden rounded-full bg-[var(--nav-hover)]">
          <div v-for="seg in moderation" :key="seg.key" :class="seg.bar" :style="{ width: seg.pct + '%' }" :title="`${seg.label}: ${seg.value}`"></div>
        </div>
        <dl class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
          <div v-for="seg in moderation" :key="seg.key" class="rounded-[8px] border border-[var(--card-border)] p-3">
            <dt class="flex items-center gap-2 text-[12.5px] text-[var(--text-secondary)]"><span class="h-2 w-2 rounded-full" :class="seg.bar"></span>{{ seg.label }}</dt>
            <dd class="mt-1 flex items-baseline gap-2">
              <span class="font-data text-[22px] font-semibold tabular-nums text-[var(--text)]">{{ seg.value.toLocaleString('ru-RU') }}</span>
              <span class="font-data text-[12px] tabular-nums text-[var(--text-muted)]">{{ seg.pct }}%</span>
            </dd>
          </div>
        </dl>

        <h2 class="card-title mt-7">{{ t('statistics.videosTitle') }}</h2>
        <dl class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
          <div v-for="row in [
            { label: t('common.total'), value: stats.videos.total },
            { label: t('statistics.approvedPlural'), value: stats.videos.approved },
            { label: t('statistics.likes'), value: stats.videos.likes },
          ]" :key="row.label" class="rounded-[8px] border border-[var(--card-border)] p-3">
            <dt class="text-[12.5px] text-[var(--text-secondary)]">{{ row.label }}</dt>
            <dd class="mt-1 font-data text-[22px] font-semibold tabular-nums text-[var(--text)]">{{ Number(row.value ?? 0).toLocaleString('ru-RU') }}</dd>
          </div>
        </dl>
      </section>

      <!-- Пользователи по тарифам -->
      <section class="card p-5">
        <h2 class="card-title">{{ t('statistics.tariffs') }}</h2>
        <p class="mb-5 text-[12.5px] text-[var(--text-muted)]">{{ t('statistics.tariffsHint') }}</p>
        <div v-if="tariffStats?.length" class="space-y-4">
          <div v-for="ts in tariffStats" :key="ts.name">
            <div class="mb-1.5 flex items-baseline justify-between gap-3 text-[13px]">
              <span class="truncate font-medium text-[var(--text)]">{{ ts.name }}</span>
              <span class="flex-none font-data tabular-nums text-[var(--text-secondary)]">{{ ts.count }} <span class="text-[var(--text-muted)]">· {{ ts.pct }}%</span></span>
            </div>
            <div class="h-1.5 overflow-hidden rounded-full bg-[var(--nav-hover)]">
              <div class="h-full rounded-full bg-blue" :style="{ width: ts.pct + '%' }"></div>
            </div>
          </div>
        </div>
        <EmptyState v-else compact icon="coin" :title="t('statistics.noTariffs')" />
      </section>
    </div>
  </AppLayout>
</template>
