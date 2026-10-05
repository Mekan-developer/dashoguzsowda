<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Toasts from '@/Components/Toasts.vue'
import ConfirmHost from '@/Components/ConfirmHost.vue'
import Icon from '@/Components/Icon.vue'

const { t, locale } = useI18n()

// ── Theme ──────────────────────────────────────────────────────────────────
const dark = ref(localStorage.getItem('dk') === '1')
watch(dark, v => {
    document.documentElement.classList.toggle('dark', v)
    localStorage.setItem('dk', v ? '1' : '0')
})
onMounted(() => {
    document.documentElement.classList.toggle('dark', dark.value)
})

// ── Sidebar ────────────────────────────────────────────────────────────────
const collapsed = ref(localStorage.getItem('sb') === '1')
watch(collapsed, v => {
    localStorage.setItem('sb', v ? '1' : '0')
    hideTip()
})

// Подсказка у иконки в свёрнутом сайдбаре. Своя, а не title: системная
// появляется с задержкой ~1 с. position: fixed — иначе её обрезал бы
// overflow сайдбара и прокрутка меню.
const tip = ref(null)
function showTip(event, text) {
    if (!collapsed.value) return
    const rect = event.currentTarget.getBoundingClientRect()
    tip.value = { text, top: rect.top + rect.height / 2, left: rect.right + 10 }
}
function hideTip() { tip.value = null }

// Прокрутка меню переживает переход: AppLayout на каждой странице создаётся
// заново, и без этого меню прыгало бы наверх — нажатые «Настройки» внизу
// уезжали бы из вида. sessionStorage: у каждой вкладки своя позиция.
const navEl = ref(null)
function saveNavScroll() {
    try { sessionStorage.setItem('sbScroll', String(navEl.value?.scrollTop ?? 0)) } catch {}
}
onMounted(() => {
    const nav = navEl.value
    if (!nav) return
    try { nav.scrollTop = Number(sessionStorage.getItem('sbScroll')) || 0 } catch {}
    // Перешли не из меню (ссылка, уведомление) — активный пункт мог остаться за краем
    nav.querySelector('[aria-current="page"]')?.scrollIntoView({ block: 'nearest' })
})

// ── i18n ───────────────────────────────────────────────────────────────────
function setLang(l) {
    locale.value = l
    localStorage.setItem('lang', l)
    // Синхронизируем с Laravel-сессией: flash-сообщения и валидация придут на том же языке
    window.axios?.patch(route('locale.update'), { locale: l }).catch(() => {})
}

// ── Shared data ────────────────────────────────────────────────────────────
const page = usePage()

// Язык, сохранённый в профиле, приоритетнее localStorage (например, вход с другого устройства)
const serverLocale = page.props.auth?.user?.locale
if (serverLocale && ['ru', 'tk'].includes(serverLocale) && serverLocale !== locale.value) {
    locale.value = serverLocale
    localStorage.setItem('lang', serverLocale)
}
const user    = computed(() => page.props.auth?.user)
const isAdmin = computed(() => user.value?.role === 'admin')
// Бейджи меню — общий проп navCounts; `counts` у страниц свой (вкладки фильтра)
const counts = computed(() => page.props.navCounts || {})

const initials = computed(() => {
    const name = user.value?.name?.trim()
    if (!name) return 'АД'
    const parts = name.split(/\s+/)
    return (parts[0][0] + (parts[1]?.[0] ?? '')).toUpperCase()
})

const roleLabel = computed(() => user.value?.role ? t(`role.${user.value.role}`) : '')
const notificationsOpen = ref(false)

// ── Notifications (индивидуальные, с dismiss на пользователя) ───────────────
const dismissedLocally = ref(new Set())
const notificationItems = computed(() =>
    (page.props.notifications || [])
        .filter(n => !dismissedLocally.value.has(n.key))
        .map(n => ({ ...n, text: t(`notifications.${n.type}`, { value: n.label }) }))
)
const notificationsTotal = computed(() => notificationItems.value.length)

function openNotification(item) {
    dismissedLocally.value.add(item.key)
    window.axios?.post(route('notifications.dismiss'), { key: item.key }).catch(() => {})
    notificationsOpen.value = false
    router.visit(route(item.routeName, item.routeParam ?? undefined))
}

