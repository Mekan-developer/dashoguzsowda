<script setup>
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import UserCreateModal from '@/Components/UserCreateModal.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import SearchInput from '@/Components/SearchInput.vue'

const { t } = useI18n()

// Управление пользователями закрыто от менеджера (routes/web.php → role:admin),
// поэтому кнопки блокировки и создания ему не показываем.
const isAdmin = computed(() => usePage().props.auth?.user?.role === 'admin')

const props = defineProps({
    users: Object, regions: Array, tariffs: Array, filters: Object,
})

// Filters
const search    = ref(props.filters?.search    || '')
const status    = ref(props.filters?.status    || '')
const regionId  = ref(props.filters?.region_id || '')

function applyFilters() {
    router.get(route('users.index'), {
        search: search.value, status: status.value, region_id: regionId.value,
    }, { preserveState: true, replace: true })
}

// Create modal
const createOpen = ref(false)

function openCreate() { createOpen.value = true }

// Block confirm
const blockTarget = ref(null)
const blockReason = ref('')

function confirmBlock(user) { blockTarget.value = user; blockReason.value = '' }
function doBlock() {
    router.patch(route('users.block', blockTarget.value.id), { reason: blockReason.value }, {
        onSuccess: () => { blockTarget.value = null },
    })
}
function doUnblock(user) {
    router.patch(route('users.unblock', user.id))
}

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('ru', { day: '2-digit', month: '2-digit', year: '2-digit' })
}
</script>

<template>
  <AppLayout>
    <template #header>{{ t('nav.users') }}</template>

    <!-- Toolbar -->
    <div class="mb-4 flex flex-wrap items-center gap-3">
      <SearchInput
        v-model="search" @submit="applyFilters"
        :placeholder="t('users.searchPlaceholder')"
        class="w-64"
      />
      <select v-model="status" @change="applyFilters" class="rounded-btn border-2 border-line bg-surface py-[9px] px-[14px] text-[13px] font-semibold text-ink outline-none transition focus:border-blue dark:bg-dbg dark:border-dline dark:text-slate-200">
        <option value="">{{ t('users.allStatuses') }}</option>
        <option value="active">{{ t('users.activeFilter') }}</option>
        <option value="blocked">{{ t('users.blockedFilter') }}</option>
      </select>
      <select v-model="regionId" @change="applyFilters" class="rounded-btn border-2 border-line bg-surface py-[9px] px-[14px] text-[13px] font-semibold text-ink outline-none transition focus:border-blue dark:bg-dbg dark:border-dline dark:text-slate-200">
        <option value="">{{ t('users.allRegions') }}</option>
        <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name_ru }}</option>
      </select>
      <div class="ml-auto">
        <button v-if="isAdmin" @click="openCreate" class="inline-flex items-center gap-1.5 rounded-btn bg-blue px-[18px] py-[9px] text-[13px] font-bold text-white shadow-[0_4px_12px_rgba(67,97,238,.3)] transition hover:bg-blue-dark">
          + {{ t('actions.add') }}
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="rounded-card bg-white shadow-soft dark:bg-dcard overflow-hidden">
      <table class="w-full">
        <thead class="bg-surface/50 dark:bg-dbg/50">
          <tr>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('users.colUser') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.region') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.tariff') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.status') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('users.colRegDate') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users.data" :key="user.id" class="hover:bg-surface/30 dark:hover:bg-white/3 transition">
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline">
              <div class="flex items-center gap-3">
                <div class="h-8 w-8 rounded-full bg-blue flex items-center justify-center text-[12px] font-extrabold text-white flex-shrink-0">
                  {{ (user.name || user.phone || '?').charAt(0).toUpperCase() }}
                </div>
                <div>
                  <div class="font-bold text-ink dark:text-slate-200">{{ user.name || '—' }}</div>
                  <div class="text-[11px] font-data text-muted">{{ user.phone }}</div>
                </div>
              </div>
            </td>
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline text-muted">{{ user.region?.name_ru || '—' }}</td>
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline">
              <span v-if="user.tariff" class="rounded-pill bg-blue-light px-2 py-0.5 text-[11px] font-bold text-blue">{{ user.tariff.name_ru }}</span>
              <span v-else class="text-muted">—</span>
            </td>
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline">
              <StatusBadge :status="user.status" />
            </td>
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline font-data text-muted">{{ formatDate(user.created_at) }}</td>
            <td class="px-4 py-[13px] text-[13px] border-b border-line dark:border-dline">
              <div class="flex items-center gap-1.5">
                <Link :href="route('users.show', user.id)" class="flex h-[30px] w-[30px] items-center justify-center rounded-[7px] bg-blue-light text-blue transition hover:bg-blue hover:text-white">
                  <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" stroke-linecap="round" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3" stroke-width="2"/></svg>
                </Link>
                <button v-if="isAdmin && user.status === 'active'" @click="confirmBlock(user)" class="flex h-[30px] w-[30px] items-center justify-center rounded-[7px] bg-red/10 text-red transition hover:bg-red hover:text-white" :title="t('actions.block')">
                  <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07" stroke-width="2"/></svg>
                </button>
                <button v-else-if="isAdmin" @click="doUnblock(user)" class="flex h-[30px] w-[30px] items-center justify-center rounded-[7px] bg-green/10 text-green transition hover:bg-green hover:text-white" :title="t('actions.unblock')">
                  <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline stroke-width="2" stroke-linecap="round" points="20 6 9 17 4 12"/></svg>
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!users.data?.length">
            <td colspan="6" class="px-4 py-10 text-center text-[13px] text-muted">{{ t('users.notFound') }}</td>
          </tr>
        </tbody>
      </table>
      <Pagination :links="users.links" />
    </div>

    <!-- Create Modal -->
    <UserCreateModal v-if="isAdmin" :open="createOpen" :regions="regions" @close="createOpen = false" />

    <!-- Block modal -->
    <div v-if="blockTarget" class="fixed inset-0 z-[600] flex items-center justify-center bg-black/40 backdrop-blur-sm" @click.self="blockTarget = null">
      <div class="w-[420px] rounded-card bg-white p-6 shadow-[0_24px_48px_rgba(0,0,0,.18)] dark:bg-dcard">
        <h3 class="mb-2 text-[17px] font-extrabold text-ink dark:text-slate-100">{{ t('users.blockTitle') }}</h3>
        <p class="mb-4 text-[13px] text-muted">{{ blockTarget?.name || blockTarget?.phone }}</p>
        <textarea v-model="blockReason" :placeholder="t('users.blockReasonPlaceholder')" rows="3"
          class="w-full rounded-btn border-2 border-line bg-surface py-[10px] px-[14px] text-[13px] font-semibold outline-none focus:border-blue dark:bg-dbg dark:border-dline dark:text-slate-200 mb-4 resize-none"></textarea>
        <div class="flex gap-2.5">
          <button @click="blockTarget = null" class="flex-1 rounded-btn border-2 border-line bg-white py-[11px] text-[13px] font-bold text-muted hover:border-blue hover:text-blue transition dark:bg-dcard dark:border-dline">{{ t('actions.cancel') }}</button>
          <button @click="doBlock" class="flex-1 rounded-btn bg-red py-[11px] text-[13px] font-bold text-white hover:opacity-90 transition">{{ t('actions.block') }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
