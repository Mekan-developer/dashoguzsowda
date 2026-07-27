<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import GeoColumn from '@/Components/GeoColumn.vue'
import ToggleSwitch from '@/Components/ToggleSwitch.vue'
import Icon from '@/Components/Icon.vue'

const { t } = useI18n()

const props = defineProps({
    monitoring:        { type: Object, required: true },
    canManageNews:     { type: Boolean, default: false },
    canManageBanners:  { type: Boolean, default: false },
    rejectionReasons:  { type: Array, default: () => [] },
    complaintReasons:  { type: Array, default: () => [] },
    ownLocale:         { type: String, default: null },
    defaultAppLocale:  { type: String, default: 'ru' },
    boostIntervalHours:{ type: Number, default: 24 },
    smsStatus:         { type: Object, required: true },
})

const opts = { preserveScroll: true, preserveState: true }

// ── Мониторинг ─────────────────────────────────────────────
const monitoring     = ref(props.monitoring)
const lastFetchedAt  = ref(new Date())
const now            = ref(Date.now())
let pollTimer = null
let tickTimer = null

async function fetchMonitoring() {
    try {
        const res = await fetch(route('settings.monitoring'), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        monitoring.value = await res.json()
        lastFetchedAt.value = new Date()
    } catch {
        // сеть недоступна — оставляем последнее известное состояние
    }
}

onMounted(() => {
    pollTimer = setInterval(fetchMonitoring, 15_000)
    tickTimer = setInterval(() => { now.value = Date.now() }, 1000)
})
onUnmounted(() => { clearInterval(pollTimer); clearInterval(tickTimer) })

const secondsAgo = computed(() => Math.max(0, Math.floor((now.value - lastFetchedAt.value.getTime()) / 1000)))

function dotClass(ok) { return ok ? 'bg-green' : 'bg-red' }
function badgeClass(ok) { return ok ? 'bg-green/10 text-green' : 'bg-red/10 text-red' }

// FCM — третье, «не настроено» состояние (нет service account) в дополнение к ok/недоступно
function fcmDotClass(fcm) {
    if (fcm.ok) return 'bg-green'
    return fcm.configured ? 'bg-red' : 'bg-orange'
}
function fcmBadgeClass(fcm) {
    if (fcm.ok) return 'bg-green/10 text-green'
    return fcm.configured ? 'bg-red/10 text-red' : 'bg-orange/10 text-orange'
}
function fcmStatusLabel(fcm) {
    if (fcm.ok) return t('settings.working')
    return fcm.configured ? t('settings.unavailable') : t('settings.notConfigured')
}

// ── Роли и права доступа ───────────────────────────────────
const permissionRows = computed(() => [
    { label: t('settings.permViewContent'), manager: true },
    { label: t('settings.permModerate'), manager: true },
    { label: t('settings.permViewUsers'), manager: true },
    { label: t('settings.permChat'), manager: true },
    { label: t('settings.permStats'), manager: true },
    { label: t('settings.permNews'), manager: 'toggle', toggleKey: 'news' },
    { label: t('settings.permBanners'), manager: 'toggle', toggleKey: 'banners' },
    { label: t('settings.permPush'), manager: false },
    { label: t('settings.permReasons'), manager: false },
    { label: t('settings.permDelete'), manager: false },
    { label: t('settings.permCreateAdmins'), manager: false },
    { label: t('settings.permCritical'), manager: false },
])

const canManageNews = ref(props.canManageNews)
function toggleManagerNews(value) {
    canManageNews.value = value
    router.patch(route('settings.manager-permissions'), { can_manage_news: value }, opts)
}

const canManageBanners = ref(props.canManageBanners)
function toggleManagerBanners(value) {
    canManageBanners.value = value
    router.patch(route('settings.manager-permissions'), { can_manage_banners: value }, opts)
}

// ── Справочники причин ─────────────────────────────────────
const rejectionCol    = ref(null)
const complaintCol    = ref(null)
const rejectionErrors = ref({})
const complaintErrors = ref({})

const rejectionItems = computed(() => props.rejectionReasons.map(r => ({ ...r, is_hidden: !r.is_active })))
const complaintItems = computed(() => props.complaintReasons.map(r => ({ ...r, is_hidden: !r.is_active })))

function createRejection(form) {
    rejectionErrors.value = {}
    router.post(route('rejection-reasons.store'), { ...form, type: 'listing', is_active: true },
        { ...opts, onSuccess: () => rejectionCol.value?.closeAdd(), onError: e => (rejectionErrors.value = e) })
}
function updateRejection(item, form) {
    rejectionErrors.value = {}
    router.put(route('rejection-reasons.update', item.id), form,
        { ...opts, onSuccess: () => rejectionCol.value?.closeEdit(), onError: e => (rejectionErrors.value = e) })
}
function toggleRejection(item) {
    router.put(route('rejection-reasons.update', item.id), { is_active: !item.is_active }, opts)
}
function destroyRejection(item) {
    if (!confirm(t('settings.confirmDeleteReason', { name: item.name_ru }))) return
    router.delete(route('rejection-reasons.destroy', item.id), opts)
}

function createComplaint(form) {
    complaintErrors.value = {}
    router.post(route('complaint-reasons.store'), { ...form, is_active: true },
        { ...opts, onSuccess: () => complaintCol.value?.closeAdd(), onError: e => (complaintErrors.value = e) })
}
function updateComplaint(item, form) {
    complaintErrors.value = {}
    router.put(route('complaint-reasons.update', item.id), form,
        { ...opts, onSuccess: () => complaintCol.value?.closeEdit(), onError: e => (complaintErrors.value = e) })
}
function toggleComplaint(item) {
    router.put(route('complaint-reasons.update', item.id), { is_active: !item.is_active }, opts)
}
function destroyComplaint(item) {
    if (!confirm(t('settings.confirmDeleteReason', { name: item.name_ru }))) return
    router.delete(route('complaint-reasons.destroy', item.id), opts)
}

// ── Локализация ────────────────────────────────────────────
const ownLocale        = ref(props.ownLocale || 'ru')
const defaultAppLocale = ref(props.defaultAppLocale)

function saveLocalization() {
    router.patch(route('settings.localization'), {
        own_locale: ownLocale.value,
        default_app_locale: defaultAppLocale.value,
    }, opts)
}

// ── Объявления: интервал поднятия ──────────────────────────
const boostIntervalHours = ref(props.boostIntervalHours)
const boostErrors        = ref({})
function saveBoostSettings() {
    boostErrors.value = {}
    router.patch(route('settings.boost'), { boost_interval_hours: boostIntervalHours.value },
        { ...opts, onError: e => (boostErrors.value = e) })
}

// ── SMS-шлюз ───────────────────────────────────────────────
const sendingTest = ref(false)
function sendTestSms() {
    sendingTest.value = true
    router.post(route('settings.sms-gateway.test'), {}, {
        ...opts,
        onFinish: () => { sendingTest.value = false },
    })
}
</script>

<template>
  <AppLayout>
    <template #header>{{ t('nav.settings') }}</template>

    <div class="space-y-8">
      <!-- 1. Мониторинг -->
      <section>
        <h2 class="mb-3 text-[13px] font-bold uppercase tracking-wide text-muted">{{ t('settings.monitoring') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div class="rounded-card bg-white dark:bg-dcard border border-line dark:border-dline p-5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full" :class="dotClass(monitoring.queues.ok)"></span>
                <span class="font-extrabold text-ink dark:text-slate-100">{{ t('settings.queues') }}</span>
              </div>
              <span class="rounded-pill px-2.5 py-1 text-[11px] font-bold" :class="badgeClass(monitoring.queues.ok)">
                {{ monitoring.queues.ok ? t('settings.working') : t('settings.unavailable') }}
              </span>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3 text-center">
              <div class="rounded-[8px] bg-surface dark:bg-dbg p-3">
                <div class="text-xl font-extrabold text-ink dark:text-slate-100">{{ monitoring.queues.pending }}</div>
                <div class="text-[11px] text-muted">{{ t('settings.inQueue') }}</div>
              </div>
              <div class="rounded-[8px] bg-surface dark:bg-dbg p-3">
                <div class="text-xl font-extrabold" :class="monitoring.queues.failed > 0 ? 'text-red' : 'text-ink dark:text-slate-100'">{{ monitoring.queues.failed }}</div>
                <div class="text-[11px] text-muted">{{ t('settings.failed') }}</div>
              </div>
            </div>
            <div class="mt-3 text-[11px] text-muted">{{ monitoring.queues.worker || t('settings.workerMissing') }} · {{ t('settings.updatedAgo', { s: secondsAgo }) }}</div>
          </div>

          <div class="rounded-card bg-white dark:bg-dcard border border-line dark:border-dline p-5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full" :class="dotClass(monitoring.ws.ok)"></span>
                <span class="font-extrabold text-ink dark:text-slate-100">WS (Reverb)</span>
              </div>
              <span class="rounded-pill px-2.5 py-1 text-[11px] font-bold" :class="badgeClass(monitoring.ws.ok)">
                {{ monitoring.ws.ok ? t('settings.connected') : t('settings.notConnected') }}
              </span>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3 text-center">
              <div class="rounded-[8px] bg-surface dark:bg-dbg p-3">
                <div class="truncate text-xl font-extrabold text-ink dark:text-slate-100">{{ monitoring.ws.host }}</div>
                <div class="text-[11px] text-muted">{{ t('settings.host') }}</div>
              </div>
              <div class="rounded-[8px] bg-surface dark:bg-dbg p-3">
                <div class="text-xl font-extrabold text-ink dark:text-slate-100">{{ monitoring.ws.port }}</div>
                <div class="text-[11px] text-muted">{{ t('settings.port') }}</div>
              </div>
            </div>
            <div class="mt-3 text-[11px] text-muted">{{ t('settings.updatedAgo', { s: secondsAgo }) }}</div>
          </div>

          <div class="rounded-card bg-white dark:bg-dcard border border-line dark:border-dline p-5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full" :class="fcmDotClass(monitoring.fcm)"></span>
                <span class="font-extrabold text-ink dark:text-slate-100">FCM</span>
              </div>
              <span class="rounded-pill px-2.5 py-1 text-[11px] font-bold" :class="fcmBadgeClass(monitoring.fcm)">
                {{ fcmStatusLabel(monitoring.fcm) }}
              </span>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3 text-center">
              <div class="rounded-[8px] bg-surface dark:bg-dbg p-3">
                <div class="truncate text-xl font-extrabold text-ink dark:text-slate-100">{{ monitoring.fcm.project_id || '—' }}</div>
                <div class="text-[11px] text-muted">{{ t('settings.projectId') }}</div>
              </div>
              <div class="rounded-[8px] bg-surface dark:bg-dbg p-3">
                <div class="text-xl font-extrabold text-ink dark:text-slate-100">{{ monitoring.fcm.tokens }}</div>
                <div class="text-[11px] text-muted">{{ t('settings.devices') }}</div>
              </div>
            </div>
            <div class="mt-3 text-[11px] text-muted">{{ t('settings.updatedAgo', { s: secondsAgo }) }}</div>
          </div>
        </div>
      </section>

      <!-- 2. Роли и права доступа -->
      <section>
        <h2 class="mb-3 text-[13px] font-bold uppercase tracking-wide text-muted">{{ t('settings.roles') }}</h2>
        <div class="overflow-hidden rounded-card bg-white dark:bg-dcard border border-line dark:border-dline">
          <table class="w-full text-[13px]">
            <thead>
              <tr class="border-b border-line dark:border-dline text-left text-muted">
                <th class="px-5 py-3 font-semibold">{{ t('settings.permission') }}</th>
                <th class="w-32 px-5 py-3 text-center font-semibold">{{ t('role.admin') }}</th>
                <th class="w-32 px-5 py-3 text-center font-semibold">{{ t('role.manager') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line dark:divide-dline">
              <tr v-for="row in permissionRows" :key="row.label">
                <td class="px-5 py-3 font-medium text-ink dark:text-slate-200">{{ row.label }}</td>
                <td class="px-5 py-3 text-center">
                  <Icon kind="check" :size="16" class="inline text-green" />
                </td>
                <td class="px-5 py-3 text-center">
                  <ToggleSwitch
                    v-if="row.manager === 'toggle' && row.toggleKey === 'news'"
                    :modelValue="canManageNews" @update:modelValue="toggleManagerNews"
                  />
                  <ToggleSwitch
                    v-else-if="row.manager === 'toggle' && row.toggleKey === 'banners'"
                    :modelValue="canManageBanners" @update:modelValue="toggleManagerBanners"
                  />
                  <Icon v-else-if="row.manager" kind="check" :size="16" class="inline text-green" />
                  <Icon v-else kind="lock" :size="15" class="inline text-muted" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- 3. Справочники причин -->
      <section>
        <h2 class="mb-3 text-[13px] font-bold uppercase tracking-wide text-muted">{{ t('settings.reasons') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <GeoColumn
            ref="rejectionCol"
            :title="t('settings.rejectionReasons')"
            :items="rejectionItems"
            :empty-text="t('settings.emptyRejectionReasons')"
            :subtitle="r => r.name_tk"
            :errors="rejectionErrors"
            @create="createRejection"
            @update="updateRejection"
            @toggle="toggleRejection"
            @destroy="destroyRejection"
          />
          <GeoColumn
            ref="complaintCol"
            :title="t('settings.complaintReasons')"
            :items="complaintItems"
            :empty-text="t('settings.emptyComplaintReasons')"
            :subtitle="r => r.name_tk"
            :errors="complaintErrors"
            @create="createComplaint"
            @update="updateComplaint"
            @toggle="toggleComplaint"
            @destroy="destroyComplaint"
          />
        </div>
      </section>

      <!-- 4. Объявления -->
      <section>
        <h2 class="mb-3 text-[13px] font-bold uppercase tracking-wide text-muted">{{ t('settings.listingsSection') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="rounded-card bg-white dark:bg-dcard border border-line dark:border-dline p-5">
            <div class="mb-1 font-extrabold text-ink dark:text-slate-100">{{ t('settings.boostInterval') }}</div>
            <div class="mb-4 text-[12px] text-muted">{{ t('settings.boostIntervalHint') }}</div>

            <div class="mb-4">
              <div class="mb-1.5 text-[12px] text-muted">{{ t('settings.boostIntervalLabel') }}</div>
              <input
                v-model.number="boostIntervalHours" type="number" min="1" max="8760"
                class="w-full rounded-btn border-2 border-line dark:border-dline bg-surface dark:bg-dbg px-3 py-2 text-[14px] font-bold text-ink dark:text-slate-100 outline-none focus:border-blue"
              />
              <div v-if="boostErrors.boost_interval_hours" class="mt-1 text-[11px] text-red">{{ boostErrors.boost_interval_hours }}</div>
            </div>

            <button @click="saveBoostSettings" class="w-full rounded-btn bg-blue py-2 text-[13px] font-bold text-white transition hover:bg-blue/90">
              {{ t('actions.save') }}
            </button>
          </div>
        </div>
      </section>

      <!-- 5. Локализация и SMS -->
      <section>
        <h2 class="mb-3 text-[13px] font-bold uppercase tracking-wide text-muted">{{ t('settings.localizationAndSms') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="rounded-card bg-white dark:bg-dcard border border-line dark:border-dline p-5">
            <div class="mb-4 font-extrabold text-ink dark:text-slate-100">{{ t('settings.localization') }}</div>

            <div class="mb-4">
              <div class="mb-1.5 text-[12px] text-muted">{{ t('settings.ownLocale') }}</div>
              <div class="flex gap-2">
                <button
                  v-for="l in ['ru', 'tk']" :key="l"
                  @click="ownLocale = l"
                  class="flex-1 rounded-btn border-2 py-1.5 text-[12px] font-bold uppercase transition"
                  :class="ownLocale === l ? 'border-blue bg-blue/10 text-blue' : 'border-line dark:border-dline text-muted'"
                >{{ l }}</button>
              </div>
            </div>

            <div class="mb-4">
              <div class="mb-1.5 text-[12px] text-muted">{{ t('settings.defaultAppLocale') }}</div>
              <div class="flex gap-2">
                <button
                  v-for="l in ['ru', 'tk']" :key="l"
                  @click="defaultAppLocale = l"
                  class="flex-1 rounded-btn border-2 py-1.5 text-[12px] font-bold uppercase transition"
                  :class="defaultAppLocale === l ? 'border-blue bg-blue/10 text-blue' : 'border-line dark:border-dline text-muted'"
                >{{ l }}</button>
              </div>
            </div>

            <button @click="saveLocalization" class="w-full rounded-btn bg-blue py-2 text-[13px] font-bold text-white transition hover:bg-blue/90">
              {{ t('actions.save') }}
            </button>
          </div>

          <div class="rounded-card bg-white dark:bg-dcard border border-line dark:border-dline p-5">
            <div class="mb-4 flex items-center justify-between">
              <div class="font-extrabold text-ink dark:text-slate-100">{{ t('settings.smsGateway') }}</div>
              <span
                class="rounded-pill px-2.5 py-1 text-[11px] font-bold"
                :class="smsStatus.connected ? 'bg-green/10 text-green' : (smsStatus.configured ? 'bg-red/10 text-red' : 'bg-orange/10 text-orange')"
              >{{ smsStatus.connected ? t('settings.connected') : (smsStatus.configured ? t('settings.notConnected') : t('settings.testMode')) }}</span>
            </div>
            <div class="mb-1 text-[13px] text-ink dark:text-slate-200">{{ smsStatus.device }}</div>
            <div class="mb-4 text-[11px] text-muted">
              {{ smsStatus.last_sync_at ? t('settings.syncedAt', { at: smsStatus.last_sync_at }) : t('settings.noSync') }}
            </div>
            <button
              @click="sendTestSms"
              :disabled="sendingTest"
              class="w-full rounded-btn border-2 border-line dark:border-dline py-2 text-[13px] font-bold text-ink dark:text-slate-200 transition hover:border-blue hover:text-blue disabled:cursor-not-allowed disabled:opacity-50"
            >{{ sendingTest ? t('push.sending') : t('settings.sendTestSms') }}</button>
          </div>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
