<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppDrawer from '@/Components/AppDrawer.vue'
import DrawerField from '@/Components/DrawerField.vue'
import Icon from '@/Components/Icon.vue'
import ImageCropUpload from '@/Components/ImageCropUpload.vue'
import DataTable from '@/Components/DataTable.vue'

const { t } = useI18n()
const page = usePage()
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin')

const props = defineProps({
    stores: Object,
    categories: Array,
    filters: Object,
})

function flattenCategories(nodes, depth = 0, acc = []) {
    for (const node of nodes || []) {
        acc.push({ id: node.id, label: '—'.repeat(depth) + ' ' + (node.name_ru || node.name_tk) })
        if (node.children?.length) flattenCategories(node.children, depth + 1, acc)
    }
    return acc
}
const categoryOptions = computed(() => flattenCategories(props.categories))

const drawer   = ref(false)
const editItem = ref(null)
const emptyForm = () => ({
    name: '', description: '', phone: '', address: '', category_id: null,
    logo: null, crop_x: 50, crop_y: 50, photos: [],
})
const form   = ref(emptyForm())
const errors = ref({})
const newPhotoPreviews = ref([])

const dataTableColumns = computed(() => [
    { key: 'logo', label: '', width: '40px', type: 'image' },
    { key: 'name', label: t('common.title'), type: 'text' },
    { key: 'user', label: t('stores.ownerColumn') },
    { key: 'category', label: t('common.category') },
    { key: 'is_popular', label: t('stores.popularColumn') },
    { key: 'sort_order', label: '', width: '56px' },
])

const dataTableActions = computed(() => isAdmin.value ? [
    { icon: 'pencil', title: t('actions.edit'), handler: openEdit },
    { icon: 'trash', title: t('actions.delete'), handler: destroy, color: 'red' },
] : [])

function isFirstPopular(s) {
    const popular = props.stores.data.filter(x => x.is_popular)
    return popular[0]?.id === s.id
}
function isLastPopular(s) {
    const popular = props.stores.data.filter(x => x.is_popular)
    return popular[popular.length - 1]?.id === s.id
}

function openEdit(s) {
    editItem.value = s
    form.value = {
        name: s.name ?? '', description: s.description ?? '', phone: s.phone ?? '',
        address: s.address ?? '', category_id: s.category_id ?? null,
        logo: null, crop_x: 50, crop_y: 50, photos: [],
    }
    newPhotoPreviews.value = []
    errors.value = {}
    drawer.value = true
}
function closeDrawer() {
    drawer.value = false
    newPhotoPreviews.value.forEach(p => URL.revokeObjectURL(p.url))
}

function onPhotosPick(e) {
    const files = Array.from(e.target.files || [])
    e.target.value = ''
    form.value.photos.push(...files)
    newPhotoPreviews.value.push(...files.map(f => ({ url: URL.createObjectURL(f) })))
}
function removeNewPhoto(index) {
    URL.revokeObjectURL(newPhotoPreviews.value[index].url)
    newPhotoPreviews.value.splice(index, 1)
    form.value.photos.splice(index, 1)
}
function removeExistingPhoto(photo) {
    if (confirm(t('stores.confirmDeletePhoto'))) {
        router.delete(route('stores.photos.destroy', [editItem.value.id, photo.id]), {
            preserveScroll: true,
            onSuccess: () => { editItem.value.photos = editItem.value.photos.filter(p => p.id !== photo.id) },
        })
    }
}

function save() {
    router.post(route('stores.update', editItem.value.id), { ...form.value, _method: 'put' }, {
        forceFormData: true,
        onSuccess: () => { closeDrawer() },
        onError: e => { errors.value = e },
    })
}
function toggle(s) { router.patch(route('stores.toggle', s.id)) }
function move(s, direction) { router.patch(route('stores.move', s.id), { direction }) }
function destroy(s) {
    if (confirm(t('actions.confirmDelete', { name: s.name }))) router.delete(route('stores.destroy', s.id))
}
</script>

