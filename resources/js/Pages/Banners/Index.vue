<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppDrawer from '@/Components/AppDrawer.vue'
import DrawerField from '@/Components/DrawerField.vue'
import Icon from '@/Components/Icon.vue'
import CreateButton from '@/Components/CreateButton.vue'
import ToggleSwitch from '@/Components/ToggleSwitch.vue'
import ImageCropUpload from '@/Components/ImageCropUpload.vue'
import DataTable from '@/Components/DataTable.vue'

const { t } = useI18n()

const props = defineProps({
    banners: Object,
    filters: Object,
})

const linkTypeMeta = computed(() => ({
    null:    t('banners.linkTypeNone'),
    url:     t('banners.linkTypeUrl'),
    listing: t('banners.linkTypeListing'),
}))

const drawer   = ref(false)
const editItem = ref(null)
const lang     = ref('ru')
const emptyForm = () => ({
    title_ru: '', title_tk: '',
    link_type: null, link_url: '', listing_id: null,
    starts_at: '', ends_at: '', is_active: true,
    image: null, crop_x: 50, crop_y: 50,
})
const form   = ref(emptyForm())
const errors = ref({})

const dataTableColumns = computed(() => [
    { key: 'id', label: t('common.id'), width: '64px', type: 'id' },
    { key: 'image', label: '', width: '40px', type: 'image' },
    { key: 'title_ru', label: t('common.title'), type: 'text' },
    { key: 'link_type', label: t('banners.linkColumn') },
    { key: 'is_active', label: t('common.status') },
    { key: 'sort_order', label: '', width: '56px' },
])

const dataTableActions = computed(() => [
    {
        icon: (b) => b.is_active ? 'eyeOff' : 'eye',
        title: (b) => b.is_active ? t('actions.hide') : t('actions.show'),
        handler: (b) => toggle(b),
    },
    { icon: 'pencil', title: t('actions.edit'), handler: openEdit },
    { icon: 'trash', title: t('actions.delete'), handler: destroy, color: 'red' },
])

// RU и TK хранятся раздельно; поле показывает активный язык (как в News)
const title = computed({
    get: () => lang.value === 'ru' ? form.value.title_ru : form.value.title_tk,
    set: v  => { form.value[lang.value === 'ru' ? 'title_ru' : 'title_tk'] = v },
})
const canSave = computed(() => (form.value.title_ru || '').trim().length > 0 && (!!editItem.value || !!form.value.image))

function toDatetimeLocal(iso) { return iso ? iso.slice(0, 16) : '' }

function isFirst(b) { return props.banners.data[0]?.id === b.id }
function isLast(b)  { return props.banners.data[props.banners.data.length - 1]?.id === b.id }

function linkSummary(b) {
    if (b.link_type === 'url') return t('banners.linkSummaryUrl')
    if (b.link_type === 'listing') return t('banners.linkSummaryListing', { id: b.listing_id })
    return '—'
}
function statusMeta(b) {
    const now = Date.now()
    if (!b.is_active) return { dot: 'bg-muted', cls: 'text-muted', label: t('banners.statusOff') }
    if (b.starts_at && new Date(b.starts_at).getTime() > now) return { dot: 'bg-orange', cls: 'text-orange', label: t('banners.statusScheduled') }
    if (b.ends_at && new Date(b.ends_at).getTime() < now) return { dot: 'bg-red', cls: 'text-red', label: t('banners.statusExpired') }
    return { dot: 'bg-green', cls: 'text-green', label: t('banners.statusActive') }
}

function openCreate() {
    editItem.value = null
    lang.value = 'ru'
    form.value = emptyForm()
    errors.value = {}
    drawer.value = true
}
function openEdit(b) {
    editItem.value = b
    lang.value = 'ru'
    form.value = {
        ...emptyForm(),
        title_ru: b.title_ru ?? '', title_tk: b.title_tk ?? '',
        link_type: b.link_type, link_url: b.link_url ?? '', listing_id: b.listing_id ?? null,
        starts_at: toDatetimeLocal(b.starts_at), ends_at: toDatetimeLocal(b.ends_at),
        is_active: b.is_active,
    }
    errors.value = {}
    drawer.value = true
}
function save() {
    // Файл требует multipart, а multipart-PUT PHP не парсит — POST + _method (как в News)
    const url  = editItem.value ? route('banners.update', editItem.value.id) : route('banners.store')
    const data = editItem.value ? { ...form.value, _method: 'put' } : form.value
    router.post(url, data, {
        forceFormData: !!form.value.image,
        onSuccess: () => { drawer.value = false },
        onError: e => { errors.value = e },
    })
}
function toggle(b) { router.patch(route('banners.toggle', b.id)) }
function move(b, direction) { router.patch(route('banners.move', b.id), { direction }) }
function destroy(b) {
    if (confirm(t('banners.confirmDelete', { name: b.title_ru }))) router.delete(route('banners.destroy', b.id))
}
</script>

