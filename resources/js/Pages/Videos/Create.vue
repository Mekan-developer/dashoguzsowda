<script setup>
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import UserSearchSelect from '@/Components/UserSearchSelect.vue'

const { t, locale } = useI18n()

const props = defineProps({
    categories: Array,
})

const nameOf = (item) => (locale.value === 'tk' && item?.name_tk) ? item.name_tk : item?.name_ru

const form = useForm({
    user_id: null,
    title: '',
    category_id: null,
    tags: [],
    video: null,
})

const tagsInput = ref('')
const videoName = ref('')

function onVideoPick(e) {
    const file = e.target.files?.[0] || null
    form.video = file
    videoName.value = file?.name || ''
}

function submit() {
    form.tags = tagsInput.value
        .split(/[,，]/)
        .map(s => s.trim())
        .filter(Boolean)
        .slice(0, 10)
    form.post(route('videos.store'), { forceFormData: true })
}
</script>

<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center gap-2 text-[14px]">
        <Link :href="route('videos.index')" class="text-muted hover:text-blue transition">{{ t('nav.videos') }}</Link>
        <span class="text-muted">/</span>
        <span class="text-ink dark:text-slate-100">{{ t('videos.createTitle') }}</span>
      </div>
    </template>

    <form class="mx-auto max-w-2xl space-y-5" @submit.prevent="submit">
      <div class="rounded-card bg-white p-5 shadow-soft dark:bg-dcard space-y-4">
        <div>
          <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('users.ownerLabel') }} *</label>
          <UserSearchSelect v-model="form.user_id" :error="form.errors.user_id" />
        </div>

        <div>
          <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('common.title') }} *</label>
          <input v-model="form.title" type="text" maxlength="255" class="input" :class="{ 'border-red': form.errors.title }" />
          <p v-if="form.errors.title" class="mt-1 text-[12px] font-semibold text-red">{{ form.errors.title }}</p>
        </div>

        <div>
          <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('common.category') }} *</label>
          <select v-model="form.category_id" class="input" :class="{ 'border-red': form.errors.category_id }">
            <option :value="null">—</option>
            <option v-for="c in categories || []" :key="c.id" :value="c.id">{{ nameOf(c) }}</option>
          </select>
          <p v-if="form.errors.category_id" class="mt-1 text-[12px] font-semibold text-red">{{ form.errors.category_id }}</p>
        </div>

        <div>
          <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('videos.tags') }}</label>
          <input v-model="tagsInput" type="text" class="input" :placeholder="t('listings.tagsPlaceholder')" />
        </div>

        <div>
          <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('videos.fileLabel') }} *</label>
          <label class="flex cursor-pointer flex-col items-center gap-2 rounded-[12px] border border-dashed border-[var(--field-border)] px-4 py-8 text-center hover:border-[var(--accent)]">
            <input type="file" accept="video/mp4,video/quicktime,video/webm,video/x-matroska,video/3gpp" class="hidden" @change="onVideoPick" />
            <span class="text-[13px] font-semibold text-[var(--text-secondary)]">{{ videoName || t('videos.pickFile') }}</span>
            <span class="text-[11px] text-muted">{{ t('videos.hint') }}</span>
          </label>
          <p v-if="form.errors.video" class="mt-1 text-[12px] font-semibold text-red">{{ form.errors.video }}</p>
        </div>
      </div>

      <div class="flex justify-end gap-2">
        <Link :href="route('videos.index')" class="rounded-[10px] border border-[var(--field-border)] px-[18px] py-[10px] text-[13px] font-semibold text-[var(--text-secondary)]">{{ t('actions.cancel') }}</Link>
        <button type="submit" :disabled="form.processing" class="rounded-[10px] bg-[var(--accent)] px-5 py-[10px] text-[13px] font-bold text-white hover:bg-[var(--accent-hover)] disabled:opacity-40">
          {{ t('actions.create') }}
        </button>
      </div>
    </form>
  </AppLayout>
</template>