<template>
  <AppLayout>
    <template #header>{{ t('nav.stores') }}</template>

    <DataTable
      :columns="dataTableColumns"
      :items="stores.data"
      :pagination="stores"
      :actions="dataTableActions"
      :search-field="'name'"
      :search-placeholder="t('stores.searchPlaceholder')"
      @dblclick="isAdmin ? openEdit($event) : null"
    >
      <template #cell-user="{ item }">
        <span class="text-[13px] text-[var(--text-secondary)]">{{ item.user?.name || item.user?.phone || '—' }}</span>
      </template>

      <template #cell-category="{ item }">
        <span class="text-[13px] text-[var(--text-secondary)]">{{ item.category?.name_ru || '—' }}</span>
      </template>

      <template #cell-is_popular="{ item }">
        <button v-if="isAdmin" @click.stop="toggle(item)" class="flex items-center gap-1.5">
          <div class="h-1.5 w-1.5 rounded-full" :class="item.is_popular ? 'bg-green' : 'bg-muted'"></div>
          <span class="text-[11px] font-bold" :class="item.is_popular ? 'text-green' : 'text-muted'">
            {{ item.is_popular ? t('stores.popularOn') : t('stores.popularOff') }}
          </span>
        </button>
        <div v-else class="flex items-center gap-1.5">
          <div class="h-1.5 w-1.5 rounded-full" :class="item.is_popular ? 'bg-green' : 'bg-muted'"></div>
          <span class="text-[11px] font-bold" :class="item.is_popular ? 'text-green' : 'text-muted'">
            {{ item.is_popular ? t('stores.popularOn') : t('stores.popularOff') }}
          </span>
        </div>
      </template>

      <template #cell-sort_order="{ item }">
        <div v-if="isAdmin && item.is_popular" class="flex flex-col -my-1">
          <button
            @click.stop="move(item, 'up')" :disabled="isFirstPopular(item)"
            class="flex h-[13px] w-[13px] items-center justify-center text-muted transition hover:text-blue disabled:opacity-25 disabled:hover:text-muted"
          ><Icon kind="arrowUp" :size="10" /></button>
          <button
            @click.stop="move(item, 'down')" :disabled="isLastPopular(item)"
            class="flex h-[13px] w-[13px] items-center justify-center text-muted transition hover:text-blue disabled:opacity-25 disabled:hover:text-muted"
          ><Icon kind="arrowDown" :size="10" /></button>
        </div>
      </template>
    </DataTable>

    <AppDrawer :open="drawer" :title="t('stores.editTitle')" @close="closeDrawer">
      <DrawerField :label="t('common.title')" :required="true" :error="errors.name">
        <input v-model="form.name" class="input" />
      </DrawerField>

      <DrawerField :label="t('common.description')" :error="errors.description">
        <textarea v-model="form.description" rows="3" class="input"></textarea>
      </DrawerField>

      <div class="grid grid-cols-2 gap-3">
        <DrawerField :label="t('common.phone')" :error="errors.phone">
          <input v-model="form.phone" class="input" />
        </DrawerField>
        <DrawerField :label="t('common.category')" :error="errors.category_id">
          <select v-model="form.category_id" class="input">
            <option :value="null">—</option>
            <option v-for="c in categoryOptions" :key="c.id" :value="c.id">{{ c.label }}</option>
          </select>
        </DrawerField>
      </div>

      <DrawerField :label="t('common.address')" :error="errors.address">
        <input v-model="form.address" class="input" />
      </DrawerField>

      <DrawerField :label="t('stores.logoLabel')" :error="errors.logo">
        <ImageCropUpload
          v-model="form.logo"
          v-model:crop-x="form.crop_x"
          v-model:crop-y="form.crop_y"
          :existing-url="editItem?.logo ? `/storage/${editItem.logo}` : null"
          :aspect="1"
          :min-width="400"
          :min-height="400"
        />
      </DrawerField>

      <DrawerField :label="t('stores.photosLabel')" :error="errors.photos">
        <div class="flex flex-wrap gap-2 mb-2">
          <div v-for="photo in editItem?.photos || []" :key="photo.id" class="relative h-16 w-16 rounded-[9px] overflow-hidden border border-line dark:border-dline">
            <img :src="`/storage/${photo.path}`" class="h-full w-full object-cover" />
            <button type="button" @click="removeExistingPhoto(photo)" class="absolute top-0.5 right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-black/60 text-white">
              <Icon kind="close" :size="9" />
            </button>
          </div>
          <div v-for="(preview, i) in newPhotoPreviews" :key="'new-'+i" class="relative h-16 w-16 rounded-[9px] overflow-hidden border border-line dark:border-dline">
            <img :src="preview.url" class="h-full w-full object-cover" />
            <button type="button" @click="removeNewPhoto(i)" class="absolute top-0.5 right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-black/60 text-white">
              <Icon kind="close" :size="9" />
            </button>
          </div>
        </div>
        <label class="inline-flex cursor-pointer items-center gap-2 rounded-[10px] border border-dashed border-[var(--field-border)] px-3.5 py-2 text-[12px] font-semibold text-[var(--text-secondary)] hover:border-[var(--accent)] hover:text-[var(--accent)] transition-colors">
          <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="hidden" @change="onPhotosPick" />
          {{ t('stores.addPhotos') }}
        </label>
        <p class="mt-1.5 text-[11px] text-[var(--text-muted)]">{{ t('stores.photosLimitHint') }}</p>
      </DrawerField>

      <template #footer>
        <div class="flex justify-end gap-2">
          <button
            @click="closeDrawer"
            class="rounded-[10px] border border-[var(--field-border)] bg-transparent px-[18px] py-[10px] text-[13px] font-semibold text-[var(--text-secondary)] transition-colors hover:bg-[var(--nav-hover)]"
          >{{ t('actions.cancel') }}</button>
          <button
            @click="save"
            class="rounded-[10px] px-5 py-[10px] text-[13px] font-bold text-white transition-colors bg-[var(--accent)] hover:bg-[var(--accent-hover)] shadow-[0_10px_22px_-8px_var(--accent)]"
          >{{ t('actions.save') }}</button>
        </div>
      </template>
    </AppDrawer>
  </AppLayout>
</template>
