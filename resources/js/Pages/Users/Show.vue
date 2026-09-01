<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'

const { t } = useI18n()

// Блокировка пользователя закрыта от менеджера (routes/web.php → role:admin)
const isAdmin = computed(() => usePage().props.auth?.user?.role === 'admin')

const props = defineProps({ user: Object, userListings: Array, stats: Object })

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('ru', { day: '2-digit', month: '2-digit', year: '2-digit' })
}
</script>

<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center gap-2">
        <Link :href="route('users.index')" class="text-muted hover:text-blue transition text-[14px]">{{ t('nav.users') }}</Link>
        <span class="text-muted">/</span>
        <span>{{ user.name || user.phone }}</span>
      </div>
    </template>

    <div class="grid gap-5" style="grid-template-columns: 320px 1fr;">
      <!-- Profile Card -->
      <div class="space-y-4">
        <div class="rounded-card bg-white shadow-soft dark:bg-dcard p-6">
          <div class="text-center mb-5">
            <div class="mx-auto h-16 w-16 rounded-full bg-blue flex items-center justify-center text-[24px] font-extrabold text-white mb-3">
              {{ (user.name || user.phone || '?').charAt(0).toUpperCase() }}
            </div>
            <h2 class="text-[17px] font-extrabold text-ink dark:text-slate-100">{{ user.name || '—' }}</h2>
            <p class="text-[13px] font-data text-muted">{{ user.phone }}</p>
            <div class="mt-2">
              <StatusBadge :status="user.status" />
            </div>
          </div>

          <div class="space-y-2.5 text-[13px]">
            <div class="flex justify-between"><span class="text-muted font-semibold">{{ t('users.gender') }}</span><span class="font-bold text-ink dark:text-slate-200">{{ user.gender === 'male' ? t('users.male') : user.gender === 'female' ? t('users.female') : '—' }}</span></div>
            <div class="flex justify-between"><span class="text-muted font-semibold">{{ t('users.birthDate') }}</span><span class="font-bold text-ink dark:text-slate-200 font-data">{{ user.birth_date ? formatDate(user.birth_date) : '—' }}</span></div>
            <div class="flex justify-between"><span class="text-muted font-semibold">{{ t('common.region') }}</span><span class="font-bold text-ink dark:text-slate-200">{{ user.region?.name_ru || '—' }}</span></div>
            <div class="flex justify-between"><span class="text-muted font-semibold">{{ t('common.city') }}</span><span class="font-bold text-ink dark:text-slate-200">{{ user.city?.name_ru || '—' }}</span></div>
            <div class="flex justify-between"><span class="text-muted font-semibold">{{ t('common.tariff') }}</span><span class="font-bold text-ink dark:text-slate-200">{{ user.tariff?.name || t('users.freeTariff') }}</span></div>
            <div class="flex justify-between"><span class="text-muted font-semibold">{{ t('users.colRegDate') }}</span><span class="font-bold font-data text-ink dark:text-slate-200">{{ formatDate(user.created_at) }}</span></div>
          </div>

          <div v-if="user.blocked_reason" class="mt-4 rounded-btn bg-red/10 p-3 text-[12px] font-semibold text-red">
            <strong>{{ t('users.blockReason') }}</strong> {{ user.blocked_reason }}
          </div>

          <div v-if="user.note" class="mt-4 rounded-btn bg-surface p-3 text-[12px] text-muted dark:bg-dbg">
            <strong>{{ t('users.note') }}</strong> {{ user.note }}
          </div>
        </div>

        <!-- Stats -->
        <div class="rounded-card bg-white shadow-soft dark:bg-dcard p-5">
          <h3 class="text-[13px] font-extrabold text-ink dark:text-slate-100 mb-3 uppercase tracking-wide">{{ t('users.stats') }}</h3>
          <div class="grid grid-cols-3 gap-3 text-center">
            <div>
              <div class="font-data text-[20px] font-black text-blue">{{ stats.listings }}</div>
              <div class="text-[11px] text-muted font-semibold">{{ t('users.listingsShort') }}</div>
            </div>
            <div>
              <div class="font-data text-[20px] font-black text-teal">{{ stats.videos }}</div>
              <div class="text-[11px] text-muted font-semibold">{{ t('users.videosShort') }}</div>
            </div>
            <div>
              <div class="font-data text-[20px] font-black text-red">{{ stats.complaints }}</div>
              <div class="text-[11px] text-muted font-semibold">{{ t('users.complaintsShort') }}</div>
            </div>
          </div>
        </div>

        <!-- Actions — только admin (routes/web.php → role:admin) -->
        <div v-if="isAdmin" class="rounded-card bg-white shadow-soft dark:bg-dcard p-5 space-y-2">
          <button
            v-if="user.status === 'active'"
            @click="router.patch(route('users.block', user.id))"
            class="w-full rounded-btn border-2 border-red/20 bg-red/5 py-[9px] text-[13px] font-bold text-red hover:bg-red hover:text-white transition"
          >{{ t('actions.block') }}</button>
          <button
            v-else
            @click="router.patch(route('users.unblock', user.id))"
            class="w-full rounded-btn border-2 border-green/20 bg-green/5 py-[9px] text-[13px] font-bold text-green hover:bg-green hover:text-white transition"
          >{{ t('actions.unblock') }}</button>
        </div>
      </div>

      <!-- Listings -->
      <div class="rounded-card bg-white shadow-soft dark:bg-dcard overflow-hidden">
        <div class="px-[22px] py-[18px] border-b border-line dark:border-dline">
          <span class="text-[15px] font-extrabold text-ink dark:text-slate-100">{{ t('users.userListings') }}</span>
        </div>
        <table class="w-full">
          <thead class="bg-surface/50 dark:bg-dbg/50">
            <tr>
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline w-16">{{ t('common.id') }}</th>
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.title') }}</th>
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.category') }}</th>
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.status') }}</th>
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.date') }}</th>
              <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="l in userListings" :key="l.id" class="hover:bg-surface/30 dark:hover:bg-white/3">
              <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline font-data text-muted">{{ l.id }}</td>
              <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline font-bold text-ink dark:text-slate-200">{{ l.title }}</td>
              <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline text-muted">{{ l.category?.name_ru || '—' }}</td>
              <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline"><StatusBadge :status="l.status" /></td>
              <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline font-data text-muted">{{ formatDate(l.created_at) }}</td>
              <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline">
                <Link :href="route('listings.show', l.id)" class="flex h-[30px] w-[30px] items-center justify-center rounded-[7px] bg-blue-light text-blue transition hover:bg-blue hover:text-white">
                  <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" stroke-linecap="round" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3" stroke-width="2"/></svg>
                </Link>
              </td>
            </tr>
            <tr v-if="!userListings?.length">
              <td colspan="6" class="px-4 py-8 text-center text-[13px] text-muted">{{ t('users.noListings') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
