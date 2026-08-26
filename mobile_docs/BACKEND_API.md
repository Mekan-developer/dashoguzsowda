# Backend API — спецификация для мобильного приложения Sowda

Документ описывает **новые** эндпоинты, которые мобильное приложение уже вызывает.
После реализации на сервере **изменения в мобильном коде не потребуются**.

**Base URL:** `https://dashoguzsowda.com.tm/api`  
**Префикс:** `/v1/...`  
**Auth:** `Authorization: Bearer {token}` (кроме публичных GET)  
**Locale:** заголовок `Accept-Language: tk` или `ru` (уже используется)

---

## Общий формат ответов

### Один объект
```json
{
  "data": { ... }
}
```

### Список
```json
{
  "data": [ ... ]
}
```

### Пагинация (как у `/v1/listings`)
```json
{
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 20,
    "total": 42
  }
}
```

### Ошибки (Laravel-style, уже используется)
```json
{
  "message": "Validation failed",
  "errors": {
    "field": ["Error text"]
  }
}
```

**Именование полей:** `snake_case`  
**ID:** число или строка (мобилка приводит к string)  
**URL изображений:** абсолютный (`https://...`) или относительный (`/storage/...`) — мобилка резолвит относительные через origin API

---

## 1. Магазины (Stores)

### `GET /v1/stores/popular`

Популярные магазины для главной страницы.

**Auth:** не обязателен  
**Response `data`:** массив объектов Store

```json
{
  "data": [
    {
      "id": 1,
      "name": "Altyn Bazar",
      "subtitle_tk": "Optom",
      "subtitle_ru": "Оптом",
      "logo_url": "/storage/stores/1/logo.jpg",
      "photos": [
        "/storage/stores/1/photo1.jpg",
        "/storage/stores/1/photo2.jpg"
      ]
    }
  ]
}
```

| Поле | Тип | Обязательно | Описание |
|------|-----|-------------|----------|
| `id` | int/string | да | ID магазина |
| `name` | string | да | Название |
| `subtitle_tk` | string | нет | Подзаголовок (категория) на туркменском |
| `subtitle_ru` | string | нет | Подзаголовок на русском |
| `subtitle` | string | нет | Fallback, если нет `_tk`/`_ru` |
| `logo_url` | string | нет | Логотип |
| `photos` | string[] | нет | Галерея (alias: `photo_urls`) |

---

### `GET /v1/stores/{id}`

Карточка магазина.

**Auth:** не обязателен  
**Response `data`:** один объект Store (та же схема, что выше)

**Errors:** `404` если магазин не найден

---

### `GET /v1/stores/{id}/listings`

Объявления магазина.

**Auth:** не обязателен  
**Query params:**

| Param | Тип | Default | Описание |
|-------|-----|---------|----------|
| `page` | int | 1 | Страница |
| `limit` | int | 20 | Размер страницы |

**Response:** пагинированный список — **та же схема Listing**, что в `GET /v1/listings`:

```json
{
  "data": [
    {
      "id": 101,
      "title": "iPhone 15",
      "price": 2500,
      "category_id": 5,
      "category_name_tk": "Elektronika",
      "photos": ["/storage/listings/101/1.jpg"],
      "author_id": 1,
      "author_name": "Altyn Bazar",
      "views": 120,
      "is_premium": true,
      "created_at": "2026-01-15T10:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 2,
    "per_page": 20,
    "total": 25
  }
}
```

---

## 2. Профиль — расширение (Store + Tariff + Stats)

Существующие эндпоинты `GET/PUT /v1/profile` нужно **расширить**.

### `GET /v1/profile` — дополнительные поля в `data`

```json
{
  "data": {
    "id": 1,
    "phone": "+99361234567",
    "name": "Mekan Berdiýew",
    "gender": "male",
    "birth_date": "1990-05-15",
    "region_id": 1,
    "city_id": 2,
    "district_id": 3,
    "avatar": "/storage/avatars/1.webp",
    "is_premium": true,
    "store": {
      "name": "Altyn Bazar",
      "description": "Optom harytlar",
      "phone": "+99361234567",
      "address": "Aşgabat, Berkarar",
      "category_id": 5,
      "category_name_tk": "Optom",
      "category_name_ru": "Оптом"
    },
    "tariff": {
      "name": "Premium",
      "ads_limit": 50,
      "ads_used": 12,
      "videos_limit": 10,
      "videos_used": 3,
      "boosts_limit": 5,
      "days_left": 28
    },
    "stats": {
      "views_count": 1200,
      "likes_count": 45,
      "premium_days_left": 28
    }
  }
}
```

#### `store` (объект, nullable)

