# Аудит бэкенда «Daşoguz söwda»

Дата аудита: **26 августа 2026 г.**, ветка `main`, коммит `c83c1f4`.
**Статус правок: «Критично» — 6 из 6, «Важно» — 14 из 14.** Отметки ✅ проставлены
по ходу работы; попутно закрыты Ж-1, Ж-3 и часть Ж-12. Раздел «Желательно»
ждёт отдельного захода.
Тесты на момент аудита: 184 passed / 14 failed → **сейчас 268 passed / 0 failed**.
Что проверялось: соответствие `mobile_docs/BACKEND_API.md` и
`mobile_docs/CLAUDE_CODE_BACKEND_PLAN.md`, архитектурные правила из `CLAUDE.md`,
производительность, безопасность, покрытие тестами.
Схема проекта — в [ARCHITECTURE.md](ARCHITECTURE.md).

> Отчёт писался до правок и сохранён как есть — формулировки «как чинить»
> остались в настоящем времени. Что из этого уже сделано, показывают отметки ✅
> в заголовках и сводка ниже.

---

## Итог одной страницей

Ядро сделано аккуратно: слои Route → FormRequest → Controller → Service →
Repository выдержаны в мобильном API почти везде, Actions действительно атомарны,
события/очереди на месте, единый формат ошибок настроен, фичи API покрыты
Pest-тестами плотно (≈130 тестов на `/api/v1`).

Основные проблемы сконцентрированы в **админской половине** и в **пробелах
авторизации на стыках**:

| Приоритет | Найдено | Статус |
|---|---|---|
| 🔴 Критично | 6 | **все 6 закрыты** |
| 🟠 Важно | 14 | **все 14 закрыты** |
| 🟡 Желательно | 12 | 3 закрыты попутно (Ж-1, Ж-3, часть Ж-12) |
| 📋 Спецификация | 10 эндпоинтов | 4 закрыты (задачи 1–3 плана), 6 ждут продуктовых решений |

---

## Что уже сделано

Разделы «Критично» и «Важно» разобраны целиком. Ключевые изменения:

| Пункт | Что сделано |
|---|---|
| К-1 | `Rule::exists(...)->where('status','approved')` в `StoreFavoriteRequest` + `whereHas('listing')` в `FavoriteRepository::paginateForUser` — снятое с публикации объявление исчезает и из уже собранного избранного |
| К-2 | `/register`, `RegisteredUserController`, `Register.vue` удалены; заодно снесена мёртвая `Welcome.vue` (роут `/` ведёт на dashboard) |
| К-3 | гейт `viewHorizon` переведён на `isAdmin()`; Spatie по-прежнему не используется — решение «перевести роли на пакет или удалить его» остаётся открытым (см. Ж-раздел) |
| К-4 | `UpdateListingRequest`, мёртвый `list([])` убран, запись перенесена в `ListingService` |
| К-5 | `orWhere` в `UserRepository::paginate` обёрнут в группу, поиск переведён на `BuildsLikeSearch::likeTerm()` (экранирование `%`/`_`) |
| К-6 | `Route::resource` заменены на явные `Route::get/put/patch/delete` — роутов в несуществующие методы больше нет |
| В-1 | `is_profile_complete`, плоские `region_id`/`city_id`/`district_id`, `GET/PUT /v1/preferences`, `GET/POST/DELETE /v1/search/recent` (задачи 1–3 плана) |
| В-2 | `DashboardService`, `PushBroadcastService`, `ReasonRepository`, `SettingRepository`, `PushNotificationRepository`; Eloquent из контроллеров убран (остались только `->load()` на связанных моделях) |
| В-3 | `UpdateListingRequest`, `UpdateVideoRequest`, Store/Update-реквесты для обоих справочников причин, `StoreRegionRequest`/`StoreCityRequest`/`StoreDistrictRequest` |
| В-4 | рассылка идёт `chunkById` по 500 получателей вместо `get()` всей базы |
| В-5 | миграция `2026_08_26_000002_add_performance_indexes` |
| В-6 | убран `LOWER()`, добавлено экранирование `%`/`_` (трейт `BuildsLikeSearch`) |
| В-7 | шесть COUNT-ов → один запрос (`NotificationRepository::counters()`), `AuthUserResource` вместо сырой модели |
| В-8 | диалоги сортируются по `withMax('messages', 'created_at')` |
| В-9 | `routes/web.php` переписан: доступ описан в одном месте, `abort_unless(isAdmin)` из контроллеров убраны, меню и кнопки скрыты по роли |
| В-10 | `SearchNewsRequest` + `NewsRepository::paginateForApi()`, отложенные новости скрыты |
| В-11 | привилегированные поля убраны из `$fillable`, запись через явные методы репозитория |
| В-12 | связка регион→город→район, поддержка `district_id` |
| В-13 | `phpunit.xml` изолирован от `.env`, мёртвый Breeze-код удалён |
| В-14 | падение брокера логируется, а не роняет ответ |

**Побочно найдено и починено при работе:**
- колонки `blocked_at` не существовало, хотя `UserService::block()` её писал —
  значение молча терялось (миграция `..._000003_add_blocked_at_to_users_table`);
- `ChatRepository::getMessages()` сортировал только по `created_at` — два сообщения
  в одну секунду возвращались в произвольном порядке;
- `UserSeeder` после ужесточения `$fillable` создавал бы admin и manager с ролью
  `user` — переведён на `forceCreate`;
- `JsonResource` в Inertia-props оборачивается в `data`: фронт ждал `auth.user.role`,
  а получил бы `auth.user.data.role` — поймано тестом до попадания в прод;
- страница настроек переключает `is_active` одним полем — `required`-правила
  сломали бы тумблеры, поэтому update-реквесты сделаны на `sometimes`.

**Тесты:** 184 passed / 14 failed → **268 passed / 0 failed**. Добавлено 12 новых
файлов тестов: история поиска, preferences, заполненность профиля, новости API,
валидация админки, права менеджера, дашборд, справочник регионов, общие props,
гейт Horizon, поиск в списке пользователей, отсутствие публичной регистрации;
регрессии на К-1 дописаны в существующий `Api/FavoriteTest.php`.

Регрессии проверены обратным прогоном: с возвращённым старым кодом
`UserSearchFilterTest` и проба на pending-объявление падают — тесты ловят
именно эти дефекты, а не проходят «всегда зелёными».

