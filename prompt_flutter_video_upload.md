# Промт: загрузка видео с Flutter (chunked / streaming)

> Скопируй этот файл целиком своему Flutter-разработчику или AI-ассистенту (Cursor/Claude).
> Он самодостаточный: описывает контракт бэкенда и даёт готовый Dart-класс.

---

## Задача

Реализовать в мобильном приложении (Flutter) загрузку видеоролика **любого размера** на
бэкенд через **частичную (chunked) загрузку**. Файл режется на части на клиенте и отправляется
по одной части за запрос — это обходит лимит сервера на размер одного запроса (100 МБ) и
позволяет грузить большие файлы, показывая прогресс.

Один ролик = одно видео (не фото). Максимальная длительность — **60 секунд** (проверяется
на сервере, желательно проверить и на клиенте до загрузки). После загрузки ролик уходит на
модерацию (`status = pending`) и сжимается на сервере в фоне.

---

## Общие правила для всех запросов

- **Base URL** (пример dev): `http://192.168.31.64:8000/api/v1` — вынеси в конфиг.
- **Авторизация**: заголовок `Authorization: Bearer <token>`. Токен берётся из SMS-авторизации
  (Sanctum), тот же, что для остальных приватных запросов.
- **Обязательно** слать `Accept: application/json` — иначе на ошибках сервер может вернуть HTML.
- **Язык ошибок**: заголовок `Accept-Language: ru` или `tk`. Сервер вернёт локализованный текст
  в поле `message` — его можно показывать пользователю напрямую.
- Формат ответа везде: `{ "data": {...}, "message": "..." }`.

---

## Ограничения (валидируются сервером)

| Параметр | Значение |
|---|---|
| Макс. длительность | 60 секунд |
| Макс. размер файла | 1 ГБ (`max_bytes` приходит в ответе `init`) |
| Размер части (chunk) | рекомендованный приходит в `chunk_size` из `init` (по умолчанию 5 МБ) |
| Форматы (контейнер) | `mp4`, `mov`, `webm`, `mkv`, `3gp`, `m4v` |
| Название (`title`) | обязательно, ≤ 255 символов |
| Теги (`tags`) | опционально, до 10 штук, каждый ≤ 30 символов |

---

## Контракт API — 3 шага

### Шаг 1. Открыть сессию загрузки

```
POST /videos/upload/init
Content-Type: application/json
```

Тело:
```json
{
  "title": "Название ролика",
  "tags": ["Недвижимость", "Аренда"],
  "filename": "reel.mp4",
  "total_size": 34567890
}
```
- `filename` нужен, чтобы сервер понял контейнер (расширение). Отправляй реальное имя файла.
- `total_size` (байты) — опционально, только подсказка для прогресса.

Ответ `201`:
```json
{
  "data": {
    "upload_id": "9b1c…-uuid",
    "chunk_size": 5242880,
    "max_bytes": 1073741824
  },
  "message": "Success"
}
```
- `upload_id` — подставляй в шаги 2 и 3.
- `chunk_size` — **используй именно это значение** как размер части.

**Важно**: `init` сразу проверяет квоту тарифа (лимит роликов). Если лимит исчерпан — вернётся
`403` ещё до загрузки байтов. Обработай это.

---

### Шаг 2. Отправить части (повторять для каждой части)

```
POST /videos/upload/{upload_id}/chunk?index=N
Content-Type: application/octet-stream
```

- Тело запроса — **сырые байты части файла** (НЕ multipart, НЕ base64, НЕ JSON). Просто `Uint8List`.
- `index` — порядковый номер части, **начиная с 0**, строго по порядку: `0, 1, 2, …`.
- Части нужно слать **последовательно** (дождаться ответа перед отправкой следующей).
  Параллельная отправка сломает порядок.

Ответ `200`:
```json
{
  "data": { "upload_id": "9b1c…", "bytes_received": 5242880, "chunks_received": 1 },
  "message": "Success"
}
```
- `bytes_received` — сколько всего байт принято → используй для прогресс-бара (`bytes_received / total_size`).

---

### Шаг 3. Завершить загрузку

```
POST /videos/upload/{upload_id}/complete
```
(тело не нужно)

Сервер собирает файл, проверяет через ffprobe (валидность + ≤ 60 сек), создаёт ролик и ставит
сжатие в очередь.

