<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'

defineProps({
    installed: Boolean,
})

const { t, locale } = useI18n()

const src = route('lessons.file', { path: 'index.html' })
const frame = ref(null)

// Материалы — отдельный сайт со своими ключами в localStorage (ds-lang, ds-theme)
// и своим кодом туркменского: tm, а не tk. Origin тот же, поэтому до загрузки
// iframe достаточно записать ключи, а открытому уроку — вызвать его setLang/setTheme.
watch(locale, (value) => {
    const lang = value === 'tk' ? 'tm' : 'ru'
    localStorage.setItem('ds-lang', lang)
    frame.value?.contentWindow?.setLang?.(lang)
}, { immediate: true })

localStorage.setItem('ds-theme', localStorage.getItem('dk') === '1' ? 'dark' : 'light')

// Тема живёт в AppLayout и видна снаружи только как класс на <html>
const themeObserver = new MutationObserver(() => {
    const theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light'
    frame.value?.contentWindow?.setTheme?.(theme)
})
onMounted(() => themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] }))
onUnmounted(() => themeObserver.disconnect())
</script>

<template>
  <AppLayout>
    <template #header>{{ t('lessons.title') }}</template>

    <template v-if="installed" #actions>
      <a
        :href="src"
        target="_blank"
        rel="noopener"
        class="flex h-10 items-center gap-2 rounded-[8px] border border-[var(--field-border)] bg-[var(--field-bg)] px-3 text-[13px] font-medium text-[var(--text-secondary)] transition-colors duration-150 hover:bg-[var(--nav-hover)] hover:text-[var(--text)]"
      >{{ t('lessons.openInNewTab') }}</a>
    </template>

    <iframe
      v-if="installed"
      ref="frame"
      :src="src"
      :title="t('lessons.title')"
      allow="autoplay"
      class="h-full min-h-[560px] w-full rounded-[10px] border border-[var(--card-border)] bg-[var(--card-bg)]"
    ></iframe>

    <div v-else class="rounded-[10px] border border-[var(--card-border)] bg-[var(--card-bg)]">
      <EmptyState icon="lesson" :title="t('lessons.emptyTitle')" :text="t('lessons.emptyText')" />
    </div>
  </AppLayout>
</template>
