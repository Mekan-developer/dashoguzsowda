---
name: media-pipeline
description: Обработка фото и видео в проекте — форматы, размеры, chunked upload, FFmpeg, очередь media. Использовать при работе с загрузкой, конвертацией или отдачей изображений и видео.
---

# Медиа-конвейер

## Работа с изображениями

- Библиотека: Intervention Image v3
- Формат: все фото → WebP
- Варианты: `thumb` (150×150 crop), `medium` (600×600 fit), `original` (resize max 120P0px)
- Путь: `storage/app/public/listings/{listing_id}/photos/`
- Обработка через Job: `ProcessListingImagesJob` → queue `media`
- Максимум 8 фото на объявление
- Аватары: thumb (150×150) + medium (400×400)

## Работа с видео

- Загрузка: **chunked upload** — клиент дробит файл на части, сервер собирает
- Сжатие: FFmpeg через фасад `Process` (в тестах — `Process::fake()`)
- Максимальная длительность: **60 секунд** (проверяется при загрузке)
- Обработка через Job: `ProcessVideoJob` → queue `media` (супервизор `supervisor-media`)
- Результат в папке ролика `videos/{uuid}/`: `processed.mp4` (запасной, поле
  `video`), `hls/master.m3u8` + `{360p,540p,720p}.m3u8` + сегменты `.ts`
  (поле `hls`, нарезка — `App\Services\Video\HlsTranscoder`, лестница
  качеств — `config/video.php` → `hls`), `preview.jpg`. Оригинал удаляется
  после успешной нарезки HLS.
- Отдача — nginx статикой (`docker/nginx/conf.d/nginx.conf`: MIME и кэш для
  `.m3u8`/`.ts`), через PHP видео не идёт. Админка играет через
  `Components/HlsPlayer.vue` (hls.js, на Safari — нативно, запасной — mp4).

## Где смотреть

- Сервисы: `app/Services/ImageConversionService.php`, `app/Services/VideoService.php`, `app/Services/Video/`
- Модели: `app/Models/ListingMedia.php`, `app/Models/Video.php`
- Актуальные поля — в миграциях `database/migrations/`, а не по памяти

Запрет из корневого `CLAUDE.md` действует всегда: видеофайл целиком в память
не загружать — приём потоком, отдача статикой.
