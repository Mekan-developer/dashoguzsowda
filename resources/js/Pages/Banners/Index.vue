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
import SearchInput from '@/Components/SearchInput.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import Pagination from '@/Components/Pagination.vue'
import DrawerFooter from '@/Components/DrawerFooter.vue'
import { confirmDialog } from '@/confirm'

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

// Поиск по заголовку — на клиенте, в пределах страницы (как было в DataTable)
const query = ref('')
const visible = computed(() => {
    const q = query.value.trim().toLowerCase()
    const items = props.banners.data || []
    return q ? items.filter(b => (b.title_ru || '').toLowerCase().includes(q) || (b.title_tk || '').toLowerCase().includes(q)) : items
})

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
// status — ключ цвета StatusBadge, label — своя подпись
function statusMeta(b) {
    const now = Date.now()
    if (!b.is_active) return { status: 'suspended', label: t('banners.statusOff') }
    if (b.starts_at && new Date(b.starts_at).getTime() > now) return { status: 'new', label: t('banners.statusScheduled') }
    if (b.ends_at && new Date(b.ends_at).getTime() < now) return { status: 'rejected', label: t('banners.statusExpired') }
    return { status: 'active', label: t('banners.statusActive') }
}

function shortDate(d) {
    return new Date(d).toLocaleDateString('ru', { day: '2-digit', month: '2-digit', year: '2-digit' })
}
function periodText(b) {
    if (b.starts_at && b.ends_at) return `${shortDate(b.starts_at)} – ${shortDate(b.ends_at)}`
    if (b.starts_at) return t('banners.from', { date: shortDate(b.starts_at) })
    if (b.ends_at) return t('banners.until', { date: shortDate(b.ends_at) })
    return t('banners.always')
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
async function destroy(b) {
    if (await confirmDialog(t('banners.confirmDelete', { name: b.title_ru }))) router.delete(route('banners.destroy', b.id))
}
</script>

<template>
  <AppLayout>
    <template #header>{{ t('nav.banners') }}</template>
    <template #actions>
      <CreateButton :label="t('actions.add')" @click="openCreate" />
    </template>

    <div class="mb-4 flex flex-wrap items-center gap-2.5">
      <SearchInput v-model="query" :placeholder="t('banners.searchPlaceholder')" class="w-full sm:w-[280px]" />
      <span class="ml-auto whitespace-nowrap text-[12.5px] tabular-nums text-[var(--text-muted)]">
        {{ t('dataTable.countOf', { shown: visible.length, total: banners.total ?? banners.data.length }) }}
      </span>
    </div>

    <!-- Список с крупным превью: по картинке баннер узнают быстрее, чем по названию -->
    <div class="card overflow-hidden">
      <div v-if="visible.length" class="divide-y divide-[var(--card-border)]">
        <article
          v-for="b in visible" :key="b.id"
          class="flex flex-col gap-4 p-4 transition-colors duration-150 hover:bg-[var(--nav-hover)] sm:flex-row sm:items-center sm:px-5"
          @dblclick="openEdit(b)"
        >
          <button type="button" class="relative aspect-[2/1] w-full flex-none overflow-hidden rounded-[8px] border border-[var(--card-border)] bg-[var(--field-bg)] sm:w-[200px]" :title="t('actions.edit')" @click="openEdit(b)">
            <img v-if="b.image" :src="`/storage/${b.image}`" class="h-full w-full object-cover" alt="" loading="lazy" />
            <span v-else class="flex h-full w-full items-center justify-center text-[var(--text-muted)]"><Icon kind="image" :size="22" /></span>
          </button>

          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <span class="font-data text-[12px] tabular-nums text-[var(--text-muted)]">#{{ b.id }}</span>
              <h3 class="truncate text-[14px] font-semibold text-[var(--text)]">{{ b.title_ru }}</h3>
              <StatusBadge :status="statusMeta(b).status" :label="statusMeta(b).label" />
            </div>
            <dl class="mt-2 grid grid-cols-1 gap-x-6 gap-y-1 text-[12.5px] sm:grid-cols-2">
              <div class="flex gap-1.5"><dt class="text-[var(--text-muted)]">{{ t('banners.linkColumn') }}:</dt><dd class="truncate text-[var(--text-secondary)]">{{ linkSummary(b) }}</dd></div>
              <div class="flex gap-1.5"><dt class="text-[var(--text-muted)]">{{ t('banners.period') }}:</dt><dd class="font-data tabular-nums text-[var(--text-secondary)]">{{ periodText(b) }}</dd></div>
            </dl>
          </div>

          <div class="flex flex-none items-center gap-1.5 sm:ml-2">
            <!-- Порядок показа в карусели -->
            <button type="button" @click.stop="move(b, 'up')" :disabled="isFirst(b)" class="icon-btn !bg-transparent hover:!bg-[var(--field-bg)]" :title="t('banners.moveUp')" :aria-label="t('banners.moveUp')"><Icon kind="arrowUp" :size="15" /></button>
            <button type="button" @click.stop="move(b, 'down')" :disabled="isLast(b)" class="icon-btn !bg-transparent hover:!bg-[var(--field-bg)]" :title="t('banners.moveDown')" :aria-label="t('banners.moveDown')"><Icon kind="arrowDown" :size="15" /></button>
            <span class="mx-1 h-6 w-px bg-[var(--card-border)]"></span>
            <button type="button" @click.stop="toggle(b)" class="icon-btn" :title="b.is_active ? t('actions.hide') : t('actions.show')" :aria-label="b.is_active ? t('actions.hide') : t('actions.show')"><Icon :kind="b.is_active ? 'eyeOff' : 'eye'" :size="16" /></button>
            <button type="button" @click.stop="openEdit(b)" class="icon-btn" :title="t('actions.edit')" :aria-label="t('actions.edit')"><Icon kind="pencil" :size="16" /></button>
            <button type="button" @click.stop="destroy(b)" class="icon-btn icon-btn-danger" :title="t('actions.delete')" :aria-label="t('actions.delete')"><Icon kind="trash" :size="16" /></button>
          </div>
        </article>
      </div>
      <EmptyState v-else-if="query" icon="search" :title="t('dataTable.empty')" :text="t('common.emptyFiltered')">
        <button type="button" class="btn btn-secondary" @click="query = ''">{{ t('common.resetFilters') }}</button>
      </EmptyState>
      <EmptyState v-else icon="layers" :title="t('banners.emptyTitle')" :text="t('banners.emptyText')">
        <CreateButton :label="t('actions.add')" @click="openCreate" />
      </EmptyState>
      <Pagination :links="banners.links" :from="banners.from" :to="banners.to" :total="banners.total" />
    </div>

    <AppDrawer :open="drawer" :title="editItem ? t('banners.editTitle') : t('banners.newTitle')" @close="drawer = false">
      <!-- Переключатель языка формы -->
      <div class="seg mb-4 !inline-flex !h-9" role="group">
        <button
          v-for="l in ['ru', 'tk']" :key="l" type="button"
          @click="lang = l"
          :aria-pressed="lang === l"
          class="seg-item uppercase"
          :class="lang === l ? 'seg-item-active' : ''"
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
        <div class="seg grid grid-cols-3 !h-10">
          <button
            v-for="(label, value) in linkTypeMeta" :key="value" type="button"
            @click="form.link_type = value === 'null' ? null : value"
            :aria-pressed="(form.link_type ?? 'null') === value"
            class="seg-item"
            :class="(form.link_type ?? 'null') === value ? 'seg-item-active' : ''"
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

      <div class="mt-5 flex items-center justify-between gap-4 rounded-[8px] border border-[var(--field-border)] bg-[var(--field-bg)] px-4 py-3">
        <div class="text-[13.5px] font-semibold text-[var(--text)]">{{ t('banners.activeLabel') }}</div>
        <ToggleSwitch v-model="form.is_active" />
      </div>

      <template #footer>
        <DrawerFooter :can-save="canSave" @cancel="drawer = false" @save="save" />
      </template>
    </AppDrawer>
  </AppLayout>
</template>
