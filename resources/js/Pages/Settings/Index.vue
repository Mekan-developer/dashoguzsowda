<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import GeoColumn from '@/Components/GeoColumn.vue'
import ToggleSwitch from '@/Components/ToggleSwitch.vue'
import SearchInput from '@/Components/SearchInput.vue'
import Icon from '@/Components/Icon.vue'
import RichTextEditor from '@/Components/RichTextEditor.vue'
import { confirmDialog } from '@/confirm'

const { t } = useI18n()

const props = defineProps({
    monitoring:        { type: Object, required: true },
    otpCodes:          { type: Array, default: () => [] },
    canManageNews:     { type: Boolean, default: false },
    canManageBanners:  { type: Boolean, default: false },
    rejectionReasons:  { type: Array, default: () => [] },
    complaintReasons:  { type: Array, default: () => [] },
    paymentMethods:    { type: Array, default: () => [] },
    boostIntervalHours:{ type: Number, default: 24 },
    aboutRu:           { type: String, default: '' },
    aboutTk:           { type: String, default: '' },
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

// Ручной reload — бэкенд отдаёт все проверки одним запросом, поэтому и кнопка
// в шапке секции, и кнопка на карточке дёргают один и тот же fetch; крутится
// только то, по чему кликнули (`reloading` хранит ключ карточки или 'all').
// Локальные проверки (queues/ws/fcm) обычно отвечают за миллисекунды — без
// минимальной задержки анимация просто не успевает провернуться, иконка
// мигает без видимого вращения. Держим спиннер минимум один полный оборот
// (animate-spin крутит 360° за 1s), даже если сам fetch уже пришёл.
const reloading = ref(null)
// После обновления статус-бейдж («Подключено»/«Недоступно») один раз мигает —
// сигнал, что значение реально перечитано, а не просто отрисовалось то же самое.
const flashing = ref([])
async function reload(key = 'all') {
    reloading.value = key
    if (key === 'ws' || key === 'all') reconnectWs()
    await Promise.all([
        fetchMonitoring(),
        new Promise(resolve => setTimeout(resolve, 1000)),
    ])
    reloading.value = null
    const keys = key === 'all' ? monitorCards.value.map(c => c.key) : [key]
    flashing.value = keys
    setTimeout(() => { flashing.value = flashing.value.filter(k => !keys.includes(k)) }, 500)
}
function isReloading(key) { return reloading.value === key || reloading.value === 'all' }

onMounted(() => {
    pollTimer = setInterval(fetchMonitoring, 15_000)
    tickTimer = setInterval(() => { now.value = Date.now() }, 1000)
})
onUnmounted(() => { clearInterval(pollTimer); clearInterval(tickTimer) })

const secondsAgo = computed(() => Math.max(0, Math.floor((now.value - lastFetchedAt.value.getTime()) / 1000)))

// ── Живое соединение самой админки с Reverb ────────────────
// Бэкенд проверяет TCP-порт из PHP-контейнера — это не тот адрес, по которому
// стучится браузер (VITE_REVERB_HOST:VITE_REVERB_PORT, в проде — через nginx
// на /app). Порт может быть открыт внутри docker-сети, а панель при этом
// никуда не подключится: не проброшен порт, не тот ключ, не тот scheme.
// Единственный честный ответ на «заработает ли чат здесь» — состояние сокета,
// который Echo держит на каждой странице панели.
const wsState    = ref('unavailable')
const wsSocketId = ref(null)
const wsClientAddress = [import.meta.env.VITE_REVERB_HOST, import.meta.env.VITE_REVERB_PORT]
    .filter(Boolean).join(':') || '—'

// Состояния pusher-js; всё незнакомое считаем «нет связи», иначе vue-i18n
// отрисует сырой ключ перевода
const WS_STATES = ['initialized', 'connecting', 'connected', 'unavailable', 'failed', 'disconnected']

function wsConnection() { return window.Echo?.connector?.pusher?.connection ?? null }
function syncWsState() {
    const connection = wsConnection()
    wsState.value    = WS_STATES.includes(connection?.state) ? connection.state : 'unavailable'
    wsSocketId.value = connection?.socket_id ?? null
}
function reconnectWs() {
    const pusher = window.Echo?.connector?.pusher
    if (pusher && pusher.connection.state !== 'connected') pusher.connect()
}

onMounted(() => {
    syncWsState()
    wsConnection()?.bind('state_change', syncWsState)
})
onUnmounted(() => wsConnection()?.unbind('state_change', syncWsState))

// Три состояния вместо двух: у FCM и SMS-шлюза «не настроено» — это не поломка,
// а рабочий режим локальной сборки, поэтому оранжевый, а не красный.
const TONES = {
    ok:   { dot: 'bg-green',  badge: 'border-green/25 bg-green/10 text-green' },
    warn: { dot: 'bg-orange', badge: 'border-orange/25 bg-orange/10 text-orange' },
    bad:  { dot: 'bg-red',    badge: 'border-red/25 bg-red/10 text-red' },
}

function smsTone(sms) {
    if (sms.connected) return 'ok'
    return sms.configured ? 'bad' : 'warn'
}
function smsStatusLabel(sms) {
    if (sms.connected) return t('settings.connected')
    return sms.configured ? t('settings.notConnected') : t('settings.testMode')
}

// Карточки описываем данными: разметка у всех четырёх одинаковая, отличаются
// только иконка, два показателя, подпись внизу и правило расчёта статуса.
// accent/accentText — фирменный цвет сервиса (плашка иконки и второе значение),
// он не про состояние: состояние показывают точка и бейдж из TONES.
const monitorCards = computed(() => {
    const { queues, ws, fcm, sms } = monitoring.value

    return [
        {
            key:        'queues',
            icon:       'layers',
            accent:     'bg-blue/10 text-blue',
            accentText: 'text-blue',
            title:      t('settings.queues'),
            subtitle:   t('settings.queuesSubtitle'),
            tone:       queues.ok ? 'ok' : 'bad',
            status:     queues.ok ? t('settings.working') : t('settings.unavailable'),
            stats: [
                { label: t('settings.inQueue'), value: queues.pending },
                { label: t('settings.failed'),  value: queues.failed, danger: queues.failed > 0 },
            ],
            note: queues.worker || t('settings.workerMissing'),
        },
        {
            key:        'ws',
            icon:       'wifi',
            accent:     'bg-teal/10 text-teal',
            accentText: 'text-teal',
            title:      'Reverb',
            subtitle:   t('settings.wsSubtitle'),
            // Статус карточки — про браузер, а не про порт: пользователя
            // интересует «заработает ли чат в этой панели».
            tone:   wsState.value === 'connected' ? 'ok'
                : (['connecting', 'initialized'].includes(wsState.value) ? 'warn' : 'bad'),
            status: t(`settings.wsState.${wsState.value}`),
            stats: [
                { label: t('settings.wsBrowser'), value: wsClientAddress, small: true },
                {
                    label:  t('settings.wsServer'),
                    value:  ws.ok ? t('settings.reachable') : t('settings.unreachable'),
                    small:  true,
                    danger: !ws.ok,
                },
            ],
            note: ws.driver && ws.driver !== 'reverb'
                ? t('settings.wsDriverWarning', { driver: ws.driver })
                : (wsSocketId.value ? t('settings.wsSocket', { id: wsSocketId.value }) : `${ws.host}:${ws.port}`),
            noteWarn: !!ws.driver && ws.driver !== 'reverb',
        },
        {
            key:        'fcm',
            icon:       'bell',
            accent:     'bg-purple/10 text-purple',
            accentText: 'text-purple',
            title:      'FCM',
            subtitle:   t('settings.fcmSubtitle'),
            tone:       fcm.ok ? 'ok' : (fcm.configured ? 'bad' : 'warn'),
            status:     fcm.ok ? t('settings.working') : (fcm.configured ? t('settings.unavailable') : t('settings.notConfigured')),
            stats: [
                { label: t('settings.projectId'), value: fcm.project_id || '—', small: true },
                { label: t('settings.devices'),   value: fcm.tokens },
            ],
            note: fcm.configured ? t('settings.credentialsFound') : t('settings.credentialsMissing'),
        },
        {
            key:        'sms',
            icon:       'phone',
            accent:     'bg-green/10 text-green',
            accentText: 'text-green',
            title:      t('settings.smsGateway'),
            subtitle:   t('settings.smsSubtitle'),
            tone:       smsTone(sms),
            status:     smsStatusLabel(sms),
            stats: [
                { label: t('settings.address'), value: sms.address || '—', small: true },
                { label: t('settings.devices'), value: sms.clients ?? '—' },
            ],
            note: sms.last_sync_at ? t('settings.syncedAt', { at: shortTime(sms.last_sync_at) }) : t('settings.noSync'),
        },
    ]
})

// ── Мониторинг OTP-кодов ───────────────────────────────────
// Страховка на случай, когда телефон-отправитель молчит: код уже лежит в базе,
// а до пользователя не дошёл — админ читает его здесь и диктует вручную.
const otpCodes     = ref(props.otpCodes)
const otpPhone     = ref('')
const otpFetchedAt = ref(Date.now())
const otpReloading = ref(false)
const copiedCodeId = ref(null)
let otpTimer = null

async function fetchOtpCodes() {
    const phone = otpPhone.value.trim()
    const url = route('settings.otp-codes') + (phone ? `?phone=${encodeURIComponent(phone)}` : '')
    try {
        const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        const data = await res.json()
        otpCodes.value = data.codes ?? []
        otpFetchedAt.value = Date.now()
    } catch {
        // сеть недоступна — оставляем последний известный список
    }
}

// Как и у карточек мониторинга: держим спиннер минимум один оборот, иначе
// локальный запрос отвечает быстрее, чем успевает провернуться иконка.
async function reloadOtpCodes() {
    otpReloading.value = true
    await Promise.all([fetchOtpCodes(), new Promise(resolve => setTimeout(resolve, 1000))])
    otpReloading.value = false
}

// Остаток жизни кода считаем от expires_in на момент ответа, а не от
// expires_at: часы браузера админа могут расходиться с серверными.
function otpRemaining(row) {
    if (row.status !== 'active') return 0
    return Math.max(0, row.expires_in - Math.floor((now.value - otpFetchedAt.value) / 1000))
}
function otpStatus(row) {
    // Код мог протухнуть между двумя опросами — не ждём следующего ответа
    return row.status === 'active' && otpRemaining(row) === 0 ? 'expired' : row.status
}
function otpCountdown(row) {
    const s = otpRemaining(row)
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`
}
function otpStatusClass(row) {
    const status = otpStatus(row)
    if (status === 'active') return 'bg-green/10 text-green'
    if (status === 'used')   return 'bg-blue/10 text-blue'
    return 'bg-orange/10 text-orange'
}
// Общее для OTP-таблицы и подписи «синхронизировано» на карточке шлюза:
// сегодняшнее время без даты, всё остальное — с датой.
function shortTime(value) {
    if (!value) return '—'
    const d = new Date(value)
    const time = d.toLocaleTimeString('ru', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
    const isToday = d.toDateString() === new Date().toDateString()
    return isToday ? time : `${d.toLocaleDateString('ru', { day: '2-digit', month: '2-digit' })} ${time}`
}

async function copyCode(row) {
    try {
        await navigator.clipboard.writeText(row.code)
    } catch {
        // Панель может открываться по http — там clipboard API недоступен
        const area = document.createElement('textarea')
        area.value = row.code
        area.setAttribute('readonly', '')
        area.style.position = 'fixed'
        area.style.opacity = '0'
        document.body.appendChild(area)
        area.select()
        try { document.execCommand('copy') } catch { /* копирование недоступно — код всё равно виден глазами */ }
        document.body.removeChild(area)
    }
    copiedCodeId.value = row.id
    setTimeout(() => { if (copiedCodeId.value === row.id) copiedCodeId.value = null }, 1500)
}

onMounted(() => {
    // Код живёт 5 минут — опрашиваем чаще карточек мониторинга, чтобы свежий
    // код появлялся в списке почти сразу после запроса с телефона
    otpTimer = setInterval(fetchOtpCodes, 10_000)
})
onUnmounted(() => clearInterval(otpTimer))

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
async function destroyRejection(item) {
    if (!(await confirmDialog(t('settings.confirmDeleteReason', { name: item.name_ru })))) return
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
async function destroyComplaint(item) {
    if (!(await confirmDialog(t('settings.confirmDeleteReason', { name: item.name_ru })))) return
    router.delete(route('complaint-reasons.destroy', item.id), opts)
}

// ── Способы оплаты ─────────────────────────────────────────
// Онлайн-оплаты в проекте нет: это список договорённостей, из которого магазин
// отмечает свои, а покупатель выбирает при оформлении заказа.
const paymentCol    = ref(null)
const paymentErrors = ref({})

const paymentItems = computed(() => props.paymentMethods.map(m => ({ ...m, is_hidden: !m.is_active })))

function createPayment(form) {
    paymentErrors.value = {}
    router.post(route('payment-methods.store'), { ...form, is_active: true },
        { ...opts, onSuccess: () => paymentCol.value?.closeAdd(), onError: e => (paymentErrors.value = e) })
}
function updatePayment(item, form) {
    paymentErrors.value = {}
    router.put(route('payment-methods.update', item.id), form,
        { ...opts, onSuccess: () => paymentCol.value?.closeEdit(), onError: e => (paymentErrors.value = e) })
}
function togglePayment(item) {
    router.put(route('payment-methods.update', item.id), { is_active: !item.is_active }, opts)
}
async function destroyPayment(item) {
    if (!(await confirmDialog(t('settings.confirmDeletePayment', { name: item.name_ru })))) return
    router.delete(route('payment-methods.destroy', item.id), opts)
}

// ── О нас ──────────────────────────────────────────────────
// Текст для одноимённого экрана приложения (GET /v1/about). Две версии
// правятся в одном редакторе с переключателем языка — как текст новости.
const aboutLang   = ref('ru')
const aboutForm   = ref({ about_ru: props.aboutRu || '', about_tk: props.aboutTk || '' })
const aboutErrors = ref({})

const aboutContent = computed({
    get: () => aboutLang.value === 'ru' ? aboutForm.value.about_ru : aboutForm.value.about_tk,
    set: v  => { aboutForm.value[aboutLang.value === 'ru' ? 'about_ru' : 'about_tk'] = v },
})

function saveAbout() {
    aboutErrors.value = {}
    // Обе версии уходят вместе: пустая — это «на этом языке текста нет»
    router.patch(route('settings.about'), { ...aboutForm.value },
        { ...opts, onError: e => (aboutErrors.value = e) })
}

// ── Объявления: интервал поднятия ──────────────────────────
const boostIntervalHours = ref(props.boostIntervalHours)
const boostErrors        = ref({})
function saveBoostSettings() {
    boostErrors.value = {}
    router.patch(route('settings.boost'), { boost_interval_hours: boostIntervalHours.value },
        { ...opts, onError: e => (boostErrors.value = e) })
}
</script>

<template>
  <AppLayout>
    <template #header>{{ t('nav.settings') }}</template>

    <div class="space-y-8">
      <!-- 1. Мониторинг -->
      <section>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-[13px] font-bold uppercase tracking-wide text-muted">{{ t('settings.monitoring') }}</h2>
          <div class="flex items-center gap-3">
            <!-- Время общее для всех карточек: бэкенд отдаёт все проверки одним
                 запросом, поэтому в подвале карточек его не дублируем -->
            <span class="text-[11.5px] text-[var(--text-muted)]">{{ t('settings.updatedAgo', { s: secondsAgo }) }}</span>
            <button
              type="button" :disabled="!!reloading" @click="reload('all')"
              class="flex items-center gap-1.5 rounded-[9px] border border-green/30 bg-green/5 px-3 py-1.5 text-[12px] font-bold text-green transition-colors hover:bg-green/10 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <Icon kind="refresh" :size="13" :class="{ 'animate-spin': reloading === 'all' }" />
              {{ t('settings.refreshAll') }}
            </button>
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div
            v-for="card in monitorCards" :key="card.key"
            class="flex flex-col rounded-2xl border border-[var(--card-border)] bg-[var(--card-bg)] p-[18px] shadow-[var(--card-shadow)]"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="flex min-w-0 items-center gap-3">
                <span class="flex h-[38px] w-[38px] flex-none items-center justify-center rounded-xl" :class="card.accent">
                  <Icon :kind="card.icon" :size="19" />
                </span>
                <div class="min-w-0">
                  <div class="truncate text-[15px] font-bold leading-tight text-[var(--text)]">{{ card.title }}</div>
                  <div class="mt-0.5 truncate text-[12px] text-[var(--text-muted)]">{{ card.subtitle }}</div>
                </div>
              </div>
              <span
                class="flex flex-none items-center gap-1.5 rounded-pill border px-2.5 py-[3px] text-[11.5px] font-bold"
                :class="[TONES[card.tone].badge, { 'status-flash': flashing.includes(card.key) }]"
              >
                <span class="h-1.5 w-1.5 rounded-full" :class="TONES[card.tone].dot"></span>
                {{ card.status }}
              </span>
            </div>

            <!-- mb-4 у показателей, а не mt-* у подвала: подвал прижат к низу
                 через mt-auto, иначе в высокой карточке отступы сложатся -->
            <div class="mb-4 mt-4 grid grid-cols-2 gap-2.5">
              <div
                v-for="(stat, i) in card.stats" :key="stat.label"
                class="min-w-0 rounded-xl border border-[var(--field-border)] bg-[var(--field-bg)] px-3 py-2.5"
              >
                <div class="truncate text-[12px] text-[var(--text-muted)]">{{ stat.label }}</div>
                <div
                  class="truncate font-data font-extrabold"
                  :class="[
                    stat.small ? 'mt-1 text-[13px]' : 'text-[22px] leading-tight',
                    stat.danger ? 'text-red' : (i === 1 ? card.accentText : 'text-[var(--text)]'),
                  ]"
                  :title="String(stat.value)"
                >{{ stat.value }}</div>
              </div>
            </div>

            <div class="mt-auto flex items-center justify-between gap-2">
              <span
                class="min-w-0 truncate text-[11.5px]"
                :class="card.noteWarn ? 'font-semibold text-orange' : 'text-[var(--text-muted)]'"
                :title="card.note"
              >{{ card.note }}</span>
              <button
                type="button" :disabled="!!reloading" @click="reload(card.key)"
                class="flex flex-none items-center gap-1.5 rounded-[8px] border border-[var(--field-border)] px-3 py-[5px] text-[12px] font-semibold text-[var(--text-secondary)] transition-colors hover:bg-[var(--nav-hover)] disabled:cursor-not-allowed disabled:opacity-50"
              >
                <!-- иконку держим всегда: если показывать её только на время
                     спиннера, подпись кнопки прыгает при каждом обновлении -->
                <Icon kind="refresh" :size="12" :class="{ 'animate-spin': isReloading(card.key) }" />
                {{ t('settings.reload') }}
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- 2. Мониторинг OTP-кодов -->
      <section>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-[13px] font-bold uppercase tracking-wide text-muted">{{ t('settings.otpMonitor') }}</h2>
          <div class="flex items-center gap-2">
            <div class="w-56">
              <SearchInput
                v-model="otpPhone"
                size="sm"
                :debounce="350"
                :placeholder="t('settings.otpSearchPlaceholder')"
                @search="fetchOtpCodes"
                @submit="fetchOtpCodes"
              />
            </div>
            <button
              type="button" :title="t('settings.reload')" :disabled="otpReloading"
              @click="reloadOtpCodes"
              class="rounded-full p-1.5 text-muted transition hover:bg-surface hover:text-ink dark:hover:bg-dbg dark:hover:text-slate-100 disabled:opacity-50"
            >
              <Icon kind="refresh" :size="15" :class="{ 'animate-spin': otpReloading }" />
            </button>
          </div>
        </div>

        <div class="overflow-hidden rounded-card bg-white dark:bg-dcard border border-line dark:border-dline">
          <div class="border-b border-line dark:border-dline px-5 py-3 text-[12px] text-muted">
            {{ t('settings.otpMonitorHint') }}
          </div>

          <div v-if="!otpCodes.length" class="px-5 py-10 text-center text-[13px] text-muted">
            {{ t('settings.otpEmpty') }}
          </div>

          <div v-else class="overflow-x-auto">
            <table class="w-full text-[13px]">
              <thead>
                <tr class="border-b border-line dark:border-dline text-left text-muted">
                  <th class="px-5 py-3 font-semibold">{{ t('common.phone') }}</th>
                  <th class="px-5 py-3 font-semibold">{{ t('settings.otpCode') }}</th>
                  <th class="px-5 py-3 font-semibold">{{ t('common.status') }}</th>
                  <th class="px-5 py-3 font-semibold">{{ t('settings.otpAttempts') }}</th>
                  <th class="px-5 py-3 font-semibold">{{ t('settings.otpRequestedAt') }}</th>
                  <th class="px-5 py-3 font-semibold">{{ t('settings.otpExpiresIn') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-line dark:divide-dline">
                <tr v-for="row in otpCodes" :key="row.id">
                  <td class="px-5 py-3">
                    <div class="font-data font-semibold text-ink dark:text-slate-100">{{ row.phone }}</div>
                    <div class="text-[11px] text-muted">{{ row.user_name || t('settings.otpNewUser') }}</div>
                  </td>
                  <td class="px-5 py-3">
                    <button
                      type="button"
                      :title="t('settings.otpCopy')"
                      class="rounded-btn bg-surface dark:bg-dbg px-3 py-1.5 font-data text-[16px] font-extrabold tracking-[0.18em] text-ink dark:text-slate-100 transition hover:bg-blue/10 hover:text-blue"
                      @click="copyCode(row)"
                    >{{ row.code }}</button>
                    <span v-if="copiedCodeId === row.id" class="ml-2 text-[11px] font-bold text-green">{{ t('settings.otpCopied') }}</span>
                  </td>
                  <td class="px-5 py-3">
                    <span class="rounded-pill px-2.5 py-1 text-[11px] font-bold" :class="otpStatusClass(row)">
                      {{ t(`settings.otpStatus.${otpStatus(row)}`) }}
                    </span>
                  </td>
                  <td class="px-5 py-3 font-data" :class="row.attempts > 0 ? 'text-orange' : 'text-muted'">
                    {{ row.attempts }} / {{ row.max_attempts }}
                  </td>
                  <td class="px-5 py-3 font-data text-muted">{{ shortTime(row.created_at) }}</td>
                  <td class="px-5 py-3 font-data" :class="otpStatus(row) === 'active' ? 'font-bold text-green' : 'text-muted'">
                    {{ otpStatus(row) === 'active' ? otpCountdown(row) : '—' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- 3. Роли и права доступа -->
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

      <!-- 4. Справочники причин -->
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

      <!-- 5. Способы оплаты -->
      <section>
        <h2 class="mb-3 text-[13px] font-bold uppercase tracking-wide text-muted">{{ t('settings.paymentSection') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <GeoColumn
            ref="paymentCol"
            :title="t('settings.paymentMethods')"
            :items="paymentItems"
            :empty-text="t('settings.emptyPaymentMethods')"
            :subtitle="m => m.name_tk"
            :errors="paymentErrors"
            @create="createPayment"
            @update="updatePayment"
            @toggle="togglePayment"
            @destroy="destroyPayment"
          />
          <div class="rounded-card border border-line bg-white p-5 text-[12px] leading-relaxed text-muted dark:border-dline dark:bg-dcard">
            {{ t('settings.paymentHint') }}
          </div>
        </div>
      </section>

      <!-- 6. О нас -->
      <section>
        <h2 class="mb-3 text-[13px] font-bold uppercase tracking-wide text-muted">{{ t('settings.aboutSection') }}</h2>
        <div class="rounded-card border border-line bg-white p-5 dark:border-dline dark:bg-dcard">
          <div class="mb-1 font-extrabold text-ink dark:text-slate-100">{{ t('settings.aboutTitle') }}</div>
          <div class="mb-4 text-[12px] text-muted">{{ t('settings.aboutHint') }}</div>

          <!-- Язык правится по одному, но сохраняются обе версии сразу -->
          <div class="mb-3 flex items-center gap-2">
            <button
              v-for="code in ['ru', 'tk']"
              :key="code"
              @click="aboutLang = code"
              class="rounded-btn px-3 py-1.5 text-[12px] font-bold uppercase transition"
              :class="aboutLang === code
                ? 'bg-blue text-white'
                : 'bg-surface text-[var(--text-secondary)] hover:bg-line dark:bg-dbg dark:hover:bg-dline'"
            >{{ code }}</button>

            <span class="ml-auto flex gap-3 text-[11px]">
              <span :class="(aboutForm.about_ru || '').trim() ? 'text-green' : 'text-muted'">
                {{ (aboutForm.about_ru || '').trim() ? t('settings.aboutRuFilled') : t('settings.aboutRuEmpty') }}
              </span>
              <span :class="(aboutForm.about_tk || '').trim() ? 'text-green' : 'text-muted'">
                {{ (aboutForm.about_tk || '').trim() ? t('settings.aboutTkFilled') : t('settings.aboutTkEmpty') }}
              </span>
            </span>
          </div>

          <RichTextEditor v-model="aboutContent" />

          <div v-if="aboutErrors.about_ru || aboutErrors.about_tk" class="mt-1 text-[11px] text-red">
            {{ aboutErrors.about_ru || aboutErrors.about_tk }}
          </div>

          <button @click="saveAbout" class="mt-4 rounded-btn bg-blue px-5 py-2 text-[13px] font-bold text-white transition hover:bg-blue/90">
            {{ t('actions.save') }}
          </button>
        </div>
      </section>

      <!-- 7. Объявления -->
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

    </div>
  </AppLayout>
</template>

<style scoped>
@keyframes status-flash {
    0%, 100% { opacity: 1; }
    50%      { opacity: 0.25; }
}
.status-flash {
    animation: status-flash 0.5s ease-in-out 1;
}
</style>
