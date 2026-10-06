<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppDrawer from '@/Components/AppDrawer.vue'
import DrawerField from '@/Components/DrawerField.vue'
import DrawerFooter from '@/Components/DrawerFooter.vue'
import CreateButton from '@/Components/CreateButton.vue'
import ToggleSwitch from '@/Components/ToggleSwitch.vue'
import IconPicker from '@/Components/IconPicker.vue'
import ImageCropUpload from '@/Components/ImageCropUpload.vue'
import ImagePreviewModal from '@/Components/ImagePreviewModal.vue'
import Icon from '@/Components/Icon.vue'
import SearchInput from '@/Components/SearchInput.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { th, td, tr, thead } from '@/table'
import { confirmDialog } from '@/confirm'

const { t } = useI18n()

const props = defineProps({ categories: Array, icons: Array })


// Свёрнутые ветки (по умолчанию всё раскрыто)
const collapsed = ref(new Set())
function toggleCollapse(id) {
    const next = new Set(collapsed.value)
    next.has(id) ? next.delete(id) : next.add(id)
    collapsed.value = next
}

// Плоский список с учётом сворачивания веток
const flatList = computed(() => {
    const result = []
    function walk(items, depth) {
        for (const item of items) {
            result.push({ ...item, depth })
            if (item.children?.length && !collapsed.value.has(item.id)) {
                walk(item.children, depth + 1)
            }
        }
    }
    walk(props.categories, 0)
    return result
})

// Все узлы дерева с путём до корня — для поиска и счётчика
const allFlat = computed(() => {
    const result = []
    function walk(items, depth, path) {
        for (const item of items) {
            result.push({ ...item, depth, path: path.join(' → ') })
            if (item.children?.length) walk(item.children, depth + 1, [...path, item.name_ru])
        }
    }
    walk(props.categories, 0, [])
    return result
})

// Поиск — на клиенте: всё дерево уже на странице
const query = ref('')
const rows = computed(() => {
    const q = query.value.trim().toLowerCase()
    if (!q) return flatList.value
    return allFlat.value.filter(c =>
        c.name_ru?.toLowerCase().includes(q) || c.name_tk?.toLowerCase().includes(q))
})

// Первый/последний среди siblings — для отключения стрелок сортировки
const siblingInfo = computed(() => {
    const map = {}
    function walk(items) {
        items.forEach((item, idx) => {
            map[item.id] = { isFirst: idx === 0, isLast: idx === items.length - 1 }
            if (item.children?.length) walk(item.children)
        })
    }
    walk(props.categories)
    return map
})

// Просмотр изображения категории в увеличенном виде
const previewOpen  = ref(false)
const previewSrc   = ref('')
function openPreview(cat) {
    previewSrc.value = cat.image_url
    previewOpen.value = true
}

// Drawer state
const drawerOpen = ref(false)
const editItem   = ref(null)
const emptyForm  = () => ({ name_ru: '', name_tk: '', parent_id: '', is_active: true, icon_path: null, icon: null, image: null, crop_x: 50, crop_y: 50 })
const form       = ref(emptyForm())
const errors     = ref({})

function openCreate(parent = null) {
    editItem.value = null
    form.value = { ...emptyForm(), parent_id: parent?.id ?? '' }
    errors.value = {}
    drawerOpen.value = true
}

function openEdit(cat) {
    editItem.value = cat
    form.value = {
        name_ru: cat.name_ru,
        name_tk: cat.name_tk ?? '',
        parent_id: cat.parent_id ?? '',
        is_active: cat.is_active,
        icon_path: cat.icon_path ?? null,
        icon: null,
        image: null,
        crop_x: 50,
        crop_y: 50,
    }
    errors.value = {}
    drawerOpen.value = true
}

function save() {
    const url  = editItem.value ? route('categories.update', editItem.value.id) : route('categories.store')
    const data = editItem.value ? { ...form.value, _method: 'put' } : form.value
    router.post(url, data, {
        forceFormData: !!form.value.icon || !!form.value.image,
        onSuccess: () => { drawerOpen.value = false },
        onError: e => { errors.value = e },
    })
}

async function destroy(cat) {
    const message = cat.children?.length
        ? t('categories.confirmDeleteWithChildren', { name: cat.name_ru })
        : t('categories.confirmDelete', { name: cat.name_ru })
    if (await confirmDialog(message)) {
        router.delete(route('categories.destroy', cat.id))
    }
}

function toggleActive(cat) {
    router.patch(route('categories.toggle', cat.id))
}

function move(cat, direction) {
    router.patch(route('categories.move', cat.id), { direction })
}

// Родитель может быть только уровня 1 или 2, и не сам редактируемый узел / его потомок
function collectDescendantIds(item, acc) {
    for (const child of item.children || []) {
        acc.add(child.id)
        collectDescendantIds(child, acc)
    }
}
const excludedParentIds = computed(() => {
    if (!editItem.value) return new Set()
    const acc = new Set([editItem.value.id])
    collectDescendantIds(editItem.value, acc)
    return acc
})
const parentOptions = computed(() => {
    const result = []
    function walk(items, depth) {
        for (const item of items) {
            if (item.level < 3 && !excludedParentIds.value.has(item.id)) {
                result.push({ ...item, depth })
            }
            if (item.children?.length) walk(item.children, depth + 1)
        }
    }
    walk(props.categories, 0)
    return result
})

