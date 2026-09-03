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
| `description` | string | нет | Описание (одноязычное — владелец пишет на своём языке) |
| `phone` | string | да | Телефон магазина, формат `+993XXXXXXXX` |
| `subtitle_tk` | string | нет | Подзаголовок (категория) на туркменском |
| `subtitle_ru` | string | нет | Подзаголовок на русском |
| `subtitle` | string | нет | Fallback, если нет `_tk`/`_ru` |
| `category_id` | int | нет | Категория магазина |
| `sells_retail` | bool | да | Торгует в розницу |
| `sells_wholesale` | bool | да | Торгует оптом |
| `has_delivery` | bool | да | Есть доставка |
| `address` | string | нет | Ориентир/адрес строкой |
| `region` / `city` / `district` | object | нет | `{ id, name_tk, name_ru }`; district может быть `null` |
| `logo_url` | string | нет | Логотип |
| `photos` | string[] | нет | Галерея (alias: `photo_urls`) |

**Вид торговли — два независимых флага, не enum.** Магазин может торговать
и оптом, и в розницу одновременно (обычная ситуация на рынке), тогда оба `true`.

**Доставка — только флаг.** Стоимость и сроки бэкенд не считает: покупатель
и владелец созваниваются и договариваются сами (решение команды).
`has_delivery: false` означает самовывоз с оплатой на месте.

**Тексты и иконки рисует мобилка.** Бэкенд не отдаёт строки «Розница», «Опт»,
«Самовывоз, оплата на месте» и эмодзи — только флаги и числа, иначе смена
формулировки потребовала бы релиза сервера.

**Видимость.** В публичной выдаче только магазины, прошедшие модерацию
(`status = approved`) и с горящей витриной (`is_active = true`). Витрина гаснет,
когда у владельца истекает тариф с правом на магазин: сам магазин не удаляется,
а его товары остаются в общей выдаче как обычные объявления.

---

### `GET /v1/stores`

Список магазинов с фильтрами (нужен экрану «Магазины» с переключателем опт/розница).

**Auth:** не обязателен  
**Query params:**

| Param | Тип | Описание |
|-------|-----|----------|
| `type` | string | `retail` \| `wholesale`. Флаги независимы, поэтому магазин «оптом и в розницу» попадает в обе выдачи |
| `has_delivery` | bool | Только с доставкой / только без |
| `region_id`, `city_id`, `district_id` | int | Адрес магазина |
| `category_id` | int | Категория |
| `search` | string | По названию |
| `page`, `limit` | int | Пагинация, `limit` 1–50 (по умолчанию 20) |

**Response:** пагинированный список объектов Store (схема выше).
Курируемые из админки идут первыми, остальные — свежими.

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

Дополнительно принимаются `search`, `category_id`, `sort` (`latest|price_asc|price_desc`)
и два товарных фильтра:

| Param | Тип | Описание |
|-------|-----|----------|
| `trade` | string | `wholesale` — только позиции с оптовой ценой, `retail` — с розничной |
| `in_stock` | bool | `1` прячет то, что владелец пометил как закончившееся (`stock_qty = 0`) |

Товары магазина — это обычные объявления с `store_id`: отдельной сущности
«товар» на бэкенде нет, поэтому карточка, избранное, жалобы и поиск у них общие
с остальными объявлениями. Объявление автора, у которого есть магазин,
привязывается к нему автоматически.

**Response:** пагинированный список — **та же схема Listing**, что в `GET /v1/listings`,
плюс торговые поля:

| Поле | Тип | Описание |
|------|-----|----------|
| `price` | number\|null | Розничная цена |
| `wholesale_price` | number\|null | Оптовая цена за единицу |
| `min_order_qty` | int\|null | Минимальная партия для опта (приходит всегда вместе с `wholesale_price`) |
| `stock_qty` | int\|null | `null` = «в наличии» (учёт не ведётся), `0` = нет в наличии, `N` = N шт |
| `store` | object\|null | `{ id, name, logo_url, sells_retail, sells_wholesale, has_delivery }` |
| `district` | object\|null | `{ id, name_tk, name_ru }` |

`price` и `wholesale_price` независимы: товар может продаваться и в розницу,
и оптом — показывайте те плашки, у которых цена не `null`.



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

