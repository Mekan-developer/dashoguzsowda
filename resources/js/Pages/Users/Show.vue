<script setup>
import { computed } from 'vue'
import { router, usePage, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import StatCard from '@/Components/StatCard.vue'
import EmptyState from '@/Components/EmptyState.vue'
import Icon from '@/Components/Icon.vue'
import { th, td, tr, thead } from '@/table'
import { confirmDialog } from '@/confirm'

const { t } = useI18n()

// Блокировка пользователя закрыта от менеджера (routes/web.php → role:admin)
const isAdmin = computed(() => usePage().props.auth?.user?.role === 'admin')

const props = defineProps({ user: Object, userListings: Array, stats: Object })

const displayName = computed(() => props.user.name || props.user.phone)

const basicRows = computed(() => [
    { label: t('users.gender'),    value: props.user.gender === 'male' ? t('users.male') : props.user.gender === 'female' ? t('users.female') : '—' },
    { label: t('users.birthDate'), value: props.user.birth_date ? formatDate(props.user.birth_date) : '—', data: true },
    { label: t('common.region'),   value: props.user.region?.name_ru || '—' },
    { label: t('common.city'),     value: props.user.city?.name_ru || '—' },
])
const accountRows = computed(() => [
    { label: t('common.phone'),       value: props.user.phone || '—', data: true },
    { label: t('common.tariff'),      value: props.user.tariff?.name || t('users.freeTariff') },
    { label: t('users.colRegDate'),   value: formatDate(props.user.created_at), data: true },
])

async function block() {
    if (!(await confirmDialog(t('users.blockConfirm', { name: displayName.value }), { title: t('users.blockTitle') }))) return
    router.patch(route('users.block', props.user.id))
}
async function unblock() {
    if (!(await confirmDialog(t('users.unblockConfirm', { name: displayName.value }), { danger: false }))) return
    router.patch(route('users.unblock', props.user.id))
}


function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('ru', { day: '2-digit', month: '2-digit', year: '2-digit' })
}
</script>

<template>
  <AppLayout>
    <template #breadcrumb>
      <Link :href="route('users.index')" class="transition-colors duration-150 hover:text-link">{{ t('nav.users') }}</Link>
      <span>/</span>
      <span class="truncate">#{{ user.id }}</span>
    </template>
    <template #header>
      <span class="flex items-center gap-3">
        <span class="truncate">{{ user.name || user.phone }}</span>
        <StatusBadge :status="user.status" />
      </span>
    </template>
    <template v-if="user.name" #description><span class="font-data">{{ user.phone }}</span></template>

    <!-- Действия — только admin (routes/web.php → role:admin) -->
    <template v-if="isAdmin" #actions>
      <button v-if="user.status === 'active'" type="button" class="btn btn-red-soft" @click="block">
        <Icon kind="lock" :size="16" />{{ t('actions.block') }}
      </button>
      <button v-else type="button" class="btn btn-green-soft" @click="unblock">
        <Icon kind="check" :size="16" />{{ t('actions.unblock') }}
      </button>
    </template>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[320px_minmax(0,1fr)]">
      <!-- Левая колонка: сведения -->
      <div class="space-y-5">
        <section class="card">
          <h2 class="card-title border-b border-[var(--card-border)] px-5 py-4">{{ t('users.basicInfo') }}</h2>
          <dl class="divide-y divide-[var(--card-border)] px-5">
            <div v-for="row in basicRows" :key="row.label" class="flex justify-between gap-4 py-3 text-[13.5px]">
              <dt class="text-[var(--text-muted)]">{{ row.label }}</dt>
              <dd class="text-right font-medium text-[var(--text)]" :class="row.data ? 'font-data tabular-nums' : ''">{{ row.value }}</dd>
            </div>
          </dl>
        </section>

        <section class="card">
          <h2 class="card-title border-b border-[var(--card-border)] px-5 py-4">{{ t('users.accountInfo') }}</h2>
          <dl class="divide-y divide-[var(--card-border)] px-5">
            <div v-for="row in accountRows" :key="row.label" class="flex justify-between gap-4 py-3 text-[13.5px]">
              <dt class="text-[var(--text-muted)]">{{ row.label }}</dt>
              <dd class="text-right font-medium text-[var(--text)]" :class="row.data ? 'font-data tabular-nums' : ''">{{ row.value }}</dd>
            </div>
          </dl>
          <div v-if="user.blocked_reason || user.note" class="space-y-3 border-t border-[var(--card-border)] p-5">
            <div v-if="user.blocked_reason" class="rounded-[8px] bg-red-500/10 px-3.5 py-3 text-[13px] text-red-700 dark:text-red-300">
              <span class="font-semibold">{{ t('users.blockReason') }}</span> {{ user.blocked_reason }}
            </div>
            <div v-if="user.note" class="rounded-[8px] bg-[var(--nav-hover)] px-3.5 py-3 text-[13px] text-[var(--text-secondary)]">
              <span class="font-semibold">{{ t('users.note') }}</span> {{ user.note }}
            </div>
          </div>
        </section>
      </div>

      <!-- Правая колонка: активность -->
      <div class="min-w-0 space-y-5">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <StatCard :label="t('users.listingsShort')"   :value="stats.listings"   icon="listing" />
          <StatCard :label="t('users.videosShort')"     :value="stats.videos"     icon="video" />
          <StatCard :label="t('users.complaintsShort')" :value="stats.complaints" icon="flag" :tone="stats.complaints ? 'danger' : 'accent'" />
        </div>

        <section class="card overflow-hidden">
          <h2 class="card-title border-b border-[var(--card-border)] px-5 py-4">{{ t('users.userListings') }}</h2>
          <div v-if="userListings?.length" class="overflow-x-auto">
            <table class="w-full min-w-[560px]">
              <thead>
                <tr :class="thead">
                  <th :class="th" class="w-[72px]">{{ t('common.id') }}</th>
                  <th :class="th">{{ t('common.title') }}</th>
                  <th :class="th" class="hidden md:table-cell">{{ t('common.category') }}</th>
                  <th :class="th">{{ t('common.status') }}</th>
                  <th :class="th" class="hidden md:table-cell">{{ t('common.date') }}</th>
                  <th :class="th" class="text-right">{{ t('common.actions') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="l in userListings" :key="l.id" class="h-[60px] border-b border-[var(--card-border)] transition-colors duration-150 last:border-b-0 hover:bg-[var(--nav-hover)]">
                  <td :class="td" class="font-data tabular-nums text-[var(--text-muted)]">{{ l.id }}</td>
                  <td :class="td"><Link :href="route('listings.show', l.id)" class="block max-w-[280px] truncate font-semibold text-[var(--text)] hover:text-link">{{ l.title }}</Link></td>
                  <td :class="td" class="hidden text-[var(--text-secondary)] md:table-cell">{{ l.category?.name_ru || '—' }}</td>
                  <td :class="td"><StatusBadge :status="l.status" /></td>
                  <td :class="td" class="hidden font-data tabular-nums text-[var(--text-secondary)] md:table-cell">{{ formatDate(l.created_at) }}</td>
                  <td :class="td" class="text-right">
                    <Link :href="route('listings.show', l.id)" class="icon-btn" :title="t('actions.show')" :aria-label="t('actions.show')">
                      <Icon kind="eye" :size="16" />
                    </Link>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <EmptyState v-else compact icon="listing" :title="t('users.noListings')" />
        </section>
      </div>
    </div>
  </AppLayout>
</template>

