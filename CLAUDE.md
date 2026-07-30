# CLAUDE.md — Архитектурное руководство проекта

## О проекте
**Доска объявлений** — мобильная платформа (Flutter) + административная панель (Laravel + Inertia + Vue 3).

| Компонент | Технология |
|---|---|
| Backend | Laravel 11, PHP 8.3 |
| Admin UI | Inertia.js + Vue 3 (`<script setup>`) |
| Mobile API | REST API v1 (потребляет Flutter) |
| База данных | MySQL 8.4 |
| Очереди | Redis + Laravel Horizon |
| WebSockets | Laravel Reverb (только чат) |
| Хранилище | Local disk (`storage/app/public/`) |
| Push | FCM (Firebase Cloud Messaging) |
| Изображения | Intervention Image v3 (WebP) |
| Роли | Spatie Laravel Permission |
| Тесты | Pest |

---

## Роли (Spatie Laravel Permission)

Три роли: `admin`, `manager`, `user`

**admin** — полный доступ ко всей системе.

**manager**:
- Может: модерировать объявления / ролики / отзывы / жалобы, просматривать пользователей, работать в чате, просматривать статистику, управлять новостями (если выдано отдельное право).
- Нельзя: удалять данные, создавать admin/manager, менять роли, изменять критические системные настройки.

**user** — пользователь мобильного приложения (работает только через API).

---

## Архитектурные правила

### Контроллеры — только тонкие
- Принимают запрос → вызывают Service/Action → возвращают ответ.
- НИКОГДА не содержат бизнес-логику, запросы к БД, вызовы Mail, отправку SMS.
- Admin: `App\Http\Controllers\Admin\`
- API: `App\Http\Controllers\Api\V1\`

### Form Requests — обязательны
- ВСЯ валидация только в Form Request классах, никогда в контроллерах.
- Admin: `App\Http\Requests\Admin\`
- API: `App\Http\Requests\Api\V1\`

### Services — бизнес-логика
- Namespace: `App\Services\`
- Один сервис на домен: `ListingService`, `VideoService`, `UserService`, `TariffService`, `ChatService`.
- Сервисы могут вызывать Repository и Actions.
- Сервисы НЕ вызывают Eloquent напрямую — только через Repository.

### Actions — единственное действие
- Namespace: `App\Actions\`
- Один класс = одно конкретное действие.
- Примеры: `CreateListingAction`, `ApproveListingAction`, `SendSmsCodeAction`, `CheckTariffLimitAction`, `BoostListingAction`, `ProcessVideoAction`.

### Repositories — все запросы к БД
- Интерфейс: `App\Repositories\Interfaces\`
- Реализация: `App\Repositories\`
- НЕЛЬЗЯ использовать Eloquent напрямую в контроллерах и сервисах.
- Примеры: `ListingRepository`, `UserRepository`, `CategoryRepository`, `ReelRepository`.

### API Resources — все ответы
- Namespace: `App\Http\Resources\Api\V1\`
- НИКОГДА не возвращать сырые модели — только через Resource.

### Events & Listeners
- Любая отправка SMS → Event → Listener → Action/Job.
- Любой push → Event → Listener → `SendPushNotificationJob`.
- Любые сайд-эффекты (логи, уведомления, интеграции) → через Events.
- `App\Events\`, `App\Listeners\`
- Примеры:
  - `ListingApproved` → `Listeners\SendListingApprovedPush`
  - `ListingRejected` → `Listeners\SendListingRejectedPush`
  - `SmsCodeRequested` → `Listeners\SendSmsCode`

### Observers
- Model-события обрабатываются через Observers.
- `App\Observers\`
- Пример: `ListingObserver` — при создании назначает статус `pending`.

### Jobs & Queues
- `App\Jobs\`
- Драйвер: Redis. Horizon обязателен.
- Очереди: `default`, `notifications`, `media`
- Ключевые Job-ы:
  - `ProcessListingImagesJob` → queue: `media`
  - `ProcessVideoJob` → queue: `media`
  - `SendPushNotificationJob` → queue: `notifications`
- Таблица `failed_jobs` обязательна. Все упавшие Job-ы должны быть ретраевыми.

---

## Структура роутов

```
routes/
  web.php           # Admin (Inertia, сессионная авторизация)
  api/
    v1.php          # Mobile Flutter API (Sanctum tokens)