### `GET|POST|PUT /v1/my/store` — свой магазин

Один магазин на пользователя, поэтому в URL нет `id`.
**Auth:** Bearer token. Доступно только на тарифе с правом на магазин — иначе `403`.

| Метод | Что делает | Ответ |
|-------|-----------|-------|
| `GET /v1/my/store` | Свой магазин | `200`, либо `404` если ещё не создан |
| `POST /v1/my/store` | Создать | `201`, магазин в статусе `pending` |
| `PUT /v1/my/store` | Изменить (частично) | `200` |
| `DELETE /v1/my/store/photos/{id}` | Удалить фото галереи | `200` |

**Body (multipart, файлы через POST + `_method=PUT`):**
`name`, `phone`, `region_id`, `city_id` — обязательны при создании;
`district_id`, `category_id`, `description`, `address`, `sells_retail`,
`sells_wholesale`, `has_delivery`, `logo`, `photos[]` (до 6 фото суммарно).

**Ответ** — схема Store плюс поля, которые видит только владелец:

| Поле | Тип | Описание |
|------|-----|----------|
| `status` | string | `pending` \| `approved` \| `rejected` |
| `is_active` | bool | `false` — тариф с правом на магазин истёк, витрина погашена |
| `rejection_reason` | object\|null | `{ id, name_tk, name_ru }` при `status = rejected` |

**Модерация.** Новый магазин и правка витринных полей (`name`, `description`,
`address`) или логотипа возвращают магазин в `pending` — покупателям он
показывается только после одобрения. Телефон, вид торговли и доставка —
рабочие настройки, их правка статус не меняет.

**Ошибки:** `403` — тариф без права на магазин; `422` — магазин уже есть,
сняты оба флага торговли, неверный формат телефона, город не из региона.

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
| `id` | int | ID магазина |
| `name` | string | Название магазина |
| `description` | string | Описание |
| `phone` | string | Телефон магазина |
| `address` | string | Адрес |
| `region_id` / `city_id` / `district_id` | int\|null | Адрес магазина |
| `sells_retail` / `sells_wholesale` / `has_delivery` | bool | Вид торговли и доставка |
| `status` | string | `pending` \| `approved` \| `rejected` |
| `is_active` | bool | `false` — витрина погашена из-за истёкшего тарифа |
| `rejection_reason` | object\|null | `{ id, name_tk, name_ru }` при отказе |
| `logo_url` | string\|null | Логотип |
| `category_id` | int | ID категории |
| `category_name_tk` | string | Название категории (tk) |
| `category_name_ru` | string | Название категории (ru) |

> Store-поля доступны только для тарифов с `can_have_store` (сейчас **Premium**).
> Полное управление магазином — на `/v1/my/store` (см. раздел 1): там логотип,
> галерея и все торговые настройки.

#### `tariff_request` (объект, nullable)

Заявка на платный тариф — тариф оплачивается наличными админу, а не онлайн.

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | int | ID заявки |
| `tariff_name` | string | Тариф, на который подана заявка |
| `amount` | number | Сумма, зафиксированная на момент подачи |
| `status` | string | `pending` \| `approved` \| `rejected` |
| `comment` | string\|null | Причина отказа от админа |
| `created_at` / `processed_at` | string\|null | ISO 8601 |

Пока `status = pending`, показывайте «Заявка на рассмотрении» вместо кнопки
смены тарифа. При `rejected` — `comment` объясняет, почему тариф не включился.

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
> У бесплатного тарифа `days_left = 0` — он бессрочный, срока действия у него нет.

Каждый план дополнительно отдаёт:

| Поле | Тип | Описание |
|------|-----|----------|
| `price` | number | Сумма, которую нужно передать администратору наличными |
| `can_have_store` | bool | Даёт ли тариф право на магазин — брать отсюда, а не сравнивать `name` |

---

### `PUT /v1/profile/subscription`

Заявка на тариф.

> **Изменение контракта.** Раньше этот эндпоинт выдавал тариф сразу.
> Теперь платный тариф так не выдаётся: оплата принимается наличными
> администратором, поэтому запрос создаёт **заявку**, а тариф включает админ
> после получения денег.

**Auth:** Bearer token  
**Body:**