// ── Realtime: новое объявление на модерацию (канал private-admin) ──────────
// Колокольчик и счётчики приходят пропами — перезапрашиваем только их, а
// открытый список объявлений обновляем целиком. Звук браузер может не дать
// проиграть до первого клика по странице — тогда молча пропускаем.
const alertSound = typeof Audio !== 'undefined' ? new Audio('/sounds/alert.mp3') : null

function onListingSubmitted() {
    if (alertSound) {
        alertSound.currentTime = 0
        alertSound.play().catch(() => {})
    }
    router.reload(page.component === 'Listings/Index'
        ? { preserveScroll: true }
        : { only: ['notifications', 'navCounts'], preserveScroll: true })
}

onMounted(() => {
    window.Echo?.private('admin').listen('.listing.submitted', onListingSubmitted)
})
onUnmounted(() => {
    window.Echo?.private('admin').stopListening('.listing.submitted', onListingSubmitted)
})

// ── Menu ───────────────────────────────────────────────────────────────────
const sections = computed(() => [
    { title: t('layout.sectionMain').toUpperCase(), eyebrow: t('layout.sectionMain'), items: [
        { label: t('nav.dashboard'),   routeName: 'dashboard',         icon: 'grid' },
        { label: t('nav.users'),       routeName: 'users.index',       icon: 'users',   badge: 'newUsers' },
        { label: t('nav.listings'),    routeName: 'listings.index',    icon: 'listing', badge: 'pendingListings', newFlag: 'hasNewListings' },
        { label: t('nav.videos'),      routeName: 'videos.index',      icon: 'video',   badge: 'pendingVideos',   newFlag: 'hasNewVideos' },
        { label: t('nav.chat'),        routeName: 'chat.index',        icon: 'chat',    badge: 'unreadChats' },
    ]},
    { title: t('layout.sectionContent').toUpperCase(), eyebrow: t('layout.sectionContent'), items: [
        // Категории и география — структура каталога, только admin (см. routes/web.php)
        ...(isAdmin.value ? [
            { label: t('nav.categories'),  routeName: 'categories.index',  icon: 'tag' },
            { label: t('nav.regions'),     routeName: 'regions.index',     icon: 'pin' },
        ] : []),
        { label: t('nav.news'),        routeName: 'news.index',        icon: 'news' },
        { label: t('nav.banners'),     routeName: 'banners.index',     icon: 'layers' },
        { label: t('nav.stores'),      routeName: 'stores.index',      icon: 'shop', badge: 'pendingStores', newFlag: 'hasNewStores' },
        // Заказы — деньги и логистика, поэтому только admin (см. routes/web.php)
        ...(isAdmin.value ? [
            { label: t('nav.orders'), routeName: 'orders.index', icon: 'cart', badge: 'pendingOrders', newFlag: 'hasNewOrders' },
        ] : []),
    ]},
    { title: t('layout.sectionModeration').toUpperCase(), eyebrow: t('layout.sectionModeration'), items: [
        { label: t('nav.complaints'),  routeName: 'complaints.index',  icon: 'flag',  badge: 'newComplaints',  newFlag: 'hasNewComplaints' },
        { label: t('nav.reviews'),     routeName: 'reviews.index',     icon: 'star',  badge: 'pendingReviews', newFlag: 'hasNewReviews' },
    ]},
    // system: группа прижата к низу меню и отделена чертой — видно, где
    // кончается рабочая навигация и начинаются настройки
    { title: t('layout.sectionSystem').toUpperCase(), eyebrow: t('layout.sectionSystem'), system: true, items: [
        // Тарифы — лимиты и деньги, только admin
        ...(isAdmin.value ? [
            { label: t('nav.tariffs'), routeName: 'tariffs.index', icon: 'coin' },
            // Заявки на тариф — деньги принимает лично админ
            { label: t('nav.tariffRequests'), routeName: 'tariff-requests.index', icon: 'receipt', badge: 'pendingTariffRequests', newFlag: 'hasNewTariffRequests' },
        ] : []),
        { label: t('nav.statistics'), routeName: 'statistics.index',  icon: 'chart' },
        ...(isAdmin.value ? [
            { label: t('nav.push'),     routeName: 'push.index',     icon: 'bell' },
            { label: t('nav.settings'), routeName: 'settings.index', icon: 'settings' },
        ] : []),
    ]},
])

