<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Toasts from '@/Components/Toasts.vue'
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
watch(collapsed, v => localStorage.setItem('sb', v ? '1' : '0'))

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
const user   = computed(() => page.props.auth?.user)
const counts = computed(() => page.props.counts || {})

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

// ── Menu ───────────────────────────────────────────────────────────────────
const sections = computed(() => [
    { title: t('layout.sectionMain').toUpperCase(), eyebrow: t('layout.sectionMain'), items: [
        { label: t('nav.dashboard'),   routeName: 'dashboard',         icon: 'grid' },
        { label: t('nav.users'),       routeName: 'users.index',       icon: 'users',   badge: 'newUsers' },
        { label: t('nav.listings'),    routeName: 'listings.index',    icon: 'listing', badge: 'pendingListings' },
        { label: t('nav.videos'),      routeName: 'videos.index',      icon: 'video',   badge: 'pendingVideos' },
        { label: t('nav.chat'),        routeName: 'chat.index',        icon: 'chat',    badge: 'unreadChats' },
    ]},
    { title: t('layout.sectionContent').toUpperCase(), eyebrow: t('layout.sectionContent'), items: [
        { label: t('nav.categories'),  routeName: 'categories.index',  icon: 'tag' },
        { label: t('nav.regions'),     routeName: 'regions.index',     icon: 'pin' },
        { label: t('nav.news'),        routeName: 'news.index',        icon: 'news' },
        { label: t('nav.banners'),     routeName: 'banners.index',     icon: 'layers' },
    ]},
    { title: t('layout.sectionModeration').toUpperCase(), eyebrow: t('layout.sectionModeration'), items: [
        { label: t('nav.complaints'),  routeName: 'complaints.index',  icon: 'flag',  badge: 'newComplaints' },
        { label: t('nav.reviews'),     routeName: 'reviews.index',     icon: 'star',  badge: 'pendingReviews' },
    ]},
    { title: t('layout.sectionSystem').toUpperCase(), eyebrow: t('layout.sectionSystem'), items: [
        { label: t('nav.tariffs'),     routeName: 'tariffs.index',     icon: 'coin' },
        { label: t('nav.statistics'), routeName: 'statistics.index',  icon: 'chart' },
        ...(user.value?.role === 'admin' ? [
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
    <aside
      class="flex flex-shrink-0 flex-col bg-[var(--sidebar-bg)] border-r border-[var(--sidebar-border)] transition-all duration-300 overflow-hidden box-border"
      :style="{ width: collapsed ? '68px' : '252px', padding: collapsed ? '18px 10px 16px' : '18px 14px 16px' }"
    >
      <!-- Brand -->
      <div class="flex items-center gap-2.5 px-1.5 pt-1 pb-[22px]">
        <div class="flex h-[60px] w-[60px] flex-none items-center justify-center rounded-[9px] dark:bg-white text-white dark:shadow-[0_0_0_4px_var(--accent-tint)]">
          <img src="/icons/logo.png" alt="Logo" class="h-auto w-[56px]" />
        </div>
        <div v-if="!collapsed" class="flex flex-col leading-[1.18]">
          <span class="text-[14.5px] font-bold text-[var(--sidebar-text-strong)]">Daşoguz söwda </span>
          <span class="text-[11px] text-[var(--sidebar-muted)]">meýdançasy</span>
        </div>
      </div>

      <!-- Nav -->
      <nav class="flex-1 overflow-y-auto overflow-x-hidden">
        <template v-for="(section, si) in sections" :key="section.title">
          <div
            v-if="!collapsed"
            class="text-[10.5px] font-bold uppercase tracking-[.07em] text-[var(--section-label)] px-2.5"
            :style="{ padding: (si === 0 ? '8px' : '16px') + ' 10px 6px' }"
          >{{ section.title }}</div>

          <Link
            v-for="item in section.items"
            :key="item.routeName"
            :href="route(item.routeName)"
            class="group flex items-center gap-2.5 rounded-lg text-[13.5px] font-medium transition-colors"
            :class="isActive(item.routeName)
              ? 'rounded-[9px] bg-[var(--accent-tint)] text-[var(--accent)] font-bold dark:bg-[var(--accent)] dark:text-white dark:font-semibold dark:shadow-[0_0_0_4px_var(--accent-tint),0_8px_18px_-6px_var(--accent)]'
              : 'text-[var(--sidebar-text)] hover:bg-[var(--nav-hover)]'"
            :style="{ padding: isActive(item.routeName) ? '9px 10px' : '8px 10px' }"
          >
            <Icon :kind="item.icon" :size="17" class="flex-none" />
            <span v-if="!collapsed" class="flex-1 truncate">{{ item.label }}</span>
            <span
              v-if="item.badge && counts[item.badge] > 0 && !collapsed"
              class="rounded-pill bg-pink px-[5px] py-px text-[9px] font-extrabold text-white"
            >{{ counts[item.badge] }}</span>
          </Link>
        </template>
      </nav>

      <!-- Collapse toggle -->
      <div class="mt-auto flex justify-end pt-3.5">
        <button
          @click="collapsed = !collapsed"
          class="flex h-7 w-7 items-center justify-center rounded-lg text-[var(--sidebar-muted)] hover:bg-[var(--nav-hover)] transition-colors"
        >
          <Icon kind="chevronLeft" :size="15" :class="collapsed ? 'rotate-180' : ''" class="transition-transform duration-300" />
        </button>
      </div>
    </aside>

    <!-- ── MAIN AREA ─────────────────────────────────────────────────────── -->
    <div class="flex flex-1 flex-col min-w-0 overflow-hidden bg-[var(--content-bg)]">

      <!-- Top bar: search + lang/theme/notifications/profile cluster -->
      <div class="flex h-[68px] flex-none items-center justify-between border-b border-[var(--card-border)] bg-[var(--card-bg)] dark:bg-[#14172A] px-[26px]">
        <div class="flex w-[280px] items-center gap-2.5 rounded-[9px] border border-[var(--field-border)] bg-[var(--field-bg)] px-3 py-2">
          <Icon kind="search" :size="16" class="text-[var(--text-muted)]" />
          <span class="text-[13px] text-[var(--text-muted)]">{{ t('topbar.searchSection') }}</span>
        </div>

        <div class="flex items-center gap-3.5">
          <!-- Язык -->
          <div class="flex flex-col items-center gap-1">
            <div class="flex items-center gap-[2px] rounded-[10px] bg-[var(--field-bg)] dark:bg-white/[.06] p-[3px]">
              <button
                v-for="l in ['ru', 'tk']" :key="l"
                @click="setLang(l)"
                class="min-w-[38px] rounded-[8px] px-[13px] py-[6.5px] text-[12px] font-bold uppercase tracking-[.02em] transition-colors cursor-pointer"
                :class="locale === l
                  ? 'bg-[var(--accent)] text-white shadow-[0_4px_10px_-3px_var(--accent)]'
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
              class="flex h-9 w-9 items-center justify-center rounded-[10px] text-[var(--text-secondary)] dark:text-white/[.68] hover:bg-[var(--nav-hover)] dark:hover:bg-white/[.07] transition-colors cursor-pointer"
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
              class="relative flex h-9 w-9 items-center justify-center rounded-[10px] text-[var(--text-secondary)] dark:text-white/[.68] hover:bg-[var(--nav-hover)] dark:hover:bg-white/[.07] transition-colors cursor-pointer"
            >
              <Icon kind="bell" :size="17" />
              <span
                v-if="notificationsTotal > 0"
                class="absolute -right-1 -top-1 flex h-[15px] min-w-[15px] items-center justify-center rounded-lg border-2 border-[var(--card-bg)] dark:border-[#14172A] bg-[#F0554C] px-[3px] text-[9.5px] font-bold text-white"
              >{{ notificationsTotal }}</span>
            </button>
            <span class="w-20 text-center whitespace-nowrap text-[9.5px] text-[var(--text-muted)] dark:text-white/[.42]">{{ t('topbar.notifications') }}</span>

            <Transition name="menu">
              <div
                v-if="notificationsOpen"
                v-click-outside="() => notificationsOpen = false"
                class="absolute right-0 top-full mt-2 w-72 rounded-2xl bg-[var(--card-bg)] shadow-[var(--card-shadow)] border border-[var(--card-border)] z-50 overflow-hidden"
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
              class="flex items-center gap-2.5 rounded-[11px] px-2 py-1.5 hover:bg-[var(--nav-hover)] dark:hover:bg-white/[.07] transition-colors cursor-pointer"
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
                class="absolute right-0 top-full mt-2 w-48 rounded-2xl bg-[var(--card-bg)] shadow-[var(--card-shadow)] border border-[var(--card-border)] z-50 overflow-hidden"
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
  </div>
</template>

<style scoped>
.menu-enter-active, .menu-leave-active { transition: all .15s ease; }
.menu-enter-from, .menu-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
