# Daşoguz söwda

Доска объявлений: мобильное приложение на Flutter + административная панель.
Этот репозиторий — backend (Laravel 11) и админка (Inertia + Vue 3).

| Часть | Технологии |
|---|---|
| Backend | Laravel 11, PHP 8.3 (php-fpm) |
| Веб-сервер | nginx 1.27 |
| Админка | Inertia.js + Vue 3, Tailwind CSS, Vite |
| Mobile API | REST `/api/v1/*`, Sanctum |
| База данных | MySQL 8.0 |
| Очереди | Redis 7 + Laravel Horizon |
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

# Собрать образы (первый раз — 10–20 минут)
docker compose build

# PHP-зависимости. В dev-стеке корень репозитория монтируется в контейнер
# и перекрывает vendor/ из образа, поэтому composer нужно прогнать на хосте.
docker compose run --rm --no-deps app composer install

# Поднять стек
docker compose up -d

# Ключ приложения, таблицы и стартовые данные, ссылка на загруженные файлы
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan storage:link
```

Готово: **http://localhost:8000**

Контейнер `node` при первом старте сам выполняет `npm ci` и поднимает Vite —
это ещё несколько минут, и до их истечения страницы админки будут без стилей.
Прогресс виден в `docker compose logs -f node`.

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
| http://localhost:8000 | Админка и API (nginx → php-fpm) |
| http://localhost:5173 | Vite dev-сервер (hot reload) |
| http://localhost:3000 | Socket.IO OTP-шлюз, страница-тестер на `/test` |
| `localhost:8070` | Reverb (WebSocket-чат) |
| `localhost:3366` | MySQL |
| `localhost:6379` | Redis |

phpMyAdmin в стеке нет. К базе подключаться на `127.0.0.1:3366` любым клиентом
(DBeaver, TablePlus) или прямо в контейнере:

```bash
docker compose exec db mysql -u admin -p dashoguzsowda
```

Порты и монтирование кода задаёт `docker-compose.override.yml` — Compose
подхватывает его автоматически, отдельных флагов не нужно.

---

## Если что-то не поднялось

**Первым делом — состояние и логи:**

```bash
docker compose ps -a
docker compose logs --tail=50 app
docker compose logs --tail=50 horizon
```

**`horizon`, `reverb` или `scheduler` сразу в `Exited`.** Почти всегда не
установлен `vendor/` (см. выше — бинд-маунт перекрывает vendor из образа):

```bash
docker compose run --rm --no-deps app composer install
docker compose up -d
```

**Ошибка подключения к БД.** Значения в `.env` должны совпадать с
`docker/db.env`: `DB_HOST=db`, пользователь `admin`, пароль `secret!`.
Если `docker/db.env` правили уже после первого запуска — том с данными создан
со старыми учётками, помогает только пересоздание:

```bash
docker compose down -v && docker compose up -d
```

**Порт занят.** Поменяйте левую часть в `ports:` в
`docker-compose.override.yml` — например `"8001:80"` у `nginx`.

**Чат не подключается.** Reverb опубликован наружу на `8070`, поэтому
браузерная переменная в `.env` — `VITE_REVERB_PORT=8070`, а серверная
`REVERB_PORT=8080` (по ней backend ходит внутри docker-сети). Значения `VITE_*`
вкомпилируются в бандл, после правки нужен перезапуск сборщика:

```bash
docker compose restart node
```

**Права на `storage/`.** Контейнеры собираются под UID хоста. Если ваш UID
не 1000:

```bash
UID=$(id -u) GID=$(id -g) docker compose up -d --build
```

**Изменили `.env`, ничего не поменялось.** Конфиг кэшируется:

```bash
docker compose exec app php artisan config:clear
```

**apt не достучался до `deb.debian.org` при сборке.** Образ по умолчанию идёт
через зеркало Яндекса. Если прямой доступ есть, соберите с ним:

```bash
docker compose build --build-arg DEBIAN_MIRROR=https://deb.debian.org app
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

Для разовых artisan-команд есть отдельный сервис (профиль `tools`) — работает
и тогда, когда `app` не поднят:

