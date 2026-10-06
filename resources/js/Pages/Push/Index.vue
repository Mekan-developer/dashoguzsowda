<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import Icon from '@/Components/Icon.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmDialog } from '@/confirm'
import { th, td, tr, thead } from '@/table'

const { t } = useI18n()

const props = defineProps({
    pushNotifications: Object,
    regions:           Array,
    tariffs:           Array,
})

const emptyForm = () => ({
    title:     '',
    body:      '',
    target:    'all',
    link_type: '',
    link_id:   '',
    filters:   { region_id: '', tariff_id: '' },
})

const form         = ref(emptyForm())
const singleUserId = ref('')
const sending      = ref(false)

const recipients = computed(() => [
    { value: 'all',      label: t('push.recipientsAll') },
    { value: 'filtered', label: t('push.recipientsSegment') },
    { value: 'selected', label: t('push.recipientsSingle') },
])

const recipientHelper = computed(() => ({
    all:      t('push.helperAll'),
    filtered: t('push.helperFiltered'),
    selected: t('push.helperSelected'),
}[form.value.target]))

const linkTypes = computed(() => [
    { value: '',        label: t('push.linkNone') },
    { value: 'listing', label: t('push.linkListing') },
    { value: 'user',    label: t('push.linkUser') },
    { value: 'news',    label: t('push.linkNews') },
])

const idDisabled = computed(() => form.value.link_type === '')

const targetLabels = computed(() => ({ all: t('push.targetAll'), filtered: t('push.targetSegment'), selected: t('push.targetOne') }))

// Рассылку не отозвать — перед отправкой говорим, кто её получит
async function send() {
    const message = {
        all:      t('push.confirmAll',     { title: form.value.title }),
        filtered: t('push.confirmSegment', { title: form.value.title }),
        selected: t('push.confirmOne',     { title: form.value.title, id: singleUserId.value }),
    }[form.value.target]
    if (!(await confirmDialog(message, { title: t('push.confirmTitle'), confirmLabel: t('actions.send'), danger: false }))) return

    sending.value = true
    router.post(route('push.send'), {
        title:     form.value.title,
        body:      form.value.body,
        target:    form.value.target,
        link_type: form.value.link_type || null,
        link_id:   form.value.link_id || null,
        filters:   form.value.target === 'filtered' ? form.value.filters : null,
        user_ids:  form.value.target === 'selected' ? [singleUserId.value].filter(Boolean) : null,
    }, {
        onFinish: () => { sending.value = false },
        onSuccess: () => {
            form.value = emptyForm()
            singleUserId.value = ''
        },
    })
}

function scrollToHistory() {
    document.getElementById('push-history')?.scrollIntoView({ behavior: 'smooth' })
}
</script>

