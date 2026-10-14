# Ролики: воспроизведение через HLS

## Что изменилось

У ролика появилось новое поле `hls` — ссылка на адаптивный HLS-плейлист
(`master.m3u8`). В нём несколько качеств — 360p / 540p / 720p по короткой
стороне кадра (выше исходника не бывает), плеер сам переключается между ними
по скорости сети. Сегменты по 2 секунды — старт быстрее, чем у mp4 целиком.

Поле приходит везде, где отдаётся ролик: `GET /v1/videos`, `GET /v1/videos/my`,
`GET /v1/videos/{id}`, ответы на создание (`POST /v1/videos`,
`POST /v1/videos/upload/{uploadId}/complete`).

```json
{
  "id": 42,
  "title": "Ролик",
  "processing": false,
  "video": "https://dashoguzsowda.com.tm/storage/videos/9b1c.../processed.mp4",
  "hls": "https://dashoguzsowda.com.tm/storage/videos/9b1c.../hls/master.m3u8",
  "preview": "https://dashoguzsowda.com.tm/storage/videos/9b1c.../preview.jpg",
  "duration_seconds": 37
}
```

Совместимость не ломается: `video` остаётся и по-прежнему указывает на mp4.
Старые сборки мобилки продолжают работать без изменений.

## Какую ссылку играть

```
url = hls ?? video
```

| Состояние | `processing` | `hls` | `video` |
|---|---|---|---|
| Готов | `false` | ссылка | `processed.mp4` |
| Ещё обрабатывается (только у автора, в `/my` и сразу после загрузки) | `true` | `null` | загруженный оригинал |
| Нарезка HLS не удалась / ролик загружен до перехода на HLS | `false` | `null` | `processed.mp4` |

- Пока `processing = true`, `video` — это **сырой оригинал** с телефона
  (может быть `.mov`/`.mkv`, сотни мегабайт). Лучше не проигрывать его,
  а показать заглушку «Ролик обрабатывается» и обновить карточку позже.
- `hls = null` при `processing = false` — штатная ситуация, просто играем `video`.
- В публичной ленте `processing: true` бывает редко (модератор одобрил ролик
  раньше, чем закончилась обработка), но проверку стоит делать на всех экранах.

## Что сделать во Flutter

### 1. Плеер

Пакет `video_player` (ExoPlayer на Android, AVPlayer на iOS) играет HLS
из коробки, отдельный пакет не нужен. Ссылка оканчивается на `.m3u8`, поэтому
`formatHint` указывать не нужно.

```dart
class Video {
  final String video;
  final String? hls;
  final bool processing;
  // ...

  /// Что отдавать плееру: HLS, если он уже готов, иначе mp4.
  String get playbackUrl => hls ?? video;

  factory Video.fromJson(Map<String, dynamic> j) => Video(
        video: j['video'] as String,
        hls: j['hls'] as String?,
        processing: j['processing'] as bool? ?? false,
        // ...
      );
}
```

```dart
final controller = VideoPlayerController.networkUrl(
  Uri.parse(video.playbackUrl),
  videoPlayerOptions: VideoPlayerOptions(mixWithOthers: false),
);
await controller.initialize();
await controller.setLooping(true);   // HLS VOD зацикливается так же, как mp4
await controller.play();
```

Если используется `chewie` или `better_player_plus` — они работают поверх
того же движка, достаточно передать `playbackUrl`.

### 2. Лента шортсов

- Держать живыми не больше 3 контроллеров: текущий, следующий
  (предзагрузка `initialize()` без `play()`) и предыдущий. Остальные —
  `dispose()`, иначе Android упирается в лимит аппаратных декодеров.
- Качество выбирать **не нужно** — плеер начинает с низкого и сам поднимается.
  Выбора качества в UI тоже нет: `video_player` не даёт переключать дорожки,
  и для коротких роликов это не нужно.
- Если раньше mp4 кэшировались на диск (`flutter_cache_manager` и т.п.) —
  для HLS этот кэш не применять: плейлист и сегменты — это множество мелких
  файлов. Сервер сам отдаёт сегменты с `Cache-Control: max-age=604800`.
- Постер до старта — `preview` (может быть `null`).
- Просмотр по-прежнему засчитывать через `POST /v1/videos/{id}/view`.

### 3. Ошибка воспроизведения → запасной mp4

Если HLS не стартовал (`controller.value.hasError` после `initialize()`
или исключение), один раз пересоздать контроллер на `video`:

```dart
Future<VideoPlayerController> openVideo(Video v) async {
  final urls = [if (v.hls != null) v.hls!, v.video];
  for (final url in urls) {
    final c = VideoPlayerController.networkUrl(Uri.parse(url));
    try {
      await c.initialize();
      if (!c.value.hasError) return c;
    } catch (_) {}
    await c.dispose();
  }
  throw Exception('video unavailable');
}
```

### 4. Свой ролик после загрузки

После `complete` ролик приходит с `processing: true`, `hls: null`. Обработка
занимает от десятков секунд до нескольких минут. В «Мои ролики»
(`GET /v1/videos/my`) показывать бейдж «Обрабатывается» и перезапрашивать
список при возврате на экран / pull-to-refresh. Поллинг чаще раза в 10–15 сек
не нужен.

## Локальная разработка

Ссылки `hls`/`video`/`preview` строятся из `APP_URL` backend-а. При
`APP_URL=http://localhost:8000` эмулятор/телефон по ним не достучится:

- Android-эмулятор — выставить на бэкенде `APP_URL=http://10.0.2.2:8000`,
  живое устройство — `APP_URL=http://<IP компьютера в сети>:8000`
  (и тот же адрес как базовый URL API в мобилке).
- Для `http://` в dev нужен cleartext:
  - Android — `android:usesCleartextTraffic="true"` в `<application>`
    debug-манифеста (`android/app/src/debug/AndroidManifest.xml`);
  - iOS — `NSAppTransportSecurity → NSAllowsArbitraryLoads = YES` только
    в debug-конфигурации.

  На проде всё по `https`, эти исключения в релиз не попадают.

## Что ещё знать

- Старые ролики получат HLS после того, как на сервере выполнят
  `php artisan videos:build-hls`; до этого у них `hls = null`, играется mp4.
- `.m3u8` отдаётся с `Cache-Control: no-cache`: ролик могут пересобрать,
  плейлист всегда берётся свежий. Ссылка на ролик при этом не меняется.
