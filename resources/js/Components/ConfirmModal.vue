<script setup>
import { useI18n } from 'vue-i18n'

defineProps({
    open:         { type: Boolean, default: false },
    message:      { type: String,  default: '' },
    danger:       { type: Boolean, default: true },
    title:        { type: String,  default: '' },
    confirmLabel: { type: String,  default: '' },
    cancelLabel:  { type: String,  default: '' },
})
defineEmits(['confirm', 'cancel'])

const { t } = useI18n()
</script>

<template>
  <Teleport to="body">
    <Transition name="ov">
      <div
        v-if="open"
        class="fixed inset-0 z-[600] flex items-center justify-center bg-black/40 backdrop-blur-sm"
        @click.self="$emit('cancel')"
      >
        <div class="w-[400px] rounded-card bg-white p-6 shadow-[0_24px_48px_rgba(0,0,0,.18)] dark:bg-dcard">
          <h3 class="mb-2 text-[17px] font-extrabold text-ink dark:text-slate-100">{{ title || t('confirm.title') }}</h3>
          <p class="mb-6 text-[13px] text-muted">{{ message || t('confirm.message') }}</p>
          <div class="flex gap-2.5">
            <button
              @click="$emit('cancel')"
              class="flex-1 rounded-btn border-2 border-line bg-white py-[11px] text-[13px] font-bold text-muted transition hover:border-blue hover:text-blue dark:bg-dcard dark:border-dline"
            >{{ cancelLabel || t('actions.cancel') }}</button>
            <button
              @click="$emit('confirm')"
              class="flex-1 rounded-btn py-[11px] text-[13px] font-bold text-white transition hover:opacity-90"
              :class="danger ? 'bg-red' : 'bg-blue'"
            >{{ confirmLabel || t('common.confirm') }}</button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.ov-enter-active, .ov-leave-active { transition: all .2s ease; }
.ov-enter-from, .ov-leave-to { opacity: 0; }
</style>