<template>
  <AppLayout>
    <template #header>{{ t('nav.push') }}</template>
    <template #description>{{ t('push.sendSubtitle') }}</template>

    <template #actions>
      <button type="button" @click="scrollToHistory" class="btn btn-secondary">
        <Icon kind="clock" :size="16" />{{ t('push.historyBtn') }}
      </button>
    </template>

    <div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
      <!-- Форма -->
      <section class="card">
        <h2 class="card-title border-b border-[var(--card-border)] px-5 py-4">{{ t('push.sendTitle') }}</h2>

        <div class="space-y-5 p-5">
          <div>
            <label for="push-title" class="field-label">{{ t('push.titleLabel') }}</label>
            <input id="push-title" v-model="form.title" type="text" :placeholder="t('push.titlePlaceholder')" class="input" />
          </div>

          <div>
            <label for="push-body" class="field-label">{{ t('push.textLabel') }}</label>
            <textarea id="push-body" v-model="form.body" rows="3" :placeholder="t('push.textPlaceholder')" class="input resize-y"></textarea>
          </div>

          <div>
            <span class="field-label">{{ t('push.recipients') }}</span>
            <div class="seg !inline-flex !h-10" role="group" :aria-label="t('push.recipients')">
              <button
                v-for="r in recipients" :key="r.value"
                type="button"
                @click="form.target = r.value"
                :aria-pressed="form.target === r.value"
                class="seg-item"
                :class="form.target === r.value ? 'seg-item-active' : ''"
              >{{ r.label }}</button>
            </div>
            <p class="mt-1.5 text-[12px] text-[var(--text-muted)]">{{ recipientHelper }}</p>

            <div v-if="form.target === 'filtered'" class="mt-3 grid grid-cols-1 gap-3 rounded-[8px] border border-[var(--card-border)] bg-[var(--field-bg)] p-3 sm:grid-cols-2">
              <div>
                <label for="push-region" class="field-label">{{ t('common.region') }}</label>
                <select id="push-region" v-model="form.filters.region_id" class="input">
                  <option value="">{{ t('push.allRegions') }}</option>
                  <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name_ru }}</option>
                </select>
              </div>
              <div>
                <label for="push-tariff" class="field-label">{{ t('common.tariff') }}</label>
                <select id="push-tariff" v-model="form.filters.tariff_id" class="input">
                  <option value="">{{ t('push.allTariffs') }}</option>
                  <option v-for="tf in tariffs" :key="tf.id" :value="tf.id">{{ tf.name_ru }}</option>
                </select>
              </div>
            </div>

            <div v-if="form.target === 'selected'" class="mt-3 rounded-[8px] border border-[var(--card-border)] bg-[var(--field-bg)] p-3">
              <label for="push-user" class="field-label">{{ t('push.userId') }}</label>
              <input id="push-user" v-model="singleUserId" type="text" placeholder="12345" inputmode="numeric" class="input font-data" />
            </div>
          </div>

          <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_160px]">
            <div>
              <label for="push-link-type" class="field-label">{{ t('push.linkType') }}</label>
              <select id="push-link-type" v-model="form.link_type" class="input">
                <option v-for="lt in linkTypes" :key="lt.value" :value="lt.value">{{ lt.label }}</option>
              </select>
            </div>
            <div>
              <label for="push-link-id" class="field-label">{{ t('push.linkId') }}</label>
              <input id="push-link-id" v-model="form.link_id" type="text" placeholder="123" :disabled="idDisabled" class="input font-data" />
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 border-t border-[var(--card-border)] px-5 py-4">
          <button type="button" disabled :title="t('push.testSendHint')" class="btn btn-secondary">{{ t('push.testSend') }}</button>
          <button type="button" @click="send" :disabled="sending || !form.title || !form.body" class="btn btn-primary">
            <Icon kind="send" :size="16" />
            {{ sending ? t('push.sending') : t('actions.send') }}
          </button>
        </div>
      </section>

      <!-- Превью — как уведомление выглядит на телефоне -->
      <aside class="card p-5 xl:sticky xl:top-0">
        <h2 class="card-title mb-3">{{ t('push.preview') }}</h2>
        <div class="flex gap-3 rounded-[10px] border border-[var(--card-border)] bg-[var(--field-bg)] p-3.5">
          <div class="flex h-9 w-9 flex-none items-center justify-center rounded-[8px] bg-[var(--accent)] text-white">
            <Icon kind="bell" :size="17" />
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex items-baseline justify-between gap-2">
              <span class="text-[12px] font-semibold text-[var(--text)]">{{ t('push.previewApp') }}</span>
              <span class="flex-none text-[11.5px] text-[var(--text-muted)]">{{ t('push.previewNow') }}</span>
            </div>
            <div class="mt-0.5 truncate text-[13.5px] font-semibold text-[var(--text)]">{{ form.title || t('push.titlePlaceholder') }}</div>
            <div class="mt-0.5 line-clamp-3 break-words text-[13px] leading-snug text-[var(--text-secondary)]">{{ form.body || t('push.previewBody') }}</div>
          </div>
        </div>
        <p class="mt-3 text-[12px] leading-relaxed text-[var(--text-muted)]">{{ t('push.previewNote') }}</p>
      </aside>
    </div>

    <!-- История рассылок -->
    <section id="push-history" class="card mt-6 overflow-hidden">
      <h2 class="card-title border-b border-[var(--card-border)] px-5 py-4">{{ t('push.history') }}</h2>
      <div v-if="pushNotifications.data?.length" class="overflow-x-auto">
        <table class="w-full min-w-[640px]">
          <thead>
            <tr :class="thead">
              <th :class="th">{{ t('push.colMessage') }}</th>
              <th :class="th">{{ t('push.colAudience') }}</th>
              <th :class="th" class="text-right">{{ t('push.colRecipients') }}</th>
              <th :class="th">{{ t('push.colSentAt') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in pushNotifications.data" :key="p.id" :class="tr">
              <td :class="td">
                <div class="max-w-[420px] truncate font-semibold text-[var(--text)]">{{ p.title }}</div>
                <div class="max-w-[420px] truncate text-[12.5px] font-normal text-[var(--text-muted)]">{{ p.body }}</div>
              </td>
              <td :class="td"><StatusBadge status="regular" :label="targetLabels[p.target] ?? p.target" /></td>
              <td :class="td" class="text-right font-data tabular-nums text-[var(--text)]">{{ Number(p.sent_count ?? 0).toLocaleString('ru-RU') }}</td>
              <td :class="td" class="whitespace-nowrap font-data tabular-nums text-[var(--text-secondary)]">{{ p.sent_at ? new Date(p.sent_at).toLocaleString('ru', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <EmptyState v-else icon="bell" :title="t('push.emptyHistory')" :text="t('push.emptyHistoryHint')" />
      <Pagination :links="pushNotifications.links" :from="pushNotifications.from" :to="pushNotifications.to" :total="pushNotifications.total" />
    </section>
  </AppLayout>
</template>
