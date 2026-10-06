<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Icon from '@/Components/Icon.vue'

/**
 * Пагинация Laravel-пагинатора: :links="items.links".
 * Если передать from/to/total — слева появится «Показано 1–12 из 52».
 */
const props = defineProps({
    links: Array,
    from: { type: Number, default: null },
    to: { type: Number, default: null },
    total: { type: Number, default: null },
})

const { t } = useI18n()

// Первая и последняя ссылки Laravel — «Previous»/«Next» (английский текст
// из пагинатора); рисуем их стрелками, страницы — номерами
const pages = computed(() => (props.links ?? []).map((link, i, all) => ({
    ...link,
    kind: i === 0 ? 'prev' : i === all.length - 1 ? 'next' : 'page',
})))

const hasPages = computed(() => (props.links?.length ?? 0) > 3)
const hasSummary = computed(() => props.total !== null && props.from !== null)
</script>

<template>
  <div
    v-if="hasPages || hasSummary"
    class="flex flex-wrap items-center gap-3 border-t border-[var(--card-border)] px-5 py-3"
    :class="hasSummary ? 'justify-between' : 'justify-center'"
  >
    <span v-if="hasSummary" class="text-[12.5px] text-[var(--text-muted)] tabular-nums">
      {{ t('dataTable.shownRange', { from, to, total }) }}
    </span>

    <nav v-if="hasPages" class="flex items-center gap-1">
      <template v-for="link in pages" :key="link.kind + link.label">
        <!-- Стрелки -->
        <component
          :is="link.url ? Link : 'span'"
          v-if="link.kind !== 'page'"
          :href="link.url ?? undefined"
          :aria-label="t(link.kind === 'prev' ? 'dataTable.prev' : 'dataTable.next')"
          :aria-disabled="!link.url"
          class="flex h-[34px] w-[34px] items-center justify-center rounded-[8px] border border-[var(--card-border)] transition-colors duration-150 ease-out"
          :class="link.url
            ? 'text-[var(--text-secondary)] hover:bg-[var(--nav-hover)] hover:text-[var(--text)]'
            : 'cursor-not-allowed text-[var(--text-muted)] opacity-40'"
        >
          <Icon kind="chevronLeft" :size="15" :class="link.kind === 'next' ? 'rotate-180' : ''" />
        </component>

        <!-- «…» между группами страниц -->
        <span
          v-else-if="!link.url"
          class="flex h-[34px] min-w-[28px] items-center justify-center text-[13px] text-[var(--text-muted)]"
        >…</span>

        <!-- Номер страницы -->
        <Link
          v-else
          :href="link.url"
          :aria-current="link.active ? 'page' : null"
          class="flex h-[34px] min-w-[34px] items-center justify-center rounded-[8px] px-2 text-[13px] font-semibold tabular-nums transition-colors duration-150 ease-out"
          :class="link.active
            ? 'bg-[var(--accent)] text-white'
            : 'text-[var(--text-secondary)] hover:bg-[var(--nav-hover)] hover:text-[var(--text)]'"
        >{{ link.label }}</Link>
      </template>
    </nav>
  </div>
</template>
