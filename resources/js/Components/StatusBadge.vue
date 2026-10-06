<script setup>
import { useI18n } from 'vue-i18n'

// label — своя подпись при том же цвете (например «Скрыта» у категории)
const props = defineProps({ status: String, type: { type: String, default: 'listing' }, label: { type: String, default: null } })

const { t } = useI18n()

// Спокойные семантические тона: приглушённый текст на прозрачной подложке,
// чтобы статус читался, но не спорил с данными строки
const tones = {
    success: { cls: 'bg-emerald-500/10 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300', dot: 'bg-emerald-500 dark:bg-emerald-400' },
    warning: { cls: 'bg-amber-500/10 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300',         dot: 'bg-amber-500 dark:bg-amber-400' },
    danger:  { cls: 'bg-red-500/10 text-red-700 dark:bg-red-400/10 dark:text-red-300',                 dot: 'bg-red-500 dark:bg-red-400' },
    info:    { cls: 'bg-sky-500/10 text-sky-700 dark:bg-sky-400/10 dark:text-sky-300',                 dot: 'bg-sky-500 dark:bg-sky-400' },
    purple:  { cls: 'bg-violet-500/10 text-violet-700 dark:bg-violet-400/10 dark:text-violet-300',     dot: 'bg-violet-500 dark:bg-violet-400' },
    neutral: { cls: 'bg-slate-500/10 text-slate-600 dark:bg-white/[.06] dark:text-slate-300',          dot: 'bg-slate-400' },
}

const config = {
    pending:  tones.warning,
    approved: tones.success,
    rejected: tones.danger,
    // Скрыто сверх лимита тарифа (истёк платный) — не решение модератора
    suspended: tones.neutral,
    active:   tones.success,
    blocked:  tones.danger,
    new:      tones.info,
    reviewed: tones.warning,
    resolved: tones.success,
    // Заказы: свой путь статусов, см. CLAUDE.md → «Заказы и корзина»
    completed: tones.success,
    canceled: tones.neutral,
    accepted: tones.success,
    declined: tones.danger,
    regular:  tones.info,
    advertising: tones.purple,
}

function text(status) {
    if (props.label) return props.label
    return config[status] ? t(`status.${status}`) : status
}
</script>

<template>
  <span
    class="inline-flex h-6 items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 text-[11.5px] font-semibold"
    :class="(config[status] || tones.neutral).cls"
  >
    <span class="h-1.5 w-1.5 flex-none rounded-full" :class="(config[status] || tones.neutral).dot"></span>
    {{ text(status) }}
  </span>
</template>
