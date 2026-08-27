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

const { t } = useI18n()

const props = defineProps({ tariffs: Array })

const drawer    = ref(false)
const editItem  = ref(null)
const emptyForm = () => ({ name: '', name_ru: '', name_tk: '', listings_limit: 10, videos_limit: 5, boost_limit: 3, duration_days: 30, is_active: true, is_free: false, can_have_store: false })
const form      = ref(emptyForm())
const errors    = ref({})

function openCreate() {
    editItem.value = null
    form.value = emptyForm()
    errors.value = {}
    drawer.value = true
}
function openEdit(item) {
    editItem.value = item
    form.value = { name: item.name ?? '', name_ru: item.name_ru, name_tk: item.name_tk, listings_limit: item.listings_limit, videos_limit: item.videos_limit, boost_limit: item.boost_limit, duration_days: item.duration_days, is_active: item.is_active, is_free: item.is_free, can_have_store: item.can_have_store ?? false }
    errors.value = {}
    drawer.value = true
}
function save() {
    const url    = editItem.value ? route('tariffs.update', editItem.value.id) : route('tariffs.store')
    const method = editItem.value ? 'put' : 'post'
    router[method](url, form.value, {
        onSuccess: () => { drawer.value = false },
        onError: e => { errors.value = e },
    })
}
const canSave = computed(() => form.value.name_ru.trim().length > 0 && form.value.name_tk.trim().length > 0)

function toggle(item) { router.patch(route('tariffs.toggle', item.id)) }
function destroy(item) {
    if (confirm(t('tariffs.confirmDelete', { name: item.name_ru }))) router.delete(route('tariffs.destroy', item.id))
}
</script>

<template>
  <AppLayout>
    <template #header>{{ t('nav.tariffs') }}</template>

    <div class="space-y-4">
      <div class="flex justify-end">
        <CreateButton :label="t('tariffs.addBtn')" @click="openCreate" />
      </div>

      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="item in tariffs" :key="item.id"
          class="rounded-card bg-white dark:bg-dcard border border-line dark:border-dline p-5 flex flex-col gap-3"
        >
          <div class="flex items-start justify-between">
            <div>
              <div class="font-extrabold text-ink dark:text-slate-100 text-base">{{ item.name_ru }}</div>
              <div class="text-xs text-muted">{{ item.name_tk }}</div>
            </div>
            <ToggleSwitch :modelValue="item.is_active" @update:modelValue="toggle(item)" />
          </div>

          <div class="grid grid-cols-3 gap-2 text-center">
            <div class="rounded-[8px] bg-surface dark:bg-dbg p-2">
              <div class="text-lg font-extrabold text-blue">{{ item.listings_limit }}</div>
              <div class="text-[10px] text-muted">{{ t('tariffs.unitListings') }}</div>
            </div>
            <div class="rounded-[8px] bg-surface dark:bg-dbg p-2">
              <div class="text-lg font-extrabold text-blue">{{ item.videos_limit }}</div>
              <div class="text-[10px] text-muted">{{ t('tariffs.unitVideos') }}</div>
            </div>
            <div class="rounded-[8px] bg-surface dark:bg-dbg p-2">
              <div class="text-lg font-extrabold text-blue">{{ item.boost_limit }}</div>
              <div class="text-[10px] text-muted">{{ t('tariffs.unitBoosts') }}</div>
            </div>
          </div>

          <div class="flex items-center gap-2 text-xs text-muted">
            <span>{{ item.duration_days }} {{ t('tariffs.days') }}</span>
            <span v-if="item.is_free" class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 font-semibold">{{ t('tariffs.free') }}</span>
            <span class="ml-auto text-[11px]">{{ item.users_count }} {{ t('tariffs.usersCount') }}</span>
          </div>

          <div class="flex gap-2 pt-1">
            <button @click="openEdit(item)" class="flex-1 py-1.5 rounded-btn border border-line dark:border-dline text-xs font-bold text-ink dark:text-slate-200 hover:bg-surface dark:hover:bg-white/5 transition">
              {{ t('tariffs.change') }}
            </button>
            <button @click="destroy(item)" class="px-3 py-1.5 rounded-btn border border-red/30 text-xs font-bold text-red hover:bg-red/5 transition">
              {{ t('actions.delete') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <AppDrawer :open="drawer" :title="editItem ? t('tariffs.editTitle') : t('tariffs.newTitle')" @close="drawer = false">
      <DrawerField :label="t('tariffs.nameRu')" :error="errors.name_ru">
        <input v-model="form.name_ru" class="input" />
      </DrawerField>
      <DrawerField :label="t('tariffs.nameTk')" :error="errors.name_tk">
        <input v-model="form.name_tk" class="input" />
      </DrawerField>
      <DrawerField :label="t('tariffs.mobileSlug')" :error="errors.name">
        <input v-model="form.name" class="input" :placeholder="t('tariffs.mobileSlugPlaceholder')" />
      </DrawerField>
      <div class="grid grid-cols-2 gap-3">
        <DrawerField :label="t('tariffs.limitListings')" :error="errors.listings_limit">
          <input v-model.number="form.listings_limit" type="number" min="0" class="input" />
        </DrawerField>
        <DrawerField :label="t('tariffs.limitVideos')" :error="errors.videos_limit">
          <input v-model.number="form.videos_limit" type="number" min="0" class="input" />
        </DrawerField>
        <DrawerField :label="t('tariffs.limitBoosts')" :error="errors.boost_limit">
          <input v-model.number="form.boost_limit" type="number" min="0" class="input" />
        </DrawerField>
        <DrawerField :label="t('tariffs.durationDays')" :error="errors.duration_days">
          <input v-model.number="form.duration_days" type="number" min="1" class="input" />
        </DrawerField>
      </div>
      <div class="flex items-center gap-6 pt-1">
        <label class="flex items-center gap-2 text-sm font-semibold text-ink dark:text-slate-200">
          <ToggleSwitch v-model="form.is_active" /> {{ t('tariffs.activeLabel') }}
        </label>
        <label class="flex items-center gap-2 text-sm font-semibold text-ink dark:text-slate-200">
          <ToggleSwitch v-model="form.is_free" /> {{ t('tariffs.freeLabel') }}
        </label>
        <label class="flex items-center gap-2 text-sm font-semibold text-ink dark:text-slate-200">
          <ToggleSwitch v-model="form.can_have_store" /> {{ t('tariffs.canHaveStoreLabel') }}
        </label>
      </div>

      <template #footer>
        <DrawerFooter
          :can-save="canSave"
          :save-label="editItem ? t('actions.save') : t('actions.create')"
          @cancel="drawer = false"
          @save="save"
        />
      </template>
    </AppDrawer>
  </AppLayout>
</template>