Ответ `201`:
```json
{
  "data": {
    "id": 42,
    "title": "Название ролика",
    "tags": ["Недвижимость", "Аренда"],
    "status": "pending",
    "duration_seconds": 24,
    "likes_count": 0,
    "views": 0,
    "processing": true,
    "video": "http://…/storage/videos/uuid/original.mp4",
    "preview": null,
    "created_at": "2026-07-16T10:00:00+00:00"
  },
  "message": "Ролик отправлен на модерацию"
}
```
- `processing: true` — сжатая версия и превью ещё не готовы. `video` пока указывает на оригинал,
  `preview` = `null`. Чтобы узнать, когда обработка закончилась, опрашивай
  `GET /videos/{id}` или `GET /videos/my` — когда `processing` станет `false`, появится `preview`.

---

### (Опц.) Отменить незавершённую загрузку

```
DELETE /videos/upload/{upload_id}
```
Освобождает временный файл на сервере. Вызывай, если пользователь отменил загрузку.
Незавершённые сессии сервер и так сам чистит через 24 часа.

---

## Таблица ошибок

| HTTP | Когда | Что показать / сделать |
|---|---|---|
| `401` | нет/просроченный токен | разлогинить, отправить на вход |
| `403` | заблокированный пользователь **или** исчерпана квота тарифа **или** чужая сессия | показать `message` |
| `404` | сессия не найдена/истекла (24ч) | начать загрузку заново с `init` |
| `422` | `title` не заполнен; части не по порядку; файл битый; длиннее 60 сек; файл не догружен | показать `message` |
| `503` | на сервере не установлен ffprobe/ffmpeg | «Загрузка временно недоступна», повторить позже |

Во всех ошибках показывай `message` из тела ответа (он уже локализован под `Accept-Language`).

---

## Алгоритм клиента

1. (Рекомендуется) Проверить длительность выбранного видео ≤ 60 сек локально (пакет
   `video_player`/`video_compress`) — мгновенный фидбэк, не гоняем зря байты.
2. `init` → получить `upload_id` и `chunk_size`.
3. Открыть файл на чтение и **читать по `chunk_size` байт** (через `RandomAccessFile`, не грузить
   весь файл в память), отправляя каждую часть с `index = 0, 1, 2, …` последовательно. Обновлять
   прогресс по `bytes_received`.
4. После последней части — `complete`.
5. Показать «Ролик на модерации». Опционально опрашивать статус до `processing: false`.

**Ретраи**: сеть на телефоне ненадёжна. При сетевой ошибке на части — повтори тот же `index`
2–3 раза с паузой. Если повтор вернул `422` с `video_chunk_out_of_order` — значит часть на самом
деле уже была принята (потерялся ответ): считай её принятой и переходи к следующему `index`.

---

## Готовый Dart-класс (пакет `http`)

