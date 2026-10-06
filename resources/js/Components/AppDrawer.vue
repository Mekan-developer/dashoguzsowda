<script setup>
import { useI18n } from 'vue-i18n'
import { confirmDialog } from '@/confirm'

defineProps({
    open:  { type: Boolean, default: false },
    title: { type: String, default: '' },
    width: { type: String, default: '480px' },
})
const emit = defineEmits(['close'])

const { t } = useI18n()

// Закрытие по клику вне панели / крестику — не мгновенное, а через подтверждение,
// чтобы случайный клик мимо не сбрасывал введённые в форму данные.
async function requestClose() {
    const ok = await confirmDialog(t('drawer.closeConfirmMessage'), {
        title: t('drawer.closeConfirmTitle'),
        confirmLabel: t('drawer.closeConfirmYes'),
        cancelLabel: t('drawer.closeConfirmNo'),
        danger: false,
    })
    if (ok) emit('close')
}
</script>

<template>
  <Teleport to="body">
    <Transition name="drawer">
      <div v-if="open" class="fixed inset-0 z-[500] flex justify-end">
        <!-- Overlay -->
        <div class="absolute inset-0 bg-[#0A0C1A]/50" @click="requestClose"></div>

        <!-- Panel -->
        <div
          class="relative flex max-w-full flex-col overflow-hidden border-l border-[var(--card-border)] bg-[var(--card-bg)] font-golos shadow-lg2"
          :style="{ width }"
          role="dialog" aria-modal="true"
        >
          <!-- Header -->
          <div class="flex flex-none items-center justify-between gap-4 border-b border-[var(--card-border)] px-6 py-4">
            <h2 class="text-[17px] font-semibold text-[var(--text)]">{{ title || t('drawer.defaultTitle') }}</h2>
            <button
              type="button"
              @click="requestClose"
              :aria-label="t('actions.close')"
              :title="t('actions.close')"
              class="btn-ghost flex h-[34px] w-[34px] items-center justify-center rounded-[8px] transition-colors duration-150"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <!-- Body -->
          <div class="flex-1 overflow-y-auto px-6 py-5">
            <slot />
          </div>

          <!-- Footer -->
          <div v-if="$slots.footer" class="flex-shrink-0 border-t border-[var(--card-border)] px-6 py-4">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.drawer-enter-active, .drawer-leave-active { transition: all .2s ease-out; }
.drawer-enter-active > div, .drawer-leave-active > div { transition: opacity .2s ease-out, transform .2s ease-out; }
.drawer-enter-from .absolute { opacity: 0; }
.drawer-enter-from > div:last-child { transform: translateX(100%); }
.drawer-leave-to .absolute { opacity: 0; }
.drawer-leave-to > div:last-child { transform: translateX(100%); }
</style>
