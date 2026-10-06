<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Icon from '@/Components/Icon.vue'
import AppDrawer from '@/Components/AppDrawer.vue'
import DrawerField from '@/Components/DrawerField.vue'
import DrawerFooter from '@/Components/DrawerFooter.vue'
import UserSearchSelect from '@/Components/UserSearchSelect.vue'

const { t } = useI18n()

const props = defineProps({
    open: { type: Boolean, default: false },
    // { categories, regions } — приходят при открытии (Inertia optional prop)
    data: { type: Object, default: null },
})
const emit = defineEmits(['close'])

const MAX_PHOTOS = 8

const form = useForm({
    user_id: null,
    title: '',
    description: '',
    type: 'goods',
    category_id: '',
    region_id: '',
    city_id: '',
    district_id: '',
    price: '',
    phone: '',
    tags: [],
    wholesale_price: '',
    min_order_qty: '',
    stock_qty: '',
    photos: [],
})

// ---- Категория: только листья дерева, путём «Родитель → Ребёнок» ----
function flattenLeaves(nodes, path = [], acc = []) {
    for (const node of nodes || []) {
        const next = [...path, node.name_ru || node.name_tk]
        if (node.children?.length) flattenLeaves(node.children, next, acc)
        else acc.push({ id: node.id, label: next.join(' → ') })
    }
    return acc
}
const categoryOptions = computed(() => flattenLeaves(props.data?.categories))

// ---- Локация: Велаят → Город → Район, сброс детей при смене родителя ----
const regions   = computed(() => props.data?.regions || [])
const cities    = computed(() => regions.value.find(r => r.id === form.region_id)?.cities || [])
const districts = computed(() => cities.value.find(c => c.id === form.city_id)?.districts || [])

watch(() => form.region_id, () => { form.city_id = ''; form.district_id = '' })
watch(() => form.city_id,   () => { form.district_id = '' })

// ---- Фото ----
const previews    = ref([])
const photoInput  = ref(null)
const photoError  = ref('')

function addPhotos(files) {
    photoError.value = ''
    for (const file of Array.from(files || [])) {
        if (form.photos.length >= MAX_PHOTOS) { photoError.value = t('listings.photosHint'); break }
        if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { photoError.value = t('userModal.onlyImage'); continue }
        form.photos.push(file)
        previews.value.push(URL.createObjectURL(file))
    }
}
function removePhoto(index) {
    URL.revokeObjectURL(previews.value[index])
    previews.value.splice(index, 1)
    form.photos.splice(index, 1)
}

// ---- Теги ----
const tagsInput = ref('')

// ---- Submit ----
const canSubmit = computed(() =>
    !form.processing
    && !!form.user_id
    && form.title.trim() !== ''
    && form.description.trim() !== ''
    && !!form.category_id
    && !!form.region_id
    && !!form.city_id
    && form.photos.length > 0)

function submit() {
    if (!canSubmit.value) return
    form.tags = tagsInput.value.split(/[,，]/).map(s => s.trim()).filter(Boolean).slice(0, 10)
    form.post(route('listings.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => emit('close'),
    })
}

// Каждое открытие — чистая форма
watch(() => props.open, (open) => {
    if (!open) return
    form.reset()
    form.clearErrors()
    tagsInput.value = ''
    photoError.value = ''
    previews.value.forEach(url => URL.revokeObjectURL(url))
    previews.value = []
})
</script>

