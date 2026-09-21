<script setup>
import { computed, ref, watch } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Icon from '@/Components/Icon.vue'

const { t } = useI18n()
const page = usePage()
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin')

const props = defineProps({ listing: Object, categories: Array, regions: Array, rejectionReasons: Array })

const rejectReason = ref('')
const showRejectModal = ref(false)
const editing = ref(false)
const tagsInput = ref('')
const photoPreviews = ref([])
const removeMediaIds = ref([])

function flattenLeafCategories(nodes, path = [], acc = []) {
    for (const node of nodes || []) {
        const next = [...path, node.name_ru]
        if (node.children?.length) {
            flattenLeafCategories(node.children, next, acc)
        } else {
            acc.push({ id: node.id, label: next.join(' → ') })
        }
    }
    return acc
}

const categoryOptions = computed(() => flattenLeafCategories(props.categories))

const form = useForm({
    title: props.listing.title ?? '',
    description: props.listing.description ?? '',
    type: props.listing.type ?? 'goods',
    price: props.listing.price ?? '',
    category_id: props.listing.category_id ?? null,
    region_id: props.listing.region_id ?? null,
    city_id: props.listing.city_id ?? null,
    district_id: props.listing.district_id ?? null,
    phone: props.listing.phone ?? '',
    tags: props.listing.tags ?? [],
    wholesale_price: props.listing.wholesale_price ?? '',
    min_order_qty: props.listing.min_order_qty ?? '',
    stock_qty: props.listing.stock_qty ?? '',
    photos: [],
    remove_media_ids: [],
})

const cityOptions = computed(() =>
    (props.regions || []).find(r => r.id === form.region_id)?.cities || [])
const districtOptions = computed(() =>
    cityOptions.value.find(c => c.id === form.city_id)?.districts || [])

watch(() => props.listing, (listing) => {
    syncForm(listing)
}, { deep: true })

function syncForm(listing) {
    form.title = listing.title ?? ''
    form.description = listing.description ?? ''
    form.type = listing.type ?? 'goods'
    form.price = listing.price ?? ''
    form.category_id = listing.category_id ?? null
    form.region_id = listing.region_id ?? null
    form.city_id = listing.city_id ?? null
    form.district_id = listing.district_id ?? null
    form.phone = listing.phone ?? ''
    form.tags = listing.tags ?? []
    form.wholesale_price = listing.wholesale_price ?? ''
    form.min_order_qty = listing.min_order_qty ?? ''
    form.stock_qty = listing.stock_qty ?? ''
    form.photos = []
    form.remove_media_ids = []
    tagsInput.value = (listing.tags || []).join(', ')
    removeMediaIds.value = []
    photoPreviews.value.forEach(p => URL.revokeObjectURL(p.url))
    photoPreviews.value = []
}

function onRegionChange() { form.city_id = null; form.district_id = null }
function onCityChange() { form.district_id = null }

function onPhotosPick(e) {
    const files = Array.from(e.target.files || [])
    e.target.value = ''
    form.photos.push(...files)
    photoPreviews.value.push(...files.map(f => ({ url: URL.createObjectURL(f) })))
}

function removeNewPhoto(index) {
    URL.revokeObjectURL(photoPreviews.value[index].url)
    photoPreviews.value.splice(index, 1)
    form.photos.splice(index, 1)
}

function markRemoveMedia(id) {
    if (!removeMediaIds.value.includes(id)) removeMediaIds.value.push(id)
}

function unmarkRemoveMedia(id) {
    removeMediaIds.value = removeMediaIds.value.filter(x => x !== id)
}

function approve() {
    router.patch(route('listings.approve', props.listing.id))
}

function doReject() {
    if (!rejectReason.value) return
    router.patch(route('listings.reject', props.listing.id), { rejection_reason_id: rejectReason.value }, {
        onSuccess: () => { showRejectModal.value = false },
    })
}

