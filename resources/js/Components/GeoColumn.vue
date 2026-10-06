<script setup>
import { ref, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Icon from '@/Components/Icon.vue'
import SearchInput from '@/Components/SearchInput.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'

const { t } = useI18n()

const props = defineProps({
    title:        { type: String, required: true },
    crumb:        { type: String, default: '' },
    items:        { type: Array, default: () => [] },
    selectedId:   { type: [Number, null], default: null },
    selectable:   { type: Boolean, default: false },
    ready:        { type: Boolean, default: true },
    notReadyText: { type: String, default: '' },
    emptyText:    { type: String, default: '' },
    subtitle:     { type: Function, default: () => '' },
    errors:       { type: Object, default: () => ({}) },
})

const emit = defineEmits(['select', 'create', 'update', 'toggle', 'destroy'])

const fieldClass = 'input'

// Поиск внутри колонки (по обоим языкам)
const query = ref('')
const visibleItems = computed(() => {
    const q = query.value.trim().toLowerCase()
    if (!q) return props.items
    return props.items.filter(i => i.name_ru?.toLowerCase().includes(q) || i.name_tk?.toLowerCase().includes(q))
})

const adding    = ref(false)
const addForm   = ref({ name_ru: '', name_tk: '' })
const editingId = ref(null)
const editForm  = ref({ name_ru: '', name_tk: '' })

function startAdd() {
    if (!props.ready) return
    editingId.value = null
    addForm.value = { name_ru: '', name_tk: '' }
    adding.value = true
}
function submitAdd() {
    if (!addForm.value.name_ru.trim() || !addForm.value.name_tk.trim()) return
    emit('create', { ...addForm.value })
}

function startEdit(item) {
    adding.value = false
    editingId.value = item.id
    editForm.value = { name_ru: item.name_ru, name_tk: item.name_tk }
}
function submitEdit(item) {
    if (!editForm.value.name_ru.trim() || !editForm.value.name_tk.trim()) return
    emit('update', item, { ...editForm.value })
}

function rowClick(item) {
    if (props.selectable && editingId.value !== item.id) emit('select', item)
}

// Parent closes the inline editors after a successful request.
defineExpose({
    closeAdd:  () => { adding.value = false },
    closeEdit: () => { editingId.value = null },
})

// When the parent selection goes away, drop any open editor.
watch(() => props.ready, (r) => { if (!r) { adding.value = false; editingId.value = null } })
</script>

<template>
  <div class="card flex min-h-0 flex-col overflow-hidden">
    <!-- Шапка колонки: название, родитель, добавление -->
    <div class="flex h-[60px] flex-none items-center justify-between gap-2 border-b border-[var(--card-border)] px-4">
      <div class="min-w-0">
        <div class="card-title">{{ title }}</div>
        <div v-if="crumb" class="truncate text-[12px] text-[var(--text-muted)]">{{ crumb }}</div>
      </div>
      <button type="button" class="btn btn-secondary btn-sm" :disabled="!ready" @click="startAdd">
        <Icon kind="plus" :size="15" />{{ t('geo.add') }}
      </button>
    </div>

    <!-- Родитель не выбран -->
    <div v-if="!ready" class="flex flex-1 items-center justify-center">
      <EmptyState compact icon="pin" :title="notReadyText" />
    </div>

    <template v-else>
      <!-- Поиск по колонке — на клиенте -->
      <div v-if="items.length > 6" class="flex-none border-b border-[var(--card-border)] p-3">
        <SearchInput v-model="query" size="sm" :placeholder="t('common.search')" class="!rounded-[8px]" />
      </div>

      <div class="min-h-0 flex-1 divide-y divide-[var(--card-border)] overflow-y-auto">
        <!-- Добавление -->
        <div v-if="adding" class="bg-[var(--nav-hover)] p-4">
          <div class="flex flex-col gap-2">
            <input v-model="addForm.name_ru" :placeholder="t('geo.nameRuPlaceholder')" :aria-label="t('geo.nameRuPlaceholder')" @keyup.enter="submitAdd" :class="fieldClass" />
            <input v-model="addForm.name_tk" :placeholder="t('geo.nameTkPlaceholder')" :aria-label="t('geo.nameTkPlaceholder')" @keyup.enter="submitAdd" :class="fieldClass" />
            <p v-if="errors.name_ru || errors.name_tk" class="text-[12px] font-medium text-red">{{ errors.name_ru || errors.name_tk }}</p>
            <div class="flex justify-end gap-2">
              <button type="button" @click="adding = false" class="btn btn-secondary btn-sm">{{ t('actions.cancel') }}</button>
              <button type="button" @click="submitAdd" class="btn btn-primary btn-sm">{{ t('actions.save') }}</button>
            </div>
          </div>
        </div>

        <!-- Строки -->
        <div
          v-for="item in visibleItems" :key="item.id"
          class="group relative transition-colors duration-150"
          :class="[
            selectable && editingId !== item.id ? 'cursor-pointer' : '',
            selectable && selectedId === item.id ? 'bg-[var(--nav-item-active)]' : (selectable ? 'hover:bg-[var(--nav-hover)]' : ''),
          ]"
          @click="rowClick(item)"
        >
          <span v-if="selectable && selectedId === item.id" class="absolute inset-y-2 left-0 w-[3px] rounded-full bg-[var(--nav-indicator)]"></span>

          <!-- Редактирование -->
          <div v-if="editingId === item.id" class="bg-[var(--nav-hover)] p-4" @click.stop>
            <div class="flex flex-col gap-2">
              <input v-model="editForm.name_ru" :placeholder="t('geo.nameRuPlaceholder')" :aria-label="t('geo.nameRuPlaceholder')" @keyup.enter="submitEdit(item)" :class="fieldClass" />
              <input v-model="editForm.name_tk" :placeholder="t('geo.nameTkPlaceholder')" :aria-label="t('geo.nameTkPlaceholder')" @keyup.enter="submitEdit(item)" :class="fieldClass" />
              <p v-if="errors.name_ru || errors.name_tk" class="text-[12px] font-medium text-red">{{ errors.name_ru || errors.name_tk }}</p>
              <div class="flex justify-end gap-2">
                <button type="button" @click="editingId = null" class="btn btn-secondary btn-sm">{{ t('actions.cancel') }}</button>
                <button type="button" @click="submitEdit(item)" class="btn btn-primary btn-sm">{{ t('actions.save') }}</button>
              </div>
            </div>
          </div>

          <!-- Просмотр -->
          <div v-else class="flex min-h-[60px] items-center gap-2 px-4 py-2.5">
            <div class="min-w-0 flex-1">
              <div class="truncate text-[13.5px] text-[var(--text)]" :class="selectedId === item.id ? 'font-semibold' : 'font-medium'">{{ item.name_ru }}</div>
              <div class="truncate text-[12px] text-[var(--text-muted)]">{{ subtitle(item) }}</div>
            </div>
            <button
              type="button"
              @click.stop="emit('toggle', item)"
              :title="item.is_hidden ? t('actions.show') : t('actions.hide')"
              class="flex-none rounded-full outline-none transition-opacity duration-150 hover:opacity-80 focus-visible:ring-2 focus-visible:ring-[var(--accent)]"
            >
              <StatusBadge :status="item.is_hidden ? 'suspended' : 'active'" :label="item.is_hidden ? t('geo.hidden') : t('geo.shown')" />
            </button>
            <div class="flex flex-none items-center gap-1 opacity-100 transition-opacity duration-150 lg:opacity-0 lg:group-hover:opacity-100 lg:group-focus-within:opacity-100">
              <button type="button" @click.stop="startEdit(item)" :title="t('actions.edit')" :aria-label="t('actions.edit')" class="icon-btn !h-8 !w-8">
                <Icon kind="pencil" :size="15" />
              </button>
              <button type="button" @click.stop="emit('destroy', item)" :title="t('actions.delete')" :aria-label="t('actions.delete')" class="icon-btn icon-btn-danger !h-8 !w-8">
                <Icon kind="trash" :size="15" />
              </button>
            </div>
            <Icon v-if="selectable" kind="chevronDown" :size="14" class="flex-none -rotate-90 text-[var(--text-muted)]" />
          </div>
        </div>

        <!-- Пусто -->
        <EmptyState v-if="!visibleItems.length && !adding" compact :icon="query ? 'search' : 'pin'" :title="query ? t('dataTable.empty') : emptyText" />
      </div>
    </template>
  </div>
</template>