```bash
docker compose run --rm artisan migrate:status
```

Фронтенд пересобирается сам — контейнер `node` держит Vite в режиме
hot reload, отдельная команда не нужна.

### Проверка окружения

Статус очередей, WebSocket, FCM и SMS-шлюза виден в админке:
**Настройки → Мониторинг** (роль admin). Отдельная страница по шлюзу —
**Настройки → SMS-шлюз**, там же кнопка тестовой отправки.

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
  Pages/              страницы админки по доменам (Listings/, Users/, ...)
  Components/         Vue-компоненты
  Layouts/
  i18n/               словари tk / ru

routes/
  web.php             админка
  api/v1.php          мобильное API

docker/                Dockerfile и конфиги образов (php, nginx, mysql)
socket-server/         Socket.IO-шлюз для OTP
bruno/                 коллекция запросов к API
mobile_docs/           спецификация API для мобильного приложения
docs/DEPLOY.md         развёртывание на сервере
CLAUDE.md              архитектурные правила проекта
```

Перед тем как писать код, загляните в [CLAUDE.md](CLAUDE.md) — там описано,
что где должно лежать: контроллеры тонкие, вся валидация в Form Request,
запросы к БД только через Repository, сайд-эффекты через события.

---

## Документация API

Генератора документации в проекте нет — Scribe убран, `/docs` больше не
отдаётся. Актуальные источники:

| Где | Что |
|---|---|
| [bruno/](bruno/) | коллекция запросов, открывается в [Bruno](https://usebruno.com) |
| [mobile_docs/BACKEND_API.md](mobile_docs/BACKEND_API.md) | спецификация эндпоинтов для мобильного приложения |
| [docs/flutter-chat-integration.md](docs/flutter-chat-integration.md) | подключение чата со стороны Flutter |
| [socket-server/README.md](socket-server/README.md) | контракт OTP-шлюза и пример клиента |

---

## Развёртывание на сервере

`docker-compose.prod.yml` — это **оверрайд**, а не самостоятельный файл:
указывать нужно оба, иначе Compose ругнётся на сервисы без образа.

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml build
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

Отличия от dev: код и собранные ассеты лежат внутри образа (ничего не
монтируется с хоста), `storage/app` — в named-томе, общем для php-контейнеров,
nginx отдаёт статику из своего образа, контейнеров `node` и dev-портов нет.

Пошаговая инструкция — **[docs/DEPLOY.md](docs/DEPLOY.md)**. Учтите: часть
разделов там описывает прежнюю схему с Caddy и TLS, которой в текущих
compose-файлах уже нет — перед деплоем сверяйтесь с самими compose-файлами.

Dev- и прод-стек используют одно имя проекта (`dzsowda`) и одинаковые
`container_name`, поэтому одновременно на одной машине не запускаются.

---

## Особенности, о которых стоит знать заранее

**SMS-коды.** Отправляются не через сторонний сервис, а через свой
Socket.IO-шлюз: Laravel шлёт код в `sms-gateway`, тот передаёт его на телефон,
а телефон отправляет SMS. Локально по умолчанию `SMS_DRIVER=log` — коды пишутся
в `storage/logs/laravel.log`, реальная отправка не нужна. Проверить шлюз в
браузере: http://localhost:3000/test (страница включена флагом
`SMS_GATEWAY_TEST_PAGE`).

**Видео.** Загрузка кусками (chunked), сборка на сервере, обработка через
FFmpeg в очереди `media`. Ограничение — 60 секунд. Внимание: `ffmpeg` в
php-образ сейчас не устанавливается, поэтому `ProcessVideoJob` в докере упадёт —
пакет нужно добавить в [docker/php/Dockerfile](docker/php/Dockerfile).

**Изображения.** Все фото конвертируются в WebP в трёх размерах. Требует
PHP-расширения `gd` со сборкой `--with-webp` — в образе оно уже есть.

**Локализация.** Туркменский и русский. Хардкодные строки в интерфейсе не
допускаются: в PHP — `__('messages.key')`, во Vue — `vue-i18n`.
