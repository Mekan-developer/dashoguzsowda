import { reactive } from 'vue'

/*
 * Единственный диалог подтверждения на всю админку — вместо браузерного
 * window.confirm(). Рисует его ConfirmHost.vue, смонтированный в AppLayout.
 *
 *   if (!(await confirmDialog(t('news.confirmDelete', { name })))) return
 *   await confirmDialog(msg, { danger: false, title: t('...'), confirmLabel: t('...') })
 */
export const confirmState = reactive({
    open: false,
    message: '',
    title: '',
    danger: true,
    confirmLabel: '',
    cancelLabel: '',
    resolve: null,
})

export function confirmDialog(message, options = {}) {
    // Новый вопрос поверх незакрытого — прежний считается отменённым
    confirmState.resolve?.(false)

    return new Promise((resolve) => {
        Object.assign(confirmState, {
            open: true,
            message,
            title: options.title ?? '',
            danger: options.danger ?? true,
            confirmLabel: options.confirmLabel ?? '',
            cancelLabel: options.cancelLabel ?? '',
            resolve,
        })
    })
}

export function settleConfirm(result) {
    const resolve = confirmState.resolve
    confirmState.open = false
    confirmState.resolve = null
    resolve?.(result)
}
