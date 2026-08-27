<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import CreateButton from '@/Components/CreateButton.vue'

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

const fieldClass = 'w-full rounded-btn border-2 border-line bg-surface py-2 px-3 text-[13px] font-semibold text-ink outline-none focus:border-blue dark:bg-dbg dark:border-dline dark:text-slate-200'

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
  <div class="flex flex-col overflow-hidden rounded-card bg-white shadow-soft dark:bg-dcard">
    <!-- Header + breadcrumb -->
    <div class="flex items-center justify-between gap-2 border-b border-line px-5 py-4 dark:border-dline">
      <div class="min-w-0">
        <span class="text-[15px] font-extrabold text-ink dark:text-slate-100">{{ title }}</span>
        <span v-if="crumb" class="ml-1.5 text-[13px] text-muted">— {{ crumb }}</span>
      </div>
      <CreateButton :label="t('geo.add')" :disabled="!ready" @click="startAdd" />
    </div>

    <!-- Parent not selected -->
    <div v-if="!ready" class="flex h-48 items-center justify-center px-5">
      <p class="text-center text-[13px] text-muted">{{ notReadyText }}</p>
    </div>

    <!-- List -->
    <div v-else class="divide-y divide-line dark:divide-dline">
      <!-- Inline add row -->
      <div v-if="adding" class="bg-blue/5 px-4 py-3 dark:bg-blue/10">
        <div class="flex flex-col gap-2">
          <input v-model="addForm.name_ru" :placeholder="t('geo.nameRuPlaceholder')" @keyup.enter="submitAdd" :class="fieldClass" />
          <input v-model="addForm.name_tk" :placeholder="t('geo.nameTkPlaceholder')" @keyup.enter="submitAdd" :class="fieldClass" />
          <p v-if="errors.name_ru || errors.name_tk" class="text-[11px] font-semibold text-red">{{ errors.name_ru || errors.name_tk }}</p>
          <div class="flex gap-2">
            <button @click="submitAdd" class="rounded-btn bg-blue px-3 py-1.5 text-[12px] font-bold text-white transition hover:opacity-90">{{ t('actions.save') }}</button>
            <button @click="adding = false" class="rounded-btn border-2 border-line px-3 py-1 text-[12px] font-bold text-muted transition hover:border-blue hover:text-blue dark:border-dline">{{ t('actions.cancel') }}</button>
          </div>
        </div>
      </div>

      <!-- Rows -->
      <div
        v-for="item in items" :key="item.id"
        class="transition"
        :class="[
          selectable && editingId !== item.id ? 'cursor-pointer hover:bg-surface dark:hover:bg-white/5' : '',
          selectable && selectedId === item.id ? 'bg-blue/5 dark:bg-blue/10' : '',
        ]"
        @click="rowClick(item)"
      >
        <!-- Inline edit -->
        <div v-if="editingId === item.id" class="bg-blue/5 px-4 py-3 dark:bg-blue/10" @click.stop>
          <div class="flex flex-col gap-2">
            <input v-model="editForm.name_ru" :placeholder="t('geo.nameRuPlaceholder')" @keyup.enter="submitEdit(item)" :class="fieldClass" />
            <input v-model="editForm.name_tk" :placeholder="t('geo.nameTkPlaceholder')" @keyup.enter="submitEdit(item)" :class="fieldClass" />
            <p v-if="errors.name_ru || errors.name_tk" class="text-[11px] font-semibold text-red">{{ errors.name_ru || errors.name_tk }}</p>
            <div class="flex gap-2">
              <button @click="submitEdit(item)" class="rounded-btn bg-blue px-3 py-1.5 text-[12px] font-bold text-white transition hover:opacity-90">{{ t('actions.save') }}</button>
              <button @click="editingId = null" class="rounded-btn border-2 border-line px-3 py-1 text-[12px] font-bold text-muted transition hover:border-blue hover:text-blue dark:border-dline">{{ t('actions.cancel') }}</button>
            </div>
          </div>
        </div>

        <!-- Display -->
        <div v-else class="flex items-center justify-between gap-2 px-5 py-3">
          <div class="min-w-0">
            <div class="truncate text-[13px] font-bold text-ink dark:text-slate-200">{{ item.name_ru }}</div>
            <div class="truncate text-[11px] text-muted">{{ subtitle(item) }}</div>
          </div>
          <div class="flex flex-shrink-0 items-center gap-1.5">
            <button
              @click.stop="emit('toggle', item)"
              :title="item.is_hidden ? t('actions.show') : t('actions.hide')"
              class="rounded-pill px-2 py-0.5 text-[10px] font-bold transition"
              :class="item.is_hidden ? 'bg-muted/10 text-muted hover:bg-muted/20' : 'bg-green/10 text-green hover:bg-green/20'"
            >{{ item.is_hidden ? t('geo.hidden') : t('geo.shown') }}</button>
            <button @click.stop="startEdit(item)" :title="t('actions.edit')" class="flex h-[28px] w-[28px] items-center justify-center rounded-[7px] bg-surface text-muted transition hover:bg-blue hover:text-white dark:bg-dbg">
              <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </button>
            <button @click.stop="emit('destroy', item)" :title="t('actions.delete')" class="flex h-[28px] w-[28px] items-center justify-center rounded-[7px] bg-red/10 text-red transition hover:bg-red hover:text-white">
              <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline stroke-width="2" stroke-linecap="round" points="3 6 5 6 21 6"/><path stroke-width="2" stroke-linecap="round" d="M19 6l-1 14H6L5 6M10 11v6M14 11v6"/></svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Empty -->
      <div v-if="!items.length && !adding" class="px-5 py-8 text-center text-[13px] text-muted">{{ emptyText }}</div>
    </div>
  </div>
</template>