```

- Mobile API prefix: `/api/v1/...`
- Admin prefix: `/admin/...` (Inertia, не API)
- API контроллеры: `App\Http\Controllers\Api\V1\`
- Admin контроллеры: `App\Http\Controllers\Admin\`

---

## Структура файлов и папок

```
app/
  Actions/
  Events/
  Http/
    Controllers/
      Admin/
      Api/V1/
    Middleware/
    Requests/
      Admin/
      Api/V1/
    Resources/
      Api/V1/
  Jobs/
  Listeners/
  Models/
  Observers/
  Repositories/
    Interfaces/
  Services/

lang/
  tk/
    messages.php
    validation.php
    auth.php
  ru/
    messages.php
    validation.php
    auth.php

resources/
  js/
    Components/
      Admin/
      Shared/
        AppLayout.vue
        NotificationToast.vue
        LanguageSwitcher.vue
        ThemeToggle.vue
    Composables/
    Pages/
      Admin/
        Dashboard/
        Listings/
        Reels/
        Users/
        Categories/
        Regions/
        Tariffs/
        News/
        Complaints/
        Reviews/
        Chat/
        PushNotifications/
        Statistics/
    Stores/
      useAuthStore.js
      useNotificationStore.js
      useLocaleStore.js
      useThemeStore.js
    app.js
  views/
    app.blade.php

storage/
  app/
    public/
      avatars/
      categories/
      listings/
        {listing_id}/
          photos/
            original/
            medium/
            thumb/
      videos/
        listings/
          {listing_id}/
        reels/
          {reel_id}/
      news/