```json
{
  "tariff_name": "Premium"
}
```

| Поле | Тип | Обязательно | Значения |
|------|-----|-------------|----------|
| `tariff_name` | string | да | `name` активного тарифа из `GET /v1/tariffs` |

**Response:**

| Код | Когда | Что дальше |
|-----|-------|-----------|
| `202` | Платный тариф | Создана заявка. В `data.tariff_request.status` — `pending`; `data.tariff` и `is_premium` пока прежние |
| `200` | Бесплатный тариф | Назначен сразу, заявка не создаётся (способ отказаться от платного) |

`data` — профиль целиком (схема `GET /v1/profile`).

**Errors:**
- `422` — неверное/неактивное имя тарифа, либо у пользователя уже есть
  незакрытая заявка (одна на пользователя)

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

---

## 13. Рекламные новости — куда ведёт `ad_link`

`GET /v1/news` и `GET /v1/news/{id}` отдают новость с полем `type`:

```json
{
  "id": 12,
  "title_ru": "Скидки в Altyn Bazar",
  "type": "ad",
  "ad_link_type": "store",
  "ad_link_id": 7
}
```

- `type: "regular"` — обычная новость, `ad_link_type` и `ad_link_id` равны `null`,
  кнопки перехода нет.
- `type: "ad"` — рекламная, оба поля заполнены. **Отдельного эндпоинта для
  перехода не существует**: ID указывает на уже знакомые мобилке ресурсы.

| `ad_link_type` | Экран | Запрос |
|---|---|---|
| `store` | карточка магазина | `GET /v1/stores/{ad_link_id}` |
| `listing` | карточка объявления | `GET /v1/listings/{ad_link_id}` |
| `product` | карточка товара магазина | `GET /v1/listings/{ad_link_id}` |

`product` — это то же объявление, но привязанное к магазину: у него непустой
`store` в ответе, заполнены `wholesale_price` / `min_order_qty` / `stock_qty`.
Отдельной сущности «товар» в бэкенде нет, поэтому запрос тот же, что и у
`listing` — отличается только оформление экрана.

Админ вводит ID руками, существование цели проверяется при сохранении новости.
Но цель могли удалить или снять с модерации уже после — тогда переход вернёт
**404**. Обрабатывать так: показать новость, кнопку перехода скрыть или
показать тост, на `/home` не выкидывать. Незнакомый `ad_link_type` (если в
админку добавят новый) трактовать так же — просто без кнопки.

---

## 14. Отзывы — чтение после модерации, правка и удаление своих

Отзыв мобилка уже умеет отправлять (`POST /v1/reviews`), он уходит в статусе
`pending` и появляется в публичной выдаче только после того, как админ нажал
«Одобрить». Ниже — три эндпоинта на чтение и два на управление своим отзывом.

### `GET /v1/listings/{id}/reviews` — отзывы об объявлении

Публичный (токен не нужен). Отдаёт **только `approved`** — того, что на
модерации или отклонено, в ответе нет. У объявления не в статусе `approved`
эндпоинт вернёт **404**, как и его карточка.

Query: `sort=latest|rating_desc|rating_asc` (по умолчанию `latest`),
`limit` 1..50 (по умолчанию 20), `page`.

```json
{
  "data": [
    {
      "id": 41,
      "text": "Продавец честный, товар как на фото",
      "rating": 5,
      "status": "approved",
      "listing_id": 108,
      "target_user_id": null,
      "author": { "id": 12, "name": "Merdan", "avatar": "https://.../avatars/x.webp" },
      "created_at": "2026-09-01T10:15:00+05:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 20,
    "total": 47,
    "rating": {
      "count": 47,
      "rated_count": 45,
      "average": 4.42,
      "breakdown": { "1": 1, "2": 0, "3": 4, "4": 12, "5": 28 }
    }
  }
}
```

- `meta.rating` — сводка по всем одобренным отзывам объекта, а не по текущей
  странице: отдельный запрос за средней оценкой делать не нужно.
- `rating` у отзыва может быть `null` — это отзыв без оценки. Он считается в
  `count`, но не входит в `rated_count` и не влияет на `average`.
- `average` — `null`, пока ни одной оценки нет. Приходит числом: `4.42` или
  `5` (JSON не хранит `5.0`), поэтому в Dart читать через `num`:
  `(json['average'] as num?)?.toDouble()`.
