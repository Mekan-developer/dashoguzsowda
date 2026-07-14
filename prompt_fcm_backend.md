# Задача: Push-уведомления через FCM (Backend)

## Контекст

Мобильное приложение **Sowda** (Flutter) уже реализовало клиентскую часть FCM:

- получение push в foreground / background / terminated;
- отображение уведомлений, когда приложение открыто;
- deep-link по нажатию на уведомление;
- отправка `fcm_token` при входе через `POST /v1/auth/verify`;
- удаление FCM-токена на устройстве при logout (локально);
- переключатель «Уведомления» в настройках (вкл/выкл на устройстве).

Firebase-проект: **`listing-app-a61a7`** (Android + iOS).

**Задача бэкенда** — хранить FCM-токены пользователей, отправлять push через FCM HTTP v1 API и (по требованиям продукта) дать админке возможность рассылки.

Перед началом изучи существующую структуру Laravel-проекта (Sanctum, users, admin panel, jobs/queues) и следуй принятым паттернам.

---

## 1. Что уже есть (не ломать)

### `POST /api/v1/auth/verify`

Тело запроса уже содержит опциональное поле:

```json
{
  "phone": "+99361123456",
  "code": "000000",
  "fcm_token": "dXhY...firebase_token..."
}
```

Мобильное приложение **уже отправляет** `fcm_token` при успешной верификации OTP, если у пользователя включены уведомления и ОС выдала разрешение.

**Ожидание:** бэкенд должен сохранять `fcm_token` и привязывать его к пользователю (после создания/нахождения user по телефону).

---

## 2. Что нужно реализовать на бэкенде

### 2.1 Хранение FCM-токенов

Рекомендуемая модель (адаптируй под свою схему):

| Поле | Тип | Описание |
|------|-----|----------|
| `user_id` | FK | Владелец токена |
| `token` | string, unique | FCM device token |
| `platform` | enum: `android`, `ios` | Платформа (опционально, но полезно) |
| `last_used_at` | timestamp | Последнее обновление |
| `created_at` / `updated_at` | timestamps | |

Правила:

- У одного пользователя может быть **несколько токенов** (несколько устройств).
- При повторной отправке того же `fcm_token` — **upsert** (обновить `user_id`, `last_used_at`).
- При logout — **удалить** токен текущего устройства (см. §2.3).
- При получении ошибки FCM `UNREGISTERED` / `INVALID_ARGUMENT` — удалять токен из БД.

### 2.2 Обновление токена без повторного логина (обязательно)

Сейчас мобильное приложение отправляет `fcm_token` **только при `auth/verify`**.

FCM-токен может обновиться **без повторного входа** (переустановка приложения, смена разрешений, refresh от Firebase). Мобильный клиент сохраняет новый токен локально, но **не может отправить его на сервер**, пока нет отдельного эндпоинта.

**Нужен новый эндпоинт:**

```
PUT /api/v1/profile/fcm-token
Authorization: Bearer {sanctum_token}
Content-Type: application/json

{
  "fcm_token": "dXhY...",
  "platform": "android"
}
```

Ответ `200`:

```json
{
  "message": "FCM token updated"
}
```

Поведение:

- `fcm_token: null` или пустая строка → удалить токен для текущего пользователя (или игнорировать — зафиксируй в контракте).
- Требует авторизации (`401` без токена).
- После реализации — сообщи мобильной команде, мы добавим вызов в `FcmService.onTokenRefresh`.

### 2.3 Logout — очистка FCM-токена

`POST /api/v1/auth/logout` уже есть.

Мобильное приложение при logout **удаляет FCM-токен на устройстве** (`FirebaseMessaging.deleteToken()`), но сервер всё равно может продолжать слать push на старый токен, если он остался в БД.

**Ожидание при logout:**

- Принимать опционально `fcm_token` в теле logout **или**
- Удалять все токены пользователя **или**
- Удалять конкретный токен, если передан.

Рекомендуемый вариант (минимальный diff для клиента):

```
POST /api/v1/auth/logout
Authorization: Bearer {sanctum_token}

{
  "fcm_token": "dXhY..."
}
```

Если тело пустое — отзывать только Sanctum-токен (как сейчас). Если `fcm_token` передан — дополнительно удалять его из таблицы device tokens.

### 2.4 Отправка push через FCM

Используй **FCM HTTP v1 API** (не legacy server key).

Рекомендуемые пакеты для Laravel:

- `kreait/laravel-firebase` или
- `laravel-notification-channels/fcm`

Service Account JSON берётся из Firebase Console → Project Settings → Service accounts.

Отправку лучше ставить в **очередь** (Redis/DB queue), не блокировать HTTP-запросы.

---

## 3. Контракт FCM-сообщения (критично для мобильного клиента)

Мобильное приложение обрабатывает **поле `data`** для deep-link. Поля `notification.title` / `notification.body` используются для отображения в системном трее.

### Обязательная структура

```json
{
  "notification": {
    "title": "Объявление одобрено",
    "body": "Ваше объявление «iPhone 14» прошло модерацию"
  },
  "data": {
    "type": "listing",
    "id": "42"
  }
}
```

### Поддерживаемые значения `data.type`

