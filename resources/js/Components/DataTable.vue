<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Icon from '@/Components/Icon.vue'
import Pagination from '@/Components/Pagination.vue'
import SearchInput from '@/Components/SearchInput.vue'

/**
 * Глобальный компонент таблицы с поиском, фильтром, пагинацией и действиями.
 *
 * Использование:
 * <DataTable
 *   :columns="[
 *     { key: 'image', label: '', width: '40px', type: 'image' },
 *     { key: 'title_ru', label: 'Заголовок', type: 'text' },
 *     { key: 'type', label: 'Тип', type: 'badge', badges: typeMeta },
 *     { key: 'status', label: 'Статус', type: 'status' },
 *   ]"
 *   :items="news.data"
 *   :pagination="news"
 *   :actions="[
 *     { icon: 'eye', title: 'Опубликовать', handler: publish },
 *     { icon: 'pencil', title: 'Редактировать', handler: edit },
 *     { icon: 'trash', title: 'Удалить', handler: delete },
 *   ]"
 *   @search="onSearch"
 *   @filter="onFilter"
 *   @dblclick="onEdit"
 * />
 */

const props = defineProps({
    // Структура колонок
    columns: {
        type: Array,
        required: true,
        // [{ key, label, width, type: 'id'|'text'|'image'|'badge'|'status', badges: {} }]
    },
    // Данные таблицы
    items: {
        type: Array,
        required: true,
    },
    // Объект пагинации (links, data и т.д.)
    pagination: {
        type: Object,
        default: null,
    },
    // Действия в конце каждой строки
    actions: {
        type: Array,
        default: () => [],
        // [{ icon, title, handler, color: 'default'|'red' }]
    },
    // Фильтры (статус, тип и т.д.)
    filters: {
        type: Object,
        default: () => ({}),
    },
    // Поле для поиска
    searchField: {
        type: String,
        default: 'title_ru',
    },
    // Плейсхолдер поиска
    searchPlaceholder: {
        type: String,
        default: '',
    },
    // Сообщение пустого состояния
    emptyMessage: {
        type: String,
        default: '',
    },
    // Показывать встроенную панель поиска/счётчика (выключить, если страница строит свою — поиск/фильтры/счётчик — над таблицей сама)
    showToolbar: {
        type: Boolean,
        default: true,
    },
})

const emit = defineEmits(['search', 'filter', 'dblclick', 'action'])

const { t } = useI18n()

// icon/title действия могут быть функцией от строки — например, глаз/зачёркнутый глаз
// в зависимости от того, опубликована ли конкретная новость
const actionIcon  = (action, item) => typeof action.icon  === 'function' ? action.icon(item)  : action.icon
const actionTitle = (action, item) => typeof action.title === 'function' ? action.title(item) : action.title

const searchQuery = ref(props.filters.search ?? '')

// Фильтрованные данные (поиск на клиенте)
const filtered = computed(() => {
    let result = props.items || []
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase()
        result = result.filter(item => {
            const searchValue = item[props.searchField]
            return searchValue && searchValue.toString().toLowerCase().includes(q)
        })
    }
    return result
})

const onSearch = value => {
    searchQuery.value = value
    emit('search', value)
}

const handleAction = (action, item) => {
    if (action.handler) {
        action.handler(item)
    }
    emit('action', { action: action.key || action.title, item })
}

const renderCell = (item, column) => {
    const value = item[column.key]

    switch (column.type) {
        case 'image':
            return value ? `url(${value.startsWith('/') ? value : `/storage/${value}`})` : null
        case 'badge':
            return column.badges?.[value]
        case 'status':
            return value
        default:
            return value
    }
}

const getCellClass = (column, value) => {
    if (column.type === 'badge' && column.badges) {
        return column.badges[value]?.cls || 'bg-surface dark:bg-dbg text-muted'
    }
    if (column.type === 'status') {
        return value ? 'text-[var(--status-ok)]' : 'text-[var(--text-muted)]'
    }
    return ''
}
</script>