---

## 🔴 Критично

### ✅ К-1. Через избранное можно прочитать чужое непромодерированное объявление

**Файлы:** `app/Http/Requests/Api/V1/StoreFavoriteRequest.php:14`,
`app/Repositories/FavoriteRepository.php:26`

```php
// StoreFavoriteRequest.php:14
'listing_id' => ['required', 'integer', 'exists:listings,id'],
```

```php
// FavoriteRepository.php:26 — статус не фильтруется
return Favorite::with('listing.media', 'listing.category')
    ->where('user_id', $userId)
    ->latest()
    ->paginate($perPage);
```

**Почему это плохо.** `GET /api/v1/listings/{id}` честно отдаёт 404 на чужое
объявление в статусе `pending`/`rejected`
(`ListingController.php:69`). Но `POST /api/v1/favorites` принимает **любой**
существующий `listing_id`, а `GET /api/v1/favorites` отдаёт объявление целиком —
через `ListingResource`, то есть вместе с `description`, `status` и **телефоном
продавца**. Модерация обходится в два запроса.

**Проверено.** Тестовая проба: объявление чужого пользователя со статусом
`pending` → `GET /listings/{id}` вернул 404, а `POST /favorites` + `GET /favorites`
вернули `title = "SECRET PENDING"`, `phone = "+99361110000"`, `status = "pending"`.

**Как чинить.**
```php
// StoreFavoriteRequest
'listing_id' => [
    'required', 'integer',
    Rule::exists('listings', 'id')->where('status', 'approved'),
],
```
и дополнительно отфильтровать выдачу, чтобы объявление, снятое с публикации
после добавления в избранное, тоже переставало отдаваться:
```php
// FavoriteRepository::paginateForUser
->whereHas('listing', fn ($q) => $q->where('status', 'approved'))
```

---

### ✅ К-2. Публичная регистрация в админку открыта всему интернету

**Файлы:** `routes/auth.php:15-18`,
`app/Http/Controllers/Auth/RegisteredUserController.php:32-51`

```php
// routes/auth.php:15
Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
Route::post('register', [RegisteredUserController::class, 'store']);
```

**Почему это плохо.** Заготовка Breeze осталась включённой. Любой может открыть
`https://dashoguzsowda.com.tm/register` и создать аккаунт с email и паролем.
Роль по умолчанию — `user`, поэтому в `/dashboard` его не пустит `role:admin,manager`,
но:
- таблица `users` засоряется аккаунтами без телефона, и они **попадают в список
  пользователей админки** (`UserRepository` фильтрует именно `role = 'user'`);
- это обходит единственный задуманный сценарий регистрации — по SMS (`CLAUDE.md`);
- при любой будущей правке роли по умолчанию или middleware это превращается в
  прямую эскалацию привилегий.

**Как чинить.** Удалить `register`-роуты и `RegisteredUserController` целиком —
пользователи регистрируются только через `/api/v1/auth/verify`, а
admin/manager создаются сидером или вручную. Заодно убрать
`Pages/Auth/Register.vue` и ссылку на регистрацию с формы входа.

---

### ✅ К-3. `/horizon` в продакшене падает с 500 вместо 403

**Файл:** `app/Providers/HorizonServiceProvider.php:33`

```php
Gate::define('viewHorizon', function ($user = null) {
    return (bool) $user?->hasRole('admin');   // ← метода нет
});
```

**Почему это плохо.** `hasRole()` — метод трейта `Spatie\Permission\Traits\HasRoles`,
а `App\Models\User` (`app/Models/User.php:12`) подключает только
`HasApiTokens, HasFactory, Notifiable`. При `APP_ENV=production` Horizon вызывает
этот гейт на каждый заход в дашборд → `BadMethodCallException: Call to undefined
method App\Models\User::hasRole()` → 500. Мониторинг очередей в проде недоступен.

**Проверено.** Прямой вызов `$admin->hasRole('admin')` на свежесозданном админе
выбросил `BadMethodCallException`.

**Как чинить.** Проект использует колонку `users.role`, а не Spatie-роли, поэтому
проще всего:
```php
return (bool) $user?->isAdmin();
```
И отдельным решением определиться, нужен ли вообще `spatie/laravel-permission`:
пакет установлен, таблицы отмигрированы (`2026_06_24_064105_create_permission_tables.php`),
но **нигде не используется** — либо перевести роли на него, либо удалить пакет
и миграцию, чтобы не оставлять две конкурирующие модели прав.

---

### ✅ К-4. Редактирование объявления из админки без валидации → 500 и порча данных

**Файл:** `app/Http/Controllers/Admin/ListingController.php:45-51`

```php
public function update(Request $request, Listing $listing)
{
    $this->listingService->list([]);                                  // ← 47: мёртвый запрос
    $listing->update($request->only('title', 'description', 'price')); // ← 48: без валидации

    return back()->with('toast', [...]);
}
```

**Почему это плохо.**
1. **Нет Form Request** — прямое нарушение правила «вся валидация только в Form
   Request». Пустой `title` уходит в БД и упирается в `NOT NULL` → 500 с
   `PDOException` вместо человеческого сообщения. `price = "not-a-number"` уйдёт
   в `decimal(12,2)`.
2. **Строка 47 — мёртвый код**: `list([])` пагинирует **все** объявления с
   `with('user', 'category.parent.parent', 'region', 'city', 'media')` и
   выбрасывает результат. Это ~6 лишних запросов и полная страница выборки на
   каждое сохранение.

**Проверено.** `PUT /listings/{id}` с `title = ""` от админа вернул 500:
`SQLSTATE[23000]: NOT NULL constraint failed: listings.title`.

**Как чинить.** Завести `App\Http\Requests\Admin\UpdateListingRequest`
(`title` required|string|max:255, `description` nullable|string|max:5000,
`price` nullable|numeric|min:0|max:9999999999), удалить строку 47 и перенести
запись в `ListingService::updateFromAdmin()`.

---

### ✅ К-5. Поиск в списке пользователей ломает все остальные фильтры и показывает админов

**Файл:** `app/Repositories/UserRepository.php:16`

