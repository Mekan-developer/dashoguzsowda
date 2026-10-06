<script setup>
import { useI18n } from 'vue-i18n'
import Icon from '@/Components/Icon.vue'

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
        class="fixed inset-0 z-[600] flex items-center justify-center bg-[#0A0C1A]/50 p-4"
        @click.self="$emit('cancel')"
      >
        <div role="alertdialog" aria-modal="true" class="card w-full max-w-[420px] p-6 shadow-lg2">
          <div class="mb-5 flex items-start gap-3.5">
            <!-- Иконка подсказывает характер действия, не только цвет кнопки -->
            <span
              class="flex h-9 w-9 flex-none items-center justify-center rounded-full"
              :class="danger ? 'bg-red/10 text-red' : 'bg-[var(--accent-tint)] text-link'"
            ><Icon :kind="danger ? 'trash' : 'check'" :size="17" /></span>
            <div class="min-w-0">
              <h3 class="text-[16px] font-semibold text-[var(--text)]">{{ title || t('confirm.title') }}</h3>
              <p class="mt-1 text-[13.5px] leading-relaxed text-[var(--text-secondary)]">{{ message || t('confirm.message') }}</p>
            </div>
          </div>
          <div class="flex justify-end gap-2">
            <button
              type="button"
              @click="$emit('cancel')"
              class="btn btn-secondary"
            >{{ cancelLabel || t('actions.cancel') }}</button>
            <button
              type="button"
              @click="$emit('confirm')"
              class="btn"
              :class="danger ? 'btn-danger' : 'btn-primary'"
            >{{ confirmLabel || t('common.confirm') }}</button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.ov-enter-active, .ov-leave-active { transition: opacity .16s ease-out; }
.ov-enter-from, .ov-leave-to { opacity: 0; }
</style>
