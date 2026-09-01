<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppDrawer from '@/Components/AppDrawer.vue'
import DrawerField from '@/Components/DrawerField.vue'
import DrawerFooter from '@/Components/DrawerFooter.vue'
import CreateButton from '@/Components/CreateButton.vue'
import Pagination from '@/Components/Pagination.vue'
import ToggleSwitch from '@/Components/ToggleSwitch.vue'
import ImageCropUpload from '@/Components/ImageCropUpload.vue'
import RichTextEditor from '@/Components/RichTextEditor.vue'
import DataTable from '@/Components/DataTable.vue'
import SearchInput from '@/Components/SearchInput.vue'

const { t } = useI18n()

const props = defineProps({
    news:    Object,
    filters: Object,
    counts:  Object,
})

const typeMeta = computed(() => ({
    regular: { label: t('news.typeRegular'), cls: 'bg-blue/10 text-blue' },
    ad:      { label: t('news.typeAd'),      cls: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' },
}))

// Значения совпадают с ad_link_type в API: мобилка открывает магазин через
// GET /v1/stores/{id}, объявление и товар — через GET /v1/listings/{id}
const adLinkTypeMeta = computed(() => ({
    store:   t('news.adLinkStore'),
    listing: t('news.adLinkListing'),
    product: t('news.adLinkProduct'),
}))

const drawer   = ref(false)
const editItem = ref(null)
const lang     = ref('ru')
const emptyForm = () => ({ title_ru: '', title_tk: '', content_ru: '', content_tk: '', type: 'regular', ad_link_type: null, ad_link_id: null, is_published: false, image: null, crop_x: 50, crop_y: 50, remove_image: false })
const form          = ref(emptyForm())
const errors        = ref({})

// Из какого раздела админки брать ID — иначе ссылка ведёт в 404 у мобилки
const adLinkHint = computed(() => ({
    store:   t('news.adLinkHintStore'),
    listing: t('news.adLinkHintListing'),
    product: t('news.adLinkHintProduct'),
}[form.value.ad_link_type] ?? ''))

// ID магазина и ID объявления — из разных таблиц, при смене типа старый не годится
function selectAdLinkType(value) {
    if (form.value.ad_link_type === value) return
    form.value.ad_link_type = value
    form.value.ad_link_id = null
}
const searchQuery  = ref(props.filters?.search ?? '')
const statusFilter = ref(props.filters?.published ?? '')

// Чипы фильтра по статусу — счётчики считают все новости в статусе, не завязаны на поиск
const statusChips = computed(() => [
    { value: '', label: t('common.all'), count: props.counts.all },
    { value: '1', label: t('status.published'), count: props.counts.published },
    { value: '0', label: t('status.draft'), count: props.counts.draft },
])

// Список пагинируется на бэкенде — поиск и фильтр по статусу уходят в запрос
let searchDebounce = null
function applyFilters() {
    clearTimeout(searchDebounce)
    router.get(route('news.index'), { search: searchQuery.value, published: statusFilter.value }, { preserveState: true, replace: true })
}
function onSearchInput(value) {
    searchQuery.value = value
    clearTimeout(searchDebounce)
    searchDebounce = setTimeout(applyFilters, 350)
}
function setStatusFilter(value) {
    statusFilter.value = value
    applyFilters()
}

// Новый файл отменяет «Удалить» существующей обложки
watch(() => form.value.image, file => { if (file) form.value.remove_image = false })

const dataTableColumns = computed(() => [
    { key: 'image', label: '', width: '40px', type: 'image' },
    { key: 'title_ru', label: t('common.title'), type: 'text' },
    { key: 'type', label: t('common.type'), type: 'badge', badges: typeMeta.value },
    { key: 'is_published', label: t('common.status'), type: 'status' },
    { key: 'created_at', label: t('common.date'), type: 'text' },
])

const dataTableActions = computed(() => [
    {
        icon: (n) => n.is_published ? 'eyeOff' : 'eye',
        title: (n) => n.is_published ? t('actions.unpublish') : t('actions.publish'),
        handler: (n) => n.is_published ? unpublish(n) : publish(n),
    },
    { icon: 'pencil', title: t('actions.edit'), handler: openEdit },
    { icon: 'trash', title: t('actions.delete'), handler: destroy, color: 'red' },
])

// RU и TK хранятся раздельно; поля показывают активный язык
const title = computed({
    get: () => lang.value === 'ru' ? form.value.title_ru : form.value.title_tk,
    set: v  => { form.value[lang.value === 'ru' ? 'title_ru' : 'title_tk'] = v },
})
const content = computed({
    get: () => lang.value === 'ru' ? form.value.content_ru : form.value.content_tk,
    set: v  => { form.value[lang.value === 'ru' ? 'content_ru' : 'content_tk'] = v },
})
const canSave = computed(() => (form.value.title_ru || '').trim().length > 0)

function openCreate() {
    editItem.value = null
    lang.value = 'ru'
    form.value = emptyForm()
    errors.value = {}
    drawer.value = true
}
function openEdit(n) {
    editItem.value = n
    lang.value = 'ru'
    form.value = { ...emptyForm(), title_ru: n.title_ru ?? '', title_tk: n.title_tk ?? '', content_ru: n.content_ru ?? '', content_tk: n.content_tk ?? '', type: n.type, is_published: n.is_published }
    errors.value = {}
    drawer.value = true
}
function save() {
    // Файл требует multipart, а multipart-PUT PHP не парсит — POST + _method
    const url  = editItem.value ? route('news.update', editItem.value.id) : route('news.store')
    const data = editItem.value ? { ...form.value, _method: 'put' } : form.value
    router.post(url, data, {
        forceFormData: !!form.value.image,
        onSuccess: () => { drawer.value = false },
        onError: e => { errors.value = e },
    })
}
function publish(n) { router.patch(route('news.publish', n.id)) }
function unpublish(n) { router.patch(route('news.unpublish', n.id)) }
function destroy(n) {
    if (confirm(t('news.confirmDelete', { name: n.title_ru }))) router.delete(route('news.destroy', n.id))
}
</script>

<template>
  <AppLayout>
    <template #header>
      <span class="inline-flex items-center gap-2 align-middle">
        {{ t('nav.news') }}
        <span class="rounded-pill border border-[var(--field-border)] bg-[var(--field-bg)] px-2.5 py-0.5 text-[11.5px] font-bold text-[var(--text-secondary)]">{{ news.total }}</span>
      </span>
    </template>

    <template #actions>
      <CreateButton :label="t('news.addNew')" @click="openCreate" />
    </template>

    <div class="space-y-4">
      <!-- Панель: поиск + сегментированный фильтр + счётчик показанных/всего -->
      <div class="flex flex-wrap items-center gap-3">
        <SearchInput
          :model-value="searchQuery"
          @update:model-value="onSearchInput"
          @submit="applyFilters"
          :placeholder="t('news.searchPlaceholder')"
          class="min-w-[220px] flex-1 sm:max-w-[340px]"
        />

        <!-- Фильтр по статусу — сегментированный контрол -->
        <div class="inline-flex gap-1 rounded-[11px] border border-[var(--field-border)] bg-[var(--field-bg)] p-1">
          <button
            v-for="chip in statusChips" :key="chip.value"
            type="button"
            @click="setStatusFilter(chip.value)"
            class="rounded-[8px] px-[15px] py-[7px] text-[12.5px] font-semibold transition-colors"
            :class="statusFilter === chip.value
              ? 'bg-[var(--accent)] text-white shadow-[0_4px_10px_-3px_var(--accent)]'
              : 'text-[var(--text-secondary)] hover:bg-[var(--nav-hover)]'"
          >{{ chip.label }}</button>
        </div>

        <!-- Показано из всего -->
        <span class="ml-auto text-[12.5px] font-semibold text-[var(--text-muted)]">
          {{ t('dataTable.countOf', { shown: news.data.length, total: news.total }) }}
        </span>
      </div>

      <!-- Используем компонент DataTable -->
      <DataTable
        :columns="dataTableColumns"
        :items="news.data"
        :pagination="news"
        :actions="dataTableActions"
        :show-toolbar="false"
        @dblclick="openEdit"
      >
        <!-- Custom ячейка для даты (форматирование) -->
        <template #cell-created_at="{ value }">
          {{ new Date(value).toLocaleDateString('ru') }}
        </template>
      </DataTable>
    </div>

    <AppDrawer :open="drawer" :title="editItem ? t('news.editTitle') : t('news.newTitle')" @close="drawer = false">
      <!-- Переключатель языка формы -->
      <div class="mb-4 inline-flex gap-1 rounded-[11px] border border-[var(--field-border)] bg-[var(--field-bg)] p-1">
        <button
          v-for="l in ['ru', 'tk']" :key="l" type="button"
          @click="lang = l"
          class="rounded-[8px] px-5 py-1.5 text-[12px] font-bold uppercase transition-colors"
          :class="lang === l ? 'bg-[var(--accent)] text-white' : 'text-[var(--text-secondary)] hover:bg-[var(--nav-hover)]'"
        >{{ l }}</button>
      </div>

      <DrawerField :label="lang === 'ru' ? t('news.titleRu') : t('news.titleTk')" :required="lang === 'ru'" :error="lang === 'ru' ? errors.title_ru : errors.title_tk">
        <input v-model="title" class="input" :placeholder="lang === 'ru' ? t('news.titlePlaceholderRu') : t('news.titlePlaceholderTk')" />
      </DrawerField>

      <DrawerField :label="lang === 'ru' ? t('news.contentRu') : t('news.contentTk')" :error="lang === 'ru' ? errors.content_ru : errors.content_tk">
        <RichTextEditor
          v-model="content"
          :placeholder="lang === 'ru' ? t('news.contentPlaceholderRu') : t('news.contentPlaceholderTk')"
        />
        <p class="mt-1.5 text-[11px] font-semibold">
          <span :class="(form.content_ru || '').trim() ? 'text-green' : 'text-muted'">
            {{ (form.content_ru || '').trim() ? t('news.ruFilled') : t('news.ruEmpty') }}
          </span>
          <span class="text-muted"> · </span>
          <span :class="(form.content_tk || '').trim() ? 'text-green' : 'text-muted'">
            {{ (form.content_tk || '').trim() ? t('news.tkFilled') : t('news.tkEmpty') }}
          </span>
        </p>
      </DrawerField>

      <DrawerField :label="t('news.cover')" :error="errors.image">
        <ImageCropUpload
          v-model="form.image"
          v-model:crop-x="form.crop_x"
          v-model:crop-y="form.crop_y"
          :existing-url="editItem?.image ? `/storage/${editItem.image}` : null"
          @remove-existing="form.remove_image = true"
        />
      </DrawerField>

      <DrawerField :label="t('news.typeLabel')" :error="errors.type">
        <div class="grid grid-cols-2 gap-1 rounded-[11px] border border-[var(--field-border)] bg-[var(--field-bg)] p-1">
          <button
            v-for="(meta, value) in typeMeta" :key="value" type="button"
            @click="form.type = value"
            class="rounded-[8px] px-2 py-[7px] text-[12px] font-bold transition-colors"
            :class="form.type === value ? 'bg-[var(--accent)] text-white' : 'text-[var(--text-secondary)] hover:bg-[var(--nav-hover)]'"
          >{{ meta.label }}</button>
        </div>
      </DrawerField>

      <!-- Блок рекламной ссылки (показывается только если тип = ad) -->
      <template v-if="form.type === 'ad'">
        <DrawerField :label="t('news.linkTypeLabel')" :required="true" :error="errors.ad_link_type">
          <div class="grid grid-cols-3 gap-1 rounded-[11px] border border-[var(--field-border)] bg-[var(--field-bg)] p-1">
            <button
              v-for="(label, value) in adLinkTypeMeta" :key="value" type="button"
              @click="selectAdLinkType(value)"
              class="rounded-[8px] px-2 py-[7px] text-[12px] font-bold transition-colors"
              :class="form.ad_link_type === value ? 'bg-[var(--accent)] text-white' : 'text-[var(--text-secondary)] hover:bg-[var(--nav-hover)]'"
            >{{ label }}</button>
          </div>
        </DrawerField>

        <DrawerField :label="t('news.entityId')" :required="true" :error="errors.ad_link_id">
          <input
            v-model.number="form.ad_link_id"
            type="number"
            :placeholder="t('news.entityIdPlaceholder')"
            class="input"
          />
          <p v-if="adLinkHint" class="mt-1 text-[11px] text-[var(--text-muted)]">{{ adLinkHint }}</p>
        </DrawerField>
      </template>

      <div class="mt-5 flex items-center justify-between gap-4 rounded-[10px] border border-[var(--field-border)] bg-[var(--field-bg)] px-4 py-3">
        <div>
          <div class="text-[13px] font-bold text-[var(--text)]">{{ t('news.publishNow') }}</div>
          <div class="mt-0.5 text-[11px] text-[var(--text-muted)]">{{ t('news.draftHint') }}</div>
        </div>
        <ToggleSwitch v-model="form.is_published" />
      </div>

      <template #footer>
        <DrawerFooter
          :can-save="canSave"
          :save-label="form.is_published ? t('actions.publish') : t('actions.save')"
          @cancel="drawer = false"
          @save="save"
        />
      </template>
    </AppDrawer>
  </AppLayout>
</template>