- `breakdown` — сколько отзывов на каждую звезду, для полосок под средней оценкой.

### `GET /v1/users/{id}/reviews` — отзывы о продавце

То же самое для отзывов, оставленных о пользователе (`target_user_id`).
Публичный, формат ответа идентичен.

### `GET /v1/reviews/my` — свои отзывы

Требует Bearer-токен. Отдаёт отзывы, написанные текущим пользователем, **во всех
статусах** — чтобы показать «на проверке» и причину отказа. Query: `status`,
`limit`, `page`.

```json
{
  "data": [
    {
      "id": 44,
      "text": "Не отвечает на звонки",
      "rating": 2,
      "status": "rejected",
      "listing": { "id": 108, "title": "Велосипед Stels" },
      "target_user": null,
      "rejection_reason": { "id": 3, "name_tk": "...", "name_ru": "Оскорбления" },
      "created_at": "2026-09-01T10:15:00+05:00"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 1 }
}
```

`rejection_reason` присутствует только у `status: "rejected"`.

### `PUT /v1/reviews/{id}` — изменить свой отзыв

Требует Bearer-токен. Правится **только свой** отзыв (чужой — `403`) и только
то, что написал автор: `text` (обязателен, ≤2000) и `rating`. Объект отзыва не
меняется — `listing_id` / `target_user_id` в теле игнорируются, отзыв остаётся
на той же карточке.

```json
{ "text": "Продавец связался и всё решил, меняю оценку", "rating": 5 }
```

- Ответ — `200` и обновлённый отзыв в том же формате, что в `/v1/reviews/my`.
- **Отзыв в любом статусе после правки становится `pending`** и уходит на
  повторную модерацию: одобренный на время пропадает из публичной ленты и из
  `meta.rating`. Показывать «отзыв снова на проверке», а не «сохранено».
- Так же чинится отклонённый: заводить новый отзыв не нужно — достаточно
  исправить текст, `rejection_reason` при этом сбрасывается.
- `rating` не прислан — прежняя оценка сохраняется; `"rating": null` — оценка
  снимается (останется отзыв без звёзд).
- Заблокированный пользователь получает `403`. Лимит — 10 запросов в минуту,
  общий с `POST /v1/reviews` и жалобами. `PATCH` работает так же, как `PUT`.

### `DELETE /v1/reviews/{id}` — удалить свой отзыв

Требует Bearer-токен, только свой отзыв (чужой — `403`). Удаляется насовсем:
из публичной ленты, из `meta.rating` объекта и из админки. Восстановления нет —
перед удалением спросить подтверждение.

```json
{ "data": null, "message": "Отзыв удалён" }
```

Заблокированному пользователю удаление своего отзыва доступно — в отличие от
создания и правки.

### Рейтинг в карточке и в ленте объявлений

Чтобы звёзды рисовались без второго запроса, `ListingResource` теперь отдаёт
блок `rating`:

```json
{
  "id": 108,
  "title": "Велосипед Stels",
  "rating": { "average": 4.42, "count": 47 },
  "user": { "id": 5, "name": "Merdan", "rating": { "average": 4.8, "count": 15 } }
}
```

- `listing.rating` — по отзывам об объявлении. Есть в `GET /v1/listings`,
  `GET /v1/listings/my`, `GET /v1/listings/{id}` и в выдаче магазина.
- `user.rating` — по отзывам о самом продавце, приходит **только в карточке**
  `GET /v1/listings/{id}` (в ленте этого ключа нет — проверять наличие).
- Оба считаются только по одобренным отзывам; `average: null` = оценок ещё нет,
  показывать «Нет отзывов», а не 0.

### Порядок для мобилки

```
1. Карточка объявления: GET /v1/listings/{id} → rating.average + rating.count («4.4 · 47 отзывов»)
2. Тап по блоку отзывов: GET /v1/listings/{id}/reviews?page=1 → список + breakdown
3. Отправка своего:      POST /v1/reviews → 201, status=pending, показать «отзыв на проверке»
4. Экран «Мои отзывы»:   GET /v1/reviews/my → статусы и причины отказа
5. Правка с этого экрана: PUT /v1/reviews/{id} → 200, status снова pending
6. Удаление:              DELETE /v1/reviews/{id} → 200, после подтверждения
```

