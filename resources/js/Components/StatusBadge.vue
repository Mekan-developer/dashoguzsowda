<script setup>
import { useI18n } from 'vue-i18n'

defineProps({ status: String, type: { type: String, default: 'listing' } })

const { t } = useI18n()

const config = {
    pending:  { cls: 'bg-orange/10 text-orange', dot: 'bg-orange' },
    approved: { cls: 'bg-green/10 text-green',   dot: 'bg-green' },
    rejected: { cls: 'bg-red/10 text-red',       dot: 'bg-red' },
    active:   { cls: 'bg-green/10 text-green',   dot: 'bg-green' },
    blocked:  { cls: 'bg-red/10 text-red',       dot: 'bg-red' },
    new:      { cls: 'bg-blue/10 text-blue',     dot: 'bg-blue' },
    reviewed: { cls: 'bg-orange/10 text-orange', dot: 'bg-orange' },
    resolved: { cls: 'bg-green/10 text-green',   dot: 'bg-green' },
    // Заказы: свой путь статусов, см. CLAUDE.md → «Заказы и корзина»
    completed: { cls: 'bg-green/10 text-green',   dot: 'bg-green' },
    canceled: { cls: 'bg-surface text-muted',     dot: 'bg-muted' },
    accepted: { cls: 'bg-green/10 text-green',    dot: 'bg-green' },
    declined: { cls: 'bg-red/10 text-red',        dot: 'bg-red' },
    regular:  { cls: 'bg-blue/10 text-blue',     dot: 'bg-blue' },
    advertising: { cls: 'bg-purple/10 text-purple', dot: 'bg-purple' },
}

function label(status) {
    return config[status] ? t(`status.${status}`) : status
}
</script>

<template>
  <span
    class="inline-flex items-center gap-1 rounded-pill px-2.5 py-0.5 text-[11px] font-bold"
    :class="config[status]?.cls || 'bg-surface text-muted'"
  >
    <span class="h-1.5 w-1.5 rounded-full" :class="config[status]?.dot || 'bg-muted'"></span>
    {{ label(status) }}
  </span>
</template>
