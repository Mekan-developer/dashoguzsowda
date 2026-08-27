<script setup>
import Icon from '@/Components/Icon.vue'

defineProps({
    open: { type: Boolean, default: false },
    src:  { type: String,  default: '' },
    alt:  { type: String,  default: '' },
})
defineEmits(['close'])
</script>

<template>
  <Teleport to="body">
    <Transition name="ov">
      <div
        v-if="open"
        class="fixed inset-0 z-[600] flex items-center justify-center bg-black/70 p-6 backdrop-blur-sm"
        @click.self="$emit('close')"
      >
        <button
          @click="$emit('close')"
          class="absolute right-5 top-5 flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
        >
          <Icon kind="close" :size="16" />
        </button>
        <img :src="src" :alt="alt" class="max-h-[85vh] max-w-[90vw] rounded-card object-contain shadow-[0_24px_48px_rgba(0,0,0,.4)]" />
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.ov-enter-active, .ov-leave-active { transition: all .2s ease; }
.ov-enter-from, .ov-leave-to { opacity: 0; }
</style>
