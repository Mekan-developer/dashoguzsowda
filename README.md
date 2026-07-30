# Daşoguz söwda

Доска объявлений: мобильное приложение на Flutter + административная панель.
Этот репозиторий — backend (Laravel 11) и админка (Inertia + Vue 3).

| Часть | Технологии |
|---|---|
| Backend | Laravel 11, PHP 8.3 |
| Админка | Inertia.js + Vue 3, Tailwind CSS |
| Mobile API | REST `/api/v1/*`, Sanctum |
| База данных | MySQL 8.4 |
| Очереди | Redis + Laravel Horizon |
| WebSocket | Laravel Reverb (чат) |
| Push | Firebase Cloud Messaging |
| Медиа | Intervention Image (WebP), FFmpeg (видео) |
| SMS-коды | Socket.IO-шлюз → телефон-отправитель |

---

## Быстрый старт

Нужен только **Docker** с плагином Compose. PHP, Composer, Node и MySQL на
машину ставить не надо — всё живёт в контейнерах.

```bash
git clone <repo-url> dashoguzsowda
cd dashoguzsowda

cp .env.example .env

# Собрать образы и поднять стек (первый раз — 10–20 минут)
docker compose up -d --build

# Зависимости PHP
docker compose exec app composer install

# Ключ приложения
docker compose exec app php artisan key:generate

# Таблицы и стартовые данные
docker compose exec app php artisan migrate --seed

# Публичная ссылка на загруженные файлы
docker compose exec app php artisan storage:link
```

Готово: **http://localhost:8000**

### Учётные записи после сидинга

| Роль | Email | Пароль |
|---|---|---|
| admin | `admin@gmail.com` | `password` |
| manager | `manager@gmail.com` | `password` |

Вход в админку — по email и паролю. Пользователи мобильного приложения
входят иначе: по номеру телефона и SMS-коду.

Это данные для разработки, на проде их обязательно менять.

### Что где слушает

| Адрес | Что это |
|---|---|
| http://localhost:8000 | Админка и API |
| http://localhost:8081 | phpMyAdmin |
| http://localhost:5173 | Vite dev-сервер (hot reload) |
| `localhost:3306` | MySQL |
| `localhost:8080` | Reverb (WebSocket) |
| `localhost:3000` | Socket.IO OTP-шлюз |

---

## Если что-то не поднялось

**Контейнер перезапускается.** Первым делом логи:

```bash
docker compose ps
docker compose logs --tail=50 app
docker compose logs --tail=50 horizon
```

**`horizon` или `reverb` в Restarting.** Обычно не установлен `vendor/`:

```bash
docker compose exec app composer install
docker compose restart horizon reverb
```

**Порт занят.** Если 8000, 3306 или 5173 уже используются, поменяйте левую
часть в `ports:` в `docker-compose.yml` — например `8001:80`.

**Права на `storage/`.** Контейнеры собираются под UID хоста. Если ваш UID
не 1000:

```bash
UID=$(id -u) GID=$(id -g) docker compose up -d --build
```

**Изменили `.env`, ничего не поменялось.** Конфиг кэшируется:

```bash
docker compose exec app php artisan config:clear
```

---

## Повседневные команды

```bash
docker compose exec app php artisan migrate          # миграции
docker compose exec app php artisan tinker           # консоль
docker compose exec app php artisan queue:failed     # упавшие задачи
docker compose exec app php artisan test             # тесты (Pest)

docker compose logs -f app                           # логи
docker compose down                                  # остановить
docker compose down -v                               # остановить и стереть БД
```

Фронтенд пересобирается сам — контейнер `node` держит Vite в режиме
hot reload, отдельная команда не нужна.

### Проверка окружения

Статус очередей, WebSocket, FCM и SMS-шлюза виден в админке:
**Настройки → мониторинг** (доступно роли admin).

---

## Структура

```
app/
  Actions/            одно действие — один класс
  Events/  Listeners/ сайд-эффекты: SMS, push, логи
  Http/
    Controllers/Admin/    админка (Inertia)
    Controllers/Api/V1/   мобильное API
    Requests/             вся валидация
    Resources/Api/V1/     формат ответов API
  Jobs/               фоновая обработка (media, notifications)
  Observers/
  Repositories/       все запросы к БД
  Services/           бизнес-логика

resources/js/
  Pages/Admin/        страницы админки
  Components/         Vue-компоненты
  Stores/             Pinia

routes/
  web.php             админка
  api/v1.php          мобильное API

docker/                конфигурация образов
socket-server/         Socket.IO-шлюз для OTP
docs/DEPLOY.md         развёртывание на сервере
CLAUDE.md              архитектурные правила проекта
```

Перед тем как писать код, загляните в [CLAUDE.md](CLAUDE.md) — там описано,
что где должно лежать: контроллеры тонкие, вся валидация в Form Request,
запросы к БД только через Repository, сайд-эффекты через события.

---

## Документация API

Генерируется Scribe, доступна на `/docs` запущенного приложения.
Пересобрать после изменения роутов:

```bash
docker compose exec app php artisan scribe:generate
```

Коллекция для Bruno — в каталоге [bruno/](bruno/).

---

## Развёртывание на сервере

Отдельный стек: Caddy с TLS, собранные образы вместо монтирования кода,
без phpMyAdmin и dev-сервера, с планировщиком и бэкапами.

Пошаговая инструкция — **[docs/DEPLOY.md](docs/DEPLOY.md)**.

```bash
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml up -d
```

Локальный и прод-стек используют одинаковые имена контейнеров, поэтому
одновременно на одной машине не запускаются.

---

## Особенности, о которых стоит знать заранее

**SMS-коды.** Отправляются не через сторонний сервис, а через свой
Socket.IO-шлюз: Laravel шлёт код в `socket-server`, тот передаёт его на
телефон, а телефон отправляет SMS. Локально по умолчанию включён
`LogSmsService` — коды пишутся в `storage/logs/laravel.log`, реальная
отправка не нужна.

**Видео.** Загрузка кусками (chunked), сборка на сервере, обработка через
FFmpeg в очереди `media`. Ограничение — 60 секунд.

**Изображения.** Все фото конвертируются в WebP в трёх размерах. Требует
PHP-расширения `gd` со сборкой `--with-webp` — в образе оно уже есть.

**Локализация.** Туркменский и русский. Хардкодные строки в интерфейсе не
допускаются: в PHP — `__('messages.key')`, во Vue — `vue-i18n`.