| Поле | Тип | Описание |
|------|-----|----------|
| `name` | string | Название магазина |
| `description` | string | Описание |
| `phone` | string | Телефон магазина |
| `address` | string | Адрес |
| `category_id` | int | ID категории |
| `category_name_tk` | string | Название категории (tk) |
| `category_name_ru` | string | Название категории (ru) |

> Store-поля доступны только для тарифов **Premium** и **Business** (`canHaveStore`).

#### `tariff` / `subscription` (объект, nullable)

Alias: мобилка читает `tariff` или `subscription`.

| Поле | Тип | Описание |
|------|-----|----------|
| `name` | string | `Basic`, `Standard`, `Premium`, `Business` |
| `ads_limit` | int | Лимит объявлений |
| `ads_used` | int | Использовано объявлений |
| `videos_limit` | int | Лимит видео |
| `videos_used` | int | Использовано видео |
| `boosts_limit` | int | Лимит бустов |
| `days_left` | int | Дней до окончания подписки |

#### `stats` (объект, nullable)

| Поле | Тип | Описание |
|------|-----|----------|
| `views_count` | int | Просмотры профиля/объявлений пользователя |
| `likes_count` | int | Опционально (мобилка также берёт из `/v1/favorites`) |
| `premium_days_left` | int | Дней до продления premium |

#### `is_premium`

`true` если тариф не Basic, или явно premium-подписка.

---

### `PUT /v1/profile` — сохранение store-полей

Существующие поля (`name`, `gender`, `birth_date`, `region_id`, `city_id`, `district_id`) без изменений.

**Дополнительно** принимать вложенный объект `store`:

```json
{
  "name": "Mekan Berdiýew",
  "gender": "male",
  "birth_date": "1990-05-15",
  "region_id": 1,
  "city_id": 2,
  "district_id": 3,
  "store": {
    "name": "Altyn Bazar",
    "description": "Optom harytlar",
    "phone": "+99361234567",
    "address": "Aşgabat, Berkarar",
    "category_id": 5
  }
}
```

**Response:** обновлённый профиль (полная схема `GET /v1/profile`).

**Validation:**
- `store.*` — только если у пользователя тариф Premium/Business
- `category_id` — должен существовать в `/v1/categories`

---

## 3. Тарифы (Subscription)

### `GET /v1/tariffs`

Список доступных тарифных планов для выбора в профиле.

**Auth:** Bearer token  
**Response `data`:** массив Tariff

```json
{
  "data": [
    {
      "name": "Basic",
      "ads_limit": 5,
      "ads_used": 0,
      "videos_limit": 1,
      "videos_used": 0,
      "boosts_limit": 0,
      "days_left": 30
    },
    {
      "name": "Standard",
      "ads_limit": 20,
      "ads_used": 0,
      "videos_limit": 5,
      "videos_used": 0,
      "boosts_limit": 2,
      "days_left": 30
    },
    {
      "name": "Premium",
      "ads_limit": 50,
      "ads_used": 0,
      "videos_limit": 10,
      "videos_used": 0,
      "boosts_limit": 5,
      "days_left": 30
    },
    {
      "name": "Business",
      "ads_limit": 100,
      "ads_used": 0,
      "videos_limit": 20,
      "videos_used": 0,
      "boosts_limit": 10,
      "days_left": 30
    }
  ]
}
```

> Для каталога планов `ads_used`/`videos_used` могут быть `0`.  
> `canHaveStore` на мобилке: `name == "Premium" || name == "Business"`.

---

### `PUT /v1/profile/subscription`

Выбор/смена тарифа.

**Auth:** Bearer token  
**Body:**

```json
{
  "tariff_name": "Premium"
}
```

| Поле | Тип | Обязательно | Значения |
|------|-----|-------------|----------|
| `tariff_name` | string | да | `Basic`, `Standard`, `Premium`, `Business` |

**Response `data`:** обновлённый профиль с новым `tariff`, `is_premium`, `stats.premium_days_left`.

**Errors:**
- `422` — неверное имя тарифа
- `402` / `403` — оплата не прошла (если тариф платный)

---

## 4. Поиск — популярные запросы

### `GET /v1/search/popular`

Популярные поисковые запросы для экрана поиска.

**Auth:** не обязателен

**Вариант A (рекомендуемый):** массив строк в `data`

```json
{
  "data": ["iPhone", "Toyota", "Nike", "Optom", "Aşgabat", "Sony"]
}
```

**Вариант B:** массив объектов

```json
{
  "data": [
    { "query": "iPhone", "rank": 1 },
    { "query": "Toyota", "rank": 2 }
  ]
}
```

**Вариант C:** объект с ключом `queries`

```json
{
  "data": {
    "queries": ["iPhone", "Toyota"]
  }
}
```

