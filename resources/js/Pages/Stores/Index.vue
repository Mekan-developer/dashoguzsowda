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
import StatusBadge from '@/Components/StatusBadge.vue'

const { t } = useI18n()
const page = usePage()
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin')

const props = defineProps({
    stores: Object,
    categories: Array,
    regions: Array,
    rejectionReasons: Array,
    counts: Object,
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
    region_id: null, city_id: null, district_id: null,
    sells_retail: true, sells_wholesale: false, has_delivery: false,
    logo: null, crop_x: 50, crop_y: 50, photos: [],
})
const form   = ref(emptyForm())
const errors = ref({})
const newPhotoPreviews = ref([])

// Города и районы — от выбранного региона: справочник приходит деревом
const cityOptions = computed(() =>
    (props.regions || []).find(r => r.id === form.value.region_id)?.cities || [])
const districtOptions = computed(() =>
    cityOptions.value.find(c => c.id === form.value.city_id)?.districts || [])

function onRegionChange() { form.value.city_id = null; form.value.district_id = null }
function onCityChange() { form.value.district_id = null }

const statusFilter = ref(props.filters?.status || '')
const statusChips = computed(() => [
    { value: '',         label: t('common.all') },
    { value: 'pending',  label: t('stores.tabPending'),  count: props.counts?.pending, tint: 'bg-orange/15 text-orange' },
    { value: 'approved', label: t('stores.tabApproved'), tint: 'bg-green/15 text-green' },
    { value: 'rejected', label: t('stores.tabRejected'), tint: 'bg-red/15 text-red' },
])
function setStatus(value) {
    statusFilter.value = value
    router.get(route('stores.index'), { ...props.filters, status: value || undefined }, {
        preserveState: true, preserveScroll: true, replace: true,
    })
}