```php
->where('role', 'user')
->when($filters['search'] ?? null,
    fn($q, $s) => $q->where('phone', 'like', "%$s%")->orWhere('name', 'like', "%$s%"))
->when($filters['status'] ?? null, ...)
->when($filters['region_id'] ?? null, ...)
```

**Почему это плохо.** `orWhere` не сгруппирован, а `OR` в SQL имеет более низкий
приоритет, чем `AND`. Итоговое условие:

```sql
WHERE role = 'user' AND phone LIKE '%x%' OR name LIKE '%x%' AND status = ... AND region_id = ...
```

То есть любая строка, у которой совпало `name`, проходит **мимо фильтра по роли**.
Менеджер, набрав в поиске имя, увидит в списке пользователей аккаунты
`admin` и `manager` — с их телефонами, заметками (`note`) и причинами блокировки.
Фильтры «статус» и «регион» при активном поиске тоже перестают работать как AND.

**Проверено.** Созданы `admin` с именем `Zorro` и `user` с именем `Zorro Client`;
`UserRepository::paginate(['search' => 'Zorro'])` вернул обоих, включая админа.

**Как чинить.**
```php
->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w
    ->where('phone', 'like', "%$s%")
    ->orWhere('name', 'like', "%$s%")))
```
(в `ComplaintRepository:17` и `ReviewRepository:15` группировка сделана правильно —
можно взять их за образец).

---

### ✅ К-6. Три зарегистрированных роута ведут в несуществующие методы

**Файлы:** `routes/web.php:49`, `routes/web.php:55`, `routes/web.php:74`

| Роут | Целевой метод | Есть в контроллере? |
|---|---|---|
| `POST /listings` | `Admin\ListingController@store` | нет |
| `POST /videos` | `Admin\VideoController@store` | нет |
| `GET /cities` | `Admin\CityController@index` | нет |

**Почему это плохо.** `Route::resource(...)->except('create', 'edit')` оставляет
`store`, а `->except('create','edit','show')` оставляет `index`. Обращение к
любому из этих URL даёт 500 (`ReflectionException` / `BadMethodCallException`),
а не 404. Это и мусор в логах, и лишняя поверхность: `POST /listings` доступен
менеджеру и падает уже после прохождения auth-цепочки.

**Как чинить.** Сузить `except`:
```php
Route::resource('listings', ListingController::class)->only('index', 'show', 'update', 'destroy');
Route::resource('videos',   VideoController::class)->only('index', 'show', 'update', 'destroy');
Route::resource('cities',   CityController::class)->only('store', 'update', 'destroy');
```

---

## 🟠 Важно

### ✅ В-1. Расхождения с `BACKEND_API.md` — закрыты задачи 1–3 из плана

**Файлы:** `routes/api/v1.php`, `app/Http/Resources/Api/V1/UserResource.php`

`mobile_docs/BACKEND_API.md` описывает контракт, который мобильное приложение
**уже вызывает**. Ни один из пунктов не закрыт:

| № | Ожидается | Факт | Что нужно |
|---|---|---|---|
| 1 | `is_profile_complete` в `GET/PUT /v1/profile` | нет поля | вычислять в `UserResource`: `name` не пуст И `region_id` И `city_id` |
| 2 | `GET/POST/DELETE /v1/search/recent` | роутов нет | таблица `search_recents`, дедуп, максимум 8 записей |
| 3 | `GET/PUT /v1/preferences` | роутов нет | `onboarding_completed` — колонка на `users` или таблица `user_preferences` |
| 4 | `GET /v1/stores/popular`, `/stores/{id}`, `/stores/{id}/listings` | нет модели `Store` | целый новый домен: миграция, модель, сервис, репозиторий, ресурс |
| 5 | `GET /v1/tariffs` | есть только `/v1/profile/tariff` | новый роут со списком активных тарифов |
| 6 | `PUT /v1/profile/subscription` | нет | смена тарифа пользователем + правила оплаты |
| 7 | `GET /v1/search/popular` | нет | нужен источник статистики запросов |
| 8 | `store` / `tariff` / `stats` / `is_premium` в `GET /v1/profile` | нет | расширить `UserResource` |
| 9 | плоские `region_id`, `city_id`, `district_id` в профиле | только вложенные `region{}`, `city{}` | добавить плоские ID (мобилка читает именно их) |
| 10 | `district_id` в `PUT /v1/profile` | не принимается (`UpdateProfileRequest:16-22`) | добавить правило + проверку `district ∈ city ∈ region` |

**Отдельно про формат тарифа.** Документ ждёт
`{name, ads_limit, ads_used, videos_limit, videos_used, boosts_limit, days_left}`,
а `TariffResource:14-20` отдаёт
`{name_tk, name_ru, listings_limit, videos_limit, boost_limit, duration_days, is_free}`.
Даже когда `GET /v1/tariffs` появится, мобилка его не распарсит без маппинга —
нужен отдельный ресурс под мобильный контракт (`ads_*` вместо `listings_*`).

**Отдельно про Listing в разделе Stores.** В `BACKEND_API.md` пример объекта
Listing (`category_name_tk`, `photos: string[]`, `author_id`, `author_name`)
не совпадает с тем, что реально отдаёт `/v1/listings` (вложенные `category{}`,
`user{}`, `photos` как массив объектов). При этом текст документа говорит
«та же схема Listing, что в `GET /v1/listings`». **Это противоречие внутри
самого документа** — до реализации Stores нужно уточнить у мобильной команды,
что именно парсит `store_mapper.dart`.

### ✅ В-2. Толстые контроллеры админки: Eloquent прямо в контроллере

**Файлы:** `app/Http/Controllers/Admin/DashboardController.php:19-54`,
`PushController.php:23-25,45-56,66-80`, `UserController.php:34,57-62`,
`ListingController.php:30-31,41`, `VideoController.php:27,37`,
`SettingsController.php:27-28`, `RegionController.php:16-20,27,35,47-49`,
`CityController.php:18,30,42`, `DistrictController.php:19,30,37`,
`RejectionReasonController.php:15,28,35`, `ComplaintReasonController.php:15,27,34`,
`Api/V1/NewsController.php:23-30`

**Почему это плохо.** `CLAUDE.md` запрещает Eloquent в контроллерах, и это не
формальность: `NewsRepository` **уже существует** и умеет `paginate()`, но
`Api\V1\NewsController:23` всё равно пишет `News::where('is_published', true)`.
Логика дублируется, поменять её в одном месте нельзя, тестировать без HTTP-слоя
невозможно.

