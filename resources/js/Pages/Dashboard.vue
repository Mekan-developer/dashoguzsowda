<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/StatCard.vue'
import StatusBadge from '@/Components/StatusBadge.vue'

const { t, locale } = useI18n()

const props = defineProps({
    stats:            Object,
    charts:           Object,
    topCategories:    Array,
    recentListings:   Array,
    recentComplaints: Array,
})

const kpiCards = computed(() => [
    { label: t('dashboard.totalUsers'),    value: props.stats?.users,            iconBg: 'rgba(67,97,238,.12)',  iconColor: '#4361ee', icon: 'users' },
    { label: t('dashboard.listings'),      value: props.stats?.listings,         iconBg: 'rgba(6,214,160,.12)',  iconColor: '#06d6a0', icon: 'listing' },
    { label: t('dashboard.onModeration'),  value: props.stats?.listings_pending, iconBg: 'rgba(251,133,0,.12)',  iconColor: '#fb8500', icon: 'clock' },
    { label: t('dashboard.videos'),        value: props.stats?.videos,           iconBg: 'rgba(0,180,216,.12)',  iconColor: '#00b4d8', icon: 'video' },
    { label: t('dashboard.videosPending'), value: props.stats?.videos_pending,   iconBg: 'rgba(247,37,133,.12)', iconColor: '#f72585', icon: 'clock' },
    { label: t('dashboard.newComplaints'), value: props.stats?.complaints_new,   iconBg: 'rgba(239,68,68,.12)',  iconColor: '#ef4444', icon: 'flag' },
])

const maxVal = computed(() => {
    const all = [
        ...Object.values(props.charts?.users_7d || {}),
        ...Object.values(props.charts?.listings_7d || {}),
    ]
    return Math.max(1, ...all)
})

const days = computed(() => {
    return Array.from({ length: 7 }, (_, i) => {
        const d = new Date()
        d.setDate(d.getDate() - 6 + i)
        const key = d.toISOString().split('T')[0]
        return {
            label:    d.toLocaleDateString(locale.value, { weekday: 'short' }),
            key,
            users:    props.charts?.users_7d?.[key]    || 0,
            listings: props.charts?.listings_7d?.[key] || 0,
        }
    })
})

const catColors = ['#4361ee', '#06d6a0', '#fb8500', '#00b4d8', '#7c3aed']
const topMax = computed(() => props.topCategories?.[0]?.listings_count || 1)

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('ru', { day: '2-digit', month: '2-digit', year: '2-digit' })
}
</script>