<template>
  <div class="space-y-4">
    <!-- Фильтры и поиск -->
    <div v-if="showToolbar" class="flex gap-3 items-center">
      <!-- Поиск -->
      <SearchInput
        :model-value="searchQuery"
        @update:model-value="onSearch"
        :placeholder="searchPlaceholder || t('dataTable.searchPlaceholder')"
        class="flex-1 max-w-xs"
      />

      <!-- Счётчик -->
      <div class="text-[12px] text-[var(--text-muted)] font-semibold whitespace-nowrap" v-if="items.length">
        {{ t('dataTable.countOf', { shown: filtered.length, total: items.length }) }}
      </div>
    </div>

    <!-- Таблица -->
    <div class="rounded-[12px] bg-white dark:bg-dcard border border-line dark:border-dline overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-surface dark:bg-dbg">
          <tr>
            <th
              v-for="col in columns"
              :key="col.key"
              class="px-4 py-3 text-left text-xs font-extrabold text-muted uppercase"
              :style="col.width ? { width: col.width } : {}"
            >
              {{ col.label }}
            </th>
            <th v-if="actions.length" class="px-3 py-3 w-24"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-line dark:divide-dline">
          <tr
            v-for="item in filtered"
            :key="item.id"
            class="hover:bg-[var(--nav-hover)] transition cursor-pointer"
            @dblclick="emit('dblclick', item)"
          >
            <!-- Ячейки -->
            <td
              v-for="col in columns"
              :key="col.key"
              class="px-4 py-3"
            >
              <!-- Image -->
              <div v-if="col.type === 'image'">
                <div
                  v-if="renderCell(item, col)"
                  class="h-10 w-10 rounded-[9px] bg-cover bg-center flex-shrink-0"
                  :style="{ backgroundImage: renderCell(item, col) }"
                ></div>
                <div v-else class="h-10 w-10 rounded-[9px] bg-[var(--field-bg)] flex items-center justify-center flex-shrink-0">
                  <Icon kind="image" :size="14" class="text-[var(--text-muted)]" />
                </div>
              </div>

              <!-- ID сущности (не порядковый номер строки) -->
              <div v-else-if="col.type === 'id'" class="font-data text-[12px] text-[var(--text-muted)]">
                {{ item[col.key] }}
              </div>

              <!-- Text -->
              <div v-else-if="col.type === 'text'">
                <div class="font-semibold text-[var(--text)] max-w-[280px] truncate">
                  <slot :name="`cell-${col.key}`" :item="item" :value="renderCell(item, col)">{{ renderCell(item, col) }}</slot>
                </div>
              </div>

              <!-- Badge -->
              <div v-else-if="col.type === 'badge'" class="inline-block">
                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold" :class="getCellClass(col, item[col.key])">
                  {{ renderCell(item, col)?.label ?? item[col.key] }}
                </span>
              </div>

              <!-- Status -->
              <div v-else-if="col.type === 'status'" class="flex items-center gap-1.5">
                <div class="w-1.5 h-1.5 rounded-full" :class="item[col.key] ? 'bg-[var(--status-ok)]' : 'bg-[var(--text-muted)]'"></div>
                <span class="text-[11px] font-bold" :class="getCellClass(col, item[col.key])">
                  {{ item[col.key] ? t('status.published') : t('status.draft') }}
                </span>
              </div>

              <!-- Custom slot -->
              <slot v-else :name="`cell-${col.key}`" :item="item" :value="renderCell(item, col)">
                {{ renderCell(item, col) }}
              </slot>
            </td>

            <!-- Действия -->
            <td v-if="actions.length" class="px-3 py-3">
              <div class="flex gap-1 justify-end">
                <button
                  v-for="(action, ai) in actions"
                  :key="action.key || ai"
                  :title="actionTitle(action, item)"
                  :aria-label="actionTitle(action, item)"
                  @click.stop="handleAction(action, item)"
                  class="h-8 w-8 flex items-center justify-center rounded-[8px] transition-colors flex-shrink-0"
                  :class="action.color === 'red'
                    ? 'bg-[var(--field-bg)] text-[var(--text-secondary)] hover:bg-red/20 hover:text-red'
                    : 'bg-[var(--field-bg)] text-[var(--text-secondary)] hover:bg-[var(--nav-hover)]'"
                >
                  <Icon :kind="actionIcon(action, item)" :size="14" />
                </button>
              </div>
            </td>
          </tr>

          <!-- Пустое состояние -->
          <tr v-if="filtered.length === 0">
            <td :colspan="columns.length + (actions.length ? 1 : 0)" class="px-4 py-10 text-center text-[var(--text-muted)] text-sm">
              {{ emptyMessage || t('dataTable.empty') }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Пагинация -->
    <Pagination v-if="pagination?.links" :links="pagination.links" />
  </div>
</template>