`DashboardController` — самый показательный: 10 запросов, два `groupBy(DB::raw(...))`
и `withCount` прямо в `__invoke()`, при том что рядом лежит готовый
`StatisticsService`.

**Как чинить.** Постепенно: завести `DashboardService`, `PushService`,
`SettingsService`, а справочники (регионы/города/районы/причины) — на
`RegionRepository` и новые `RejectionReasonRepository` / `ComplaintReasonRepository`.
Начать с `Api\V1\NewsController` — там репозиторий уже готов, правка на 5 строк.

### ✅ В-3. Отсутствие Form Request-валидации в четырёх местах админки

| Файл:строка | Код | Что пройдёт в БД |
|---|---|---|
| `Admin/ListingController.php:48` | `$listing->update($request->only(...))` | см. К-4 |
| `Admin/VideoController.php:43` | `$video->update($request->only('title'))` | пустой заголовок → 500 (`NOT NULL`) |
| `Admin/RejectionReasonController.php:35` | `$rejectionReason->update($request->only('name_ru','name_tk','type','is_active'))` | `type` вне enum → ошибка драйвера; пустые названия |
| `Admin/ComplaintReasonController.php:34` | `$complaintReason->update($request->only('name_ru','name_tk','is_active'))` | пустые названия |

Дополнительно inline-валидация вместо Form Request (тоже нарушение правила, но
хотя бы валидация есть): `RegionController.php:26,34`, `CityController.php:13-17,25-29`,
`DistrictController.php:15-18,26-29`, `CategoryController.php:60`, `BannerController.php:61`.

**Как чинить.** По одному Form Request на каждый `update`; inline-правила
перенести в `Admin/StoreRegionRequest`, `StoreCityRequest`, `StoreDistrictRequest`.

### ✅ В-4. Push-рассылка загружает всю базу пользователей в память

**Файл:** `app/Http/Controllers/Admin/PushController.php:64-80`

```php
private function resolveTargetUsers(array $data)
{
    $query = User::where('role', 'user')->where('status', 'active');
    ...
    return $query->get();   // ← 80: все активные пользователи одним массивом
}
```

**Почему это плохо.** При `target = 'all'` в память поднимаются все модели
`User` целиком. На 100 тысячах пользователей это сотни мегабайт и почти
гарантированный `Allowed memory size exhausted` прямо в HTTP-запросе админки.
Далее `PushNotificationService::sendToUsers()` (`:38`) ещё раз выбирает **все**
их FCM-токены одним `whereIn` и группирует в памяти.

**Как чинить.** Перенести рассылку в Job и идти чанками:
```php
$query->select('id')->chunkById(1000, function ($users) use (...) {
    $this->pushNotificationService->sendToUsers($users, ...);
});
```
Заодно унести создание записи `PushNotification` (`:45-56`) из контроллера в сервис.

### ✅ В-5. Нет индексов под самые частые запросы

**Файлы:** `database/migrations/2024_01_01_000007_create_listings_table.php`,
`..._000009_create_videos_table.php`, `..._000015_create_messages_table.php`,
`..._000012_create_reviews_table.php`, `..._000014_create_complaints_table.php`

Индексы есть только там, где их создал `constrained()` (внешние ключи) плюс
уникальные пары в `favorites` и `video_likes`. Не проиндексировано:

| Таблица | Колонки | Кто страдает |
|---|---|---|
| `listings` | `(status, is_boosted, created_at)` | `paginateForApi` — главная лента приложения |
| `listings` | `(user_id, status)` | `CheckTariffLimitAction`, `/listings/my` |
| `listings` | `(user_id, is_boosted)` | `CheckBoostLimitAction` |
| `videos` | `(status, created_at)` | публичная лента роликов |
| `videos` | `(user_id, status)` | `CheckVideoLimitAction`, `/videos/my` |
| `messages` | `(user_id, sender, is_read)` | счётчик непрочитанных в каждом Inertia-ответе |
| `reviews`, `complaints` | `status` | счётчики и фильтры в админке |

**Почему это плохо.** Каждое открытие ленты — полный скан `listings` с
сортировкой. Пока данных мало, это незаметно; на десятках тысяч объявлений
главный экран приложения начнёт отвечать секундами.

**Как чинить.** Одна миграция:
```php
Schema::table('listings', function (Blueprint $t) {
    $t->index(['status', 'is_boosted', 'created_at']);
    $t->index(['user_id', 'status']);
});
Schema::table('videos', fn (Blueprint $t) => $t->index(['status', 'created_at'])->index(['user_id', 'status']));
Schema::table('messages', fn (Blueprint $t) => $t->index(['user_id', 'sender', 'is_read']));
```

### ✅ В-6. Поиск по объявлениям: лишний LOWER() и неэкранированные wildcard

**Файл:** `app/Repositories/ListingRepository.php:81-86`

```php
$term = '%'.mb_strtolower($s).'%';
$q->whereRaw('LOWER(title) LIKE ?', [$term])
  ->orWhereRaw('LOWER(description) LIKE ?', [$term]);
```

**Почему это плохо.** `LIKE '%...%'` по функции `LOWER()` от колонки — это
гарантированный full scan с вычислением функции на каждой строке. В MySQL 8 при
`utf8mb4_unicode_ci`/`utf8mb4_0900_ai_ci` сравнение и так регистронезависимо,
поэтому `LOWER()` здесь ничего не даёт, но лишает шанса на индекс.

**Как чинить.** Минимум — убрать `LOWER()` и сравнивать напрямую
(`->where('title', 'like', "%$s%")`). Правильно — `FULLTEXT`-индекс по
`(title, description)` и `whereFullText()`. Обратить внимание: `whereRaw` с
`LOWER()` завезли, судя по всему, ради SQLite в тестах — тогда стоит развести
поведение по драйверу.

### ✅ В-7. Каждый запрос админки делает 6 COUNT-ов и отдаёт сырую модель `User`

**Файл:** `app/Http/Middleware/HandleInertiaRequests.php:28-40`

