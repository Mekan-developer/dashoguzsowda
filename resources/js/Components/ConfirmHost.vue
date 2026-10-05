<script setup>
import { onMounted, onUnmounted } from 'vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import { confirmState, settleConfirm } from '@/confirm'

// Esc — отмена, Enter — подтверждение, как у нативного confirm()
function onKey(e) {
    if (!confirmState.open) return
    if (e.key === 'Escape') { e.preventDefault(); settleConfirm(false) }
    if (e.key === 'Enter')  { e.preventDefault(); settleConfirm(true) }
}

onMounted(() => document.addEventListener('keydown', onKey))
onUnmounted(() => {
    document.removeEventListener('keydown', onKey)
    settleConfirm(false)
})
</script>

<template>
  <ConfirmModal
    :open="confirmState.open"
    :message="confirmState.message"
    :title="confirmState.title"
    :danger="confirmState.danger"
    :confirm-label="confirmState.confirmLabel"
    :cancel-label="confirmState.cancelLabel"
    @confirm="settleConfirm(true)"
    @cancel="settleConfirm(false)"
  />
</template>