Мобилка поддерживает все три варианта.

---

## 5. История поиска (Recent)

**Auth:** Bearer token (обязательно). Без авторизации мобилка хранит историю только локально.

### `GET /v1/search/recent`

Последние до **8** запросов пользователя (новые сверху).

```json
{ "data": ["iPhone", "Toyota", "Nike"] }
```

Поддерживаются те же варианты формата, что у popular (`[{ "query": "..." }]`, `{ "queries": [...] }`).

### `POST /v1/search/recent`

```json
{ "query": "iPhone" }
```

Правила:

1. Trim; пустой → `422`
2. Дедуп: тот же query поднимается наверх
3. Максимум 8 записей на пользователя
4. Response: обновлённый список (как GET)

### `DELETE /v1/search/recent`

Очистить всю историю текущего пользователя.

```json
{ "data": { "cleared": true } }
```

---

## 6. Профиль — `is_profile_complete`

Расширить `GET /v1/profile` (и response `PUT`):

```json
{
  "data": {
    "is_profile_complete": true
  }
}
```

Aliases: `profile_completed`, `is_profile_done`.

**Правило на сервере (минимум):**

`name` не пустой **и** заданы `region_id` + `city_id`.

Мобилка:

- splash / OTP → если `false`, открывает `/register`
- после `PUT /v1/profile` кэширует флаг локально

---

## 7. Preferences / Onboarding flag

Язык и тема **остаются локальными**. Синхронизируется только флаг прохождения онбординга (после логина).

### `GET /v1/preferences` / `PUT /v1/preferences`

**Auth:** Bearer

```json
{
  "data": {
    "onboarding_completed": true
  }
}
```

PUT body:

```json
{ "onboarding_completed": true }
```

Aliases: `onboarding_done`.

> Онбординг-экран (выбор языка/темы) работает **до** логина → локальный флаг всегда пишется.  
> API нужен для синхронизации между устройствами после auth.

---

## 8. Что остаётся локальным (API не нужен)

| Фича | Где хранится |
|------|--------------|
| **Корзина** | SharedPreferences (`keyCart`) |
| **Язык** | SharedPreferences (`keyLocale`) |
| **Тема** | SharedPreferences |
| **Push toggle** | SharedPreferences + FCM |
| **История поиска (guest)** | SharedPreferences fallback |
| **Onboarding locale/theme UI** | SharedPreferences |

---

## 9. Порядок внедрения (рекомендация)

1. **`is_profile_complete` в `/v1/profile`** — роутинг splash/OTP
2. **`GET/POST/DELETE /v1/search/recent`** — история поиска
3. **`GET/PUT /v1/preferences`** — sync onboarding flag
4. **`GET /v1/stores/popular`** + store detail/listings
5. **`GET /v1/tariffs`** + **`PUT /v1/profile/subscription`** + store/tariff/stats в профиле
6. **`GET /v1/search/popular`** — популярные запросы

Подробный план для Claude Code: [`docs/CLAUDE_CODE_BACKEND_PLAN.md`](./CLAUDE_CODE_BACKEND_PLAN.md)

---

## 10. Маппинг файлов мобилки (для справки)

| API | Mobile files |
|-----|--------------|
| Stores | `stores_remote_data_source.dart`, `store_mapper.dart`, `api_store_repository.dart` |
| Profile store/tariff/stats/complete | `profile_mapper.dart`, `tariff_mapper.dart`, `api_profile_repository.dart` |
| Tariffs | `profile_remote_data_source.dart` |
| Search popular + recent | `search_remote_data_source.dart`, `api_search_repository.dart` |
| Preferences / onboarding | `preferences_remote_data_source.dart`, `api_settings_repository.dart` |
| Splash routing | `splash_cubit.dart`, `auth_cubit.dart` |

---

## 11. Пример полного flow: смена тарифа на Premium

```
1. GET /v1/tariffs          → список планов
2. PUT /v1/profile/subscription  { "tariff_name": "Premium" }
3. GET /v1/profile          → tariff + is_premium=true
4. PUT /v1/profile          { ..., "store": { "name": "...", ... } }
5. GET /v1/profile          → store заполнен
```

После шага 2 мобилка может открыть форму редактирования профиля для заполнения store-полей (если `canHaveStore == true`).

---

## 12. Пример flow: логин + регистрация профиля

```
1. POST /v1/auth/verify          → { token, is_new }
2. GET  /v1/profile              → is_profile_complete=false
3. PUT  /v1/profile              → name, region_id, city_id, ...
4. GET  /v1/profile              → is_profile_complete=true
5. GET  /v1/preferences          → onboarding_completed (optional sync)
```