```php
'auth'  => ['user' => $request->user()],          // ← 28: сырая модель в props
'counts' => fn () => $request->user() ? [
    'newUsers'        => User::where(...)->count(),
    'pendingListings' => Listing::where('status','pending')->count(),
    'pendingVideos'   => Video::where('status','pending')->count(),
    'unreadChats'     => Message::where(...)->distinct('user_id')->count('user_id'),
    'newComplaints'   => Complaint::where('status','new')->count(),
    'pendingReviews'  => Review::where('status','pending')->count(),
] : [],
'notifications' => fn () => ... NotificationService::forUser(...),
```

**Почему это плохо.**
1. Шесть агрегатов по неиндексированным колонкам (см. В-5) на **каждый** переход
   в админке, включая XHR-переходы Inertia. Плюс `NotificationService::forUser()`
   тянет ещё несколько выборок.
2. `'user' => $request->user()` отдаёт модель как есть. `password` и
   `remember_token` скрыты через `$hidden`, но в props уезжают `note`,
   `blocked_reason`, `fcm_token`, `tariff_ends_at`. Правило «никогда не возвращать
   сырые модели» в `CLAUDE.md` написано про API, но здесь ровно та же проблема.

**Как чинить.** Завести `AdminUserResource` для `auth.user`; счётчики закэшировать
на 30–60 секунд (`Cache::remember`) или считать одним запросом с `UNION ALL`/
подзапросами; `counts` и `notifications` уже ленивые (`fn () =>`) — можно
дополнительно отдавать их только на полной загрузке через `Inertia::lazy()`.

### ✅ В-8. Список диалогов сортируется не по последнему сообщению

**Файл:** `app/Repositories/ChatRepository.php:20`

```php
return User::where('role', 'user')
    ->whereHas('messages')
    ->withCount([...])
    ->with(['messages' => fn($q) => $q->latest()->limit(1)])
    ->latest('updated_at')   // ← 20: это updated_at ПОЛЬЗОВАТЕЛЯ
    ->paginate($perPage);
```

**Почему это плохо.** Новое сообщение не меняет `users.updated_at`, поэтому
диалог с только что написавшим пользователем **не поднимается наверх**. Оператор
поддержки видит список в порядке последнего редактирования профиля.

**Как чинить.**
```php
->withMax('messages as last_message_at', 'created_at')
->orderByDesc('last_message_at')
```

### ✅ В-9. Права менеджера в коде шире, чем в `CLAUDE.md`

**Файл:** `routes/web.php:30-111`

Группа `role:admin,manager` покрывает почти всю админку. Менеджер сейчас может:

| Действие | Роут | По `CLAUDE.md` |
|---|---|---|
| Создавать пользователей | `POST /users` | не указано |
| Редактировать пользователей | `PUT /users/{user}` | «просматривать» |
| Блокировать / разблокировать | `PATCH /users/{user}/block` | не указано |
| Назначать тариф пользователю | `POST /users/{user}/tariff` | не указано |
| Создавать / менять тарифы | `POST /tariffs`, `PUT /tariffs/{tariff}` | «нельзя менять критические настройки» |
| Создавать / менять категории | `POST /categories`, `PUT /categories/{c}` | не указано |
| Создавать / менять регионы, города, районы | `POST /regions`, `POST /cities/{c}/districts` … | не указано |

Удаление везде закрыто, но не через middleware, а вручную:
`abort_unless(request()->user()->isAdmin(), 403)` в семи контроллерах
(`ListingController:55`, `VideoController:50`, `NewsController:66`, `BannerController:46`,
`TariffController:41`, `CategoryController:44`, `UserController:75`) плюс
`if (! $request->user()->isAdmin())` ещё в трёх (`RegionController:57`,
`CityController:50`, `DistrictController:44`). Легко забыть при добавлении нового
контроллера.

**Почему это плохо.** Расхождение кода и документа означает, что фактическую
модель прав никто не держит в голове целиком. Тарифы — это деньги, категории и
регионы — структура всего каталога.

**Как чинить.** Решить, что менеджеру можно, и зафиксировать это в роутах:
вынести справочники (тарифы, категории, регионы) в `role:admin`-группу, а
`abort_unless(...isAdmin())` заменить на middleware `role:admin` на конкретных
роутах `destroy`.

### ✅ В-10. `GET /api/v1/news` — без Form Request, `limit` не ограничен

**Файл:** `app/Http/Controllers/Api/V1/NewsController.php:21-46`

```php
$query = News::where('is_published', true)->latest('published_at');
if ($request->has('type')) {
    $query->where('type', $request->get('type'));   // ← 27: значение не проверяется
}
$news = $query->paginate($request->get('limit', 20)); // ← 30: limit без потолка
```

**Почему это плохо.** `GET /api/v1/news?limit=1000000` вернёт всю таблицу новостей
с картинками — DoS без авторизации. Все остальные листинги API защищены
(`SearchListingsRequest`, `MyVideosRequest` и т. д. ограничивают `limit` до 50) —
здесь просто забыли. Плюс не фильтруется `published_at <= now()`: новость с датой
публикации в будущем и флагом `is_published = true` попадёт в ленту.

**Как чинить.** `App\Http\Requests\Api\V1\SearchNewsRequest`
(`type` in `regular,ad`; `limit` between 1,50; `page` min 1) и запись через
`NewsRepository`.

### ✅ В-11. `fillable` пользователя содержит поля, меняющие права и тариф

**Файл:** `app/Models/User.php:14-18`

```php
protected $fillable = [
    'name', 'phone', 'phone_verified_at', 'email', 'avatar', 'gender', 'birth_date',
    'region_id', 'city_id', 'district_id', 'role', 'locale', 'status', 'blocked_reason',
    'tariff_id', 'tariff_ends_at', 'note', 'fcm_token', 'password',
];
```

**Почему это плохо.** `role`, `status`, `tariff_id`, `tariff_ends_at`,
`phone_verified_at` — это привилегии и деньги. Сейчас массового присвоения не
происходит: все пути записи идут через Form Request
(`UpdateProfileRequest` разрешает только 5 полей, `UpdateUserRequest` — 4), то есть
дыры **нет**. Но защита держится исключительно на дисциплине: один
`$user->update($request->all())` в новом коде — и пользователь делает себя админом.

