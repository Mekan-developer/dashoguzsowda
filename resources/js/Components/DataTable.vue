<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Icon from '@/Components/Icon.vue'
import Pagination from '@/Components/Pagination.vue'
import SearchInput from '@/Components/SearchInput.vue'
import EmptyState from '@/Components/EmptyState.vue'
import StatusBadge from '@/Components/StatusBadge.vue'

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
        // [{ icon, title, handler, color: 'default'|'red'|'green', visible?: (item) => bool }]
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
    // Пустое состояние: иконка и пояснение под заголовком
    emptyIcon: { type: String, default: 'search' },
    emptyText: { type: String, default: '' },
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
    <div v-if="showToolbar" class="flex flex-wrap items-center gap-2.5">
      <!-- Поиск -->
      <SearchInput
        :model-value="searchQuery"
        @update:model-value="onSearch"
        :placeholder="searchPlaceholder || t('dataTable.searchPlaceholder')"
        class="w-full sm:w-[280px]"
      />

      <!-- Дополнительные фильтры страницы (сегменты, select) — в той же строке -->
      <slot name="toolbar" />

      <!-- Счётчик -->
      <div class="ml-auto whitespace-nowrap text-[12.5px] tabular-nums text-[var(--text-muted)]" v-if="items.length">
        {{ t('dataTable.countOf', { shown: filtered.length, total: items.length }) }}
      </div>
    </div>

    <!-- Таблица -->
    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
      <table class="w-full min-w-[640px]">
        <thead class="border-b border-[var(--card-border)] bg-black/[.015] dark:bg-white/[.02]">
          <tr>
            <th
              v-for="col in columns"
              :key="col.key"
              class="h-11 whitespace-nowrap px-5 text-left text-[11.5px] font-semibold uppercase tracking-[.06em] text-[var(--text-muted)]"
              :style="col.width ? { width: col.width } : {}"
            >
              {{ col.label }}
            </th>
            <th v-if="actions.length" class="h-11 whitespace-nowrap px-5 text-right text-[11.5px] font-semibold uppercase tracking-[.06em] text-[var(--text-muted)]">{{ t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-[var(--card-border)]">
          <tr
            v-for="item in filtered"
            :key="item.id"
            class="cursor-pointer transition-colors duration-150 hover:bg-[var(--nav-hover)]"
            @dblclick="emit('dblclick', item)"
          >
            <!-- Ячейки -->
            <td
              v-for="col in columns"
              :key="col.key"
              class="h-[64px] px-5 py-2.5 text-[13.5px]"
            >
              <!-- Image -->
              <div v-if="col.type === 'image'">
                <div
                  v-if="renderCell(item, col)"
                  class="h-11 w-11 rounded-[8px] border border-[var(--card-border)] bg-cover bg-center flex-shrink-0"
                  :style="{ backgroundImage: renderCell(item, col) }"
                ></div>
                <div v-else class="h-11 w-11 rounded-[8px] border border-[var(--card-border)] bg-[var(--field-bg)] flex items-center justify-center flex-shrink-0">
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
                <span class="inline-flex h-6 items-center rounded-full px-2.5 text-[11.5px] font-semibold" :class="getCellClass(col, item[col.key])">
                  {{ renderCell(item, col)?.label ?? item[col.key] }}
                </span>
              </div>

              <!-- Status -->
              <StatusBadge
                v-else-if="col.type === 'status'"
                :status="item[col.key] ? 'approved' : 'suspended'"
                :label="item[col.key] ? t('status.published') : t('status.draft')"
              />

              <!-- Custom slot -->
              <slot v-else :name="`cell-${col.key}`" :item="item" :value="renderCell(item, col)">
                {{ renderCell(item, col) }}
              </slot>
            </td>

            <!-- Действия -->
            <td v-if="actions.length" class="px-5 py-2.5">
              <div class="flex justify-end gap-1.5">
                <button
                  v-for="(action, ai) in actions.filter(a => !a.visible || a.visible(item))"
                  :key="action.key || ai"
                  type="button"
                  :title="actionTitle(action, item)"
                  :aria-label="actionTitle(action, item)"
                  @click.stop="handleAction(action, item)"
                  class="icon-btn"
                  :class="{ red: 'icon-btn-danger', green: 'icon-btn-success' }[action.color] || ''"
                >
                  <Icon :kind="actionIcon(action, item)" :size="16" />
                </button>
              </div>
            </td>
          </tr>

          <!-- Пустое состояние -->
          <tr v-if="filtered.length === 0">
            <td :colspan="columns.length + (actions.length ? 1 : 0)">
              <EmptyState :icon="emptyIcon" :title="emptyMessage || t('dataTable.empty')" :text="emptyText">
                <slot name="empty-action" />
              </EmptyState>
            </td>
          </tr>
        </tbody>
      </table>
      </div>

      <!-- Пагинация -->
      <Pagination v-if="pagination?.links" :links="pagination.links" :from="pagination.from" :to="pagination.to" :total="pagination.total" />
    </div>
  </div>
</template>
