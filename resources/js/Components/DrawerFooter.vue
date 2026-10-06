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
      class="btn btn-secondary"
    >{{ t('actions.cancel') }}</button>
    <button
      type="button"
      @click="emit('save')"
      :disabled="!canSave"
      class="btn btn-primary"
    >{{ saveLabel || t('actions.save') }}</button>
  </div>
</template>
