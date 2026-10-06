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
import StatusBadge from '@/Components/StatusBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import Icon from '@/Components/Icon.vue'
import { confirmDialog } from '@/confirm'

const { t } = useI18n()

const props = defineProps({ tariffs: Array })

const drawer    = ref(false)
const editItem  = ref(null)
// duration_days у бесплатного тарифа не существует: он бессрочный
const emptyForm = () => ({ name: '', name_ru: '', name_tk: '', price: 0, listings_limit: 10, videos_limit: 5, boost_limit: 3, duration_days: 30, is_active: true, is_free: false, can_have_store: false, can_see_wholesale: false })
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
    form.value = { name: item.name ?? '', name_ru: item.name_ru, name_tk: item.name_tk, price: Number(item.price ?? 0), listings_limit: item.listings_limit, videos_limit: item.videos_limit, boost_limit: item.boost_limit, duration_days: item.duration_days ?? 30, is_active: item.is_active, is_free: item.is_free, can_have_store: item.can_have_store ?? false, can_see_wholesale: item.can_see_wholesale ?? false }
    errors.value = {}
    drawer.value = true
}
function save() {
    const url    = editItem.value ? route('tariffs.update', editItem.value.id) : route('tariffs.store')
    const method = editItem.value ? 'put' : 'post'
    // Бесплатный тариф бессрочен — срок не отправляем вовсе
    const payload = { ...form.value, duration_days: form.value.is_free ? null : form.value.duration_days }
    router[method](url, payload, {
        onSuccess: () => { drawer.value = false },
        onError: e => { errors.value = e },
    })
}
const canSave = computed(() => form.value.name_ru.trim().length > 0 && form.value.name_tk.trim().length > 0)

function toggle(item) { router.patch(route('tariffs.toggle', item.id)) }
async function destroy(item) {
    if (await confirmDialog(t('tariffs.confirmDelete', { name: item.name_ru }))) router.delete(route('tariffs.destroy', item.id))
}
</script>