function boost() {
    router.patch(route('listings.boost', props.listing.id), {}, { preserveScroll: true })
}

function destroy() {
    if (!confirm(t('listings.deleteConfirm'))) return
    router.delete(route('listings.destroy', props.listing.id))
}

function saveEdit() {
    if (!form.title.trim() || !form.category_id) return
    form.tags = tagsInput.value
        .split(/[,，]/)
        .map(s => s.trim())
        .filter(Boolean)
        .slice(0, 10)
    form.remove_media_ids = [...removeMediaIds.value]
    form.transform((data) => ({ ...data, _method: 'patch' })).post(route('listings.update', props.listing.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { editing.value = false },
    })
}

function cancelEdit() {
    form.clearErrors()
    syncForm(props.listing)
    editing.value = false
}

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('ru', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })
}
function categoryPath(category) {
    if (!category) return '—'
    const chain = []
    let c = category
    while (c) { chain.unshift(c.name_ru); c = c.parent }
    return chain.join(' → ')
}

const activePhoto = ref(0)
</script>

<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center gap-2 text-[14px]">
        <Link :href="route('listings.index')" class="text-muted hover:text-blue transition">{{ t('nav.listings') }}</Link>
        <span class="text-muted">/</span>
        <span class="text-ink dark:text-slate-100 max-w-[300px] truncate">{{ listing.title }}</span>
      </div>
    </template>

    <div class="grid gap-5" style="grid-template-columns: 1fr 360px;">
      <div class="space-y-5">
        <div class="rounded-card bg-white shadow-soft dark:bg-dcard p-5">
          <h3 class="text-[15px] font-extrabold text-ink dark:text-slate-100 mb-4">{{ t('listings.media') }}</h3>
          <div v-if="listing.media?.length">
            <div class="rounded-[12px] overflow-hidden bg-surface dark:bg-dbg aspect-video mb-3">
              <img :src="`/storage/${listing.media[activePhoto]?.path}`" class="w-full h-full object-contain" :alt="listing.title" />
            </div>
            <div class="flex gap-2 flex-wrap">
              <button v-for="(m, i) in listing.media" :key="m.id" @click="activePhoto = i"
                class="h-14 w-14 rounded-[7px] overflow-hidden border-2 transition"
                :class="activePhoto === i ? 'border-blue' : 'border-line dark:border-dline'">
                <img :src="`/storage/${m.path}`" class="h-full w-full object-cover" />
              </button>
            </div>
          </div>
          <div v-else class="aspect-video rounded-[12px] bg-surface dark:bg-dbg flex items-center justify-center">
            <svg class="h-10 w-10 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" stroke-width="2"/><circle cx="8.5" cy="8.5" r="1.5" stroke-width="2"/><polyline stroke-width="2" points="21 15 16 10 5 21"/></svg>
          </div>
        </div>

        <div class="rounded-card bg-white shadow-soft dark:bg-dcard p-5">
          <div class="mb-4 flex items-center justify-between gap-3">
            <h3 class="text-[15px] font-extrabold text-ink dark:text-slate-100">{{ t('listings.info') }}</h3>
            <button
              v-if="!editing"
              @click="editing = true; tagsInput = (listing.tags || []).join(', ')"
              class="flex h-[30px] w-[30px] items-center justify-center rounded-[7px] text-muted transition hover:bg-blue hover:text-white"
              :title="t('actions.edit')" :aria-label="t('actions.edit')"
            >
              <Icon kind="pencil" :size="14" />
            </button>
          </div>

          <div v-if="editing" class="space-y-4">
            <div>
              <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('common.title') }}</label>
              <input v-model="form.title" type="text" maxlength="255" class="input" :class="{ 'border-red': form.errors.title }" />
              <p v-if="form.errors.title" class="mt-1 text-[12px] font-semibold text-red">{{ form.errors.title }}</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('common.type') }}</label>
                <select v-model="form.type" class="input">
                  <option value="goods">{{ t('listings.product') }}</option>
                  <option value="services">{{ t('listings.service') }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('common.price') }}</label>
                <input v-model="form.price" type="number" min="0" step="0.01" class="input" :placeholder="t('listings.negotiable')" />
              </div>
            </div>
            <div>
              <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('common.category') }}</label>
              <select v-model="form.category_id" class="input" :class="{ 'border-red': form.errors.category_id }">
                <option v-for="c in categoryOptions" :key="c.id" :value="c.id">{{ c.label }}</option>
              </select>
            </div>
            <div class="grid grid-cols-3 gap-3">
              <div>
                <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('common.region') }}</label>
                <select v-model="form.region_id" class="input" @change="onRegionChange">
                  <option v-for="r in regions || []" :key="r.id" :value="r.id">{{ r.name_ru || r.name_tk }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('common.city') }}</label>
                <select v-model="form.city_id" class="input" @change="onCityChange">
                  <option v-for="c in cityOptions" :key="c.id" :value="c.id">{{ c.name_ru || c.name_tk }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('common.district') }}</label>
                <select v-model="form.district_id" class="input">
                  <option :value="null">—</option>
                  <option v-for="d in districtOptions" :key="d.id" :value="d.id">{{ d.name_ru || d.name_tk }}</option>
                </select>
              </div>
            </div>
            <div>
              <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('common.phone') }}</label>
              <input v-model="form.phone" type="text" class="input" :class="{ 'border-red': form.errors.phone }" />
              <p v-if="form.errors.phone" class="mt-1 text-[12px] font-semibold text-red">{{ form.errors.phone }}</p>
            </div>
            <div>
              <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('common.description') }}</label>
              <textarea v-model="form.description" rows="4" maxlength="5000" class="input"></textarea>
            </div>
            <div>
              <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('listings.tagsLabel') }}</label>
              <input v-model="tagsInput" type="text" class="input" :placeholder="t('listings.tagsPlaceholder')" />
            </div>
            <div class="grid grid-cols-3 gap-3">
              <div>
                <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('listings.wholesalePrice') }}</label>
                <input v-model="form.wholesale_price" type="number" min="0" step="0.01" class="input" :class="{ 'border-red': form.errors.wholesale_price }" />
                <p v-if="form.errors.wholesale_price" class="mt-1 text-[12px] font-semibold text-red">{{ form.errors.wholesale_price }}</p>
              </div>
              <div>
                <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('listings.minOrderQty') }}</label>
                <input v-model="form.min_order_qty" type="number" min="1" class="input" />
              </div>
              <div>
                <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('listings.stockQty') }}</label>
                <input v-model="form.stock_qty" type="number" min="0" class="input" />
              </div>
            </div>
            <div>
              <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('listings.photosLabel') }}</label>
              <div class="mb-2 flex flex-wrap gap-2">
                <div
                  v-for="m in listing.media || []"
                  :key="m.id"
                  class="relative h-16 w-16 overflow-hidden rounded-[9px] border"
                  :class="removeMediaIds.includes(m.id) ? 'border-red opacity-40' : 'border-line dark:border-dline'"
                >
                  <img :src="`/storage/${m.path}`" class="h-full w-full object-cover" />
                  <button
                    v-if="!removeMediaIds.includes(m.id)"
                    type="button"
                    class="absolute right-0.5 top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-black/60 text-white text-[10px]"
                    @click="markRemoveMedia(m.id)"
                  >×</button>
                  <button
                    v-else
                    type="button"
                    class="absolute inset-0 flex items-center justify-center bg-black/40 text-[10px] font-bold text-white"
                    @click="unmarkRemoveMedia(m.id)"
                  >↩</button>
                </div>
                <div v-for="(preview, i) in photoPreviews" :key="'new-'+i" class="relative h-16 w-16 overflow-hidden rounded-[9px] border border-line dark:border-dline">
                  <img :src="preview.url" class="h-full w-full object-cover" />
                  <button type="button" class="absolute right-0.5 top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-black/60 text-white text-[10px]" @click="removeNewPhoto(i)">×</button>
                </div>
              </div>
              <label class="inline-flex cursor-pointer items-center gap-2 rounded-[10px] border border-dashed border-[var(--field-border)] px-3.5 py-2 text-[12px] font-semibold text-[var(--text-secondary)]">
                <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="hidden" @change="onPhotosPick" />
                {{ t('listings.addPhotos') }}
              </label>
              <p v-if="form.errors.photos" class="mt-1 text-[12px] font-semibold text-red">{{ form.errors.photos }}</p>
            </div>
            <div class="flex gap-2.5">
              <button @click="cancelEdit" class="flex-1 rounded-btn border-2 border-line py-[9px] text-[13px] font-bold text-muted transition hover:border-blue hover:text-blue dark:border-dline">{{ t('actions.cancel') }}</button>
              <button @click="saveEdit" :disabled="!form.title.trim() || !form.category_id || form.processing" class="flex-1 rounded-btn bg-blue py-[9px] text-[13px] font-bold text-white transition hover:opacity-90 disabled:opacity-40">{{ t('actions.save') }}</button>
            </div>
          </div>

          <template v-else>
            <div class="grid grid-cols-2 gap-4 text-[13px]">
              <div><span class="text-muted font-semibold">{{ t('common.title') }}:</span><br><span class="font-bold text-ink dark:text-slate-200">{{ listing.title }}</span></div>
              <div><span class="text-muted font-semibold">{{ t('common.type') }}:</span><br><span class="font-bold text-ink dark:text-slate-200">{{ listing.type === 'goods' ? t('listings.product') : t('listings.service') }}</span></div>
              <div><span class="text-muted font-semibold">{{ t('common.price') }}:</span><br><span class="font-data font-bold text-ink dark:text-slate-200">{{ listing.price ? Number(listing.price).toLocaleString('ru') + ' TMT' : t('listings.negotiable') }}</span></div>
              <div><span class="text-muted font-semibold">{{ t('common.category') }}:</span><br><span class="font-bold text-ink dark:text-slate-200">{{ categoryPath(listing.category) }}</span></div>
              <div><span class="text-muted font-semibold">{{ t('common.region') }}:</span><br><span class="font-bold text-ink dark:text-slate-200">{{ listing.region?.name_ru || '—' }}, {{ listing.city?.name_ru || '—' }}</span></div>
              <div><span class="text-muted font-semibold">{{ t('common.phone') }}:</span><br><span class="font-data font-bold text-ink dark:text-slate-200">{{ listing.phone }}</span></div>
              <div><span class="text-muted font-semibold">{{ t('common.views') }}:</span><br><span class="font-data font-bold text-ink dark:text-slate-200">{{ listing.views || 0 }}</span></div>
              <div><span class="text-muted font-semibold">{{ t('common.date') }}:</span><br><span class="font-data font-bold text-ink dark:text-slate-200">{{ formatDate(listing.created_at) }}</span></div>
            </div>
            <div v-if="listing.description" class="mt-4 pt-4 border-t border-line dark:border-dline">
              <p class="text-[12px] font-semibold text-muted mb-1">{{ t('common.description') }}:</p>
              <p class="text-[13px] text-ink dark:text-slate-200 whitespace-pre-wrap">{{ listing.description }}</p>
            </div>
            <div v-if="listing.tags?.length" class="mt-3 flex flex-wrap gap-1.5">
              <span v-for="tag in listing.tags" :key="tag" class="rounded-pill bg-blue-light px-2.5 py-0.5 text-[11px] font-bold text-blue">#{{ tag }}</span>
            </div>
          </template>
        </div>
      </div>

      <div class="space-y-4">
        <div class="rounded-card bg-white shadow-soft dark:bg-dcard p-5">
          <h3 class="text-[13px] font-extrabold text-ink dark:text-slate-100 mb-3 uppercase tracking-wide">{{ t('common.author') }}</h3>
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-blue flex items-center justify-center text-[14px] font-extrabold text-white flex-shrink-0">
              {{ (listing.user?.name || listing.user?.phone || '?').charAt(0).toUpperCase() }}
            </div>
            <div>
              <div class="text-[13px] font-bold text-ink dark:text-slate-200">{{ listing.user?.name || '—' }}</div>
              <div class="text-[12px] font-data text-muted">{{ listing.user?.phone }}</div>
            </div>
          </div>
          <div class="mt-3">
            <Link :href="route('users.show', listing.user_id)" class="text-[12px] font-bold text-blue hover:underline">{{ t('listings.profileLink') }}</Link>
          </div>
        </div>

        <div class="rounded-card bg-white shadow-soft dark:bg-dcard p-5">
          <h3 class="text-[13px] font-extrabold text-ink dark:text-slate-100 mb-3 uppercase tracking-wide">{{ t('listings.moderation') }}</h3>
          <div class="mb-3"><StatusBadge :status="listing.status" /></div>
          <div v-if="listing.rejection_reason" class="mb-3 rounded-btn bg-red/10 p-3 text-[12px] font-semibold text-red">
            {{ listing.rejection_reason.name_ru }}
          </div>
          <div class="space-y-2">
            <button v-if="listing.status !== 'approved'" @click="approve"
              class="w-full rounded-btn bg-green/10 border-2 border-green/20 py-[9px] text-[13px] font-bold text-green hover:bg-green hover:text-white transition">
              {{ t('listings.approveBtn') }}
            </button>
            <button @click="showRejectModal = true"
              class="w-full rounded-btn bg-red/10 border-2 border-red/20 py-[9px] text-[13px] font-bold text-red hover:bg-red hover:text-white transition">
              {{ t('listings.rejectBtn') }}
            </button>
            <button @click="boost"
              class="w-full rounded-btn bg-blue/10 border-2 border-blue/20 py-[9px] text-[13px] font-bold text-blue hover:bg-blue hover:text-white transition">
              {{ t('listings.boostBtn') }}
            </button>
            <button v-if="isAdmin" @click="destroy"
              class="w-full rounded-btn bg-red/10 border-2 border-red/20 py-[9px] text-[13px] font-bold text-red hover:bg-red hover:text-white transition">
              {{ t('listings.deleteBtn') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showRejectModal" class="fixed inset-0 z-[600] flex items-center justify-center bg-black/40 backdrop-blur-sm" @click.self="showRejectModal = false">
      <div class="w-[440px] rounded-card bg-white p-6 shadow-[0_24px_48px_rgba(0,0,0,.18)] dark:bg-dcard">
        <h3 class="mb-4 text-[17px] font-extrabold text-ink dark:text-slate-100">{{ t('listings.rejectTitle') }}</h3>
        <div class="space-y-1 mb-5">
          <label v-for="r in rejectionReasons" :key="r.id" class="flex items-center gap-3 cursor-pointer rounded-btn p-3 hover:bg-surface dark:hover:bg-white/5 transition">
            <input type="radio" :value="r.id" v-model="rejectReason" class="accent-blue" />
            <span class="text-[13px] font-semibold text-ink dark:text-slate-200">{{ r.name_ru }}</span>
          </label>
        </div>
        <div class="flex gap-2.5">
          <button @click="showRejectModal = false" class="flex-1 rounded-btn border-2 border-line py-[11px] text-[13px] font-bold text-muted hover:border-blue hover:text-blue transition dark:border-dline">{{ t('actions.cancel') }}</button>
          <button @click="doReject" :disabled="!rejectReason" class="flex-1 rounded-btn bg-red py-[11px] text-[13px] font-bold text-white hover:opacity-90 disabled:opacity-40 transition">{{ t('actions.reject') }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
