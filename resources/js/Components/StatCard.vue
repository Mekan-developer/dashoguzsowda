<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import Icon from '@/Components/Icon.vue'

/**
 * KPI-карточка: подпись, значение, мелкая строка изменения.
 *
 * <StatCard :label="t('dashboard.totalUsers')" :value="stats.users"
 *           icon="users" tone="info" sub="+12 за 7 дней" :href="route('users.index')" />
 */
const props = defineProps({
    label: String,
    value: [String, Number],
    sub:   String,
    // Тон подсказки: зелёный — прирост, жёлтый — ждёт действия и т.п.
    subTone: { type: String, default: 'muted' },
    icon:  { type: String, default: null },
    // accent | success | warning | danger | info
    tone:  { type: String, default: 'accent' },
    href:  { type: String, default: null },
})

const tones = {
    accent:  'bg-[var(--accent-tint)] text-link',
    success: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    warning: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    danger:  'bg-red-500/10 text-red-600 dark:text-red-400',
    info:    'bg-sky-500/10 text-sky-600 dark:text-sky-400',
}
const subTones = {
    muted:   'text-[var(--text-muted)]',
    success: 'text-emerald-600 dark:text-emerald-400',
    warning: 'text-amber-600 dark:text-amber-400',
    danger:  'text-red-600 dark:text-red-400',
}

const formatted = computed(() =>
    typeof props.value === 'number' ? props.value.toLocaleString('ru-RU') : (props.value ?? '—'),
)
</script>

<template>
  <component
    :is="href ? Link : 'div'"
    :href="href ?? undefined"
    class="card group flex min-w-0 flex-col p-5"
    :class="href ? 'outline-none transition-colors duration-150 ease-out hover:border-[var(--text-muted)] focus-visible:ring-2 focus-visible:ring-[var(--accent)]' : ''"
  >
    <div class="flex items-start justify-between gap-3">
      <span class="truncate text-[12.5px] font-medium text-[var(--text-secondary)]">{{ label }}</span>
      <span v-if="icon" class="flex h-8 w-8 flex-none items-center justify-center rounded-[8px]" :class="tones[tone]">
        <Icon :kind="icon" :size="16" />
      </span>
    </div>
    <div class="mt-1 font-data text-[26px] font-semibold leading-tight tabular-nums text-[var(--text)]">{{ formatted }}</div>
    <div v-if="sub" class="mt-1 truncate text-[12px] font-medium" :class="subTones[subTone]">{{ sub }}</div>
  </component>
</template>