<template>
  <AppLayout>
    <template #header>{{ t('nav.tariffs') }}</template>

    <template #description>{{ t('tariffs.description') }}</template>
    <template #actions>
      <CreateButton :label="t('tariffs.addBtn')" @click="openCreate" />
    </template>

    <!-- Карточки рядом — тарифы сравнивают между собой: цена, лимиты, права -->
    <div v-if="tariffs.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
      <article
        v-for="item in tariffs" :key="item.id"
        class="card flex flex-col"
        :class="!item.is_active && !item.is_free ? 'opacity-70' : ''"
      >
        <!-- Название и включённость -->
        <div class="flex items-start justify-between gap-3 border-b border-[var(--card-border)] p-5">
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <h2 class="truncate text-[16px] font-semibold text-[var(--text)]">{{ item.name_ru }}</h2>
              <StatusBadge v-if="item.is_free" status="regular" :label="t('tariffs.free')" />
            </div>
            <div class="truncate text-[12.5px] text-[var(--text-muted)]">{{ item.name_tk }}</div>
          </div>
          <!-- Бесплатный тариф не выключается: на нём все клиенты без платного -->
          <StatusBadge v-if="item.is_free" status="active" :label="t('tariffs.alwaysActive')" />
          <ToggleSwitch v-else :modelValue="item.is_active" @update:modelValue="toggle(item)" :aria-label="t('tariffs.activeLabel')" />
        </div>

        <!-- Цена и срок -->
        <div class="px-5 pt-4">
          <div class="flex items-baseline gap-1.5">
            <span class="font-data text-[26px] font-semibold tabular-nums text-[var(--text)]">{{ Number(item.price).toLocaleString('ru-RU') }}</span>
            <span class="text-[13px] font-medium text-[var(--text-secondary)]">{{ t('tariffRequests.amountUnit') }}</span>
          </div>
          <div class="text-[12.5px] text-[var(--text-muted)]">{{ item.is_free ? t('tariffs.unlimited') : t('tariffs.perPeriod', { n: item.duration_days }) }}</div>
        </div>

        <!-- Лимиты и права -->
        <dl class="mx-5 my-4 divide-y divide-[var(--card-border)] rounded-[8px] border border-[var(--card-border)] text-[13px]">
          <div v-for="row in [
            { label: t('tariffs.limitListings'), value: item.listings_limit },
            { label: t('tariffs.limitVideos'),   value: item.videos_limit },
            { label: t('tariffs.limitBoosts'),   value: item.boost_limit },
          ]" :key="row.label" class="flex items-center justify-between px-3 py-2">
            <dt class="text-[var(--text-secondary)]">{{ row.label }}</dt>
            <dd class="font-data font-semibold tabular-nums text-[var(--text)]">{{ row.value }}</dd>
          </div>
          <div v-for="row in [
            { label: t('tariffs.featureStore'),     on: item.can_have_store },
            { label: t('tariffs.featureWholesale'), on: item.can_see_wholesale },
          ]" :key="row.label" class="flex items-center justify-between px-3 py-2">
            <dt class="text-[var(--text-secondary)]">{{ row.label }}</dt>
            <dd :class="row.on ? 'text-emerald-600 dark:text-emerald-400' : 'text-[var(--text-muted)]'">
              <Icon :kind="row.on ? 'check' : 'close'" :size="15" />
              <span class="sr-only">{{ row.on ? t('common.yes') : t('common.no') }}</span>
            </dd>
          </div>
        </dl>

        <div class="mt-auto flex items-center gap-2 border-t border-[var(--card-border)] px-5 py-3">
          <span class="flex items-center gap-1.5 text-[12.5px] text-[var(--text-muted)]" :title="t('tariffs.subscribers')">
            <Icon kind="users" :size="14" />
            <span class="font-data tabular-nums text-[var(--text-secondary)]">{{ item.users_count }}</span>
          </span>
          <div class="ml-auto flex gap-1.5">
            <button type="button" @click="openEdit(item)" class="btn btn-secondary btn-sm">
              <Icon kind="pencil" :size="14" />{{ t('tariffs.change') }}
            </button>
            <button v-if="!item.is_free" type="button" @click="destroy(item)" class="icon-btn icon-btn-danger" :title="t('actions.delete')" :aria-label="t('actions.delete')">
              <Icon kind="trash" :size="16" />
            </button>
          </div>
        </div>
      </article>
    </div>
    <div v-else class="card">
      <EmptyState icon="coin" :title="t('tariffs.emptyTitle')">
        <CreateButton :label="t('tariffs.addBtn')" @click="openCreate" />
      </EmptyState>
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
      <!-- Цену админ принимает наличными и сверяет с суммой в заявке -->
      <DrawerField :label="t('tariffs.priceLabel')" :error="errors.price">
        <input v-model.number="form.price" type="number" min="0" step="0.01" class="input" />
        <p class="mt-1.5 text-[12px] text-[var(--text-muted)]">{{ t('tariffs.priceHint') }}</p>
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
        <!-- Бесплатный тариф действует бессрочно — срок для него не задаётся -->
        <DrawerField v-if="!form.is_free" :label="t('tariffs.durationDays')" :error="errors.duration_days">
          <input v-model.number="form.duration_days" type="number" min="1" class="input" />
        </DrawerField>
      </div>
      <div class="flex flex-wrap items-center gap-x-6 gap-y-3 pt-1">
        <!-- Бесплатный всегда активен — переключать нечего, сервер всё равно включит -->
        <label v-if="!form.is_free" class="flex items-center gap-2 text-sm font-semibold text-ink dark:text-slate-200">
          <ToggleSwitch v-model="form.is_active" /> {{ t('tariffs.activeLabel') }}
        </label>
        <!-- С бесплатного флаг не снимается, только переносится на другой тариф -->
        <label class="flex items-center gap-2 text-sm font-semibold text-ink dark:text-slate-200">
          <ToggleSwitch v-model="form.is_free" :disabled="!!editItem?.is_free" /> {{ t('tariffs.freeLabel') }}
        </label>
        <label class="flex items-center gap-2 text-sm font-semibold text-ink dark:text-slate-200">
          <ToggleSwitch v-model="form.can_have_store" /> {{ t('tariffs.canHaveStoreLabel') }}
        </label>
        <label class="flex items-center gap-2 text-sm font-semibold text-ink dark:text-slate-200">
          <ToggleSwitch v-model="form.can_see_wholesale" /> {{ t('tariffs.canSeeWholesaleLabel') }}
        </label>
      </div>
      <p v-if="form.is_free" class="text-[12px] text-[var(--text-muted)]">{{ t('tariffs.freeUnlimitedHint') }}</p>
      <p v-if="editItem?.is_free" class="text-[12px] text-[var(--text-muted)]">{{ t('tariffs.freeProtectedHint') }}</p>
      <p v-if="errors.is_free" class="text-[12px] text-red">{{ errors.is_free }}</p>

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