```dart
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';
import 'package:http/http.dart' as http;

class VideoUploadException implements Exception {
  final int statusCode;
  final String message;
  VideoUploadException(this.statusCode, this.message);
  @override
  String toString() => 'VideoUploadException($statusCode): $message';
}

class VideoUploader {
  final String apiBase; // например http://192.168.31.64:8000/api/v1
  final String token;   // Bearer-токен из SMS-авторизации
  final String locale;  // 'ru' или 'tk'

  VideoUploader({required this.apiBase, required this.token, this.locale = 'ru'});

  Map<String, String> get _auth => {
        'Accept': 'application/json',
        'Accept-Language': locale,
        'Authorization': 'Bearer $token',
      };

  /// Полный цикл загрузки. Возвращает объект ролика (data).
  /// onProgress: 0.0..1.0
  Future<Map<String, dynamic>> upload({
    required File file,
    required String title,
    List<String> tags = const [],
    void Function(double progress)? onProgress,
  }) async {
    final totalSize = await file.length();

    // 1) init
    final init = await _init(file, title, tags, totalSize);
    final uploadId = init['upload_id'] as String;
    final chunkSize = (init['chunk_size'] as num).toInt();

    // 2) chunks — потоковое чтение, последовательная отправка
    final raf = await file.open();
    try {
      int index = 0;
      int sent = 0;
      while (sent < totalSize) {
        final toRead = (totalSize - sent) < chunkSize ? (totalSize - sent) : chunkSize;
        final Uint8List bytes = await raf.read(toRead);
        await _sendChunk(uploadId, index, bytes);
        sent += bytes.length;
        index += 1;
        onProgress?.call(sent / totalSize);
      }
    } finally {
      await raf.close();
    }

    // 3) complete
    return await _complete(uploadId);
  }

  Future<Map<String, dynamic>> _init(
      File file, String title, List<String> tags, int totalSize) async {
    final res = await http.post(
      Uri.parse('$apiBase/videos/upload/init'),
      headers: {..._auth, 'Content-Type': 'application/json'},
      body: jsonEncode({
        'title': title,
        'tags': tags,
        'filename': file.path.split(Platform.pathSeparator).last,
        'total_size': totalSize,
      }),
    );
    final body = jsonDecode(utf8.decode(res.bodyBytes));
    if (res.statusCode != 201) {
      throw VideoUploadException(res.statusCode, body['message'] ?? 'Init failed');
    }
    return Map<String, dynamic>.from(body['data']);
  }

  Future<void> _sendChunk(String uploadId, int index, Uint8List bytes) async {
    const maxRetries = 3;
    for (int attempt = 1;; attempt++) {
      try {
        final res = await http.post(
          Uri.parse('$apiBase/videos/upload/$uploadId/chunk?index=$index'),
          headers: {..._auth, 'Content-Type': 'application/octet-stream'},
          body: bytes,
        );
        if (res.statusCode == 200) return;

        // Часть уже была принята (потерялся ответ на предыдущей попытке) — идём дальше
        if (res.statusCode == 422 && attempt > 1) return;

        final body = jsonDecode(utf8.decode(res.bodyBytes));
        throw VideoUploadException(res.statusCode, body['message'] ?? 'Chunk failed');
      } on VideoUploadException {
        rethrow; // ошибки бэкенда не ретраим
      } catch (_) {
        if (attempt >= maxRetries) rethrow; // сетевые — ретраим тот же index
        await Future.delayed(Duration(seconds: attempt));
      }
    }
  }

  Future<Map<String, dynamic>> _complete(String uploadId) async {
    final res = await http.post(
      Uri.parse('$apiBase/videos/upload/$uploadId/complete'),
      headers: _auth,
    );
    final body = jsonDecode(utf8.decode(res.bodyBytes));
    if (res.statusCode != 201) {
      throw VideoUploadException(res.statusCode, body['message'] ?? 'Complete failed');
    }
    return Map<String, dynamic>.from(body['data']);
  }

  Future<void> abort(String uploadId) async {
    await http.delete(Uri.parse('$apiBase/videos/upload/$uploadId'), headers: _auth);
  }
}
```

Пример вызова:
```dart
final uploader = VideoUploader(apiBase: apiBase, token: token, locale: 'ru');
try {
  final video = await uploader.upload(
    file: File(pickedPath),
    title: 'Сдаётся квартира',
    tags: ['Недвижимость'],
    onProgress: (p) => setState(() => _progress = p),
  );
  // video['status'] == 'pending', video['processing'] == true
} on VideoUploadException catch (e) {
  showError(e.message); // текст уже локализован сервером
}
```

---

## Критерии приёмки

- [ ] Видео > 100 МБ успешно загружается (одиночный `POST /videos` тут не подходит — только chunked).
- [ ] Части читаются потоком (`RandomAccessFile`), приложение не держит весь файл в памяти.
- [ ] Части шлются последовательно, `index` начинается с 0 и не имеет пропусков.
- [ ] Прогресс-бар обновляется по мере отправки частей.
- [ ] Ошибки (`403` квота/блок, `422` длиннее 60 сек, `503`, `404` истёкшая сессия) показывают `message` из ответа.
- [ ] После `complete` ролик виден в `GET /videos/my` со статусом `pending`.
- [ ] При отмене пользователем вызывается `DELETE /videos/upload/{id}`.

---

## Примечание (не для мобильной стороны)

Сервер завершает загрузку и сжимает видео через **ffmpeg/ffprobe**. Если на сервере они не
установлены, `complete` вернёт `503 video_service_unavailable`. Установка на бэкенде:
`sudo apt install -y ffmpeg`.