**Как чинить.** Убрать `role`, `status`, `blocked_reason`, `tariff_id`,
`tariff_ends_at`, `phone_verified_at` из `$fillable` и присваивать их явно
(`$user->role = ...; $user->save();`) в `UserService::block()`,
`TariffService::assignToUser()`, `AuthService::verify()`. Заодно удалить legacy
`fcm_token` — по `CLAUDE.md` эта колонка не читается и не пишется.

### ✅ В-12. Профиль: город не проверяется на принадлежность региону, района нет вовсе

**Файл:** `app/Http/Requests/Api/V1/UpdateProfileRequest.php:16-22`

```php
'region_id'  => ['sometimes', 'nullable', 'exists:regions,id'],
'city_id'    => ['sometimes', 'nullable', 'exists:cities,id'],
```

**Почему это плохо.** Можно сохранить регион «Ахал» с городом «Туркменабат».
В `StoreListingRequest:23` и `StoreUserRequest:20-21` связка проверяется правильно —
здесь забыли. Плюс `district_id` не принимается совсем, хотя колонка есть
(`2026_07_06_120000_add_district_and_phone_verified_to_users_table.php`) и
`BACKEND_API.md` его ждёт.

**Как чинить.**
```php
'city_id'     => ['sometimes','nullable', Rule::exists('cities','id')->where('region_id', $this->input('region_id'))],
'district_id' => ['sometimes','nullable', Rule::exists('districts','id')->where('city_id', $this->input('city_id'))],
```
и добавить `district` в `->load(...)` и в `UserResource`.

### ✅ В-13. Тестовое окружение не изолировано — 14 падающих тестов

**Файл:** `phpunit.xml:20-33`

**Почему это плохо.** В блоке `<php>` не задан `BROADCAST_CONNECTION`, поэтому
значение берётся из `.env` разработчика (`reverb`). Тесты чата пытаются достучаться
до реального Reverb и падают с `BroadcastException: Pusher error: No matching
application for ID [889026]`. То есть **результат прогона зависит от `.env`
машины** — на CI без `.env` он будет другим.

Текущий расклад падений (`./vendor/bin/pest`, 184 passed / 14 failed):

| Тест | Причина |
|---|---|
| `ChatReplyTest > lets an admin reply to a user dialog` | реальный Reverb из `.env` |
| `Auth/PasswordResetTest` (3 шт.) | `password_reset_tokens` заведена с колонкой `phone`, а не `email` (см. Ж-1) |
| `Auth/EmailVerificationTest` (3 шт.) | `BadMethodCallException` — Breeze-функциональность не под этот проект |
| `Auth/AuthenticationTest > users can authenticate` | `UserFactory` не задаёт `email`, а вход в админку по email |
| `ProfileTest` (5 шт.) | роуты Breeze `/profile` в `web.php` отсутствуют — 404 |
| `ExampleTest > returns a successful response` | `/` теперь редиректит на `/dashboard`, тест ждёт 200 |

**Почему это важнее, чем кажется.** 14 «привычно красных» тестов — это шум, в
котором незаметно утонет 15-й, уже настоящий.

**Как чинить.**
1. В `phpunit.xml` добавить `<env name="BROADCAST_CONNECTION" value="null"/>`
   (и `FCM`-заглушки, если понадобятся).
2. Удалить тесты Breeze, которые не описывают этот продукт
   (`EmailVerificationTest`, `PasswordResetTest`, `ProfileTest`, `ExampleTest`),
   вместе с соответствующими роутами и контроллерами (см. К-2, Ж-1).
3. `AuthenticationTest` починить, добавив `email` в `UserFactory::admin()`.

### ✅ В-14. Ошибка WebSocket-брокера роняет ответ админки

**Файл:** `app/Services/ChatService.php:47,62`

```php
broadcast(new NewMessageEvent($message))->toOthers();
```

**Почему это плохо.** `NewMessageEvent` реализует `ShouldBroadcast`, поэтому в
проде с `QUEUE_CONNECTION=redis` вещание уходит в очередь и падение Reverb
пользователю не видно — это правильно. Но если очередь когда-либо переключат на
`sync` (как в тестах), исключение брокера превратится в 500 **после того, как
сообщение уже сохранено в БД**: оператор увидит ошибку и отправит ответ повторно.

**Как чинить.** Явно зафиксировать инвариант: обернуть `broadcast()` в
`try/catch` с `Log::warning()` — сообщение сохранено, доставка в реальном времени
не критична.

---

## 🟡 Желательно

### ✅ Ж-1. Таблица `password_reset_tokens` заведена под телефон, а Laravel ищет email

**Файл:** `database/migrations/0001_01_01_000000_create_users_table.php:33`

```php
Schema::create('password_reset_tokens', function (Blueprint $table) {
    $table->string('phone')->primary();   // ← фреймворк пишет сюда колонку `email`
```

Стандартный `DatabaseTokenRepository` вставляет `['email' => ..., 'token' => ...]`,
колонки `email` нет → «Забыли пароль» в админке падает с ошибкой драйвера.
Чинится либо переименованием колонки в `email`, либо (проще) удалением
восстановления пароля вместе с остальным неиспользуемым Breeze (см. К-2).

### Ж-2. Дефолтные учётки с паролем `password` в сидере

**Файл:** `database/seeders/UserSeeder.php:13-28`

`admin@gmail.com / password` и `manager@gmail.com / password` описаны в `README.md`
как штатный способ входа. Если `db:seed` когда-нибудь выполнят на проде, админ-доступ
получит любой, кто читал репозиторий. Стоит брать пароль из `.env`
(`ADMIN_PASSWORD`) и падать, если он не задан при `APP_ENV=production`.

### ✅ Ж-3. `Setting::get()` — запрос к БД на каждый вызов, в том числе в middleware

**Файлы:** `app/Models/Setting.php:11`, `EnsureNewsPermission.php:17`,
`EnsureBannerPermission.php:17`, `ListingService.php:64`

```php
public static function get(string $key, mixed $default = null): mixed
{
    return static::where('key', $key)->value('value') ?? $default;
}
```

Настройки меняются раз в месяц, а читаются на каждом запросе к новостям и
баннерам админки. Достаточно `Cache::rememberForever("setting:$key", ...)` с
инвалидацией в `set()`.

### Ж-4. Модели с бизнес-логикой и запросами