<template>
  <AppLayout>
    <template #header>Dashboard</template>

    <!-- KPI -->
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6 mb-5">
      <StatCard v-for="card in kpiCards" :key="card.label" :label="card.label" :value="card.value" :iconBg="card.iconBg" :iconColor="card.iconColor">
        <template #icon>
          <svg v-if="card.icon === 'users'" :style="{color: card.iconColor}" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4" stroke-width="2"/><path stroke-width="2" d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
          <svg v-else-if="card.icon === 'listing'" :style="{color: card.iconColor}" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" stroke-linecap="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
          <svg v-else-if="card.icon === 'clock'" :style="{color: card.iconColor}" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-width="2" stroke-linecap="round" d="M12 6v6l4 2"/></svg>
          <svg v-else-if="card.icon === 'video'" :style="{color: card.iconColor}" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polygon stroke-width="2" points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" stroke-width="2"/></svg>
          <svg v-else-if="card.icon === 'flag'" :style="{color: card.iconColor}" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" stroke-linecap="round" d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15" stroke-width="2"/></svg>
        </template>
      </StatCard>
    </div>

    <!-- Charts row -->
    <div class="grid gap-5 mb-5" style="grid-template-columns: 1fr 380px;">
      <!-- Bar chart -->
      <div class="rounded-card bg-white shadow-soft dark:bg-dcard">
        <div class="flex items-center justify-between px-[22px] py-[18px] border-b border-line dark:border-dline">
          <span class="text-[15px] font-extrabold text-ink dark:text-slate-100">{{ t('dashboard.activity7d') }}</span>
          <div class="flex items-center gap-4 text-[12px] font-bold">
            <span class="flex items-center gap-1.5 text-muted"><span class="h-2.5 w-2.5 rounded-sm bg-blue inline-block"></span>{{ t('dashboard.legendUsers') }}</span>
            <span class="flex items-center gap-1.5 text-muted"><span class="h-2.5 w-2.5 rounded-sm bg-green inline-block"></span>{{ t('dashboard.legendListings') }}</span>
          </div>
        </div>
        <div class="px-6 py-5 flex items-end gap-2" style="height:220px;">
          <div v-for="day in days" :key="day.key" class="flex flex-1 flex-col items-center gap-1">
            <div class="flex w-full items-end justify-center gap-1" style="height:160px">
              <div class="w-3 rounded-t-[3px] bg-blue transition-all" :style="{ height: Math.max(2, day.users / maxVal * 160) + 'px' }"></div>
              <div class="w-3 rounded-t-[3px] bg-green transition-all" :style="{ height: Math.max(2, day.listings / maxVal * 160) + 'px' }"></div>
            </div>
            <div class="text-[10px] font-bold capitalize text-muted">{{ day.label }}</div>
          </div>
        </div>
      </div>

      <!-- Top categories -->
      <div class="rounded-card bg-white shadow-soft dark:bg-dcard">
        <div class="px-[22px] py-[18px] border-b border-line dark:border-dline">
          <span class="text-[15px] font-extrabold text-ink dark:text-slate-100">{{ t('dashboard.topCategories') }}</span>
        </div>
        <div class="px-[22px] py-4">
          <div v-for="(cat, i) in topCategories" :key="cat.id" class="mb-4">
            <div class="mb-1 flex justify-between">
              <span class="text-[13px] font-bold text-ink dark:text-slate-200">{{ cat.name_ru }}</span>
              <span class="font-data text-[12px] font-bold text-muted">{{ cat.listings_count }}</span>
            </div>
            <div class="h-2 rounded-full bg-surface dark:bg-dbg">
              <div class="h-full rounded-full transition-all" :style="{ width: (cat.listings_count / topMax * 100) + '%', background: catColors[i] }"></div>
            </div>
          </div>
          <p v-if="!topCategories?.length" class="text-[13px] text-muted text-center py-4">{{ t('common.noData') }}</p>
        </div>
      </div>
    </div>

    <!-- Recent Listings -->
    <div class="rounded-card bg-white shadow-soft dark:bg-dcard mb-5 overflow-hidden">
      <div class="flex items-center justify-between px-[22px] py-[18px] border-b border-line dark:border-dline">
        <span class="text-[15px] font-extrabold text-ink dark:text-slate-100">{{ t('dashboard.recentListings') }}</span>
        <Link :href="route('listings.index')" class="text-[12px] font-bold text-blue hover:underline">{{ t('dashboard.allLink') }}</Link>
      </div>
      <table class="w-full">
        <thead class="bg-surface/50 dark:bg-dbg/50">
          <tr>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline w-16">{{ t('common.id') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('dashboard.listing') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.author') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.category') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.status') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.date') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="listing in recentListings" :key="listing.id" class="hover:bg-surface/30 dark:hover:bg-white/3 transition">
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline font-data text-muted">{{ listing.id }}</td>
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline">
              <Link :href="route('listings.show', listing.id)" class="font-bold text-ink dark:text-slate-200 hover:text-blue">{{ listing.title }}</Link>
            </td>
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline text-muted">{{ listing.user?.name || listing.user?.phone || '—' }}</td>
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline text-muted">{{ listing.category?.name_ru || '—' }}</td>
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline"><StatusBadge :status="listing.status" /></td>
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline font-data text-muted">{{ formatDate(listing.created_at) }}</td>
          </tr>
          <tr v-if="!recentListings?.length"><td colspan="6" class="px-4 py-8 text-center text-[13px] text-muted">{{ t('dashboard.noListings') }}</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Recent Complaints -->
    <div class="rounded-card bg-white shadow-soft dark:bg-dcard overflow-hidden">
      <div class="flex items-center justify-between px-[22px] py-[18px] border-b border-line dark:border-dline">
        <span class="text-[15px] font-extrabold text-ink dark:text-slate-100">{{ t('dashboard.newComplaints') }}</span>
        <Link :href="route('complaints.index')" class="text-[12px] font-bold text-blue hover:underline">{{ t('dashboard.allLink') }}</Link>
      </div>
      <div class="divide-y divide-line dark:divide-dline">
        <div v-for="c in recentComplaints" :key="c.id" class="flex items-center gap-3 px-[22px] py-4">
          <div class="flex-shrink-0 flex h-8 w-8 items-center justify-center rounded-full bg-red/10 text-red text-[13px] font-bold">!</div>
          <div class="flex-1 min-w-0">
            <p class="text-[13px] font-bold text-ink dark:text-slate-200">
              <span class="text-blue">{{ c.user?.name || c.user?.phone }}</span>
              {{ t('dashboard.complainedAbout') }} «{{ c.listing?.title || '...' }}»
            </p>
            <p class="text-[12px] text-muted">{{ c.complaint_reason?.name_ru || '—' }}</p>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0">
            <span class="font-data text-[11px] text-muted">{{ formatDate(c.created_at) }}</span>
            <Link :href="route('complaints.index')" class="rounded-[7px] bg-blue-light px-3 py-1.5 text-[11px] font-bold text-blue hover:bg-blue hover:text-white transition">{{ t('dashboard.review') }}</Link>
          </div>
        </div>
        <div v-if="!recentComplaints?.length" class="px-[22px] py-8 text-center text-[13px] text-muted">{{ t('dashboard.noNewComplaints') }}</div>
      </div>
    </div>
  </AppLayout>
</template>