| `type` | Обязательные поля | Куда ведёт приложение |
|--------|-------------------|------------------------|
| `listing` | `id` — ID объявления | `/listings/{id}` |
| `news` | `id` — ID новости | `/news/{id}` |
| `chat` | — | `/chat` |
| `external` | `url` — полный URL | открывает браузер |
| `url` | `url` | то же, что `external` (алиас) |

Если `type` неизвестен или `id` отсутствует для `listing`/`news` — приложение откроет `/home`.

**Все значения в `data` должны быть строками** (требование FCM: `data` payload — только string key/value).

Примеры:

```json
// Одобрение объявления
{ "type": "listing", "id": "42" }

// Новость / реклама
{ "type": "news", "id": "7" }

// Новое сообщение в чате поддержки
{ "type": "chat" }

// Внешняя ссылка
{ "type": "external", "url": "https://example.com/promo" }
```

### Важно для iOS

Для background delivery на iOS в `data` желательно также передавать `content_available: true` (через `apns` payload в FCM v1). Для обычных пользовательских уведомлений достаточно блока `notification` + `data`.

---

## 4. Типы уведомлений (бизнес-требования)

Из ТЗ продукта (`docs/todo.md`):

| Событие | Когда отправлять | Пример `data` |
|---------|------------------|---------------|
| Подтверждение номера | После успешной верификации (опционально) | `{ "type": "chat" }` или без deep-link |
| Одобрение объявления | Listing переходит в `approved` | `{ "type": "listing", "id": "{listing_id}" }` |
| Отклонение объявления | Listing переходит в `rejected` | `{ "type": "listing", "id": "{listing_id}" }` |
| Рекламное уведомление | Рассылка из админки | `{ "type": "news", "id": "..." }` или `external` |
| Системное уведомление | Рассылка из админки | по согласованию |
| Новое сообщение в чате | Админ ответил пользователю | `{ "type": "chat" }` |

**Примечание:** realtime-чат в приложении работает через **Pusher/Reverb** в foreground. FCM для чата нужен в первую очередь, когда приложение в background/закрыто.

---

## 5. Админка (по ТЗ)

В админ-панели нужна возможность отправлять push:

- всем пользователям;
- выбранным пользователям;
- по фильтру (например, регион, активность);
- с переходом на объект системы (`listing`, `news`, `chat`, `external`).

Минимальный UI:

- заголовок, текст;
- тип deep-link (`type` + `id` / `url`);
- выбор аудитории;
- предпросмотр payload;
- лог отправок (успех/ошибка, количество доставленных).

---

## 6. Сервис отправки (псевдокод)

```php
class PushNotificationService
{
    public function sendToUser(User $user, string $title, string $body, array $data): void
    {
        $tokens = $user->fcmTokens()->pluck('token');

        foreach ($tokens as $token) {
            dispatch(new SendFcmNotificationJob(
                token: $token,
                title: $title,
                body: $body,
                data: $this->stringifyData($data), // все значения → string
            ));
        }
    }

    private function stringifyData(array $data): array
    {
        return collect($data)
            ->mapWithKeys(fn ($v, $k) => [(string) $k => (string) $v])
            ->all();
    }
}
```

Обработка ошибок FCM в job:

- `NOT_FOUND`, `UNREGISTERED` → удалить токен из БД;
- остальные — retry с backoff, затем dead-letter / log.

---

## 7. Тестирование

### 7.1 Регистрация токена

1. Войти через `POST /v1/auth/verify` с непустым `fcm_token`.
2. Проверить, что токен сохранён в БД и привязан к `user_id`.

### 7.2 Обновление токена

1. Авторизованный `PUT /v1/profile/fcm-token` с новым токеном.
2. Старый токен не должен оставаться активным (или upsert по unique token).

### 7.3 Logout

1. `POST /v1/auth/logout` с `fcm_token`.
2. Push на этот токен больше не должен уходить.

### 7.4 Deep-link

Отправить тестовый push (Firebase Console или свой API) с payload:

```json
{
  "notification": { "title": "Test", "body": "Tap me" },
  "data": { "type": "listing", "id": "1" }
}
```

На устройстве при нажатии должно открыться объявление `#1`.

### 7.5 Bruno

Добавь/обнови коллекцию в `docs/bruno/`:

- `Auth/Verify_Code.bru` — пример с реальным `fcm_token`;
- `Profile/Update_Fcm_Token.bru` — новый;
- `Auth/Logout.bru` — опциональное тело с `fcm_token`.

---

## 8. Чеклист готовности

- [ ] `fcm_token` сохраняется при `auth/verify`
- [ ] `PUT /v1/profile/fcm-token` реализован и задокументирован
- [ ] `auth/logout` удаляет FCM-токен устройства
- [ ] FCM HTTP v1 настроен (service account, queue)
- [ ] Push при одобрении/отклонении объявления
- [ ] Push при ответе админа в чате (background)
- [ ] Админка: ручная рассылка с deep-link
- [ ] Невалидные токены чистятся автоматически
- [ ] Bruno-документация обновлена

---

## 9. Координация с мобильной командой

После реализации `PUT /v1/profile/fcm-token` и обновления `logout` — сообщи, чтобы мы добавили:

1. вызов `PUT /v1/profile/fcm-token` в `FcmService.onTokenRefresh`;
2. передачу `fcm_token` в `POST /v1/auth/logout`.

Firebase project ID для сверки: **`listing-app-a61a7`**.