- `app/Models/User.php:46-52` — `activeTariff()` делает `Tariff::where('is_free', true)->first()`
  при каждом вызове. В `CheckBoostLimitAction:28` он дёргается через
  `$listing->user->activeTariff()`, то есть ещё и с ленивой загрузкой `user`.
  В `VideoRepository::attachAdminMeta:171` эту проблему уже обошли вручную,
  вытащив бесплатный тариф один раз — значит, боль известна.
- `app/Models/Setting.php:11,16` — статические методы доступа к БД в модели.
- `app/Models/Category.php:27-30` + `$appends = ['icon_url']` — аксессор дёргает
  `Storage::disk('public')->url()` на каждую категорию при каждой сериализации.

Правильное место для `activeTariff()` — `TariffService`; `Setting::get/set` —
`SettingRepository`.

### Ж-5. N+1 при обновлении фотографий объявления

**Файл:** `app/Services/ListingService.php:140-145`

```php
foreach ($listing->media as $media) {      // ← ленивая загрузка, если media не подгружены
    if (in_array($media->id, $removeMediaIds)) {
        $this->deleteMediaFiles($media);
        $this->listingRepository->deleteMedia($media);   // ← отдельный DELETE на каждое фото
    }
}
```

До 8 отдельных `DELETE` вместо одного `whereIn`. Плюс `in_array` без строгого
сравнения на значениях из HTTP (строки vs int) — работает случайно.
Стоит вынести в `ListingRepository::deleteMediaByIds(Listing $listing, array $ids)`
и собрать пути файлов до удаления.

### Ж-6. Рекурсивный пересчёт уровней категорий

**Файл:** `app/Services/CategoryService.php:147-153`

`recomputeDescendantLevels()` идёт вглубь по одному ребёнку за раз: запрос на
каждый узел плюс `update()` с `fresh()` (ещё один `SELECT`). Дерево ограничено
тремя уровнями, так что катастрофы нет, но при 200 подкатегориях перенос ветки
даст ~400 запросов. `CategoryRepository::descendants():58-73` уже умеет собирать
поддерево пачками — можно использовать его и один `whereIn(...)->update()`.

### Ж-7. Отзывы: можно похвалить самого себя и спамить дублями

**Файл:** `app/Http/Requests/Api/V1/StoreReviewRequest.php:22-25`

`target_user_id` проверяется только на `exists:users,id`. Нет запрета на
`target_user_id === auth()->id()`, нет уникальности «один отзыв на пару
автор–цель». Модерация это ловит вручную, но правило дешевле:
```php
'target_user_id' => [..., Rule::notIn([$this->user()->id])],
```
плюс уникальный индекс `(user_id, target_user_id)` / `(user_id, listing_id)`.

То же и с жалобами (`StoreComplaintRequest`): можно пожаловаться на собственное
объявление и слать одну и ту же жалобу бесконечно (защищает только `throttle:10,1`).

### Ж-8. Фабрики есть только для `User`

**Каталог:** `database/factories/` — единственный файл `UserFactory.php`.

`CLAUDE.md` требует фабрики для тестовых данных, но тесты создают модели руками:
`Region::create([...])`, `City::create([...])`, `Listing::create([...])` — этот блок
дословно повторяется в `FavoriteTest.php:12-28`, `ListingTest.php`, `VideoTest.php`
и других. Любое изменение схемы `listings` придётся править в десятке `beforeEach`.
Нужны `ListingFactory`, `VideoFactory`, `CategoryFactory`, `RegionFactory`,
`CityFactory`, `TariffFactory`.

### Ж-9. `SendSmsCode` выполняется синхронно внутри HTTP-запроса

**Файлы:** `app/Listeners/SendSmsCode.php:14`, `app/Services/Sms/LocalModemSmsService.php:36`

Листенер не реализует `ShouldQueue`, поэтому `POST /auth/send-code` ждёт HTTP-ответа
от socket-server (таймаут 5 секунд). Если шлюз тормозит — тормозит и логин.
С другой стороны, при `ShouldQueue` пользователь потеряет мгновенную обратную
связь «телефон-шлюз не подключён». Решение зависит от продукта: либо очередь
плюс отдельный статус отправки, либо оставить синхронно, но снизить таймаут.

### Ж-10. Одобрение ролика не шлёт push, в отличие от объявления

**Файлы:** `app/Actions/ApproveVideoAction.php:14-17`, `RejectVideoAction.php:15-18`

`ApproveListingAction` кидает `ListingApproved`, а `ApproveVideoAction` — ничего.
Автор ролика не узнаёт о решении модератора. В `CLAUDE.md` push для роликов не
описан, так что формально это не баг — но с точки зрения пользователя поведение
непоследовательное. Нужно решение продукта.

### Ж-11. `POST /videos/{id}/like` доступен заблокированному пользователю

**Файл:** `routes/api/v1.php:108`

Публикация контента закрыта через `not_blocked`, а лайки — нет. Заблокированный
может накручивать лайки. Достаточно добавить `not_blocked` в middleware роута.

### Ж-12. Мелочи, которые стоит поправить заодно

| Файл:строка | Что |
|---|---|
| `Admin/RegionController.php:29,37,52,63`, `CityController.php:20,32,45,56`, `DistrictController.php:21,32,39,50` | хардкодные русские строки в тостах вместо `__('messages.*')` — прямое нарушение правила локализации |
| `Http/Middleware/EnsureRole.php:14`, `EnsureNewsPermission.php:20`, `EnsureBannerPermission.php:20` | `abort(403, 'Недостаточно прав')` — тоже хардкод |
| `Admin/SendPushRequest.php:20` | `link_type` — просто `string`, не ограничен контрактом deep-link (`listing\|news\|chat\|external\|url`) |
| `Admin/RegionController.php:46-50` | скрытие региона каскадом прячет города и районы, а показ обратно **не раскрывает** — асимметрия, которую админ не ожидает |
| `Api/V1/ListingResource.php:19` | `phone` уезжает в публичную ленту без токена. Для доски объявлений это, вероятно, задумано — но стоит зафиксировать решение явно, иначе телефоны легко выкачиваются скриптом |
| `Models/User.php:17` | `fcm_token` — legacy-колонка в `fillable`, хотя по `CLAUDE.md` не читается и не пишется |
| `Repositories/ListingRepository.php:16,70,100` | `with('category.parent.parent')` — фиксированные три уровня. При изменении `Category::MAX_LEVEL` тихо сломается `categoryPath()` в ресурсе |