const dataTableColumns = computed(() => [
    { key: 'id', label: t('common.id'), width: '64px', type: 'id' },
    { key: 'logo', label: '', width: '40px', type: 'image' },
    { key: 'name', label: t('common.title'), type: 'text' },
    { key: 'user', label: t('stores.ownerColumn') },
    { key: 'trade', label: t('stores.tradeColumn') },
    { key: 'status', label: t('common.status') },
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
        region_id: s.region_id ?? null, city_id: s.city_id ?? null, district_id: s.district_id ?? null,
        sells_retail: !!s.sells_retail, sells_wholesale: !!s.sells_wholesale,
        has_delivery: !!s.has_delivery,
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

// Модерация: магазин попадает в мобильную витрину только после одобрения
function approve(s) { router.patch(route('stores.approve', s.id), {}, { preserveScroll: true }) }

const rejectTarget = ref(null)
const rejectReason = ref('')
function openReject(s) { rejectTarget.value = s; rejectReason.value = '' }
function doReject() {
    if (!rejectReason.value) return
    router.patch(route('stores.reject', rejectTarget.value.id), { rejection_reason_id: rejectReason.value }, {
        preserveScroll: true,
        onSuccess: () => { rejectTarget.value = null },
    })
}

function reasonName(reason) {
    return reason?.name_ru || reason?.name_tk || ''
}
</script>

<template>
  <AppLayout>
    <template #header>{{ t('nav.stores') }}</template>

    <!-- Фильтр по статусу модерации: магазин виден в мобилке только после одобрения -->
    <div class="mb-4 flex flex-wrap gap-2">
      <button
        v-for="chip in statusChips"
        :key="chip.value"
        @click="setStatus(chip.value)"
        class="flex items-center gap-1.5 rounded-[20px] px-3.5 py-1.5 text-[13px] font-bold transition"
        :class="statusFilter === chip.value
          ? 'bg-[var(--accent)] text-white shadow-[0_4px_12px_var(--accent-tint)]'
          : 'bg-white dark:bg-dcard border border-line dark:border-dline text-ink dark:text-slate-200 hover:bg-surface dark:hover:bg-white/5'"
      >
        {{ chip.label }}
        <span
          v-if="chip.count"
          class="rounded-pill px-1.5 py-px text-[11px] font-extrabold"
          :class="statusFilter === chip.value ? 'bg-white/25 text-white' : chip.tint"
        >{{ chip.count }}</span>
      </button>
    </div>

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

      <template #cell-trade="{ item }">
        <div class="flex flex-wrap items-center gap-1">
          <span v-if="item.sells_retail" class="rounded-pill bg-blue/15 px-2 py-px text-[11px] font-bold text-blue">
            {{ t('stores.retail') }}
          </span>
          <span v-if="item.sells_wholesale" class="rounded-pill bg-orange/15 px-2 py-px text-[11px] font-bold text-orange">
            {{ t('stores.wholesale') }}
          </span>
          <span v-if="item.has_delivery" class="rounded-pill bg-green/15 px-2 py-px text-[11px] font-bold text-green">
            {{ t('stores.delivery') }}
          </span>
        </div>
      </template>

      <template #cell-status="{ item }">
        <div class="flex items-center gap-2">
          <span :title="item.status === 'rejected' && item.rejection_reason ? reasonName(item.rejection_reason) : undefined">
            <StatusBadge :status="item.status" />
          </span>
          <!-- Витрина погашена: у владельца истёк тариф с правом на магазин -->
          <span v-if="!item.is_active" class="rounded-pill bg-muted/15 px-2 py-px text-[11px] font-bold text-muted">
            {{ t('stores.inactive') }}
          </span>
          <template v-if="isAdmin && item.status !== 'approved'">
            <button
              @click.stop="approve(item)"
              class="flex h-7 w-7 items-center justify-center rounded-[8px] bg-green/15 text-green transition hover:bg-green/25"
              :title="t('actions.approve')" :aria-label="t('actions.approve')"
            ><Icon kind="check" :size="13" /></button>
          </template>
          <button
            v-if="isAdmin && item.status !== 'rejected'"
            @click.stop="openReject(item)"
            class="flex h-7 w-7 items-center justify-center rounded-[8px] bg-red/15 text-red transition hover:bg-red/25"
            :title="t('actions.reject')" :aria-label="t('actions.reject')"
          ><Icon kind="close" :size="13" /></button>
        </div>
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

      <div class="grid grid-cols-3 gap-3">
        <DrawerField :label="t('common.region')" :error="errors.region_id">
          <select v-model="form.region_id" class="input" @change="onRegionChange">
            <option :value="null">—</option>
            <option v-for="r in regions || []" :key="r.id" :value="r.id">{{ r.name_ru || r.name_tk }}</option>
          </select>
        </DrawerField>
        <DrawerField :label="t('common.city')" :error="errors.city_id">
          <select v-model="form.city_id" class="input" :disabled="!form.region_id" @change="onCityChange">
            <option :value="null">—</option>
            <option v-for="c in cityOptions" :key="c.id" :value="c.id">{{ c.name_ru || c.name_tk }}</option>
          </select>
        </DrawerField>
        <DrawerField :label="t('common.district')" :error="errors.district_id">
          <select v-model="form.district_id" class="input" :disabled="!form.city_id">
            <option :value="null">—</option>
            <option v-for="d in districtOptions" :key="d.id" :value="d.id">{{ d.name_ru || d.name_tk }}</option>
          </select>
        </DrawerField>
      </div>

      <DrawerField :label="t('common.address')" :error="errors.address">
        <input v-model="form.address" class="input" />
      </DrawerField>

      <!-- Опт и розница — независимые флаги: магазин может торговать и так, и так -->
      <DrawerField :label="t('stores.tradeColumn')" :error="errors.sells_retail">
        <div class="flex flex-wrap gap-4">
          <label class="flex cursor-pointer items-center gap-2 text-[13px] text-[var(--text-secondary)]">
            <input type="checkbox" v-model="form.sells_retail" class="accent-blue" />
            {{ t('stores.retail') }}
          </label>
          <label class="flex cursor-pointer items-center gap-2 text-[13px] text-[var(--text-secondary)]">
            <input type="checkbox" v-model="form.sells_wholesale" class="accent-blue" />
            {{ t('stores.wholesale') }}
          </label>
          <label class="flex cursor-pointer items-center gap-2 text-[13px] text-[var(--text-secondary)]">
            <input type="checkbox" v-model="form.has_delivery" class="accent-blue" />
            {{ t('stores.delivery') }}
          </label>
        </div>
        <p class="mt-1.5 text-[11px] text-[var(--text-muted)]">{{ t('stores.deliveryHint') }}</p>
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

    <!-- Отказ по магазину — с причиной из общего справочника (тип store) -->
    <div v-if="rejectTarget" class="fixed inset-0 z-[600] flex items-center justify-center bg-black/40 backdrop-blur-sm" @click.self="rejectTarget = null">
      <div class="w-full max-w-md rounded-card bg-white p-6 shadow-soft dark:bg-dcard">
        <h3 class="mb-4 text-[17px] font-extrabold text-ink dark:text-slate-100">{{ t('stores.rejectTitle') }}</h3>
        <div class="mb-5 space-y-1 max-h-72 overflow-y-auto">
          <label v-for="r in rejectionReasons || []" :key="r.id" class="flex items-center gap-3 cursor-pointer rounded-btn p-3 hover:bg-surface dark:hover:bg-white/5 transition">
            <input type="radio" :value="r.id" v-model="rejectReason" class="accent-blue" />
            <span class="text-[13px] text-ink dark:text-slate-200">{{ r.name_ru || r.name_tk }}</span>
          </label>
          <p v-if="!(rejectionReasons || []).length" class="p-3 text-[13px] text-muted">{{ t('stores.noRejectionReasons') }}</p>
        </div>
        <div class="flex gap-2">
          <button @click="rejectTarget = null" class="flex-1 rounded-btn border-2 border-line py-[11px] text-[13px] font-bold text-muted hover:border-blue hover:text-blue transition dark:border-dline">{{ t('actions.cancel') }}</button>
          <button @click="doReject" :disabled="!rejectReason" class="flex-1 rounded-btn bg-red py-[11px] text-[13px] font-bold text-white hover:opacity-90 disabled:opacity-40 transition">{{ t('actions.reject') }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
