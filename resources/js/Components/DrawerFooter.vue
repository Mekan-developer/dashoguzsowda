<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps({
    canSave:    { type: Boolean, default: true },
    saveLabel:  { type: String, default: null },
})
const emit = defineEmits(['cancel', 'save'])
</script>

<template>
  <div class="flex justify-end gap-2">
    <button
      type="button"
      @click="emit('cancel')"
      class="rounded-[10px] border border-[var(--field-border)] bg-transparent px-[18px] py-[10px] text-[13px] font-semibold text-[var(--text-secondary)] transition-colors hover:bg-[var(--nav-hover)]"
    >{{ t('actions.cancel') }}</button>
    <button
      type="button"
      @click="emit('save')"
      :disabled="!canSave"
      class="rounded-[10px] px-5 py-[10px] text-[13px] font-bold transition-colors"
      :class="canSave
        ? 'bg-[var(--accent)] text-white hover:bg-[var(--accent-hover)] shadow-[0_10px_22px_-8px_var(--accent)]'
        : 'cursor-not-allowed bg-[var(--field-disabled-bg)] text-[var(--text-muted)]'"
    >{{ saveLabel || t('actions.save') }}</button>
  </div>
</template>