---

## Покрытие тестами

### Что покрыто хорошо

`tests/Feature/Api/` — 99 тестов, и они действительно проверяют поведение,
а не «200 OK»:

| Файл | Тестов | Что закрыто |
|---|---|---|
| `ListingTest.php` | 25 | создание, квоты, leaf-категории, фильтры, поддерево категорий, `nearest`, просмотры, boost + интервал + лимит, права на чужое |
| `VideoTest.php` | 17 | загрузка, длительность, квота, лента, лайки, просмотры, удаление файлов |
| `AuthTest.php` | 15 | SMS-цикл целиком: кулдаун, срок, попытки, блокировка, мультидевайс FCM |
| `ProfileTest.php` | 9 | частичное обновление, аватар, смена номера |
| `FavoriteTest.php` | 8 | идемпотентность, `is_favorite` в ленте и карточке |
| `VideoChunkUploadTest.php` | 8 | порядок частей, чужая сессия, 503 без ffprobe, отмена |
| `ComplaintTest.php` / `ReviewTest.php` | 7 + 7 | модерация, блокировка, неактивные причины |
| `ChatTest.php` | 5 | изоляция истории по пользователю, broadcast |
| `TariffTest.php` | 4 | бесплатный тариф, платный с датой, вычет квоты |

Админка покрыта заметно слабее (`BannerTest` 11, `CategoryTest` 8, `SettingsTest` 7,
`UserCreateTest` 7, `VideoAdminTest` 6, `PushTest` 5, `StatisticsTest` 4).
Unit-тестов три: `PushNotificationServiceTest`, `SendPushNotificationJobTest`, `ExampleTest`.

### Чего нет — и что писать в первую очередь

**Приоритет 1 — тесты на найденные дефекты (пишутся до правки, как регрессия).**
✅ Написаны все: К-1 — в `Api/FavoriteTest.php`, К-5 — `UserSearchFilterTest.php`,
К-4 — `AdminValidationTest.php`, К-3 — `HorizonAccessTest.php`,
К-2 — `Auth/NoPublicRegistrationTest.php` (имена файлов отличаются от плана ниже).

```php
// tests/Feature/Api/FavoriteTest.php — К-1
it('не даёт добавить в избранное чужое непромодерированное объявление');
it('не отдаёт в /favorites объявление, снятое с публикации после добавления');

// tests/Feature/UserListTest.php — К-5
it('не показывает admin и manager в поиске по списку пользователей');
it('применяет фильтр по статусу вместе с поиском');

// tests/Feature/ListingAdminTest.php — К-4
it('отклоняет сохранение объявления с пустым заголовком с 422, а не 500');

// tests/Feature/HorizonGateTest.php — К-3
it('пускает admin в /horizon и не пускает manager');

// tests/Feature/RegisterDisabledTest.php — К-2
it('возвращает 404 на GET и POST /register');
```

**Приоритет 2 — публичные API-эндпоинты без единого теста:**

| Эндпоинт | Что проверять |
|---|---|
| `GET /api/v1/news`, `/news/{id}` | только опубликованные; `limit` ограничен; 404 на черновик |
| `GET /api/v1/categories` | скрытый родитель прячет всё поддерево; не глубже 3 уровней |
| `GET /api/v1/regions` | `is_hidden` не отдаётся ни на одном из трёх уровней |
| `GET /api/v1/banners` | учитываются `starts_at` / `ends_at` / `is_active` |
| `GET /api/v1/complaint-reasons` | только `is_active` |
| `GET /api/health` | 200 |

**Приоритет 3 — Unit-тесты на Actions и Services** (`CLAUDE.md`: «каждый Action и
Service покрыт тестами»; сейчас покрыт один сервис из восемнадцати):

- `CheckTariffLimitAction` / `CheckVideoLimitAction` / `CheckBoostLimitAction` —
  граничные случаи: ровно на лимите, лимит 0, тарифа нет вовсе, тариф истёк.
- `VerifySmsCodeAction` — истёкший код, исчерпанные попытки, `hash_equals`.
- `SendSmsCodeAction` — кулдаун, гашение предыдущих кодов.
- `TariffService::getRemainingLimits()` — вычет `pending + approved`, не уходит в минус.
- `ListingService::canBoost()` — граница интервала из `settings`.
- `CategoryService::assertValidParent()` — циклы, превышение `MAX_LEVEL`, перенос ветки.
- `ImageConversionService::toWebp()` — соблюдение аспекта и лимита байт.

**Приоритет 4 — админка:**
модерация объявлений (approve/reject + событие push), справочники регионов/городов/районов
(каскадное скрытие), права менеджера на каждый закрытый роут (В-9),
`ChatRepository::getDialogs()` (порядок диалогов, В-8).

---

## Что сделано правильно (чтобы не сломать при правках)

- **Слои в мобильном API выдержаны.** 14 из 15 контроллеров `Api/V1` — тонкие,
  без единого запроса к БД. Единственное исключение — `NewsController`.
- **Единый формат ошибок.** `bootstrap/app.php:53-92` приводит все исключения на
  `/api/*` к JSON и не пускает наружу стек-трейс даже при `APP_DEBUG=true`.
- **Порядок middleware исправлен осознанно.** `prependToPriorityList` для
  `SetApiLocale` (`bootstrap/app.php:45-48`) — чтобы 401 приходил на нужном языке.
  С комментарием, почему.
- **Потоковая загрузка видео.** `VideoService::appendChunk()` и `File::move()` —
  файл любого размера никогда не оказывается в памяти целиком.
- **Атомарность там, где нужно.** `VideoRepository::toggleLike():114-136` —
  транзакция + перехват `UniqueConstraintViolationException` на гонке;
  `incrementViews()` — атомарный инкремент.
- **Комментарии объясняют «почему», а не «что».** `2026_07_08_000002_fix_tariffs_table_schema.php`,
  `EnsureUserIsNotBlocked`, `ProfileController::updateFcmToken` — по ним видно,
  какую именно ошибку чинили.
- **Прогрессивная отдача медиа.** `processing: true` + временные ссылки на
  оригинал — карточка не пустует, пока идёт конвертация.
