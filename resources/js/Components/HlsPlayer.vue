<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'

// src — master.m3u8 (может быть null, пока ролик не нарезан);
// fallback — обычный mp4, играет, когда HLS нет или он не поднялся
const props = defineProps({
    src: { type: String, default: null },
    fallback: { type: String, default: null },
    poster: { type: String, default: null },
})

const el = ref(null)
let hls = null
let loadSeq = 0

function destroy() {
    hls?.destroy()
    hls = null
}

async function load() {
    const call = ++loadSeq
    destroy()
    const video = el.value
    if (!video) return

    if (!props.src) {
        video.src = props.fallback || ''
        return
    }

    // Safari/iOS играют HLS сами — hls.js там не нужен
    if (video.canPlayType('application/vnd.apple.mpegurl')) {
        video.src = props.src
        return
    }

    // Библиотека ~400 КБ — грузим только когда на странице есть что играть
    const { default: Hls } = await import('hls.js')
    // Пока грузилась библиотека, src сменился или компонент ушёл со страницы
    if (call !== loadSeq || !el.value) return

    if (!Hls.isSupported()) {
        video.src = props.fallback || ''
        return
    }

    const src = props.src
    hls = new Hls({
        // Ролики до минуты: держать впереди больше не нужно, трафик дороже
        maxBufferLength: 10,
        maxMaxBufferLength: 20,
        // Первое качество — по замеру сети, а не всегда самое низкое
        startLevel: -1,
        capLevelToPlayerSize: true,
    })

    hls.on(Hls.Events.ERROR, (_e, data) => {
        if (!data.fatal) return
        // Плейлист не открылся/сломался — откатываемся на mp4
        destroy()
        if (src === props.src) video.src = props.fallback || ''
    })

    hls.loadSource(src)
    hls.attachMedia(video)
}

onMounted(load)
watch(() => [props.src, props.fallback], load)
onBeforeUnmount(() => {
    loadSeq++
    destroy()
})
</script>

<template>
  <video
    ref="el"
    :poster="poster || undefined"
    controls
    playsinline
    preload="metadata"
  ></video>
</template>