<template>
  <AppLayout>
    <template #header>{{ t('nav.banners') }}</template>

    <div class="space-y-4">
      <div class="flex justify-end">
        <CreateButton :label="t('actions.add')" @click="openCreate" />
      </div>

      <DataTable
        :columns="dataTableColumns"
        :items="banners.data"
        :pagination="banners"
        :actions="dataTableActions"
        :search-field="'title_ru'"
        :search-placeholder="t('banners.searchPlaceholder')"
        @dblclick="openEdit"
      >
        <template #cell-link_type="{ item }">
          <span class="text-[13px] text-[var(--text-secondary)]">{{ linkSummary(item) }}</span>
        </template>

        <template #cell-is_active="{ item }">
          <div class="flex items-center gap-1.5">
            <div class="h-1.5 w-1.5 rounded-full" :class="statusMeta(item).dot"></div>
            <span class="text-[11px] font-bold" :class="statusMeta(item).cls">{{ statusMeta(item).label }}</span>
          </div>
        </template>

        <template #cell-sort_order="{ item }">
          <div class="flex flex-col -my-1">
            <button
              @click.stop="move(item, 'up')" :disabled="isFirst(item)"
              class="flex h-[13px] w-[13px] items-center justify-center text-muted transition hover:text-blue disabled:opacity-25 disabled:hover:text-muted"
            ><Icon kind="arrowUp" :size="10" /></button>
            <button
              @click.stop="move(item, 'down')" :disabled="isLast(item)"
              class="flex h-[13px] w-[13px] items-center justify-center text-muted transition hover:text-blue disabled:opacity-25 disabled:hover:text-muted"
            ><Icon kind="arrowDown" :size="10" /></button>
          </div>
        </template>
      </DataTable>
    </div>

    <AppDrawer :open="drawer" :title="editItem ? t('banners.editTitle') : t('banners.newTitle')" @close="drawer = false">
      <!-- Переключатель языка формы -->
      <div class="mb-4 inline-flex gap-1 rounded-[11px] border border-[var(--field-border)] bg-[var(--field-bg)] p-1">
        <button
          v-for="l in ['ru', 'tk']" :key="l" type="button"
          @click="lang = l"
          class="rounded-[8px] px-5 py-1.5 text-[12px] font-bold uppercase transition-colors"
          :class="lang === l ? 'bg-[var(--accent)] text-white' : 'text-[var(--text-secondary)] hover:bg-[var(--nav-hover)]'"
        >{{ l }}</button>
      </div>

      <DrawerField :label="lang === 'ru' ? t('banners.titleRu') : t('banners.titleTk')" :required="lang === 'ru'" :error="lang === 'ru' ? errors.title_ru : errors.title_tk">
        <input v-model="title" class="input" :placeholder="lang === 'ru' ? t('banners.titlePlaceholderRu') : t('banners.titlePlaceholderTk')" />
      </DrawerField>

      <DrawerField :label="t('banners.cover')" :required="!editItem" :error="errors.image">
        <ImageCropUpload
          v-model="form.image"
          v-model:crop-x="form.crop_x"
          v-model:crop-y="form.crop_y"
          :existing-url="editItem?.image ? `/storage/${editItem.image}` : null"
          :aspect="2"
          :min-width="1200"
          :min-height="600"
        />
      </DrawerField>

      <DrawerField :label="t('banners.linkTypeLabel')" :error="errors.link_type">
        <div class="grid grid-cols-3 gap-1 rounded-[11px] border border-[var(--field-border)] bg-[var(--field-bg)] p-1">
          <button
            v-for="(label, value) in linkTypeMeta" :key="value" type="button"
            @click="form.link_type = value === 'null' ? null : value"
            class="rounded-[8px] px-2 py-[7px] text-[12px] font-bold transition-colors"
            :class="(form.link_type ?? 'null') === value ? 'bg-[var(--accent)] text-white' : 'text-[var(--text-secondary)] hover:bg-[var(--nav-hover)]'"
          >{{ label }}</button>
        </div>
      </DrawerField>

      <DrawerField v-if="form.link_type === 'url'" :label="t('banners.linkUrlLabel')" :required="true" :error="errors.link_url">
        <input v-model="form.link_url" type="url" :placeholder="t('banners.linkUrlPlaceholder')" class="input" />
      </DrawerField>

      <DrawerField v-if="form.link_type === 'listing'" :label="t('banners.listingIdLabel')" :required="true" :error="errors.listing_id">
        <input v-model.number="form.listing_id" type="number" :placeholder="t('banners.listingIdPlaceholder')" class="input" />
      </DrawerField>

      <div class="grid grid-cols-2 gap-3">
        <DrawerField :label="t('banners.startsAtLabel')" :error="errors.starts_at">
          <input v-model="form.starts_at" type="datetime-local" class="input" />
        </DrawerField>
        <DrawerField :label="t('banners.endsAtLabel')" :error="errors.ends_at">
          <input v-model="form.ends_at" type="datetime-local" class="input" />
        </DrawerField>
      </div>

      <div class="mt-5 flex items-center justify-between gap-4 rounded-[10px] border border-[var(--field-border)] bg-[var(--field-bg)] px-4 py-3">
        <div class="text-[13px] font-bold text-[var(--text)]">{{ t('banners.activeLabel') }}</div>
        <ToggleSwitch v-model="form.is_active" />
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <button
            @click="drawer = false"
            class="rounded-[10px] border border-[var(--field-border)] bg-transparent px-[18px] py-[10px] text-[13px] font-semibold text-[var(--text-secondary)] transition-colors hover:bg-[var(--nav-hover)]"
          >{{ t('actions.cancel') }}</button>
          <button
            @click="save"
            :disabled="!canSave"
            class="rounded-[10px] px-5 py-[10px] text-[13px] font-bold transition-colors"
            :class="canSave
              ? 'bg-[var(--accent)] text-white hover:bg-[var(--accent-hover)] shadow-[0_10px_22px_-8px_var(--accent)]'
              : 'cursor-not-allowed bg-[var(--field-disabled-bg)] text-[var(--text-muted)]'"
          >{{ t('actions.save') }}</button>
        </div>
      </template>
    </AppDrawer>
  </AppLayout>
</template>