const canSave = computed(() => form.value.name_ru.trim().length > 0)
</script>

<template>
  <AppLayout>
    <template #header>{{ t('nav.categories') }}</template>

    <template #actions>
      <CreateButton :label="t('categories.addBtn')" @click="openCreate()" />
    </template>

    <!-- Поиск по дереву: совпадения показываются с путём до корня -->
    <div class="mb-4 flex flex-wrap items-center gap-2.5">
      <SearchInput v-model="query" :placeholder="t('categories.searchPlaceholder')" :debounce="0" class="w-full sm:w-[320px]" />
      <span class="ml-auto whitespace-nowrap text-[12.5px] tabular-nums text-[var(--text-muted)]">
        {{ t('dataTable.countOf', { shown: rows.length, total: allFlat.length }) }}
      </span>
    </div>

    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
      <table class="w-full min-w-[640px]">
        <thead>
          <tr :class="thead">
            <th :class="th" class="w-[72px]">{{ t('common.id') }}</th>
            <th :class="th">{{ t('categories.colName') }}</th>
            <th :class="th" class="w-[100px]">{{ t('categories.colLevel') }}</th>
            <th :class="th" class="w-[130px]">{{ t('common.status') }}</th>
            <th :class="th" class="w-[110px]">{{ t('categories.colOrder') }}</th>
            <th :class="th" class="text-right">{{ t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="cat in rows" :key="cat.id" :class="tr" class="!h-[60px]">
            <td :class="td" class="font-data tabular-nums text-[var(--text-muted)]">{{ cat.id }}</td>
            <td :class="td">
              <!-- Отступ по уровню + вертикальная линия: видно, чья это подкатегория -->
              <div class="flex min-w-0 items-center" :style="{ paddingLeft: (query ? 0 : cat.depth * 24) + 'px' }">
                <span v-if="!query && cat.depth" class="mr-2 h-7 w-px flex-none bg-[var(--field-border)]"></span>
                <button
                  v-if="!query && cat.children?.length"
                  type="button"
                  @click="toggleCollapse(cat.id)"
                  :aria-expanded="!collapsed.has(cat.id)"
                  :title="collapsed.has(cat.id) ? t('categories.expand') : t('categories.collapse')"
                  class="mr-1.5 flex h-6 w-6 flex-none items-center justify-center rounded-[6px] text-[var(--text-muted)] transition-colors duration-150 hover:bg-[var(--nav-hover)] hover:text-[var(--text)]"
                >
                  <Icon kind="chevronDown" :size="14" class="transition-transform duration-150" :class="{ '-rotate-90': collapsed.has(cat.id) }" />
                </button>
                <span v-else-if="!query" class="mr-1.5 w-6 flex-none"></span>

                <button
                  v-if="cat.image_url"
                  type="button"
                  @click.stop="openPreview(cat)"
                  :title="t('categories.image')"
                  class="mr-3 h-9 w-9 flex-none overflow-hidden rounded-[8px] border border-[var(--card-border)] transition-opacity duration-150 hover:opacity-80"
                ><img :src="cat.image_url" class="h-full w-full object-cover" alt="" /></button>
                <div v-else class="mr-3 flex h-9 w-9 flex-none items-center justify-center overflow-hidden rounded-[8px] border border-[var(--card-border)] bg-[var(--field-bg)]">
                  <img v-if="cat.icon_url" :src="cat.icon_url" class="h-[18px] w-[18px] object-contain" alt="" />
                  <Icon v-else kind="tag" :size="15" class="text-[var(--text-muted)]" />
                </div>

                <div class="min-w-0">
                  <div class="flex items-center gap-2">
                    <span class="truncate font-semibold text-[var(--text)]">{{ cat.name_ru }}</span>
                    <img v-if="cat.image_url && cat.icon_url" :src="cat.icon_url" class="h-3.5 w-3.5 flex-none object-contain opacity-70" alt="" />
                  </div>
                  <div class="truncate text-[12px] text-[var(--text-muted)]">
                    <template v-if="query && cat.path">{{ cat.path }} · </template>{{ cat.name_tk || '—' }}<template v-if="cat.children?.length"> · {{ t('categories.subCount', { n: cat.children.length }) }}</template>
                  </div>
                </div>
              </div>
            </td>
            <td :class="td">
              <span class="inline-flex h-6 items-center rounded-full bg-[var(--nav-hover)] px-2.5 font-data text-[11.5px] font-semibold text-[var(--text-secondary)]">L{{ cat.level }}</span>
            </td>
            <td :class="td">
              <StatusBadge :status="cat.is_active ? 'active' : 'suspended'" :label="cat.is_active ? t('categories.active') : t('categories.hidden')" />
            </td>
            <td :class="td">
              <div class="flex items-center gap-1">
                <button
                  type="button" @click="move(cat, 'up')" :disabled="siblingInfo[cat.id]?.isFirst"
                  class="icon-btn !h-7 !w-7 !bg-transparent hover:!bg-[var(--nav-hover)]" :title="t('categories.moveUp')" :aria-label="t('categories.moveUp')"
                ><Icon kind="arrowUp" :size="14" /></button>
                <button
                  type="button" @click="move(cat, 'down')" :disabled="siblingInfo[cat.id]?.isLast"
                  class="icon-btn !h-7 !w-7 !bg-transparent hover:!bg-[var(--nav-hover)]" :title="t('categories.moveDown')" :aria-label="t('categories.moveDown')"
                ><Icon kind="arrowDown" :size="14" /></button>
              </div>
            </td>
            <td :class="td">
              <div class="flex items-center justify-end gap-1.5">
                <button type="button" @click="toggleActive(cat)" :title="cat.is_active ? t('actions.hide') : t('actions.show')" :aria-label="cat.is_active ? t('actions.hide') : t('actions.show')" class="icon-btn">
                  <Icon :kind="cat.is_active ? 'eyeOff' : 'eye'" :size="16" />
                </button>
                <button v-if="cat.level < 3" type="button" @click="openCreate(cat)" :title="t('categories.addSub')" :aria-label="t('categories.addSub')" class="icon-btn">
                  <Icon kind="plus" :size="16" />
                </button>
                <button type="button" @click="openEdit(cat)" :title="t('actions.edit')" :aria-label="t('actions.edit')" class="icon-btn">
                  <Icon kind="pencil" :size="16" />
                </button>
                <button type="button" @click="destroy(cat)" :title="t('actions.delete')" :aria-label="t('actions.delete')" class="icon-btn icon-btn-danger">
                  <Icon kind="trash" :size="16" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!rows.length">
            <td colspan="6">
              <EmptyState v-if="query" icon="search" :title="t('dataTable.empty')" :text="t('common.emptyFiltered')">
                <button type="button" class="btn btn-secondary" @click="query = ''">{{ t('common.resetFilters') }}</button>
              </EmptyState>
              <EmptyState v-else icon="tag" :title="t('categories.empty')" :text="t('categories.emptyHint')">
                <CreateButton :label="t('categories.addBtn')" @click="openCreate()" />
              </EmptyState>
            </td>
          </tr>
        </tbody>
      </table>
      </div>
    </div>

    <AppDrawer :open="drawerOpen" :title="editItem ? t('categories.editTitle') : t('categories.newTitle')" @close="drawerOpen = false">
      <div class="space-y-4 p-5">
        <DrawerField :label="t('categories.parentCategory')">
          <select v-model="form.parent_id" class="input">
            <option value="">{{ t('categories.rootOption') }}</option>
            <option v-for="p in parentOptions" :key="p.id" :value="p.id">
              {{ '— '.repeat(p.depth) }}{{ p.name_ru }}
            </option>
          </select>
        </DrawerField>
        <DrawerField v-if="!form.parent_id" :label="t('categories.image')" :error="errors.image">
          <ImageCropUpload
            v-model="form.image"
            v-model:crop-x="form.crop_x"
            v-model:crop-y="form.crop_y"
            :existing-url="editItem?.image_url"
            :aspect="1"
            :min-width="700"
            :min-height="700"
          />
          <p class="mt-1.5 text-[12px] text-[var(--text-muted)]">{{ t('categories.imageHint') }}</p>
        </DrawerField>
        <DrawerField :label="t('categories.nameRu')" required :error="errors.name_ru">
          <input v-model="form.name_ru" class="input" :class="errors.name_ru ? 'border-red' : ''" />
        </DrawerField>
        <DrawerField :label="t('categories.nameTk')" :error="errors.name_tk">
          <input v-model="form.name_tk" class="input" :class="errors.name_tk ? 'border-red' : ''" />
        </DrawerField>
        <DrawerField :label="t('categories.icon')" :error="errors.icon || errors.icon_path">
          <IconPicker
            v-model:icon-path="form.icon_path"
            v-model:icon-file="form.icon"
            :library="icons"
            :existing-url="editItem?.icon_url"
          />
        </DrawerField>
        <DrawerField :label="t('categories.activeField')">
          <ToggleSwitch v-model="form.is_active" />
          <p class="mt-1.5 text-[12px] text-[var(--text-muted)]">{{ t('categories.hiddenHint') }}</p>
        </DrawerField>
      </div>
      <template #footer>
        <DrawerFooter
          :can-save="canSave"
          :save-label="editItem ? t('actions.save') : t('actions.create')"
          @cancel="drawerOpen = false"
          @save="save"
        />
      </template>
    </AppDrawer>

    <ImagePreviewModal :open="previewOpen" :src="previewSrc" @close="previewOpen = false" />
  </AppLayout>
</template>
