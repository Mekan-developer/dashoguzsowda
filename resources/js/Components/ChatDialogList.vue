<script setup>
import { Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import EmptyState from '@/Components/EmptyState.vue'

/** Левая колонка чата: диалоги с последним сообщением, временем и непрочитанными */
defineProps({
    dialogs:  { type: Array, default: () => [] },
    activeId: { type: Number, default: null },
})

const { t, locale } = useI18n()

// Сегодня — время, раньше — дата: так список быстрее читается
function shortTime(d) {
    if (!d) return ''
    const date = new Date(d)
    const today = new Date().toDateString() === date.toDateString()
    return today
        ? date.toLocaleTimeString(locale.value, { hour: '2-digit', minute: '2-digit' })
        : date.toLocaleDateString(locale.value, { day: '2-digit', month: '2-digit' })
}
</script>

<template>
  <div v-if="dialogs.length" class="divide-y divide-[var(--card-border)]">
    <Link
      v-for="d in dialogs" :key="d.id"
      :href="route('chat.show', d.id)"
      :aria-current="d.id === activeId ? 'page' : null"
      class="relative flex items-center gap-3 px-4 py-3 outline-none transition-colors duration-150 focus-visible:bg-[var(--nav-hover)]"
      :class="d.id === activeId ? 'bg-[var(--nav-item-active)]' : 'hover:bg-[var(--nav-hover)]'"
    >
      <span v-if="d.id === activeId" class="absolute inset-y-2 left-0 w-[3px] rounded-full bg-[var(--nav-indicator)]"></span>
      <div class="flex h-10 w-10 flex-none items-center justify-center rounded-full bg-[var(--accent-tint)] text-[14px] font-semibold text-link">
        {{ (d.name || d.phone || '?').charAt(0).toUpperCase() }}
      </div>
      <div class="min-w-0 flex-1">
        <div class="flex items-baseline justify-between gap-2">
          <span class="truncate text-[13.5px] text-[var(--text)]" :class="d.unread_count > 0 ? 'font-semibold' : 'font-medium'">{{ d.name || d.phone }}</span>
          <span class="flex-none font-data text-[11.5px] tabular-nums text-[var(--text-muted)]">{{ shortTime(d.messages?.[0]?.created_at) }}</span>
        </div>
        <div class="mt-0.5 flex items-center gap-2">
          <p class="min-w-0 flex-1 truncate text-[12.5px]" :class="d.unread_count > 0 ? 'text-[var(--text-secondary)]' : 'text-[var(--text-muted)]'">
            {{ d.messages?.[0]?.text || t('chat.noMessages') }}
          </p>
          <span
            v-if="d.unread_count > 0"
            class="h-[18px] min-w-[18px] flex-none rounded-full bg-[var(--accent)] px-1.5 text-center text-[11px] font-semibold leading-[18px] tabular-nums text-white"
          >{{ d.unread_count }}</span>
        </div>
      </div>
    </Link>
  </div>
  <EmptyState v-else compact icon="chat" :title="t('chat.noDialogs')" />
</template>