```

---

## Схема базы данных

### users
```
id, phone (unique), name, gender (nullable), birth_date (nullable),
region_id (fk, nullable), city_id (fk, nullable), avatar (nullable),
is_blocked (bool, default false), block_reason (nullable), blocked_at (nullable),
fcm_token (nullable, LEGACY — не используется новой логикой, см. fcm_tokens), created_at, updated_at
```

### fcm_tokens
```
id, user_id (fk), token (unique), platform (android|ios, nullable),
last_used_at (nullable), created_at, updated_at
```
- Несколько токенов на пользователя (мультидевайс). `token` уникален глобально —
  повторная регистрация того же токена под другим пользователем перепривязывает его (upsert по `token`).
- Пишется только через `FcmTokenRepository` (`App\Actions\RegisterFcmTokenAction` / `RemoveFcmTokenAction`).

### sms_codes
```
id, phone, code, expires_at, used_at (nullable), created_at
```

### regions
```
id, name_tk, name_ru, is_active (bool), sort_order (int), created_at, updated_at
```

### cities
```
id, region_id (fk), name_tk, name_ru, is_active (bool), sort_order (int), created_at, updated_at
```

### categories
```
id, parent_id (fk self, nullable), name_tk, name_ru, icon (nullable),
slug, level (1|2|3), is_active (bool), sort_order (int), created_at, updated_at
```
- Максимум 3 уровня вложенности.
- `level` вычисляется при создании и кешируется.

### tariffs
```
id, name_tk, name_ru, listings_limit (int), videos_limit (int),
boost_limit (int), duration_days (int), is_active (bool),
is_default (bool), created_at, updated_at
```
- Только один тариф может быть `is_default = true`.

### user_tariffs
```
id, user_id (fk), tariff_id (fk), starts_at, expires_at, created_at
```
- При регистрации пользователю автоматически назначается дефолтный тариф.

### listings
```
id, user_id (fk), category_id (fk), title, description (nullable),
type (product|service), price (decimal, nullable), phone,
region_id (fk), city_id (fk),
status (pending|approved|rejected, default pending),
rejection_reason_id (fk, nullable),
media_type (photo|video),
views_count (int, default 0),
is_boosted (bool, default false), boosted_at (nullable),
latitude (nullable), longitude (nullable),
created_at, updated_at
```
- Медиатип: либо `photo` (до 8 фото), либо `video` (1 видео). Не оба сразу.

### listing_photos
```
id, listing_id (fk), original_path, medium_path, thumb_path,
sort_order (int), created_at
```

### listing_videos
```
id, listing_id (fk), original_path, processed_path (nullable),
duration_seconds (int), is_processed (bool, default false), created_at
```

### listing_tags
```
id, listing_id (fk), tag (string)
```

### reels
```
id, user_id (fk), title, original_path, processed_path (nullable),
duration_seconds (int), is_processed (bool, default false),
status (pending|approved|rejected, default pending),
rejection_reason_id (fk, nullable),
likes_count (int, default 0),
views_count (int, default 0),
created_at, updated_at
```

### reel_tags
```
id, reel_id (fk), tag (string)
```

### reel_likes
```
id, reel_id (fk), user_id (fk), created_at
UNIQUE(reel_id, user_id)
```

### favorites
```
id, user_id (fk), listing_id (fk), created_at
UNIQUE(user_id, listing_id)
```

### reviews
```
id, user_id (fk), reviewable_type, reviewable_id,
content (text), status (pending|approved|rejected, default pending),
created_at, updated_at
```
- Полиморфная связь: можно прикрепить к объявлению или пользователю.

### complaints
```
id, user_id (fk), listing_id (fk), complaint_reason_id (fk),
status (pending|resolved, default pending),
created_at, updated_at
```

### complaint_reasons
```
id, name_tk, name_ru, is_active (bool), created_at, updated_at
```

### rejection_reasons
```
id, entity_type (listing|reel|review), name_tk, name_ru,
is_active (bool), created_at, updated_at
```

### news
```
id, author_id (fk users), title_tk, title_ru, content_tk (longtext), content_ru (longtext),
type (news|advertisement), link_type (user|listing|null, nullable),
link_id (bigint, nullable), image (nullable),
is_published (bool, default false), published_at (nullable),
created_at, updated_at
```

### messages (чат пользователь ↔ admin)
```
id, user_id (fk), sender_type (user|admin),
content (text), is_read (bool, default false), created_at
```
- Один диалог на пользователя. Нет чатов пользователей между собой.

### push_notification_logs
```
id, title, body, type (phone_confirm|listing_approved|listing_rejected|advertisement|system),
link_type (nullable), link_id (nullable),
target_type (all|selected|filtered), recipients_count (int),
sent_at, created_at
```

---

## Работа с изображениями

- Библиотека: Intervention Image v3
- Формат: все фото → WebP
- Варианты: `thumb` (150×150 crop), `medium` (600×600 fit), `original` (resize max 1200px)
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

---

## SMS-авторизация

- SMS отправляется через **локальный модем/телефон** — без сторонних SMS-сервисов.
- Создать кастомный SMS-драйвер: `App\Services\Sms\LocalModemSmsService`.
- Код хранится в `sms_codes`, TTL — 5 минут.
- После подтверждения: `used_at` проставляется.
- При смене номера — требуется повторное подтверждение.

---

## Лимиты тарифов

- При создании объявления/ролика → `CheckTariffLimitAction` проверяет квоту.
- Если лимит исчерпан → 403 с локализованным сообщением `__('messages.tariff_limit_exceeded')`.
- Подсчёт: считать активные (не удалённые) объявления пользователя за текущий период тарифа.
- `TariffService::getRemainingLimits(User $user): array`

## Поднятие объявлений (boost)

- `listings.is_boosted`, `listings.boosted_at`
- Интервал между поднятиями задаётся в настройках системы (таблица `settings` или конфиг).
- `ListingService::canBoost(Listing $listing): bool`
- Лимит поднятий берётся из тарифа пользователя.

---

## Push-уведомления (FCM)

- FCM HTTP v1 API через `kreait/laravel-firebase`. Service account: `FIREBASE_CREDENTIALS`
  в `.env` (файл `storage/app/firebase/service-account.json`, не коммитится), проект — `FIREBASE_PROJECT_ID`.
- Токены — таблица `fcm_tokens` (несколько на пользователя, мультидевайс). `users.fcm_token` — legacy, не читается и не пишется.
- Регистрация токена: `App\Actions\RegisterFcmTokenAction` (при `auth/verify` и `PUT /api/v1/profile/fcm-token`).
- Удаление токена: `App\Actions\RemoveFcmTokenAction` (при `auth/logout`, если передан `fcm_token`).
- Отправка: `App\Services\PushNotificationService::sendToUser()` / `sendToUsers()` → по одной `SendPushNotificationJob` на каждый токен получателя → queue `notifications`.
- Невалидные токены (`NotFound`/`InvalidArgument` от FCM) чистятся из `fcm_tokens` автоматически в самой job.
- Deep-link payload (`data`, все значения — строки): `{ "type": "listing"|"news"|"chat"|"external"|"url", "id": "123" }` (для `external`/`url` — `"url"` вместо `"id"`). Неизвестный `type` или отсутствующий `id` → мобильный клиент открывает `/home`.
- Типы push-событий: одобрение/отклонение объявления (`ListingApproved`/`ListingRejected` → Listener), ответ администратора в чате (`AdminReplied` → Listener), ручная рассылка из админки (`/admin/push`).

---

## Чат (WebSocket)

- Laravel Reverb — единственный WebSocket провайдер.
- Один диалог на пользователя (user ↔ support).
- Reverb channel: `private-chat.{user_id}`
- Событие: `NewMessageEvent`
- Нет чата между пользователями.

---

## Аутентификация

- **Admin panel**: сессионная (Breeze, web guard)
- **Mobile API**: Laravel Sanctum (Bearer token)
- Breeze уже установлен с Inertia + Vue

---

## Формат ответов API

Одиночный объект:
```json
{
  "data": { ... },
  "message": "Success"
}
```

Список с пагинацией:
```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 20,
    "total": 200
  }
}
```

Ошибка валидации (422):
```json
{
  "message": "...",
  "errors": { "field": ["..."] }
}
```

---

## Локализация

- Laravel: `lang/tk/` и `lang/ru/`
- Все тексты: `__('messages.key')` — никаких хардкодных строк
- Vue: `vue-i18n` + Pinia `useLocaleStore`
- Язык хранится в `localStorage` и синхронизируется с Laravel-сессией
- Language switcher — в каждом layout-е

---

## Frontend (Admin Panel — Inertia + Vue 3)

- Всегда `<script setup>` с Composition API
- Pinia для shared state
- Только Tailwind CSS — никакого кастомного CSS без крайней необходимости
- Dark/Light mode: `dark:` классы, Pinia store + localStorage, класс `dark` на `<html>`
- **Notification Toast**: `useNotificationStore`
  - Позиция: top-right
  - Auto-dismiss: 6 секунд
  - Типы: `success`, `error`, `warning`, `info`
  - Использование: `notificationStore.success('Сохранено!')` / `notificationStore.error('Ошибка!')`
- Каждая Vue-страница: dark mode классы + notification store + i18n
- Каждый Laravel-ответ: локализован через `__()`

---

## Чеклист модуля (для каждой новой фичи)

```
1.  Migration
2.  Model
3.  Repository Interface
4.  Repository Implementation
5.  Service (если нужна бизнес-логика)
6.  Action (если нужно изолированное действие)
7.  Form Request (Admin + API отдельно)
8.  Controller (Admin + API отдельно)
9.  API Resource
10. Observer (если нужны model events)
11. Event + Listener (если есть сайд-эффекты)
12. Job (если есть фоновая обработка)
13. Vue Component / Page (Admin)
14. Pinia Store (если shared state)
15. Pest тесты (Feature + Unit)
16. Lang files (tk + ru)
```

---

## Тесты (Pest)

- Feature тесты: `tests/Feature/`
- Unit тесты: `tests/Unit/`
- Каждый Action и Service — покрыт тестами
- Фабрики для тестовых данных обязательны
- Внешние сервисы (SMS, FCM, FFmpeg) — только mock/fake, никаких реальных вызовов в тестах

---

## Статусы модерации

Применяются к: `listings`, `reels`, `reviews`

```
pending → approved
pending → rejected (с выбором причины из rejection_reasons)
rejected → pending (пользователь исправил и отправил повторно)
```

---

## Ключевые команды

```bash
php artisan horizon              # запуск Horizon
php artisan reverb:start         # запуск WebSocket сервера
php artisan queue:failed         # список упавших Job-ов
php artisan queue:retry all      # ретрай всех упавших Job-ов
php artisan storage:link         # линк публичного хранилища
```

---

## Запрещено

- Eloquent запросы в контроллерах и сервисах напрямую
- Логика в контроллерах (кроме вызова сервиса/экшена)
- `Mail::send()` / SMS / push напрямую — только через Events
- Любой WebSocket провайдер кроме Reverb
- Хардкодные строки в UI — только через `__()` и `vue-i18n`
- Загрузка видеофайла целиком в память — только StreamedResponse
- Прямая запись в `fcm_tokens` вне `FcmTokenRepository`

! Na kazhdom session-e answer in russian language
