<script setup>
import Icon from '@/Components/Icon.vue'

/**
 * Пустое состояние списка: что произошло и что можно сделать.
 *
 * <EmptyState icon="news" :title="t('news.emptyTitle')" :text="t('news.emptyText')">
 *   <CreateButton :label="t('actions.create')" @click="openCreate" />
 * </EmptyState>
 *
 * В таблице — внутри <td colspan="…">, компонент сам задаёт отступы.
 */
defineProps({
    icon:  { type: String, default: 'search' },
    title: { type: String, required: true },
    text:  { type: String, default: '' },
    // compact — для небольших блоков (карточки дашборда, боковые списки)
    compact: { type: Boolean, default: false },
})
</script>

<template>
  <div class="flex flex-col items-center justify-center text-center" :class="compact ? 'px-4 py-8' : 'px-6 py-14'">
    <span
      class="mb-3 flex items-center justify-center rounded-full bg-[var(--nav-hover)] text-[var(--text-muted)]"
      :class="compact ? 'h-10 w-10' : 'h-12 w-12'"
    >
      <Icon :kind="icon" :size="compact ? 18 : 22" />
    </span>
    <p class="text-[14px] font-semibold text-[var(--text)]">{{ title }}</p>
    <p v-if="text" class="mt-1 max-w-[360px] text-[13px] text-[var(--text-muted)]">{{ text }}</p>
    <div v-if="$slots.default" class="mt-4 flex items-center gap-2"><slot /></div>
  </div>
</template>
