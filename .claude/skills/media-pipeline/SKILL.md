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
- Сжатие: FFmpeg через shell_exec / процесс
- Максимальная длительность: **60 секунд** (проверяется при загрузке)
- Отдача: **StreamedResponse** — никогда не грузить файл целиком в память
- Обработка через Job: `ProcessVideoJob` → queue `media`
- После обработки → `is_processed = true`, путь записывается в `processed_path`

## Где смотреть

- Сервисы: `app/Services/ImageConversionService.php`, `app/Services/VideoService.php`, `app/Services/Video/`
- Модели: `app/Models/ListingMedia.php`, `app/Models/Video.php`
- Актуальные поля — в миграциях `database/migrations/`, а не по памяти

Запрет из корневого `CLAUDE.md` действует всегда: видеофайл целиком в память
не загружать, только StreamedResponse.