Одобренный отзыв появляется в публичной ленте сразу после нажатия «Одобрить» в
админке — push об этом не шлётся, отдельного вебхука нет. Мобилка узнаёт об
изменении при следующем запросе списка.

---

## 15. Заказы и корзина

Заказ существует только у товаров магазина **с доставкой**. Магазин без
доставки работает как раньше: покупатель звонит продавцу и договаривается сам,
кнопки «В корзину» у его товаров нет.

**Корзина живёт на устройстве** — своего API у неё нет. Мобилка хранит список
`listing_id + qty` локально и отправляет его целиком одним запросом при
оформлении. Сервер в этот момент перепроверяет всё заново (товар мог уехать на
модерацию, цена — измениться, остаток — закончиться), поэтому оформление может
вернуть 422 даже по товару, который лежал в корзине со вчера.

### Кто что делает

```
покупатель  → POST /v1/orders                     (status: pending)
                ↳ части заказа сразу уходят владельцам магазинов
владелец    → accept / decline своей части        (отвечает первым)
админ       → видит ответы магазинов, подтверждает (status: approved)
                ↳ списываются остатки, кроме частей, от которых отказались
админ       → completed  (доставил, деньги на месте — как и с тарифами)
```

Статусы заказа: `pending` · `approved` · `completed` · `rejected` · `canceled`.
Промежуточного «в пути» нет: доставку ведёт админ и отмечает только результат.
Статусы части заказа (магазина): `pending` · `accepted` · `declined` ·
`canceled` (заказ закрыт админом раньше, чем магазин ответил). Когда админ
подтверждает заказ, не ответившие части становятся `accepted` — он
подтверждает как раз после разговора с магазином.

Тексты статусов рисует мобилка из своих словарей — бэкенд отдаёт только коды.

### Можно ли заказать этот товар

`ListingResource` (лента, карточка, выдача магазина) отдаёт готовый флаг:

```json
{
  "id": 108,
  "price": 100,
  "wholesale_price": 80,
  "min_order_qty": 5,
  "stock_qty": 10,
  "is_orderable": true,
  "store": { "id": 3, "name": "Altyn Bazar", "has_delivery": true }
}
```

- `is_orderable: true` → показываем «В корзину». Флаг уже учитывает: товар
  одобрен, магазин опубликован и с доставкой, есть цена, `stock_qty ≠ 0`.
- `stock_qty`: `null` — «в наличии» (учёт не ведётся), `0` — нет, `N` — N шт.
  Больше `N` заказать нельзя.
- Оптовая цена применяется **сама**, как только `qty >= min_order_qty`.
  Если у товара только `wholesale_price` (розничной цены нет), заказ на
  меньшее количество отбивается с 422.

### `POST /v1/orders` — оформить заказ

```json
{
  "items": [
    { "listing_id": 108, "qty": 2 },
    { "listing_id": 251, "qty": 5 }
  ],
  "contact_name": "Merdan",
  "phone": "+99361234567",
  "region_id": 1,
  "city_id": 4,
  "district_id": 9,
  "address": "ул. Магтымгулы, 12, кв. 5",
  "comment": "Позвонить за час"
}
```

- `items` — обязателен, до 50 разных товаров; повторы одного `listing_id`
  складываются.
- `address` — обязателен всегда (заказ = доставка).
- `contact_name`, `phone`, `region_id`, `city_id`, `district_id` — необязательны:
  пусто → берутся из профиля покупателя.

Ответ `201`:

```json
{
  "data": {
    "id": 17,
    "number": "000017",
    "status": "pending",
    "total": 350,
    "contact_name": "Merdan",
    "phone": "+99361234567",
    "address": "ул. Магтымгулы, 12, кв. 5",
    "city": { "id": 4, "name_tk": "Änew", "name_ru": "Анау" },
    "comment": "Позвонить за час",
    "admin_comment": null,
    "can_cancel": true,
    "stores": [
      {
        "id": 21,
        "status": "pending",
        "subtotal": 200,
        "store": { "id": 3, "name": "Altyn Bazar", "phone": "+99361234567", "logo_url": "..." },
        "items": [
          {
            "id": 44, "listing_id": 108, "title": "Рис длиннозёрный",
            "unit_price": 100, "is_wholesale": false, "qty": 2, "total": 200,
            "photo": "https://.../thumb.webp"
          }
        ]
      }
    ],
    "created_at": "2026-09-02T12:00:00+00:00"
  },
  "message": "Заказ оформлен. Администратор свяжется с вами для подтверждения"
}
```

