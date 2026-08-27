<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ConfirmModal from '@/Components/ConfirmModal.vue'

defineProps({
    open:  { type: Boolean, default: false },
    title: { type: String, default: '' },
    width: { type: String, default: '480px' },
})
const emit = defineEmits(['close'])

const { t } = useI18n()

// Закрытие по клику вне панели / крестику — не мгновенное, а через подтверждение,
// чтобы случайный клик мимо не сбрасывал введённые в форму данные.
const confirmingClose = ref(false)
function requestClose() { confirmingClose.value = true }
function confirmClose() { confirmingClose.value = false; emit('close') }
function cancelClose()  { confirmingClose.value = false }
</script>

<template>
  <Teleport to="body">
    <Transition name="drawer">
      <div v-if="open" class="fixed inset-0 z-[500] flex justify-end">
        <!-- Overlay -->
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="requestClose"></div>

        <!-- Panel -->
        <div
          class="relative flex flex-col bg-white shadow-[0_0_60px_rgba(0,0,0,.2)] dark:bg-dcard overflow-hidden font-golos"
          :style="{ width }"
        >
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-5 border-b border-line dark:border-dline">
            <h2 class="text-[16px] font-extrabold text-ink dark:text-slate-100">{{ title || t('drawer.defaultTitle') }}</h2>
            <button
              @click="requestClose"
              class="flex h-8 w-8 items-center justify-center rounded-[7px] text-muted hover:bg-surface dark:hover:bg-white/10 transition"
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
          <div v-if="$slots.footer" class="flex-shrink-0 border-t border-line dark:border-dline px-6 py-4">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

  <ConfirmModal
    :open="confirmingClose"
    :title="t('drawer.closeConfirmTitle')"
    :message="t('drawer.closeConfirmMessage')"
    :confirm-label="t('drawer.closeConfirmYes')"
    :cancel-label="t('drawer.closeConfirmNo')"
    :danger="false"
    @confirm="confirmClose"
    @cancel="cancelClose"
  />
</template>

<style scoped>
.drawer-enter-active, .drawer-leave-active { transition: all .3s ease; }
.drawer-enter-from .absolute { opacity: 0; }
.drawer-enter-from > div:last-child { transform: translateX(100%); }
.drawer-leave-to .absolute { opacity: 0; }
.drawer-leave-to > div:last-child { transform: translateX(100%); }
</style>
