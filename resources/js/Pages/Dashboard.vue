<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/StatCard.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import Icon from '@/Components/Icon.vue'

const { t, locale } = useI18n()

const props = defineProps({
    stats:            Object,
    charts:           Object,
    topCategories:    Array,
    recentListings:   Array,
    recentComplaints: Array,
})

const sum = obj => Object.values(obj || {}).reduce((a, b) => a + Number(b || 0), 0)
const users7d    = computed(() => sum(props.charts?.users_7d))
const listings7d = computed(() => sum(props.charts?.listings_7d))

// Первая строка — объём площадки, вторая — то, что ждёт решения персонала
const volumeCards = computed(() => [
    { label: t('dashboard.totalUsers'), value: props.stats?.users,    icon: 'users',   tone: 'info',
      sub: t('dashboard.last7d', { n: users7d.value }), subTone: users7d.value ? 'success' : 'muted', href: route('users.index') },
    { label: t('dashboard.listings'),   value: props.stats?.listings, icon: 'listing', tone: 'accent',
      sub: t('dashboard.last7d', { n: listings7d.value }), subTone: listings7d.value ? 'success' : 'muted', href: route('listings.index') },
    { label: t('dashboard.videos'),     value: props.stats?.videos,   icon: 'video',   tone: 'accent', href: route('videos.index') },
])
const queueCards = computed(() => [
    { label: t('dashboard.onModeration'),  value: props.stats?.listings_pending, icon: 'clock', href: route('listings.index', { status: 'pending' }) },
    { label: t('dashboard.videosPending'), value: props.stats?.videos_pending,   icon: 'clock', href: route('videos.index', { status: 'pending' }) },
    { label: t('dashboard.newComplaints'), value: props.stats?.complaints_new,   icon: 'flag',  href: route('complaints.index') },
].map(c => ({
    ...c,
    tone: c.value ? 'warning' : 'success',
    sub: c.value ? t('dashboard.needsAction') : t('dashboard.allClear'),
    subTone: c.value ? 'warning' : 'muted',
})))

// ── График за 7 дней ──────────────────────────────────────────────────────
const days = computed(() => Array.from({ length: 7 }, (_, i) => {
    const d = new Date()
    d.setDate(d.getDate() - 6 + i)
    const key = d.toISOString().split('T')[0]
    return {
        key,
        label: d.toLocaleDateString(locale.value, { weekday: 'short' }),
        date:  d.toLocaleDateString(locale.value, { day: '2-digit', month: '2-digit' }),
        users:    Number(props.charts?.users_7d?.[key] || 0),
        listings: Number(props.charts?.listings_7d?.[key] || 0),
    }
}))
// Верх шкалы — «круглое» число, чтобы сетка читалась
const scaleMax = computed(() => {
    const max = Math.max(1, ...days.value.flatMap(d => [d.users, d.listings]))
    const step = Math.pow(10, Math.floor(Math.log10(max)))
    return Math.ceil(max / step) * step
})
const gridLines = computed(() => [1, 0.5, 0].map(f => Math.round(scaleMax.value * f)))
const chartEmpty = computed(() => days.value.every(d => !d.users && !d.listings))

const topMax = computed(() => props.topCategories?.[0]?.listings_count || 1)

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('ru', { day: '2-digit', month: '2-digit', year: '2-digit' })
}
</script>

