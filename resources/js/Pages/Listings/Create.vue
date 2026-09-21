<script setup>
import { computed, ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import UserSearchSelect from '@/Components/UserSearchSelect.vue'

const { t } = useI18n()

const props = defineProps({
    categories: Array,
    regions: Array,
})

function flattenLeafCategories(nodes, path = [], acc = []) {
    for (const node of nodes || []) {
        const next = [...path, node.name_ru || node.name_tk]
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
    user_id: null,
    title: '',
    description: '',
    type: 'goods',
    category_id: null,
    region_id: null,
    city_id: null,
    district_id: null,
    price: '',
    phone: '',
    tags: [],
    wholesale_price: '',
    min_order_qty: '',
    stock_qty: '',
    photos: [],
})

const tagsInput = ref('')
const photoPreviews = ref([])

const cityOptions = computed(() =>
    (props.regions || []).find(r => r.id === form.region_id)?.cities || [])
const districtOptions = computed(() =>
    cityOptions.value.find(c => c.id === form.city_id)?.districts || [])

function onRegionChange() { form.city_id = null; form.district_id = null }
function onCityChange() { form.district_id = null }

function onPhotosPick(e) {
    const files = Array.from(e.target.files || [])
    e.target.value = ''
    form.photos.push(...files)
    photoPreviews.value.push(...files.map(f => ({ url: URL.createObjectURL(f) })))
}

function removePhoto(index) {
    URL.revokeObjectURL(photoPreviews.value[index].url)
    photoPreviews.value.splice(index, 1)
    form.photos.splice(index, 1)
}

function parseTags() {
    form.tags = tagsInput.value
        .split(/[,，]/)
        .map(s => s.trim())
        .filter(Boolean)
        .slice(0, 10)
}

function submit() {
    parseTags()
    form.post(route('listings.store'), { forceFormData: true })
}
</script>

<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center gap-2 text-[14px]">
        <Link :href="route('listings.index')" class="text-muted hover:text-blue transition">{{ t('nav.listings') }}</Link>
        <span class="text-muted">/</span>
        <span class="text-ink dark:text-slate-100">{{ t('listings.createTitle') }}</span>
      </div>
    </template>

    <form class="mx-auto max-w-3xl space-y-5" @submit.prevent="submit">
      <div class="rounded-card bg-white p-5 shadow-soft dark:bg-dcard space-y-4">
        <div>
          <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('users.ownerLabel') }} *</label>
          <UserSearchSelect v-model="form.user_id" :error="form.errors.user_id" />
        </div>

        <div>
          <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('common.title') }} *</label>
          <input v-model="form.title" type="text" maxlength="255" class="input" :class="{ 'border-red': form.errors.title }" />
          <p v-if="form.errors.title" class="mt-1 text-[12px] font-semibold text-red">{{ form.errors.title }}</p>
        </div>

        <div>
          <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('common.description') }} *</label>
          <textarea v-model="form.description" rows="4" maxlength="5000" class="input" :class="{ 'border-red': form.errors.description }"></textarea>
          <p v-if="form.errors.description" class="mt-1 text-[12px] font-semibold text-red">{{ form.errors.description }}</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('common.type') }} *</label>
            <select v-model="form.type" class="input">
              <option value="goods">{{ t('listings.product') }}</option>
              <option value="services">{{ t('listings.service') }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('common.category') }} *</label>
            <select v-model="form.category_id" class="input" :class="{ 'border-red': form.errors.category_id }">
              <option :value="null">—</option>
              <option v-for="c in categoryOptions" :key="c.id" :value="c.id">{{ c.label }}</option>
            </select>
            <p v-if="form.errors.category_id" class="mt-1 text-[12px] font-semibold text-red">{{ form.errors.category_id }}</p>
          </div>
        </div>

        <div class="grid grid-cols-3 gap-3">
          <div>
            <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('common.region') }} *</label>
            <select v-model="form.region_id" class="input" @change="onRegionChange">
              <option :value="null">—</option>
              <option v-for="r in regions || []" :key="r.id" :value="r.id">{{ r.name_ru || r.name_tk }}</option>
            </select>
            <p v-if="form.errors.region_id" class="mt-1 text-[12px] font-semibold text-red">{{ form.errors.region_id }}</p>
          </div>
          <div>
            <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('common.city') }} *</label>
            <select v-model="form.city_id" class="input" :disabled="!form.region_id" @change="onCityChange">
              <option :value="null">—</option>
              <option v-for="c in cityOptions" :key="c.id" :value="c.id">{{ c.name_ru || c.name_tk }}</option>
            </select>
            <p v-if="form.errors.city_id" class="mt-1 text-[12px] font-semibold text-red">{{ form.errors.city_id }}</p>
          </div>
          <div>
            <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('common.district') }}</label>
            <select v-model="form.district_id" class="input" :disabled="!form.city_id">
              <option :value="null">—</option>
              <option v-for="d in districtOptions" :key="d.id" :value="d.id">{{ d.name_ru || d.name_tk }}</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('common.price') }}</label>
            <input v-model="form.price" type="number" min="0" step="0.01" class="input" :placeholder="t('listings.negotiable')" />
          </div>
          <div>
            <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('common.phone') }}</label>
            <input v-model="form.phone" type="text" class="input" placeholder="+9936XXXXXXX" :class="{ 'border-red': form.errors.phone }" />
            <p v-if="form.errors.phone" class="mt-1 text-[12px] font-semibold text-red">{{ form.errors.phone }}</p>
          </div>
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
            <input v-model="form.stock_qty" type="number" min="0" class="input" :placeholder="t('listings.stockUnlimited')" />
          </div>
        </div>

        <div>
          <label class="mb-1 block text-[12px] font-semibold text-muted">{{ t('listings.photosLabel') }} *</label>
          <div class="mb-2 flex flex-wrap gap-2">
            <div v-for="(preview, i) in photoPreviews" :key="i" class="relative h-16 w-16 overflow-hidden rounded-[9px] border border-line dark:border-dline">
              <img :src="preview.url" class="h-full w-full object-cover" />
              <button type="button" class="absolute right-0.5 top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-black/60 text-white text-[10px]" @click="removePhoto(i)">×</button>
            </div>
          </div>
          <label class="inline-flex cursor-pointer items-center gap-2 rounded-[10px] border border-dashed border-[var(--field-border)] px-3.5 py-2 text-[12px] font-semibold text-[var(--text-secondary)] hover:border-[var(--accent)] hover:text-[var(--accent)]">
            <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="hidden" @change="onPhotosPick" />
            {{ t('listings.addPhotos') }}
          </label>
          <p v-if="form.errors.photos" class="mt-1 text-[12px] font-semibold text-red">{{ form.errors.photos }}</p>
          <p class="mt-1.5 text-[11px] text-muted">{{ t('listings.photosHint') }}</p>
        </div>
      </div>

      <div class="flex justify-end gap-2">
        <Link :href="route('listings.index')" class="rounded-[10px] border border-[var(--field-border)] px-[18px] py-[10px] text-[13px] font-semibold text-[var(--text-secondary)]">{{ t('actions.cancel') }}</Link>
        <button type="submit" :disabled="form.processing" class="rounded-[10px] bg-[var(--accent)] px-5 py-[10px] text-[13px] font-bold text-white hover:bg-[var(--accent-hover)] disabled:opacity-40">
          {{ t('actions.create') }}
        </button>
      </div>
    </form>
  </AppLayout>
</template>