function isActive(routeName) {
    try { return route().current(routeName) } catch { return false }
}

const eyebrow = computed(() => sections.value.find(s => s.items.some(i => isActive(i.routeName)))?.eyebrow ?? '')

// ── User menu ──────────────────────────────────────────────────────────────
const userMenuOpen = ref(false)

function logout() {
    router.post(route('logout'))
}
</script>

<template>
  <div class="flex h-screen overflow-hidden font-golos">

    <!-- ── SIDEBAR ──────────────────────────────────────────────────────── -->
    <!--
      Геометрия подобрана так, чтобы при сворачивании ничего не прыгало:
      иконки стоят на x = 15 (nav) + 12 (пункт) = 27 — ровно по центру
      свёрнутых 72px, лого 36px на x = 18 — тоже по центру. Меняется только
      ширина, подписи гаснут по opacity и обрезаются overflow.
    -->
    <aside
      class="flex flex-shrink-0 flex-col overflow-hidden border-r border-[var(--sidebar-border)] bg-[var(--sidebar-bg)] transition-[width] duration-[240ms] ease-in-out motion-reduce:transition-none"
      :style="{ width: collapsed ? '72px' : '256px' }"
    >
      <!-- Brand -->
      <div class="flex h-[72px] flex-none items-center gap-3 border-b border-[var(--sidebar-border)] px-[18px]">
        <!-- Лого круглое: подложка тоже круглая, иначе в тёмной теме торчат углы плашки -->
        <div class="flex h-9 w-9 flex-none items-center justify-center rounded-full p-[2px] dark:bg-white/[.06]">
          <img src="/icons/logo-128.png" :alt="t('layout.brandTitle')" class="h-full w-full object-contain" />
        </div>
        <div
          class="flex min-w-0 flex-col whitespace-nowrap leading-tight transition-opacity duration-200 ease-out"
          :class="collapsed ? 'opacity-0' : 'opacity-100'"
          :aria-hidden="collapsed"
        >
          <span class="truncate text-[15px] font-semibold text-[var(--sidebar-text-strong)]">{{ t('layout.brandTitle') }}</span>
          <span class="truncate text-[11.5px] text-[var(--sidebar-muted)]">{{ t('layout.brandSubtitle') }}</span>
        </div>
      </div>

      <!-- Nav -->
      <nav ref="navEl" @scroll.passive="saveNavScroll" class="flex flex-1 flex-col overflow-y-auto overflow-x-hidden px-[15px] pb-4 pt-5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <div
          v-for="(section, si) in sections"
          :key="section.title"
          :class="section.system ? 'mt-auto pt-5' : (si > 0 ? 'mt-5' : '')"
        >
          <!-- Перед «Системой» — черта: здесь кончается рабочая навигация -->
          <div v-if="section.system" class="mx-3 mb-4 h-px bg-[var(--sidebar-border)]"></div>

          <!-- Заголовок группы. Высота остаётся и в свёрнутом виде — иначе
               пункты съезжали бы по вертикали; вместо текста там короткая черта -->
          <div class="relative mb-1 flex h-6 items-center px-3">
            <span
              class="whitespace-nowrap text-[11px] font-semibold uppercase tracking-[.08em] text-[var(--section-label)] transition-opacity duration-200 ease-out"
              :class="collapsed ? 'opacity-0' : 'opacity-100'"
              :aria-hidden="collapsed"
            >{{ section.title }}</span>
            <span
              class="absolute left-3 top-1/2 h-px w-[18px] bg-[var(--sidebar-border)] transition-opacity duration-200 ease-out"
              :class="collapsed && !section.system ? 'opacity-100' : 'opacity-0'"
            ></span>
          </div>

          <div class="flex flex-col gap-0.5">
            <Link
              v-for="item in section.items"
              :key="item.routeName"
              :href="route(item.routeName)"
              :aria-current="isActive(item.routeName) ? 'page' : null"
              @mouseenter="showTip($event, item.label)"
              @mouseleave="hideTip"
              @focus="showTip($event, item.label)"
              @blur="hideTip"
              class="group relative flex h-[38px] items-center gap-3 overflow-hidden rounded-[6px] px-3 text-[14px] outline-none transition-colors duration-200 ease-out focus-visible:ring-1 focus-visible:ring-[var(--nav-indicator)]"
              :class="isActive(item.routeName)
                ? 'bg-[var(--nav-item-active)] font-semibold text-[var(--sidebar-text-strong)]'
                : 'font-medium text-[var(--sidebar-text)] hover:bg-[var(--nav-item-hover)] hover:text-[var(--sidebar-text-strong)]'"
            >
              <!-- Индикатор активного пункта: вырастает по вертикали из центра -->
              <span
                class="absolute left-0 top-1/2 h-[18px] w-[3px] -translate-y-1/2 rounded-full bg-[var(--nav-indicator)] transition-transform duration-200 ease-out motion-reduce:transition-none"
                :class="isActive(item.routeName) ? 'scale-y-100' : 'scale-y-0'"
              ></span>

              <span
                class="relative flex flex-none items-center justify-center transition-[color,transform] duration-200 ease-out motion-reduce:transition-none"
                :class="isActive(item.routeName)
                  ? 'translate-x-px text-[var(--sidebar-text-strong)]'
                  : 'text-[var(--nav-icon)] group-hover:translate-x-px group-hover:text-[var(--sidebar-text)]'"
              >
                <Icon :kind="item.icon" :size="18" />
                <!-- Счётчик не помещается — показываем точку, чтобы не терять сигнал о модерации -->
                <span
                  v-if="item.badge && counts[item.badge] > 0"
                  class="absolute -right-[4px] -top-[3px] h-[7px] w-[7px] rounded-full bg-[var(--badge-bg)] ring-2 ring-[var(--sidebar-bg)] transition-opacity duration-200"
                  :class="collapsed ? 'opacity-100' : 'opacity-0'"
                ></span>
                <!-- «Новое с последнего открытия» — не счётчик очереди, гаснет от одного захода в раздел -->
                <span
                  v-if="item.newFlag && counts[item.newFlag]"
                  class="absolute -left-[4px] -top-[3px] h-[7px] w-[7px] rounded-full bg-[#4ADE80] ring-2 ring-[var(--sidebar-bg)]"
                ></span>
              </span>

              <span
                class="min-w-0 flex-1 truncate whitespace-nowrap transition-opacity duration-200 ease-out"
                :class="collapsed ? 'opacity-0' : 'opacity-100'"
              >{{ item.label }}</span>
              <span
                v-if="item.badge && counts[item.badge] > 0"
                class="h-[18px] min-w-[20px] flex-none rounded-[4px] bg-[var(--badge-bg)] px-1.5 text-center text-[11px] font-semibold leading-[18px] tabular-nums text-white transition-opacity duration-200 ease-out"
                :class="collapsed ? 'opacity-0' : 'opacity-100'"
              >{{ counts[item.badge] }}</span>
            </Link>
          </div>
        </div>
      </nav>

      <!-- Collapse toggle — того же вида, что пункты меню, иконка на той же оси -->
      <div class="flex-none border-t border-[var(--sidebar-border)] px-[15px] py-3">
        <button
          type="button"
          @click="collapsed = !collapsed"
          @mouseenter="showTip($event, t('layout.expand'))"
          @mouseleave="hideTip"
          :aria-expanded="!collapsed"
          class="group flex h-[38px] w-full cursor-pointer items-center gap-3 overflow-hidden rounded-[6px] px-3 text-[14px] font-medium text-[var(--sidebar-muted)] outline-none transition-colors duration-200 ease-out hover:bg-[var(--nav-item-hover)] hover:text-[var(--sidebar-text)] focus-visible:ring-1 focus-visible:ring-[var(--nav-indicator)]"
        >
          <Icon
            kind="chevronLeft" :size="18"
            class="transition-transform duration-[240ms] ease-in-out motion-reduce:transition-none"
            :class="collapsed ? 'rotate-180' : ''"
          />
          <span
            class="whitespace-nowrap transition-opacity duration-200 ease-out"
            :class="collapsed ? 'opacity-0' : 'opacity-100'"
          >{{ t('layout.collapse') }}</span>
        </button>
      </div>
    </aside>

    <!-- Подсказка у иконки свёрнутого меню -->
    <div
      v-if="tip"
      role="tooltip"
      class="pointer-events-none fixed z-50 -translate-y-1/2 whitespace-nowrap rounded-[6px] bg-[#1C2036] px-2.5 py-1.5 text-[12.5px] font-medium text-[#E9EBF3] shadow-[0_4px_12px_rgba(0,0,0,.18)] dark:border dark:border-white/[.08]"
      :style="{ top: tip.top + 'px', left: tip.left + 'px' }"
    >{{ tip.text }}</div>

    <!-- ── MAIN AREA ─────────────────────────────────────────────────────── -->
    <div class="flex flex-1 flex-col min-w-0 overflow-hidden bg-[var(--content-bg)]">

      <!-- Top bar: lang/theme/notifications/profile cluster -->
      <div class="flex h-[68px] flex-none items-center justify-end border-b border-[var(--card-border)] bg-[var(--card-bg)] dark:bg-[#14172A] px-[26px]">
        <div class="flex items-center gap-3.5">
          <!-- Язык -->
          <div class="flex flex-col items-center gap-1">
            <div class="flex items-center gap-[2px] rounded-[8px] bg-[var(--field-bg)] dark:bg-white/[.06] p-[3px]">
              <button
                v-for="l in ['ru', 'tk']" :key="l"
                @click="setLang(l)"
                class="min-w-[38px] rounded-[6px] px-[13px] py-[6.5px] text-[12px] font-bold uppercase tracking-[.02em] transition-colors cursor-pointer"
                :class="locale === l
                  ? 'bg-[var(--accent)] text-white'
                  : 'text-[var(--text-muted)] dark:text-white/[.42] hover:text-[var(--text)]'"
              >{{ l }}</button>
            </div>
            <span class="text-[9.5px] text-[var(--text-muted)] dark:text-white/[.42]">{{ t('topbar.langLabel') }}</span>
          </div>

          <div class="h-[30px] w-px bg-[var(--card-border)]"></div>

          <!-- Тема -->
          <div class="flex flex-col items-center gap-1">
            <button
              @click="dark = !dark"
              class="flex h-9 w-9 items-center justify-center rounded-[8px] text-[var(--text-secondary)] dark:text-white/[.68] hover:bg-[var(--nav-hover)] dark:hover:bg-white/[.07] transition-colors cursor-pointer"
            >
              <Icon :kind="dark ? 'moon' : 'sun'" :size="17" />
            </button>
            <span class="w-20 text-center whitespace-nowrap text-[9.5px] text-[var(--text-muted)] dark:text-white/[.42]">{{ dark ? t('topbar.lightTheme') : t('topbar.darkTheme') }}</span>
          </div>

          <div class="h-[30px] w-px bg-[var(--card-border)]"></div>

          <!-- Уведомления -->
          <div class="relative flex flex-col items-center gap-1">
            <button
              @click="notificationsOpen = !notificationsOpen"
              class="relative flex h-9 w-9 items-center justify-center rounded-[8px] text-[var(--text-secondary)] dark:text-white/[.68] hover:bg-[var(--nav-hover)] dark:hover:bg-white/[.07] transition-colors cursor-pointer"
            >
              <Icon kind="bell" :size="17" />
              <span
                v-if="notificationsTotal > 0"
                class="absolute -right-1 -top-1 flex h-[15px] min-w-[15px] items-center justify-center rounded-[4px] border-2 border-[var(--card-bg)] dark:border-[#14172A] bg-[var(--badge-bg)] px-[3px] text-[9.5px] font-bold text-white"
              >{{ notificationsTotal }}</span>
            </button>
            <span class="w-20 text-center whitespace-nowrap text-[9.5px] text-[var(--text-muted)] dark:text-white/[.42]">{{ t('topbar.notifications') }}</span>

            <Transition name="menu">
              <div
                v-if="notificationsOpen"
                v-click-outside="() => notificationsOpen = false"
                class="absolute right-0 top-full mt-2 w-72 rounded-[10px] bg-[var(--card-bg)] shadow-[var(--card-shadow)] border border-[var(--card-border)] z-50 overflow-hidden"
              >
                <div class="px-4 py-3 border-b border-[var(--card-border)] text-[13px] font-bold text-[var(--text)]">{{ t('topbar.notifications') }}</div>
                <div v-if="notificationItems.length" class="max-h-80 overflow-y-auto">
                  <button
                    v-for="item in notificationItems"
                    :key="item.key"
                    type="button"
                    @click="openNotification(item)"
                    class="flex w-full items-center gap-2.5 px-4 py-3 text-left text-[13px] text-[var(--text)] hover:bg-[var(--nav-hover)] dark:hover:bg-white/[.07] transition-colors cursor-pointer"
                  >
                    <Icon :kind="item.icon" :size="16" class="flex-none text-[var(--text-secondary)]" />
                    <span class="flex-1 truncate">{{ item.text }}</span>
                  </button>
                </div>
                <div v-else class="px-4 py-6 text-center text-[12.5px] text-[var(--text-muted)]">{{ t('topbar.noNotifications') }}</div>
              </div>
            </Transition>
          </div>

          <div class="h-[30px] w-px bg-[var(--card-border)]"></div>

          <!-- Профиль -->
          <div class="relative">
            <button
              @click="userMenuOpen = !userMenuOpen"
              class="flex items-center gap-2.5 rounded-[8px] px-2 py-1.5 hover:bg-[var(--nav-hover)] dark:hover:bg-white/[.07] transition-colors cursor-pointer"
            >
              <div class="relative h-9 w-9 flex-none">
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-[var(--accent-tint)] dark:bg-[rgba(109,99,242,.18)] text-[12.5px] font-bold text-[var(--accent)]">
                  {{ initials }}
                </div>
                <span class="absolute -bottom-0.5 -right-0.5 h-[10px] w-[10px] rounded-full bg-[#4ADE80] border-2 border-[var(--card-bg)] dark:border-[#14172A]"></span>
              </div>
              <div class="hidden w-[120px] flex-col items-start leading-[1.25] sm:flex">
                <span class="block w-full truncate text-[13px] font-bold text-[var(--text)] dark:text-[#F5F5FA]">{{ user?.name }}</span>
                <span class="block w-full truncate text-[10.5px] text-[var(--text-muted)] dark:text-white/[.42]">{{ roleLabel }}</span>
              </div>
              <Icon kind="chevronDown" :size="14" class="text-[var(--text-muted)] dark:text-white/[.42]" />
            </button>

            <Transition name="menu">
              <div
                v-if="userMenuOpen"
                v-click-outside="() => userMenuOpen = false"
                class="absolute right-0 top-full mt-2 w-48 rounded-[10px] bg-[var(--card-bg)] shadow-[var(--card-shadow)] border border-[var(--card-border)] z-50 overflow-hidden"
              >
                <div class="px-4 py-3 border-b border-[var(--card-border)]">
                  <div class="text-[13px] font-bold text-[var(--text)]">{{ user?.name }}</div>
                  <div class="text-[11px] text-[var(--text-muted)]">{{ user?.phone }}</div>
                </div>
                <button
                  @click="logout"
                  class="flex w-full items-center gap-2 px-4 py-3 text-[13px] font-semibold text-red hover:bg-red/5 transition"
                >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                  {{ t('topbar.logout') }}
                </button>
              </div>
            </Transition>
          </div>
        </div>
      </div>

      <!-- Page header row -->
      <div class="flex flex-none items-center justify-between px-8 pb-2 pt-[26px]">
        <div>
          <div v-if="eyebrow" class="mb-1 text-[11.5px] text-[var(--text-muted)]">{{ eyebrow }}</div>
          <div class="text-[22px] font-extrabold text-[var(--text)]"><slot name="header" /></div>
        </div>
        <div><slot name="actions" /></div>
      </div>

      <!-- Page content -->
      <main class="flex-1 overflow-y-auto px-8 pb-8 pt-2">
        <slot />
      </main>
    </div>

    <!-- Global Toasts -->
    <Toasts />

    <!-- Единый диалог подтверждения (confirmDialog из @/confirm) -->
    <ConfirmHost />
  </div>
</template>

<style scoped>
.menu-enter-active, .menu-leave-active { transition: all .15s ease; }
.menu-enter-from, .menu-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
