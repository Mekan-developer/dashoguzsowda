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
import { confirmDialog } from '@/confirm'

const { t } = useI18n()

const props = defineProps({ categories: Array, icons: Array })

const levelBadge = {
    1: 'bg-blue-light text-blue',
    2: 'bg-green/10 text-green',
    3: 'bg-orange/10 text-orange',
}

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

    <div class="rounded-card bg-white shadow-soft dark:bg-dcard overflow-hidden">
      <table class="w-full">
        <thead class="bg-surface/50 dark:bg-dbg/50">
          <tr>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline w-16">#</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('categories.colName') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('categories.colTk') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline w-20">{{ t('categories.colLevel') }}</th>
            <th class="px-4 py-[11px] text-left text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.status') }}</th>
            <th class="px-4 py-[11px] text-right text-[11px] font-bold uppercase tracking-[.07em] text-muted border-b-2 border-line dark:border-dline">{{ t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="cat in flatList" :key="cat.id" class="hover:bg-surface/30 dark:hover:bg-white/3 transition">
            <td class="px-4 py-[12px] text-[12px] border-b border-line dark:border-dline font-data text-muted">
              <div class="flex items-center gap-1">
                <span>{{ cat.id }}</span>
                <div class="flex flex-col -my-1">
                  <button
                    @click="move(cat, 'up')" :disabled="siblingInfo[cat.id]?.isFirst"
                    class="flex h-[13px] w-[13px] items-center justify-center text-muted transition hover:text-blue disabled:opacity-25 disabled:hover:text-muted"
                  ><Icon kind="arrowUp" :size="10" /></button>
                  <button
                    @click="move(cat, 'down')" :disabled="siblingInfo[cat.id]?.isLast"
                    class="flex h-[13px] w-[13px] items-center justify-center text-muted transition hover:text-blue disabled:opacity-25 disabled:hover:text-muted"
                  ><Icon kind="arrowDown" :size="10" /></button>
                </div>
              </div>
            </td>
            <td class="px-4 py-[12px] text-[13px] border-b border-line dark:border-dline">
              <div class="flex items-center" :style="{ paddingLeft: cat.depth * 20 + 'px' }">
                <button
                  v-if="cat.children?.length"
                  @click="toggleCollapse(cat.id)"
                  class="mr-1 flex h-4 w-4 flex-shrink-0 items-center justify-center text-muted transition hover:text-blue"
                  :class="{ '-rotate-90': collapsed.has(cat.id) }"
                >
                  <Icon kind="chevronDown" :size="12" />
                </button>
                <span v-else class="mr-1 w-4 flex-shrink-0"></span>

                <div class="mr-2 flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-[7px] bg-surface dark:bg-dbg overflow-hidden">
                  <img v-if="cat.icon_url" :src="cat.icon_url" class="h-4 w-4 object-contain" alt="" />
                  <Icon v-else kind="image" :size="12" class="text-muted" />
                </div>
                <button
                  v-if="cat.image_url"
                  type="button"
                  @click.stop="openPreview(cat)"
                  :title="t('categories.image')"
                  class="mr-2 h-7 w-7 flex-shrink-0 overflow-hidden rounded-[7px] bg-surface transition hover:opacity-80 dark:bg-dbg"
                >
                  <img :src="cat.image_url" class="h-full w-full object-cover" alt="" />
                </button>

                <span class="font-bold text-ink dark:text-slate-200">{{ cat.name_ru }}</span>
                <span v-if="cat.children?.length" class="ml-2 text-[10px] font-data font-bold text-muted">({{ cat.children.length }})</span>
              </div>
            </td>
            <td class="px-4 py-[12px] text-[13px] border-b border-line dark:border-dline text-muted">{{ cat.name_tk }}</td>
            <td class="px-4 py-[12px] text-[12px] border-b border-line dark:border-dline">
              <span class="rounded-pill px-2.5 py-0.5 text-[11px] font-bold" :class="levelBadge[cat.level]">
                L{{ cat.level }}
              </span>
            </td>
            <td class="px-4 py-[12px] text-[13px] border-b border-line dark:border-dline">
              <span :class="cat.is_active ? 'text-green font-bold' : 'text-muted'">{{ cat.is_active ? t('categories.active') : t('categories.hidden') }}</span>
            </td>
            <td class="px-4 py-[12px] text-[13px] border-b border-line dark:border-dline">
              <div class="flex items-center justify-end gap-1.5">
                <button @click="toggleActive(cat)" :title="cat.is_active ? t('actions.hide') : t('actions.show')"
                  class="flex h-[28px] w-[28px] items-center justify-center rounded-[7px] bg-surface text-muted transition hover:bg-blue hover:text-white dark:bg-dbg">
                  <Icon :kind="cat.is_active ? 'eye' : 'eyeOff'" :size="13" />
                </button>
                <button v-if="cat.level < 3" @click="openCreate(cat)" :title="t('categories.addSub')"
                  class="flex h-[28px] w-[28px] items-center justify-center rounded-[7px] bg-blue-light text-blue transition hover:bg-blue hover:text-white">
                  <Icon kind="plus" :size="13" />
                </button>
                <button @click="openEdit(cat)" :title="t('actions.edit')"
                  class="flex h-[28px] w-[28px] items-center justify-center rounded-[7px] bg-surface text-muted transition hover:bg-blue hover:text-white dark:bg-dbg">
                  <Icon kind="pencil" :size="13" />
                </button>
                <button @click="destroy(cat)" :title="t('actions.delete')"
                  class="flex h-[28px] w-[28px] items-center justify-center rounded-[7px] bg-red/10 text-red transition hover:bg-red hover:text-white">
                  <Icon kind="trash" :size="13" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!flatList.length"><td colspan="6" class="px-4 py-10 text-center text-[13px] text-muted">{{ t('categories.empty') }}</td></tr>
        </tbody>
      </table>
    </div>

    <AppDrawer :open="drawerOpen" :title="editItem ? t('categories.editTitle') : t('categories.newTitle')" @close="drawerOpen = false">
      <div class="space-y-4 p-5">
        <DrawerField :label="t('categories.parentCategory')">
          <select v-model="form.parent_id" class="w-full rounded-btn border-2 border-line bg-surface py-[9px] px-[14px] text-[13px] font-semibold text-ink outline-none focus:border-blue dark:bg-dbg dark:border-dline dark:text-slate-200">
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
          <p class="mt-1.5 text-[11px] text-muted">{{ t('categories.imageHint') }}</p>
        </DrawerField>
        <DrawerField :label="t('categories.nameRu')" required :error="errors.name_ru">
          <input v-model="form.name_ru" class="w-full rounded-btn border-2 border-line bg-surface py-[9px] px-[14px] text-[13px] font-semibold text-ink outline-none focus:border-blue dark:bg-dbg dark:border-dline dark:text-slate-200" :class="errors.name_ru ? 'border-red' : ''" />
        </DrawerField>
        <DrawerField :label="t('categories.nameTk')" :error="errors.name_tk">
          <input v-model="form.name_tk" class="w-full rounded-btn border-2 border-line bg-surface py-[9px] px-[14px] text-[13px] font-semibold text-ink outline-none focus:border-blue dark:bg-dbg dark:border-dline dark:text-slate-200" :class="errors.name_tk ? 'border-red' : ''" />
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
          <p class="mt-1.5 text-[11px] text-muted">{{ t('categories.hiddenHint') }}</p>
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