<template>
  <AppLayout>
    <template #header>{{ t('nav.dashboard') }}</template>
    <template #description>{{ t('dashboard.overview') }}</template>


    <!-- KPI: объём площадки + очередь модерации -->
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-6">
      <StatCard v-for="card in volumeCards" :key="card.label" v-bind="card" class="xl:col-span-2" />
    </div>

    <h2 class="mb-3 text-[13px] font-semibold uppercase tracking-[.06em] text-[var(--text-muted)]">{{ t('dashboard.moderationQueue') }}</h2>
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
      <StatCard v-for="card in queueCards" :key="card.label" v-bind="card" />
    </div>

    <div class="mb-6 grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
      <!-- Активность за 7 дней -->
      <section class="card">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--card-border)] px-5 py-4">
          <h2 class="card-title">{{ t('dashboard.activity7d') }}</h2>
          <div class="flex items-center gap-4 text-[12.5px] text-[var(--text-secondary)]">
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-[3px] bg-blue"></span>{{ t('dashboard.legendUsers') }}</span>
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-[3px] bg-[#8FA3C8]"></span>{{ t('dashboard.legendListings') }}</span>
          </div>
        </div>

        <EmptyState v-if="chartEmpty" compact icon="chart" :title="t('dashboard.chartEmpty')" />
        <div v-else class="flex gap-3 px-5 pb-4 pt-5">
          <!-- Шкала -->
          <div class="flex h-[180px] flex-col justify-between pb-0 text-right font-data text-[11px] tabular-nums text-[var(--text-muted)]">
            <span v-for="v in gridLines" :key="v" class="-translate-y-1/2 leading-none">{{ v }}</span>
          </div>
          <div class="min-w-0 flex-1">
            <div class="relative flex h-[180px] items-end gap-2">
              <!-- Линии сетки -->
              <div class="pointer-events-none absolute inset-0 flex flex-col justify-between">
                <div v-for="v in gridLines" :key="v" class="border-t border-dashed border-[var(--card-border)]"></div>
              </div>
              <div
                v-for="day in days" :key="day.key"
                class="group relative flex h-full flex-1 items-end justify-center gap-1 rounded-[6px] transition-colors duration-150 hover:bg-[var(--nav-hover)]"
                :title="t('dashboard.perDay', { date: day.date, users: day.users, listings: day.listings })"
              >
                <div class="w-full max-w-[14px] rounded-t-[3px] bg-blue" :style="{ height: Math.max(2, day.users / scaleMax * 100) + '%' }"></div>
                <div class="w-full max-w-[14px] rounded-t-[3px] bg-[#8FA3C8]" :style="{ height: Math.max(2, day.listings / scaleMax * 100) + '%' }"></div>
              </div>
            </div>
            <div class="mt-2 flex gap-2">
              <div v-for="day in days" :key="day.key" class="flex-1 text-center text-[11.5px] capitalize text-[var(--text-muted)]">{{ day.label }}</div>
            </div>
          </div>
        </div>
      </section>

      <!-- Топ категорий: одна шкала одного цвета — сравниваются длины, а не цвета -->
      <section class="card">
        <div class="border-b border-[var(--card-border)] px-5 py-4">
          <h2 class="card-title">{{ t('dashboard.topCategories') }}</h2>
        </div>
        <div v-if="topCategories?.length" class="space-y-4 px-5 py-5">
          <div v-for="cat in topCategories" :key="cat.id">
            <div class="mb-1.5 flex justify-between gap-3 text-[13px]">
              <span class="truncate font-medium text-[var(--text)]">{{ cat.name_ru }}</span>
              <span class="font-data tabular-nums text-[var(--text-secondary)]">{{ cat.listings_count }}</span>
            </div>
            <div class="h-1.5 rounded-full bg-[var(--nav-hover)]">
              <div class="h-full rounded-full bg-blue" :style="{ width: (cat.listings_count / topMax * 100) + '%' }"></div>
            </div>
          </div>
        </div>
        <EmptyState v-else compact icon="tag" :title="t('common.noData')" />
      </section>
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
      <!-- Последние объявления -->
      <section class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-[var(--card-border)] px-5 py-4">
          <h2 class="card-title">{{ t('dashboard.recentListings') }}</h2>
          <Link :href="route('listings.index')" class="text-[13px] font-medium text-link hover:underline">{{ t('dashboard.allLink') }}</Link>
        </div>
        <div v-if="recentListings?.length" class="overflow-x-auto">
          <table class="w-full min-w-[560px]">
            <tbody>
              <tr v-for="listing in recentListings" :key="listing.id" class="border-b border-[var(--card-border)] transition-colors duration-150 last:border-b-0 hover:bg-[var(--nav-hover)]">
                <td class="w-14 px-5 py-3 font-data text-[12.5px] tabular-nums text-[var(--text-muted)]">{{ listing.id }}</td>
                <td class="px-2 py-3">
                  <Link :href="route('listings.show', listing.id)" class="block max-w-[280px] truncate text-[13.5px] font-semibold text-[var(--text)] hover:text-link">{{ listing.title }}</Link>
                  <div class="truncate text-[12px] text-[var(--text-muted)]">{{ listing.user?.name || listing.user?.phone || '—' }} · {{ listing.category?.name_ru || '—' }}</div>
                </td>
                <td class="px-3 py-3"><StatusBadge :status="listing.status" /></td>
                <td class="whitespace-nowrap px-5 py-3 text-right font-data text-[12.5px] tabular-nums text-[var(--text-muted)]">{{ formatDate(listing.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <EmptyState v-else compact icon="listing" :title="t('dashboard.noListings')" />
      </section>

      <!-- Новые жалобы -->
      <section class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-[var(--card-border)] px-5 py-4">
          <h2 class="card-title">{{ t('dashboard.newComplaints') }}</h2>
          <Link :href="route('complaints.index')" class="text-[13px] font-medium text-link hover:underline">{{ t('dashboard.allLink') }}</Link>
        </div>
        <div v-if="recentComplaints?.length" class="divide-y divide-[var(--card-border)]">
          <Link
            v-for="c in recentComplaints" :key="c.id"
            :href="route('complaints.index')"
            class="flex items-start gap-3 px-5 py-3.5 transition-colors duration-150 hover:bg-[var(--nav-hover)]"
          >
            <span class="mt-0.5 flex h-8 w-8 flex-none items-center justify-center rounded-full bg-red-500/10 text-red-600 dark:text-red-400">
              <Icon kind="flag" :size="15" />
            </span>
            <div class="min-w-0 flex-1">
              <p class="line-clamp-2 text-[13px] text-[var(--text)]">
                <span class="font-semibold">{{ c.user?.name || c.user?.phone }}</span>
                {{ t('dashboard.complainedAbout') }} «{{ c.listing?.title || '…' }}»
              </p>
              <p class="mt-0.5 truncate text-[12px] text-[var(--text-muted)]">
                {{ c.complaint_reason?.name_ru || '—' }} · <span class="font-data">{{ formatDate(c.created_at) }}</span>
              </p>
            </div>
          </Link>
        </div>
        <EmptyState v-else compact icon="check" :title="t('dashboard.noNewComplaints')" />
      </section>
    </div>
  </AppLayout>
</template>
