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
    class="group flex h-10 flex-shrink-0 items-center gap-2 rounded-[8px] px-4 text-[13.5px] font-semibold outline-none transition-colors duration-150 ease-out focus-visible:ring-2 focus-visible:ring-[var(--accent)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--content-bg)]"
    :class="disabled
      ? 'cursor-not-allowed bg-[var(--field-disabled-bg)] text-[var(--text-muted)]'
      : 'bg-[var(--accent)] text-white hover:bg-[var(--accent-hover)] active:bg-[var(--accent)]'"
  >
    <!-- Плюс чуть поворачивается при наведении — единственная анимация кнопки -->
    <Icon kind="plus" :size="16" class="transition-transform duration-150 ease-out group-hover:rotate-90 motion-reduce:transition-none" />
    {{ label }}
  </button>
</template>
