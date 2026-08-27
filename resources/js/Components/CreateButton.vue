<script setup>
import Icon from '@/Components/Icon.vue'

defineProps({
    label:    { type: String, required: true },
    disabled: { type: Boolean, default: false },
})
defineEmits(['click'])
</script>

<template>
  <button
    type="button"
    :disabled="disabled"
    @click="$emit('click')"
    class="flex flex-shrink-0 items-center gap-[7px] rounded-[9px] px-4 py-[10px] text-[13px] font-bold transition-colors"
    :class="disabled
      ? 'cursor-not-allowed bg-[var(--field-disabled-bg)] text-[var(--text-muted)]'
      : 'bg-[var(--accent)] text-white shadow-[0_8px_18px_-6px_var(--accent)] hover:bg-[var(--accent-hover)]'"
  >
    <Icon kind="plus" :size="14" />
    {{ label }}
  </button>
</template>