<template>
  <AppDrawer :open="open" :title="t('listings.createTitle')" width="600px" @close="$emit('close')">
    <div v-if="!data" class="py-10 text-center text-[13px] text-[var(--text-muted)]">{{ t('common.loading') }}</div>

    <template v-else>
      <DrawerField :label="t('users.ownerLabel')" required>
        <UserSearchSelect v-model="form.user_id" :error="form.errors.user_id" />
      </DrawerField>

      <DrawerField :label="t('common.title')" :error="form.errors.title" required>
        <input v-model="form.title" type="text" maxlength="255" class="input" />
      </DrawerField>

      <DrawerField :label="t('common.description')" :error="form.errors.description" required>
        <textarea v-model="form.description" rows="4" maxlength="5000" class="input resize-y"></textarea>
      </DrawerField>

      <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)] gap-3.5">
        <DrawerField :label="t('common.type')" :error="form.errors.type" required>
          <!-- Товар / услуга — сегментами, как пол в панели пользователя -->
          <div class="flex rounded-[8px] p-1" :style="{ background: 'var(--field-bg)', border: '1px solid var(--field-border)' }">
            <button
              v-for="opt in [{ value: 'goods', label: t('listings.product') }, { value: 'services', label: t('listings.service') }]"
              :key="opt.value" type="button"
              class="flex-1 rounded-[7px] py-[7px] text-[12.5px] font-semibold transition"
              :style="form.type === opt.value ? { background: 'var(--accent)', color: '#fff' } : { color: 'var(--text-secondary)' }"
              @click="form.type = opt.value"
            >{{ opt.label }}</button>
          </div>
        </DrawerField>
        <DrawerField :label="t('common.category')" :error="form.errors.category_id" required>
          <select v-model="form.category_id" class="input">
            <option value="">—</option>
            <option v-for="c in categoryOptions" :key="c.id" :value="c.id">{{ c.label }}</option>
          </select>
        </DrawerField>
      </div>

      <DrawerField
        :label="t('userModal.location')"
        :error="form.errors.region_id || form.errors.city_id || form.errors.district_id"
        required
      >
        <div class="grid grid-cols-3 gap-2.5">
          <select v-model="form.region_id" class="input">
            <option value="">{{ t('userModal.velayat') }}</option>
            <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name_ru || r.name_tk }}</option>
          </select>
          <select v-model="form.city_id" :disabled="!form.region_id" class="input disabled:cursor-not-allowed disabled:opacity-50">
            <option value="">{{ form.region_id ? t('userModal.cityOption') : t('userModal.firstVelayat') }}</option>
            <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name_ru || c.name_tk }}</option>
          </select>
          <select v-model="form.district_id" :disabled="!form.city_id" class="input disabled:cursor-not-allowed disabled:opacity-50">
            <option value="">{{ form.city_id ? t('userModal.districtOption') : t('userModal.firstCity') }}</option>
            <option v-for="d in districts" :key="d.id" :value="d.id">{{ d.name_ru || d.name_tk }}</option>
          </select>
        </div>
      </DrawerField>

      <div class="grid grid-cols-2 gap-3.5">
        <DrawerField :label="t('common.price')" :error="form.errors.price">
          <input v-model="form.price" type="number" min="0" step="0.01" class="input" :placeholder="t('listings.negotiable')" />
        </DrawerField>
        <DrawerField :label="t('common.phone')" :error="form.errors.phone">
          <input v-model="form.phone" type="tel" class="input" placeholder="+9936XXXXXXX" />
        </DrawerField>
      </div>

      <DrawerField :label="t('listings.tagsLabel')" :error="form.errors.tags">
        <input v-model="tagsInput" type="text" class="input" :placeholder="t('listings.tagsPlaceholder')" />
      </DrawerField>

      <!-- Фото -->
      <DrawerField :label="t('listings.photosLabel')" :error="photoError || form.errors.photos" required>
        <div
          class="grid grid-cols-4 gap-2.5"
          @dragover.prevent
          @drop.prevent="addPhotos($event.dataTransfer.files)"
        >
          <div
            v-for="(url, i) in previews" :key="url"
            class="group relative aspect-square overflow-hidden rounded-[8px] border border-[var(--field-border)]"
          >
            <img :src="url" class="h-full w-full object-cover" alt="" />
            <!-- Первое фото — обложка в ленте -->
            <span v-if="i === 0" class="absolute bottom-1 left-1 rounded-[4px] bg-black/60 px-1.5 py-0.5 text-[11px] font-semibold text-white">1</span>
            <button
              type="button"
              class="absolute right-1 top-1 flex h-6 w-6 items-center justify-center rounded-full bg-black/60 text-white opacity-0 transition group-hover:opacity-100 focus:opacity-100"
              :aria-label="t('actions.delete')"
              @click="removePhoto(i)"
            ><Icon kind="close" :size="12" /></button>
          </div>
          <button
            v-if="form.photos.length < MAX_PHOTOS"
            type="button"
            class="flex aspect-square flex-col items-center justify-center gap-1 rounded-[8px] border-[1.5px] border-dashed border-[var(--field-border)] bg-[var(--field-bg)] text-[var(--text-muted)] transition hover:border-[var(--accent)] hover:text-[var(--accent)]"
            @click="photoInput.click()"
          >
            <Icon kind="plus" :size="18" />
            <span class="text-[12px] font-semibold">{{ form.photos.length }}/{{ MAX_PHOTOS }}</span>
          </button>
        </div>
        <input
          ref="photoInput" type="file" accept="image/jpeg,image/png,image/webp" multiple class="hidden"
          @change="addPhotos($event.target.files); $event.target.value = ''"
        />
        <p class="mt-1.5 text-[11.5px] text-[var(--text-muted)]">{{ t('listings.photosHint') }}</p>
      </DrawerField>

      <!-- Опт и остаток — для товаров магазина, необязательно -->
      <div class="mb-4 mt-6 flex items-center gap-3">
        <span class="h-px flex-1 bg-[var(--field-border)]"></span>
        <span class="text-[11.5px] font-semibold text-[var(--text-muted)]">{{ t('listings.wholesaleDivider') }}</span>
        <span class="h-px flex-1 bg-[var(--field-border)]"></span>
      </div>

      <div class="grid grid-cols-3 gap-3">
        <DrawerField :label="t('listings.wholesalePrice')" :error="form.errors.wholesale_price">
          <input v-model="form.wholesale_price" type="number" min="0" step="0.01" class="input" />
        </DrawerField>
        <DrawerField :label="t('listings.minOrderQty')" :error="form.errors.min_order_qty">
          <input v-model="form.min_order_qty" type="number" min="1" class="input" />
        </DrawerField>
        <DrawerField :label="t('listings.stockQty')" :error="form.errors.stock_qty">
          <input v-model="form.stock_qty" type="number" min="0" class="input" :placeholder="t('listings.stockUnlimited')" />
        </DrawerField>
      </div>
    </template>

    <template #footer>
      <!-- Фото уходят одним запросом — показываем, что идёт загрузка -->
      <div v-if="form.progress" class="mb-3 h-1 overflow-hidden rounded-full bg-[var(--field-bg)]">
        <div class="h-full bg-[var(--accent)] transition-[width] duration-200" :style="{ width: form.progress.percentage + '%' }"></div>
      </div>
      <DrawerFooter
        :can-save="canSubmit"
        :save-label="t('actions.create')"
        @cancel="$emit('close')"
        @save="submit"
      />
    </template>
  </AppDrawer>
</template>
