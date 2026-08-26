# CLAUDE.md — Архитектурное руководство проекта

## О проекте
**Доска объявлений** — мобильная платформа (Flutter) + административная панель (Laravel + Inertia + Vue 3).

Стек смотреть в `composer.json` и `package.json`, схему БД — в `database/migrations/`
и `app/Models/`, структуру страниц админки — в `resources/js/Pages/`.
Здесь только то, чего из кода не видно.

Дополнительные подсказки подгружаются сами, когда нужны:
- `resources/js/CLAUDE.md` — правила фронтенда админки (при работе с `resources/js/**`)
- скилл `new-module` — чеклист создания новой фичи
- скилл `media-pipeline` — обработка фото и видео

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
- Примеры: `ListingRepository`, `UserRepository`, `CategoryRepository`, `VideoRepository`.

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

- Mobile API prefix: `/api/v1/...` → `routes/api/v1.php`, Sanctum-токены
- Admin prefix: `/admin/...` → `routes/web.php`, Inertia + сессия (не API)
- API контроллеры: `App\Http\Controllers\Api\V1\`
- Admin контроллеры: `App\Http\Controllers\Admin\`

---

## SMS-авторизация

- SMS отправляется через **локальный модем/телефон** — без сторонних SMS-сервисов.
- Кастомный SMS-драйвер: `App\Services\Sms\LocalModemSmsService`.
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
- Vue: `vue-i18n`, словари в `resources/js/i18n/` (`tk.js`, `ru.js`)
- Язык хранится в `localStorage` и синхронизируется с Laravel-сессией
- Language switcher — в каждом layout-е

---

## Тесты (Pest)

- Feature тесты: `tests/Feature/`
- Unit тесты: `tests/Unit/`
- Каждый Action и Service — покрыт тестами
- Фабрики для тестовых данных обязательны
- Внешние сервисы (SMS, FCM, FFmpeg) — только mock/fake, никаких реальных вызовов в тестах

---

## Статусы модерации

Применяются к: `listings`, `videos`, `reviews`

```
pending → approved
pending → rejected (с выбором причины из rejection_reasons)
rejected → pending (пользователь исправил и отправил повторно)
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
