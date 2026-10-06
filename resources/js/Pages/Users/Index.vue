<script setup>
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import UserCreateModal from '@/Components/UserCreateModal.vue'
import CreateButton from '@/Components/CreateButton.vue'
import SearchInput from '@/Components/SearchInput.vue'
import EmptyState from '@/Components/EmptyState.vue'
import Icon from '@/Components/Icon.vue'
import { th, td, tr, thead } from '@/table'

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

const hasFilters = computed(() => !!(search.value || status.value || regionId.value))
function resetFilters() {
    search.value = ''; status.value = ''; regionId.value = ''
    applyFilters()
}


function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('ru', { day: '2-digit', month: '2-digit', year: '2-digit' })
}
</script>

<template>
  <AppLayout>
    <template #header>{{ t('nav.users') }}</template>

    <template #actions>
      <CreateButton v-if="isAdmin" :label="t('actions.add')" @click="openCreate" />
    </template>

    <!-- Панель фильтров: поиск, статус, регион — одна строка одной высоты -->
    <div class="mb-4 flex flex-wrap items-center gap-2.5">
      <SearchInput
        v-model="search" @submit="applyFilters"
        :placeholder="t('users.searchPlaceholder')"
        class="w-full sm:w-[280px]"
      />
      <select v-model="status" @change="applyFilters" :aria-label="t('common.status')" class="filter-select">
        <option value="">{{ t('users.allStatuses') }}</option>
        <option value="active">{{ t('users.activeFilter') }}</option>
        <option value="blocked">{{ t('users.blockedFilter') }}</option>
      </select>
      <select v-model="regionId" @change="applyFilters" :aria-label="t('common.region')" class="filter-select">
        <option value="">{{ t('users.allRegions') }}</option>
        <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name_ru }}</option>
      </select>
      <span class="ml-auto whitespace-nowrap text-[12.5px] tabular-nums text-[var(--text-muted)]">
        {{ t('dataTable.countOf', { shown: users.data.length, total: users.total }) }}
      </span>
    </div>

    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[640px]">
          <thead>
            <tr :class="thead">
              <th :class="th" class="w-[72px]">{{ t('common.id') }}</th>
              <th :class="th">{{ t('users.colUser') }}</th>
              <th :class="th" class="hidden lg:table-cell">{{ t('common.region') }}</th>
              <th :class="th" class="hidden md:table-cell">{{ t('common.tariff') }}</th>
              <th :class="th">{{ t('common.status') }}</th>
              <th :class="th" class="hidden md:table-cell">{{ t('users.colRegDate') }}</th>
              <th :class="th" class="text-right">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="user in users.data" :key="user.id"
              @dblclick="router.visit(route('users.show', user.id))"
              class="h-[64px] cursor-pointer border-b border-[var(--card-border)] transition-colors duration-150 last:border-b-0 hover:bg-[var(--nav-hover)]"
            >
              <td :class="td" class="font-data tabular-nums text-[var(--text-muted)]">{{ user.id }}</td>
              <td :class="td">
                <div class="flex min-w-0 items-center gap-3">
                  <div class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-[var(--accent-tint)] text-[12.5px] font-semibold text-link">
                    {{ (user.name || user.phone || '?').charAt(0).toUpperCase() }}
                  </div>
                  <div class="min-w-0">
                    <Link :href="route('users.show', user.id)" class="block max-w-[260px] truncate font-semibold text-[var(--text)] transition-colors duration-150 hover:text-link">{{ user.name || '—' }}</Link>
                    <div class="font-data text-[12px] text-[var(--text-muted)]">{{ user.phone }}</div>
                  </div>
                </div>
              </td>
              <td :class="td" class="hidden text-[var(--text-secondary)] lg:table-cell">{{ user.region?.name_ru || '—' }}</td>
              <td :class="td" class="hidden md:table-cell">
                <span v-if="user.tariff" class="inline-flex h-6 items-center rounded-full bg-[var(--accent-tint)] px-2.5 text-[11.5px] font-semibold text-link">{{ user.tariff.name_ru }}</span>
                <span v-else class="text-[var(--text-muted)]">—</span>
              </td>
              <td :class="td"><StatusBadge :status="user.status" /></td>
              <td :class="td" class="hidden whitespace-nowrap font-data tabular-nums text-[var(--text-secondary)] md:table-cell">{{ formatDate(user.created_at) }}</td>
              <td :class="td">
                <div class="flex items-center justify-end gap-1.5">
                  <Link :href="route('users.show', user.id)" class="icon-btn" :title="t('actions.show')" :aria-label="t('actions.show')">
                    <Icon kind="eye" :size="16" />
                  </Link>
                  <button v-if="isAdmin && user.status === 'active'" type="button" @click="confirmBlock(user)" class="icon-btn icon-btn-danger" :title="t('actions.block')" :aria-label="t('actions.block')">
                    <Icon kind="lock" :size="16" />
                  </button>
                  <button v-else-if="isAdmin" type="button" @click="doUnblock(user)" class="icon-btn icon-btn-success" :title="t('actions.unblock')" :aria-label="t('actions.unblock')">
                    <Icon kind="check" :size="16" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!users.data?.length">
              <td colspan="7">
                <EmptyState icon="users" :title="t('users.notFound')" :text="hasFilters ? t('common.emptyFiltered') : ''">
                  <button v-if="hasFilters" type="button" class="btn btn-secondary" @click="resetFilters">{{ t('common.resetFilters') }}</button>
                </EmptyState>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :links="users.links" :from="users.from" :to="users.to" :total="users.total" />
    </div>

    <!-- Create Modal -->
    <UserCreateModal v-if="isAdmin" :open="createOpen" :regions="regions" @close="createOpen = false" />

    <!-- Block modal -->
    <div v-if="blockTarget" class="fixed inset-0 z-[600] flex items-center justify-center bg-[#0A0C1A]/50 p-4" @click.self="blockTarget = null">
      <div class="card w-full max-w-[420px] p-6 shadow-lg2">
        <h3 class="mb-1 text-[16px] font-semibold text-[var(--text)]">{{ t('users.blockTitle') }}</h3>
        <p class="mb-4 text-[13px] text-muted">{{ blockTarget?.name || blockTarget?.phone }}</p>
        <textarea v-model="blockReason" :placeholder="t('users.blockReasonPlaceholder')" rows="3"
          class="input mb-4 resize-none"></textarea>
        <div class="flex justify-end gap-2">
          <button @click="blockTarget = null" class="btn btn-secondary">{{ t('actions.cancel') }}</button>
          <button @click="doBlock" class="btn btn-danger">{{ t('actions.block') }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
