<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppDrawer from '@/Components/AppDrawer.vue'
import CreateButton from '@/Components/CreateButton.vue'
import DrawerField from '@/Components/DrawerField.vue'
import DrawerFooter from '@/Components/DrawerFooter.vue'
import Icon from '@/Components/Icon.vue'
import ImageCropUpload from '@/Components/ImageCropUpload.vue'
import DataTable from '@/Components/DataTable.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import UserSearchSelect from '@/Components/UserSearchSelect.vue'
import { confirmDialog } from '@/confirm'

const { t } = useI18n()
const page = usePage()
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin')

const props = defineProps({
    stores: Object,
    categories: Array,
    regions: Array,
    rejectionReasons: Array,
    paymentMethods: Array,
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
const isCreate = computed(() => !editItem.value)
const emptyForm = () => ({
    user_id: null,
    name: '', description: '', phone: '', address: '', category_id: null,
    region_id: null, city_id: null, district_id: null,
    sells_retail: true, sells_wholesale: false, has_delivery: false,
    payment_method_ids: [],
    commission_percent: 0,
    logo: null, crop_x: 50, crop_y: 50, photos: [],
})
const form   = ref(emptyForm())
const errors = ref({})
const newPhotoPreviews = ref([])

const cityOptions = computed(() =>
    (props.regions || []).find(r => r.id === form.value.region_id)?.cities || [])
const districtOptions = computed(() =>
    cityOptions.value.find(c => c.id === form.value.city_id)?.districts || [])

function onRegionChange() { form.value.city_id = null; form.value.district_id = null }
function onCityChange() { form.value.district_id = null }

const statusFilter = ref(props.filters?.status || '')
const statusChips = computed(() => [
    { value: '',         label: t('common.all') },
    { value: 'pending',  label: t('stores.tabPending'),  count: props.counts?.pending },
    { value: 'approved', label: t('stores.tabApproved') },
    { value: 'rejected', label: t('stores.tabRejected') },
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
    { key: 'commission_percent', label: t('stores.commissionColumn'), width: '110px' },
    { key: 'status', label: t('common.status') },
    { key: 'is_popular', label: t('stores.popularColumn') },
    { key: 'sort_order', label: '', width: '56px' },
])

// Модерация — тоже в колонке действий, как на остальных страницах
const dataTableActions = computed(() => isAdmin.value ? [
    { icon: 'check', title: t('actions.approve'), handler: approve, color: 'green', visible: s => s.status !== 'approved' },
    { icon: 'close', title: t('actions.reject'), handler: openReject, color: 'red', visible: s => s.status !== 'rejected' },
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

function openCreate() {
    editItem.value = null
    form.value = emptyForm()
    if (props.paymentMethods?.length) {
        form.value.payment_method_ids = [props.paymentMethods[0].id]
    }
    newPhotoPreviews.value = []
    errors.value = {}
    drawer.value = true
}

function openEdit(s) {
    editItem.value = s
    form.value = {
        user_id: s.user_id ?? null,
        name: s.name ?? '', description: s.description ?? '', phone: s.phone ?? '',
        address: s.address ?? '', category_id: s.category_id ?? null,
        region_id: s.region_id ?? null, city_id: s.city_id ?? null, district_id: s.district_id ?? null,
        sells_retail: !!s.sells_retail, sells_wholesale: !!s.sells_wholesale,
        has_delivery: !!s.has_delivery,
        payment_method_ids: (s.payment_methods || []).map(m => m.id),
        commission_percent: Number(s.commission_percent ?? 0),
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
async function removeExistingPhoto(photo) {
    if (await confirmDialog(t('stores.confirmDeletePhoto'))) {
        router.delete(route('stores.photos.destroy', [editItem.value.id, photo.id]), {
            preserveScroll: true,
            onSuccess: () => { editItem.value.photos = editItem.value.photos.filter(p => p.id !== photo.id) },
        })
    }
}

function save() {
    if (props.paymentMethods?.length && !form.value.payment_method_ids.length) {
        errors.value = { payment_method_ids: t('stores.paymentRequired') }
        return
    }

    if (isCreate.value) {
        router.post(route('stores.store'), { ...form.value }, {
            forceFormData: true,
            onSuccess: () => { closeDrawer() },
            onError: e => { errors.value = e },
        })
        return
    }

    router.post(route('stores.update', editItem.value.id), { ...form.value, _method: 'put' }, {
        forceFormData: true,
        onSuccess: () => { closeDrawer() },
        onError: e => { errors.value = e },
    })
}
function toggle(s) { router.patch(route('stores.toggle', s.id)) }
function move(s, direction) { router.patch(route('stores.move', s.id), { direction }) }
async function destroy(s) {
    if (await confirmDialog(t('actions.confirmDelete', { name: s.name }))) router.delete(route('stores.destroy', s.id))
}

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

// Нейтральная метка режима торговли
const sectionHead = 'card-title mb-4 mt-2 border-t border-[var(--card-border)] pt-5'
const tag = 'inline-flex h-6 items-center rounded-full bg-[var(--nav-hover)] px-2.5 text-[11.5px] font-semibold text-[var(--text-secondary)]'

function reasonName(reason) {
    return reason?.name_ru || reason?.name_tk || ''
}
</script>

<template>
  <AppLayout>
    <template #header>{{ t('nav.stores') }}</template>

    <template #actions>
      <CreateButton v-if="isAdmin" :label="t('actions.create')" @click="openCreate" />
    </template>

    <DataTable
      :columns="dataTableColumns"
      :items="stores.data"
      :pagination="stores"
      :actions="dataTableActions"
      :search-field="'name'"
      :search-placeholder="t('stores.searchPlaceholder')"
      empty-icon="shop"
      :empty-message="statusFilter ? t('dataTable.empty') : t('stores.emptyTitle')"
      :empty-text="statusFilter ? t('common.emptyFiltered') : t('stores.emptyText')"
      @dblclick="isAdmin ? openEdit($event) : null"
    >
      <!-- Фильтр по статусу модерации: магазин виден в мобилке только после одобрения -->
      <template #toolbar>
        <div class="seg">
          <button
            v-for="chip in statusChips" :key="chip.value"
            type="button"
            @click="setStatus(chip.value)"
            :aria-pressed="statusFilter === chip.value"
            class="seg-item"
            :class="statusFilter === chip.value ? 'seg-item-active' : ''"
          >{{ chip.label }}<template v-if="chip.count"> ({{ chip.count }})</template></button>
        </div>
      </template>

      <template #cell-user="{ item }">
        <span class="block max-w-[180px] truncate text-[var(--text-secondary)]">{{ item.user?.name || item.user?.phone || '—' }}</span>
      </template>

      <template #cell-trade="{ item }">
        <div class="flex flex-wrap items-center gap-1">
          <span v-if="item.sells_retail" :class="tag">{{ t('stores.retail') }}</span>
          <span v-if="item.sells_wholesale" :class="tag">{{ t('stores.wholesale') }}</span>
          <span v-if="item.has_delivery" :class="tag" class="!bg-emerald-500/10 !text-emerald-700 dark:!text-emerald-300">{{ t('stores.delivery') }}</span>
        </div>
      </template>

      <!-- Комиссия платформы: у каждого магазина своя, 0 — комиссии нет -->
      <template #cell-commission_percent="{ item }">
        <span v-if="Number(item.commission_percent) > 0" class="font-data font-semibold tabular-nums text-[var(--text)]">{{ Number(item.commission_percent) }} %</span>
        <span v-else class="text-[var(--text-muted)]">—</span>
      </template>

      <template #cell-status="{ item }">
        <div class="flex items-center gap-2">
          <span :title="item.status === 'rejected' && item.rejection_reason ? reasonName(item.rejection_reason) : undefined">
            <StatusBadge :status="item.status" />
          </span>
          <!-- Витрина погашена: у владельца истёк тариф с правом на магазин -->
          <StatusBadge v-if="!item.is_active" status="suspended" :label="t('stores.inactive')" />
        </div>
      </template>

      <template #cell-is_popular="{ item }">
        <component
          :is="isAdmin ? 'button' : 'span'"
          :type="isAdmin ? 'button' : undefined"
          @click.stop="isAdmin && toggle(item)"
          class="rounded-full outline-none focus-visible:ring-2 focus-visible:ring-[var(--accent)]"
          :class="isAdmin ? 'cursor-pointer transition-opacity duration-150 hover:opacity-80' : ''"
        >
          <StatusBadge :status="item.is_popular ? 'active' : 'suspended'" :label="item.is_popular ? t('stores.popularOn') : t('stores.popularOff')" />
        </component>
      </template>

      <template #cell-sort_order="{ item }">
        <div v-if="isAdmin && item.is_popular" class="flex items-center gap-0.5">
          <button
            type="button" @click.stop="move(item, 'up')" :disabled="isFirstPopular(item)"
            class="icon-btn !h-7 !w-7 !bg-transparent hover:!bg-[var(--nav-hover)]" :title="t('stores.moveUp')" :aria-label="t('stores.moveUp')"
          ><Icon kind="arrowUp" :size="14" /></button>
          <button
            type="button" @click.stop="move(item, 'down')" :disabled="isLastPopular(item)"
            class="icon-btn !h-7 !w-7 !bg-transparent hover:!bg-[var(--nav-hover)]" :title="t('stores.moveDown')" :aria-label="t('stores.moveDown')"
          ><Icon kind="arrowDown" :size="14" /></button>
        </div>
      </template>
    </DataTable>

    <AppDrawer :open="drawer" :title="isCreate ? t('stores.createTitle') : t('stores.editTitle')" @close="closeDrawer">
      <h3 class="card-title mb-4">{{ t('stores.sectionMain') }}</h3>
      <DrawerField v-if="isCreate" :label="t('users.ownerLabel')" :required="true" :error="errors.user_id">
        <UserSearchSelect v-model="form.user_id" :error="errors.user_id" />
      </DrawerField>

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

      <h3 :class="sectionHead">{{ t('stores.sectionAddress') }}</h3>
      <div class="grid grid-cols-1 gap-x-3 sm:grid-cols-3">
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

      <h3 :class="sectionHead">{{ t('stores.sectionTrade') }}</h3>
      <!-- Опт и розница — независимые флаги: магазин может торговать и так, и так -->
      <DrawerField :label="t('stores.tradeColumn')" :error="errors.sells_retail">
        <div class="flex flex-wrap gap-4">
          <label class="flex cursor-pointer items-center gap-2 text-[13.5px] text-[var(--text)]">
            <input type="checkbox" v-model="form.sells_retail" class="accent-blue" />
            {{ t('stores.retail') }}
          </label>
          <label class="flex cursor-pointer items-center gap-2 text-[13.5px] text-[var(--text)]">
            <input type="checkbox" v-model="form.sells_wholesale" class="accent-blue" />
            {{ t('stores.wholesale') }}
          </label>
          <label class="flex cursor-pointer items-center gap-2 text-[13.5px] text-[var(--text)]">
            <input type="checkbox" v-model="form.has_delivery" class="accent-blue" />
            {{ t('stores.delivery') }}
          </label>
        </div>
        <p class="mt-1.5 text-[12px] text-[var(--text-muted)]">{{ t('stores.deliveryHint') }}</p>
      </DrawerField>

      <!-- Чем покупатель может рассчитаться с этим магазином: деньги идут
           мимо системы, прямо продавцу, поэтому условия расчёта — его -->
      <DrawerField :label="t('stores.paymentLabel')" :error="errors.payment_method_ids">
        <div class="flex flex-wrap gap-4">
          <label
            v-for="method in paymentMethods || []"
            :key="method.id"
            class="flex cursor-pointer items-center gap-2 text-[13.5px] text-[var(--text)]"
          >
            <input type="checkbox" :value="method.id" v-model="form.payment_method_ids" class="accent-blue" />
            {{ method.name_ru || method.name_tk }}
          </label>
        </div>
        <p class="mt-1.5 text-[12px] text-[var(--text-muted)]">{{ t('stores.paymentHint') }}</p>
      </DrawerField>

      <!-- Комиссия платформы: своя ставка у каждого магазина, удерживается
           с него при заказе — сумма покупателя от неё не меняется -->
      <DrawerField :label="t('stores.commissionLabel')" :error="errors.commission_percent">
        <div class="relative">
          <input
            v-model.number="form.commission_percent"
            type="number" min="0" max="100" step="0.01"
            class="input pr-9"
          />
          <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-[13px] font-bold text-[var(--text-muted)]">%</span>
        </div>
        <p class="mt-1.5 text-[12px] text-[var(--text-muted)]">{{ t('stores.commissionHint') }}</p>
      </DrawerField>

      <h3 :class="sectionHead">{{ t('stores.sectionMedia') }}</h3>
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
          <div v-for="photo in editItem?.photos || []" :key="photo.id" class="relative h-16 w-16 overflow-hidden rounded-[8px] border border-[var(--card-border)]">
            <img :src="`/storage/${photo.path}`" class="h-full w-full object-cover" />
            <button type="button" @click="removeExistingPhoto(photo)" class="absolute top-0.5 right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-black/60 text-white">
              <Icon kind="close" :size="9" />
            </button>
          </div>
          <div v-for="(preview, i) in newPhotoPreviews" :key="'new-'+i" class="relative h-16 w-16 overflow-hidden rounded-[8px] border border-[var(--card-border)]">
            <img :src="preview.url" class="h-full w-full object-cover" />
            <button type="button" @click="removeNewPhoto(i)" class="absolute top-0.5 right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-black/60 text-white">
              <Icon kind="close" :size="9" />
            </button>
          </div>
        </div>
        <label class="btn btn-secondary btn-sm border-dashed">
          <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="hidden" @change="onPhotosPick" />
          {{ t('stores.addPhotos') }}
        </label>
        <p class="mt-1.5 text-[12px] text-[var(--text-muted)]">{{ t('stores.photosLimitHint') }}</p>
      </DrawerField>

      <template #footer>
        <DrawerFooter @cancel="closeDrawer" @save="save" />
      </template>
    </AppDrawer>

    <!-- Отказ по магазину — с причиной из общего справочника (тип store) -->
    <div v-if="rejectTarget" class="fixed inset-0 z-[600] flex items-center justify-center bg-[#0A0C1A]/50 p-4" @click.self="rejectTarget = null">
      <div class="card w-full max-w-[440px] p-6 shadow-lg2">
        <h3 class="mb-4 text-[16px] font-semibold text-[var(--text)]">{{ t('stores.rejectTitle') }}</h3>
        <div class="mb-5 space-y-1 max-h-72 overflow-y-auto">
          <label v-for="r in rejectionReasons || []" :key="r.id" class="flex items-center gap-3 cursor-pointer rounded-btn p-3 hover:bg-surface dark:hover:bg-white/5 transition">
            <input type="radio" :value="r.id" v-model="rejectReason" class="accent-blue" />
            <span class="text-[13px] text-ink dark:text-slate-200">{{ r.name_ru || r.name_tk }}</span>
          </label>
          <p v-if="!(rejectionReasons || []).length" class="p-3 text-[13px] text-muted">{{ t('stores.noRejectionReasons') }}</p>
        </div>
        <div class="flex justify-end gap-2">
          <button @click="rejectTarget = null" class="btn btn-secondary">{{ t('actions.cancel') }}</button>
          <button @click="doReject" :disabled="!rejectReason" class="btn btn-danger">{{ t('actions.reject') }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