Товары из разных магазинов складываются в **один** заказ и раскладываются по
`stores[]` — по одному блоку на магазин, у каждого своя сумма и свой статус.
`title` и `unit_price` в позициях — снимок на момент заказа: если продавец
потом поднимет цену, в заказе останется та, о которой договаривались.

Ошибки оформления — 422 с `errors.items[0]`, текст уже локализован и его можно
показывать как есть: пустая корзина, товар недоступен, магазин без доставки,
нет в наличии, осталось меньше, чем заказано, партия меньше минимальной,
цена не указана, свой собственный товар.

### `GET /v1/orders` — мои заказы

`?status=pending|approved|completed|rejected|canceled`, `?limit=20`.
Пагинация обычная (`meta` + `links`), в `data` — те же объекты, что выше.

### `GET /v1/orders/{id}` — карточка заказа

Только свой заказ, иначе 404. Причина отказа админа — в `admin_comment`.

### `POST /v1/orders/{id}/cancel` — отменить

Работает, только пока `status = pending` (флаг `can_cancel`). После того как
админ взял заказ в работу — 422: дальше вопрос решается с ним по телефону.

### Магазин: заказы, пришедшие владельцу

Эти четыре эндпоинта — для аккаунта **владельца магазина**, экран «Заказы» в
его магазине. Заказ приходит ему сразу после оформления: наличие подтверждает
он, а админ подтверждает заказ уже по его ответу. Владелец видит только свои
позиции; контакты и адрес покупателя ему не отдаются — везёт платформа.

```
GET  /v1/my/store/orders?status=pending&limit=20
GET  /v1/my/store/orders/{id}
POST /v1/my/store/orders/{id}/accept    { "comment": "..." }   // товар есть, отдаю
POST /v1/my/store/orders/{id}/decline   { "comment": "..." }   // отказ, остатки вернутся
```

`{id}` здесь — id **части заказа** (`stores[].id`), а не самого заказа.

```json
{
  "data": {
    "id": 21,
    "order_number": "000017",
    "order_status": "approved",
    "status": "pending",
    "subtotal": 200,
    "can_respond": true,
    "items": [ { "id": 44, "title": "Рис длиннозёрный", "qty": 2, "unit_price": 100, "total": 200 } ],
    "created_at": "2026-09-02T12:00:00+00:00"
  }
}
```

- `can_respond: true` → показываем кнопки «Принять» / «Отказать». Ответ даётся
  один раз, повтор — 422. Отвечать можно, только пока заказ в `pending`:
  после решения админа кнопки пропадают, вопрос решается по телефону.
- `meta.pending` в списке — сколько заказов ждёт ответа (бейдж на вкладке).
- `comment` при отказе необязателен, но именно его читает админ.

### Push-уведомления

| Событие | Кому | `data.type` | `data.id` |
|---|---|---|---|
| Заказ подтверждён админом | покупателю | `order` | id заказа |
| Заказ отклонён | покупателю | `order` | id заказа |
| Заказ доставлен / отменён админом | покупателю | `order` | id заказа |
| Новый заказ в магазине (сразу после оформления) | владельцу | `store_order` | id части заказа |

`store_order` мобилка пока не знает — по общему правилу неизвестный `type`
открывает `/home`, это штатный fallback.

### Порядок внедрения для мобилки

```
1. В карточке и ленте читать is_orderable → кнопка «В корзину»
2. Локальная корзина: listing_id + qty + снимок цены для показа
3. Экран оформления: адрес обязателен, остальное — префилл из профиля
4. POST /v1/orders → 201 показать «Заказ №000017 принят»
   422 → показать errors.items[0] и подсветить проблемный товар
5. «Мои заказы»: GET /v1/orders, отмена пока can_cancel
6. Для владельца магазина: вкладка «Заказы» → /v1/my/store/orders
```
