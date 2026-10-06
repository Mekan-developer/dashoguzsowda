<script setup>
import { ref, computed, watch, onBeforeUnmount, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * Загрузка изображения с выбором области кропа: показывается ВСЁ изображение
 * целиком (contain), поверх — рамка нужных пропорций (aspect), которую можно
 * таскать по изображению. Файл не режется на клиенте — на сервер уходят
 * crop_x/crop_y (проценты, семантика background-position), финальный кроп
 * делает ImageConversionService. Переиспользуемый: обложки новостей, фото
 * объявлений, изображение категории и т.д.
 */
const props = defineProps({
    modelValue:  File,                              // выбранный файл
    cropX:       { type: Number, default: 50 },
    cropY:       { type: Number, default: 50 },
    existingUrl: String,                            // уже сохранённое изображение (режим редактирования)
    aspect:      { type: Number, default: 16 / 9 },  // пропорции рамки кропа
    maxBytes:    { type: Number, default: 5 * 1024 * 1024 },
    minWidth:    { type: Number, default: 1200 },
    minHeight:   { type: Number, default: 675 },
})
const emit = defineEmits(['update:modelValue', 'update:cropX', 'update:cropY', 'removeExisting'])

const { t } = useI18n()

const PREVIEW_HEIGHT = 340

const fileInput  = ref(null)
const wrapperEl   = ref(null)  // контейнер превью — источник координат для рамки
const imgEl       = ref(null)  // сам <img>, показывается целиком (object-contain)
const objectUrl  = ref(null)
const error      = ref('')
const warning    = ref('')
const hideExisting = ref(false)

// Прямоугольник отрисованного <img> внутри wrapperEl (contain может оставлять поля)
const imgBox = ref({ left: 0, top: 0, width: 0, height: 0 })

const ACCEPTED = ['image/jpeg', 'image/png', 'image/webp']

const previewUrl = computed(() => objectUrl.value || (!hideExisting.value && props.existingUrl) || null)

function updateImgBox() {
    if (!wrapperEl.value || !imgEl.value) return
    const wrapRect = wrapperEl.value.getBoundingClientRect()
    const imgRect  = imgEl.value.getBoundingClientRect()
    if (!imgRect.width || !imgRect.height) return
    imgBox.value = {
        left:   imgRect.left - wrapRect.left,
        top:    imgRect.top - wrapRect.top,
        width:  imgRect.width,
        height: imgRect.height,
    }
}

let resizeObserver = typeof ResizeObserver !== 'undefined' ? new ResizeObserver(() => updateImgBox()) : null

function onImgLoad(e) {
    warning.value = ''
    // Предупреждаем о малом исходнике, но не блокируем (только для нового файла)
    if (objectUrl.value && (e.target.naturalWidth < props.minWidth || e.target.naturalHeight < props.minHeight)) {
        warning.value = t('imageUpload.smallWarning', { w: props.minWidth, h: props.minHeight })
    }
    nextTick(updateImgBox)
}

// Рамка кропа: наибольший прямоугольник заданных пропорций, вписанный в отрисованное изображение
const frameRect = computed(() => {
    const { left, top, width, height } = imgBox.value
    if (!width || !height) return null

    let fw, fh
    if (width / height > props.aspect) {
        fh = height
        fw = fh * props.aspect
    } else {
        fw = width
        fh = fw / props.aspect
    }

    const maxLeft = width - fw
    const maxTop  = height - fh

    return {
        left: left + maxLeft * (props.cropX / 100),
        top:  top + maxTop * (props.cropY / 100),
        width: fw,
        height: fh,
        maxLeft,
        maxTop,
        imgLeft: left,
        imgTop:  top,
    }
})
const hasSlack = computed(() => !!frameRect.value && (frameRect.value.maxLeft > 1 || frameRect.value.maxTop > 1))

watch(() => props.modelValue, file => {
    if (objectUrl.value) URL.revokeObjectURL(objectUrl.value)
    objectUrl.value = file ? URL.createObjectURL(file) : null
})
watch(previewUrl, async url => {
    warning.value = ''
    resizeObserver?.disconnect()
    if (!url) {
        imgBox.value = { left: 0, top: 0, width: 0, height: 0 }
        return
    }
    await nextTick()
    if (wrapperEl.value) resizeObserver?.observe(wrapperEl.value)
}, { immediate: true })
onBeforeUnmount(() => {
    resizeObserver?.disconnect()
    if (objectUrl.value) URL.revokeObjectURL(objectUrl.value)
})

function pick() { fileInput.value?.click() }

function onSelect(e) {
    const file = e.target.files?.[0]
    e.target.value = ''
    if (!file) return
    error.value = ''
    if (!ACCEPTED.includes(file.type)) {
        error.value = t('imageUpload.badFormat')
        return
    }
    if (file.size > props.maxBytes) {
        error.value = t('imageUpload.tooBig', { mb: Math.round(props.maxBytes / 1024 / 1024) })
        return
    }
    emit('update:cropX', 50)
    emit('update:cropY', 50)
    emit('update:modelValue', file)
}

function remove() {
    error.value = ''
    warning.value = ''
    emit('update:cropX', 50)
    emit('update:cropY', 50)
    if (props.modelValue) {
        emit('update:modelValue', null)
    } else if (props.existingUrl) {
        hideExisting.value = true
        emit('removeExisting')
    }
}

// Драг рамки: смещение мыши → позиция рамки → проценты crop_x/crop_y
let drag = null
function onPointerDown(e) {
    if (!hasSlack.value || !frameRect.value) return
    drag = { startX: e.clientX, startY: e.clientY, left: frameRect.value.left, top: frameRect.value.top }
    wrapperEl.value.setPointerCapture(e.pointerId)
}
function onPointerMove(e) {
    if (!drag || !frameRect.value) return
    const f = frameRect.value
    const clamp = (v, min, max) => Math.max(min, Math.min(max, v))

    const rawLeft = drag.left + (e.clientX - drag.startX)
    const rawTop  = drag.top + (e.clientY - drag.startY)
    const left = clamp(rawLeft, f.imgLeft, f.imgLeft + f.maxLeft)
    const top  = clamp(rawTop, f.imgTop, f.imgTop + f.maxTop)

    if (f.maxLeft > 0) emit('update:cropX', (left - f.imgLeft) / f.maxLeft * 100)
    if (f.maxTop > 0) emit('update:cropY', (top - f.imgTop) / f.maxTop * 100)
}
function onPointerUp(e) {
    drag = null
    wrapperEl.value?.releasePointerCapture?.(e.pointerId)
}
</script>

<template>
  <div>
    <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onSelect" />

    <!-- Пустое состояние -->
    <button
      v-if="!previewUrl"
      type="button"
      @click="pick"
      class="flex w-full flex-col items-center justify-center gap-2 rounded-[8px] border-2 border-dashed border-[var(--field-border)] bg-[var(--field-bg)] py-8 text-[var(--text-secondary)] transition-colors hover:border-[var(--accent)] hover:text-[var(--accent)]"
    >
      <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[var(--accent-tint)] text-xl font-bold text-[var(--accent)]">+</span>
      <span class="text-[13px] font-semibold">{{ t('imageUpload.upload') }}</span>
      <span class="text-[12px] text-[var(--text-muted)]">{{ t('imageUpload.formats', { mb: Math.round(maxBytes / 1024 / 1024), w: minWidth, h: minHeight }) }}</span>
    </button>

    <!-- Изображение целиком + перетаскиваемая рамка кропа -->
    <template v-else>
      <div
        ref="wrapperEl"
        class="relative flex w-full select-none items-center justify-center overflow-hidden rounded-[8px] border border-[var(--field-border)] bg-[var(--field-bg)] touch-none"
        :class="hasSlack ? 'cursor-move' : ''"
        :style="{ height: PREVIEW_HEIGHT + 'px' }"
        @pointerdown="onPointerDown"
        @pointermove="onPointerMove"
        @pointerup="onPointerUp"
        @pointercancel="onPointerUp"
      >
        <img
          ref="imgEl"
          :src="previewUrl"
          draggable="false"
          class="pointer-events-none max-h-full max-w-full"
          @load="onImgLoad"
        />
        <div
          v-if="frameRect"
          class="pointer-events-none absolute border-2 border-white"
          :style="{
            left: frameRect.left + 'px',
            top: frameRect.top + 'px',
            width: frameRect.width + 'px',
            height: frameRect.height + 'px',
            boxShadow: '0 0 0 9999px rgba(15,23,42,.55)',
          }"
        ></div>
      </div>
      <p v-if="hasSlack" class="mt-1.5 text-[12px] text-[var(--text-muted)]">{{ t('imageUpload.dragHint') }}</p>

      <div class="mt-2 flex gap-2">
        <button
          type="button" @click="pick"
          class="rounded-[8px] border border-[var(--field-border)] px-3.5 py-[7px] text-[12px] font-semibold text-[var(--text-secondary)] transition-colors hover:bg-[var(--nav-hover)]"
        >{{ t('actions.replace') }}</button>
        <button
          type="button" @click="remove"
          class="rounded-[8px] px-3.5 py-[7px] text-[12px] font-semibold text-red transition-colors hover:bg-red/10"
        >{{ t('actions.delete') }}</button>
      </div>
    </template>

    <p v-if="error" class="mt-1.5 text-[12px] font-semibold text-red">{{ error }}</p>
    <p v-else-if="warning" class="mt-1.5 text-[12px] font-semibold text-orange">{{ warning }}</p>
  </div>
</template>
