<script setup>
import { ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from '@/Components/Icon.vue'

const toasts = ref([])

watch(() => usePage().props.flash?.toast, (t) => {
    if (!t) return
    const id = Date.now()
    toasts.value.push({ ...t, id })
    setTimeout(() => { toasts.value = toasts.value.filter(x => x.id !== id) }, 3500)
}, { immediate: true })

function dismiss(id) {
    toasts.value = toasts.value.filter(x => x.id !== id)
}

// Тост — нейтральная карточка; тип передают иконка и цветная полоса слева
const icons = { success: 'check', error: 'close', info: 'bell', warning: 'clock' }
const tone  = {
    success: { bar: 'bg-green',  icon: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' },
    error:   { bar: 'bg-red',    icon: 'bg-red-500/10 text-red-600 dark:text-red-400' },
    info:    { bar: 'bg-blue',   icon: 'bg-sky-500/10 text-sky-600 dark:text-sky-400' },
    warning: { bar: 'bg-orange', icon: 'bg-amber-500/10 text-amber-600 dark:text-amber-400' },
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed bottom-6 right-6 z-[1000] flex flex-col gap-2.5 pointer-events-none">
      <TransitionGroup name="toast">
        <div
          v-for="t in toasts" :key="t.id"
          @click="dismiss(t.id)"
          :role="t.type === 'error' ? 'alert' : 'status'"
          class="card pointer-events-auto relative flex w-[340px] max-w-[calc(100vw-32px)] cursor-pointer items-center gap-3 overflow-hidden py-3 pl-4 pr-4 text-[13.5px] font-medium text-[var(--text)] shadow-lg2"
        >
          <span class="absolute inset-y-0 left-0 w-[3px]" :class="(tone[t.type] || tone.info).bar"></span>
          <span class="flex h-7 w-7 flex-none items-center justify-center rounded-full" :class="(tone[t.type] || tone.info).icon">
            <Icon :kind="icons[t.type] || 'bell'" :size="15" />
          </span>
          <span class="flex-1">{{ t.message }}</span>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
.toast-enter-active { transition: opacity .18s ease-out, transform .18s ease-out; }
.toast-leave-active { transition: opacity .15s ease-in, transform .15s ease-in; }
.toast-enter-from   { opacity: 0; transform: translateY(8px); }
.toast-leave-to     { opacity: 0; transform: translateX(16px); }
</style>
