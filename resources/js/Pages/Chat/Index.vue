<script setup>
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import ChatDialogList from '@/Components/ChatDialogList.vue'
import EmptyState from '@/Components/EmptyState.vue'

const { t } = useI18n()

defineProps({ dialogs: Object })
</script>

<template>
  <AppLayout>
    <template #header>{{ t('nav.chat') }}</template>

    <!-- Две колонки, как в открытом диалоге: слева список, справа — подсказка.
         На узком экране остаётся только список. -->
    <div class="card flex h-[calc(100vh-212px)] min-h-[460px] overflow-hidden">
      <aside class="flex w-full flex-none flex-col border-[var(--card-border)] md:w-[320px] md:border-r">
        <div class="flex h-14 flex-none items-center border-b border-[var(--card-border)] px-4">
          <h2 class="card-title">{{ t('chat.dialogsWithUsers') }}</h2>
        </div>
        <div class="flex-1 overflow-y-auto">
          <ChatDialogList :dialogs="dialogs.data" />
        </div>
        <Pagination :links="dialogs.links" />
      </aside>
      <div class="hidden flex-1 items-center justify-center md:flex">
        <EmptyState icon="chat" :title="t('chat.selectDialog')" :text="t('chat.selectDialogHint')" />
      </div>
    </div>
  </AppLayout>
</template>
